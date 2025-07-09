<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\BillingMethod;
use App\Exceptions\PaymentException;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $publicKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('payment.gateways.stripe.secret_key');
        $this->publicKey = config('payment.gateways.stripe.public_key');
        $this->webhookSecret = config('payment.gateways.stripe.webhook_secret');
        
        if ($this->secretKey) {
            Stripe::setApiKey($this->secretKey);
        }
    }

    /**
     * Create a payment
     */
    public function createPayment(array $data): array
    {
        try {
            $user = $data['user'];
            $amount = $data['amount'] * 100; // Convert to cents
            $currency = strtolower($data['currency']);

            // Ensure user has Stripe customer ID
            if (!$user->stripe_customer_id) {
                $customer = Customer::create([
                    'email' => $user->email,
                    'name' => $user->name,
                    'metadata' => ['user_id' => $user->id],
                ]);
                $user->update(['stripe_customer_id' => $customer->id]);
            }

            // Get payment method
            $paymentMethodId = null;
            if (isset($data['billing_method_id'])) {
                $billingMethod = BillingMethod::findOrFail($data['billing_method_id']);
                $details = json_decode($billingMethod->details, true);
                $paymentMethodId = $details['payment_method_id'] ?? null;
            }

            // Create payment intent
            $paymentIntentData = [
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $user->stripe_customer_id,
                'description' => 'Order #' . $data['transaction']->order_id,
                'metadata' => [
                    'transaction_id' => $data['transaction']->id,
                    'order_id' => $data['transaction']->order_id,
                ],
            ];

            if ($paymentMethodId) {
                $paymentIntentData['payment_method'] = $paymentMethodId;
                $paymentIntentData['confirm'] = true;
                $paymentIntentData['off_session'] = true;
            }

            $paymentIntent = PaymentIntent::create($paymentIntentData);

            $status = match ($paymentIntent->status) {
                'succeeded' => 'completed',
                'requires_action', 'requires_confirmation' => 'pending',
                'canceled' => 'cancelled',
                default => 'pending',
            };

            return [
                'transaction_id' => $paymentIntent->id,
                'status' => $status,
                'client_secret' => $paymentIntent->client_secret,
                'raw_response' => $paymentIntent->toArray(),
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe payment creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getError()->code,
                'user_id' => $data['user']->id,
            ]);

            throw new PaymentException('Payment failed: ' . $e->getError()->message);
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(array $data): array
    {
        try {
            $refundData = [
                'payment_intent' => $data['transaction_id'],
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'reason' => $data['reason'],
                ],
            ];

            if (isset($data['amount'])) {
                $refundData['amount'] = $data['amount'] * 100; // Convert to cents
            }

            $refund = Refund::create($refundData);

            $status = match ($refund->status) {
                'succeeded' => 'completed',
                'pending' => 'pending',
                'failed' => 'failed',
                default => 'pending',
            };

            return [
                'refund_id' => $refund->id,
                'status' => $status,
                'raw_response' => $refund->toArray(),
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $data['transaction_id'],
            ]);

            throw new PaymentException('Refund failed: ' . $e->getError()->message);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($transactionId);

            $status = match ($paymentIntent->status) {
                'succeeded' => 'completed',
                'requires_action', 'requires_confirmation' => 'pending',
                'canceled' => 'cancelled',
                'processing' => 'processing',
                default => 'pending',
            };

            return [
                'status' => $status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'raw_response' => $paymentIntent->toArray(),
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to retrieve Stripe payment status', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            throw new PaymentException('Failed to retrieve payment status');
        }
    }

    /**
     * Parse webhook payload
     */
    public function parseWebhook(array $payload): array
    {
        $event = $payload;

        return match ($event['type']) {
            'payment_intent.succeeded' => [
                'type' => 'payment.completed',
                'data' => [
                    'transaction_id' => $event['data']['object']['id'],
                    'amount' => $event['data']['object']['amount'] / 100,
                    'currency' => strtoupper($event['data']['object']['currency']),
                ],
            ],
            'payment_intent.payment_failed' => [
                'type' => 'payment.failed',
                'data' => [
                    'transaction_id' => $event['data']['object']['id'],
                    'failure_reason' => $event['data']['object']['last_payment_error']['message'] ?? 'Payment failed',
                ],
            ],
            'charge.dispute.created' => [
                'type' => 'chargeback.created',
                'data' => [
                    'transaction_id' => $event['data']['object']['payment_intent'],
                    'amount' => $event['data']['object']['amount'] / 100,
                    'reason' => $event['data']['object']['reason'],
                ],
            ],
            default => [
                'type' => 'unknown',
                'data' => $event['data']['object'],
            ],
        };
    }

    /**
     * Check if gateway is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publicKey);
    }

    /**
     * Get gateway display name
     */
    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'INR', 'SGD'];
    }

    /**
     * Get supported features
     */
    public function getFeatures(): array
    {
        return [
            'cards' => true,
            'recurring' => true,
            'refunds' => true,
            'webhooks' => true,
            'saved_cards' => true,
            'apple_pay' => true,
            'google_pay' => true,
        ];
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $this->webhookSecret
            );
            return true;
        } catch (\Exception $e) {
            Log::warning('Invalid Stripe webhook signature', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create payment method from token
     */
    public function createPaymentMethod(string $token, string $customerId): array
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($token);
            $paymentMethod->attach(['customer' => $customerId]);

            return [
                'payment_method_id' => $paymentMethod->id,
                'brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
                'exp_month' => $paymentMethod->card->exp_month,
                'exp_year' => $paymentMethod->card->exp_year,
            ];

        } catch (ApiErrorException $e) {
            throw new PaymentException('Failed to create payment method: ' . $e->getError()->message);
        }
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(string $paymentMethodId): bool
    {
        try {
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->detach();
            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to remove Stripe payment method', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethodId,
            ]);
            return false;
        }
    }
}