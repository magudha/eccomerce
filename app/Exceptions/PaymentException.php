<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentException extends Exception
{
    protected string $errorCode;
    protected array $context;

    public function __construct(
        string $message = 'Payment processing failed',
        string $errorCode = 'PAYMENT_ERROR',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render(Request $request): JsonResponse
    {
        $statusCode = $this->getHttpStatusCode();
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'type' => 'payment_error',
            ],
            'status' => $statusCode
        ], $statusCode);
    }

    /**
     * Get appropriate HTTP status code
     */
    protected function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'PAYMENT_NOT_FOUND' => 404,
            'PAYMENT_UNAUTHORIZED' => 403,
            'PAYMENT_INVALID_DATA' => 422,
            'PAYMENT_INSUFFICIENT_FUNDS' => 402,
            'PAYMENT_CARD_DECLINED' => 402,
            'PAYMENT_GATEWAY_ERROR' => 502,
            'PAYMENT_TIMEOUT' => 408,
            default => 400,
        };
    }

    /**
     * Create an instance for insufficient funds
     */
    public static function insufficientFunds(string $message = 'Insufficient funds'): self
    {
        return new self($message, 'PAYMENT_INSUFFICIENT_FUNDS');
    }

    /**
     * Create an instance for card declined
     */
    public static function cardDeclined(string $message = 'Card was declined'): self
    {
        return new self($message, 'PAYMENT_CARD_DECLINED');
    }

    /**
     * Create an instance for gateway error
     */
    public static function gatewayError(string $message = 'Payment gateway error'): self
    {
        return new self($message, 'PAYMENT_GATEWAY_ERROR');
    }

    /**
     * Create an instance for invalid data
     */
    public static function invalidData(string $message = 'Invalid payment data'): self
    {
        return new self($message, 'PAYMENT_INVALID_DATA');
    }

    /**
     * Create an instance for payment not found
     */
    public static function notFound(string $message = 'Payment not found'): self
    {
        return new self($message, 'PAYMENT_NOT_FOUND');
    }

    /**
     * Create an instance for unauthorized access
     */
    public static function unauthorized(string $message = 'Unauthorized payment access'): self
    {
        return new self($message, 'PAYMENT_UNAUTHORIZED');
    }

    /**
     * Create an instance for timeout
     */
    public static function timeout(string $message = 'Payment processing timeout'): self
    {
        return new self($message, 'PAYMENT_TIMEOUT');
    }
}

class InvalidGatewayException extends PaymentException
{
    public function __construct(
        string $message = 'Invalid payment gateway',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 'INVALID_GATEWAY', $context, $code, $previous);
    }
}

class GatewayConfigurationException extends PaymentException
{
    public function __construct(
        string $message = 'Payment gateway configuration error',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 'GATEWAY_CONFIGURATION_ERROR', $context, $code, $previous);
    }
}

class RefundException extends PaymentException
{
    public function __construct(
        string $message = 'Refund processing failed',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 'REFUND_ERROR', $context, $code, $previous);
    }

    /**
     * Create an instance for already refunded payment
     */
    public static function alreadyRefunded(string $message = 'Payment has already been refunded'): self
    {
        return new self($message, ['error_type' => 'already_refunded']);
    }

    /**
     * Create an instance for refund not allowed
     */
    public static function notAllowed(string $message = 'Refund not allowed for this payment'): self
    {
        return new self($message, ['error_type' => 'not_allowed']);
    }

    /**
     * Create an instance for partial refund not supported
     */
    public static function partialNotSupported(string $message = 'Partial refunds not supported'): self
    {
        return new self($message, ['error_type' => 'partial_not_supported']);
    }
}

class WebhookException extends PaymentException
{
    public function __construct(
        string $message = 'Webhook processing failed',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 'WEBHOOK_ERROR', $context, $code, $previous);
    }

    /**
     * Create an instance for invalid signature
     */
    public static function invalidSignature(string $message = 'Invalid webhook signature'): self
    {
        return new self($message, ['error_type' => 'invalid_signature']);
    }

    /**
     * Create an instance for unknown event type
     */
    public static function unknownEvent(string $eventType): self
    {
        return new self(
            "Unknown webhook event type: {$eventType}",
            ['error_type' => 'unknown_event', 'event_type' => $eventType]
        );
    }
}