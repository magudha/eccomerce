<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Contracts\PaymentGatewayInterface;
use App\Factories\PaymentGatewayFactory;
use App\Exceptions\PaymentException;
use App\Exceptions\InvalidGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Create a payment
     */
    public function createPayment(User $user, array $data): PaymentTransaction
    {
        $order = Order::findOrFail($data['order_id']);
        
        // Verify order belongs to user and isn't already paid
        if ($order->user_id !== $user->id || $order->payStatus == 1) {
            throw new PaymentException('Invalid order or order already paid');
        }

        $gateway = $this->gatewayFactory->create($data['gateway']);
        
        return DB::transaction(function () use ($user, $data, $order, $gateway) {
            // Create transaction record
            $transaction = PaymentTransaction::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'gateway' => $data['gateway'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? config('payment.default_currency'),
                'status' => 'pending',
                'metadata' => $data['metadata'] ?? [],
            ]);

            try {
                // Process payment through gateway
                $result = $gateway->createPayment([
                    'user' => $user,
                    'transaction' => $transaction,
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? config('payment.default_currency'),
                    'billing_method_id' => $data['billing_method_id'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ]);

                // Update transaction with gateway response
                $transaction->update([
                    'gateway_transaction_id' => $result['transaction_id'],
                    'status' => $result['status'],
                    'gateway_response' => $result['raw_response'] ?? [],
                ]);

                // Update order if payment successful
                if ($result['status'] === 'completed') {
                    $order->update([
                        'payStatus' => 1,
                        'paid_method' => $data['gateway'],
                        'pay_key' => $result['transaction_id'],
                    ]);
                }

                return $transaction->fresh();

            } catch (\Exception $e) {
                $transaction->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);

                Log::error('Payment failed', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw new PaymentException('Payment processing failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Process refund
     */
    public function refundPayment(array $data): array
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $data['transaction_id'])
            ->where('status', 'completed')
            ->firstOrFail();

        $gateway = $this->gatewayFactory->create($transaction->gateway);

        return DB::transaction(function () use ($transaction, $gateway, $data) {
            try {
                $result = $gateway->refundPayment([
                    'transaction_id' => $transaction->gateway_transaction_id,
                    'amount' => $data['amount'] ?? $transaction->amount,
                    'reason' => $data['reason'] ?? 'Refund requested',
                ]);

                // Create refund record
                $refund = PaymentTransaction::create([
                    'id' => Str::uuid(),
                    'user_id' => $transaction->user_id,
                    'order_id' => $transaction->order_id,
                    'parent_transaction_id' => $transaction->id,
                    'gateway' => $transaction->gateway,
                    'amount' => -($data['amount'] ?? $transaction->amount),
                    'currency' => $transaction->currency,
                    'status' => $result['status'],
                    'gateway_transaction_id' => $result['refund_id'],
                    'gateway_response' => $result['raw_response'] ?? [],
                    'metadata' => ['reason' => $data['reason'] ?? 'Refund requested'],
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Refund failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);

                throw new PaymentException('Refund processing failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): array
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $transactionId)
            ->firstOrFail();

        $gateway = $this->gatewayFactory->create($transaction->gateway);

        try {
            $status = $gateway->getPaymentStatus($transactionId);
            
            // Update local status if different
            if ($status['status'] !== $transaction->status) {
                $transaction->update(['status' => $status['status']]);
                
                // Update order status if payment completed
                if ($status['status'] === 'completed' && $transaction->order) {
                    $transaction->order->update(['payStatus' => 1]);
                }
            }

            return $status;

        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new PaymentException('Failed to retrieve payment status');
        }
    }

    /**
     * Get available payment gateways
     */
    public function getAvailableGateways(): array
    {
        $enabledGateways = config('payment.enabled_gateways', []);
        $gateways = [];

        foreach ($enabledGateways as $gateway) {
            try {
                $gatewayInstance = $this->gatewayFactory->create($gateway);
                if ($gatewayInstance->isConfigured()) {
                    $gateways[] = [
                        'name' => $gateway,
                        'display_name' => $gatewayInstance->getDisplayName(),
                        'supported_currencies' => $gatewayInstance->getSupportedCurrencies(),
                        'features' => $gatewayInstance->getFeatures(),
                    ];
                }
            } catch (InvalidGatewayException $e) {
                Log::warning('Gateway not available: ' . $gateway, ['error' => $e->getMessage()]);
            }
        }

        return $gateways;
    }

    /**
     * Handle webhook from payment gateway
     */
    public function handleWebhook(string $gateway, array $payload): void
    {
        $gatewayInstance = $this->gatewayFactory->create($gateway);
        
        try {
            $event = $gatewayInstance->parseWebhook($payload);
            
            if ($event['type'] === 'payment.completed') {
                $this->handlePaymentCompleted($event['data']);
            } elseif ($event['type'] === 'payment.failed') {
                $this->handlePaymentFailed($event['data']);
            } elseif ($event['type'] === 'refund.completed') {
                $this->handleRefundCompleted($event['data']);
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Handle payment completed webhook
     */
    private function handlePaymentCompleted(array $data): void
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $data['transaction_id'])
            ->first();

        if ($transaction && $transaction->status !== 'completed') {
            $transaction->update(['status' => 'completed']);
            
            if ($transaction->order) {
                $transaction->order->update(['payStatus' => 1]);
            }
        }
    }

    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed(array $data): void
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $data['transaction_id'])
            ->first();

        if ($transaction && $transaction->status !== 'failed') {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $data['failure_reason'] ?? 'Payment failed',
            ]);
        }
    }

    /**
     * Handle refund completed webhook
     */
    private function handleRefundCompleted(array $data): void
    {
        $transaction = PaymentTransaction::where('gateway_transaction_id', $data['refund_id'])
            ->first();

        if ($transaction && $transaction->status !== 'completed') {
            $transaction->update(['status' => 'completed']);
        }
    }
}