<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'gateway' => [
                'required',
                'string',
                Rule::in(config('payment.enabled_gateways', [])),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                Rule::in(['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'INR', 'SGD']),
            ],
            'order_id' => [
                'required',
                'exists:orders,id',
            ],
            'billing_method_id' => [
                'sometimes',
                'exists:billing_methods,id',
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
            'metadata.*' => [
                'string',
                'max:255',
            ],
            'return_url' => [
                'sometimes',
                'url',
            ],
            'cancel_url' => [
                'sometimes',
                'url',
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'gateway.required' => 'Please select a payment gateway.',
            'gateway.in' => 'The selected payment gateway is not supported.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least $0.01.',
            'amount.max' => 'Payment amount cannot exceed $999,999.99.',
            'currency.size' => 'Currency code must be exactly 3 characters.',
            'currency.in' => 'The selected currency is not supported.',
            'order_id.required' => 'Order ID is required.',
            'order_id.exists' => 'The specified order does not exist.',
            'billing_method_id.exists' => 'The specified billing method does not exist.',
            'return_url.url' => 'Return URL must be a valid URL.',
            'cancel_url.url' => 'Cancel URL must be a valid URL.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that billing method belongs to authenticated user
            if ($this->has('billing_method_id')) {
                $billingMethod = \App\Models\BillingMethod::find($this->billing_method_id);
                if ($billingMethod && $billingMethod->user_id !== auth()->id()) {
                    $validator->errors()->add('billing_method_id', 'The billing method does not belong to you.');
                }
            }

            // Validate that order belongs to authenticated user
            if ($this->has('order_id')) {
                $order = \App\Models\Order::find($this->order_id);
                if ($order) {
                    if ($order->user_id !== auth()->id()) {
                        $validator->errors()->add('order_id', 'The order does not belong to you.');
                    }
                    if ($order->payStatus == 1) {
                        $validator->errors()->add('order_id', 'This order has already been paid.');
                    }
                }
            }

            // Validate amount against order total
            if ($this->has('order_id') && $this->has('amount')) {
                $order = \App\Models\Order::find($this->order_id);
                if ($order && abs($order->total - $this->amount) > 0.01) {
                    $validator->errors()->add('amount', 'Payment amount must match the order total.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default currency if not provided
        if (!$this->has('currency')) {
            $this->merge([
                'currency' => config('payment.default_currency', 'USD'),
            ]);
        }

        // Format amount to 2 decimal places
        if ($this->has('amount')) {
            $this->merge([
                'amount' => round($this->amount, 2),
            ]);
        }
    }
}