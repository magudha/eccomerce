<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Create a payment
     */
    public function createPayment(array $data): array;

    /**
     * Refund a payment
     */
    public function refundPayment(array $data): array;

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): array;

    /**
     * Parse webhook payload
     */
    public function parseWebhook(array $payload): array;

    /**
     * Check if gateway is properly configured
     */
    public function isConfigured(): bool;

    /**
     * Get gateway display name
     */
    public function getDisplayName(): string;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get supported features
     */
    public function getFeatures(): array;

    /**
     * Validate webhook signature
     */
    public function validateWebhook(array $payload, string $signature): bool;
}