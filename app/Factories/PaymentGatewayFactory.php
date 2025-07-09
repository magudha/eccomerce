<?php

namespace App\Factories;

use App\Contracts\PaymentGatewayInterface;
use App\Gateways\StripeGateway;
use App\Gateways\PayPalGateway;
use App\Gateways\RazorPayGateway;
use App\Gateways\FlutterwaveGateway;
use App\Gateways\PaystackGateway;
use App\Gateways\MpesaGateway;
use App\Gateways\PaytmGateway;
use App\Exceptions\InvalidGatewayException;

class PaymentGatewayFactory
{
    private array $gateways = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
        'razorpay' => RazorPayGateway::class,
        'flutterwave' => FlutterwaveGateway::class,
        'paystack' => PaystackGateway::class,
        'mpesa' => MpesaGateway::class,
        'paytm' => PaytmGateway::class,
    ];

    /**
     * Create a payment gateway instance
     */
    public function create(string $gateway): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$gateway])) {
            throw new InvalidGatewayException("Gateway '{$gateway}' is not supported");
        }

        $gatewayClass = $this->gateways[$gateway];
        
        if (!class_exists($gatewayClass)) {
            throw new InvalidGatewayException("Gateway class '{$gatewayClass}' does not exist");
        }

        $instance = app($gatewayClass);

        if (!$instance instanceof PaymentGatewayInterface) {
            throw new InvalidGatewayException("Gateway '{$gateway}' must implement PaymentGatewayInterface");
        }

        return $instance;
    }

    /**
     * Get all available gateways
     */
    public function getAvailableGateways(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * Register a new gateway
     */
    public function register(string $name, string $class): void
    {
        $this->gateways[$name] = $class;
    }

    /**
     * Check if gateway is registered
     */
    public function isRegistered(string $gateway): bool
    {
        return isset($this->gateways[$gateway]);
    }
}