<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Http\Requests\AddBillingMethodRequest;
use App\Services\PaymentService;
use App\Services\BillingMethodService;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\BillingMethodResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private BillingMethodService $billingMethodService
    ) {
        $this->middleware('auth:api');
        $this->middleware('throttle:payment')->only(['createPayment', 'refundPayment']);
    }

    /**
     * Get user's billing methods
     */
    public function getBillingMethods(): JsonResponse
    {
        $billingMethods = $this->billingMethodService->getUserBillingMethods(auth()->user());
        
        return response()->json([
            'success' => true,
            'data' => BillingMethodResource::collection($billingMethods),
            'message' => 'Billing methods retrieved successfully.'
        ]);
    }

    /**
     * Add a new billing method
     */
    public function addBillingMethod(AddBillingMethodRequest $request): JsonResponse
    {
        $billingMethod = $this->billingMethodService->addBillingMethod(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => new BillingMethodResource($billingMethod),
            'message' => 'Billing method added successfully.'
        ], 201);
    }

    /**
     * Remove a billing method
     */
    public function removeBillingMethod(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|exists:billing_methods,id']);
        
        $this->billingMethodService->removeBillingMethod(
            auth()->user(),
            $request->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Billing method removed successfully.'
        ]);
    }

    /**
     * Create a payment
     */
    public function createPayment(CreatePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createPayment(
            auth()->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => new PaymentResource($payment),
            'message' => 'Payment processed successfully.'
        ]);
    }

    /**
     * Process refund
     */
    public function refundPayment(RefundPaymentRequest $request): JsonResponse
    {
        $refund = $this->paymentService->refundPayment(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => $refund,
            'message' => 'Refund processed successfully.'
        ]);
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request): JsonResponse
    {
        $request->validate(['transaction_id' => 'required|string']);
        
        $status = $this->paymentService->getPaymentStatus($request->transaction_id);

        return response()->json([
            'success' => true,
            'data' => $status,
            'message' => 'Payment status retrieved successfully.'
        ]);
    }

    /**
     * Get available payment gateways
     */
    public function getAvailableGateways(): JsonResponse
    {
        $gateways = $this->paymentService->getAvailableGateways();

        return response()->json([
            'success' => true,
            'data' => $gateways,
            'message' => 'Available payment gateways retrieved successfully.'
        ]);
    }
}