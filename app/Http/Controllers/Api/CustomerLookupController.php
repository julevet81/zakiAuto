<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerPassportLookupResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerLookupController extends Controller
{
    /**
     * Public (unauthenticated) customer lookup by passport number.
     *
     * URL: GET /api/lookup/customer/{passport_no}
     *
     * Throttled at 10 requests/minute/IP via the 'lookup' named limiter
     * defined in AppServiceProvider::boot().
     *
     * Returns 404 for both "not found" and "invalid format" so that
     * timing/shape attacks cannot distinguish whether a passport number
     * exists in the system.
     */
    public function show(string $passport_no): JsonResponse
    {
        // Normalise: trim whitespace + uppercase so "ab123456",
        // "AB123456", and " Ab123456 " all match the same record.
        $passportNo = strtoupper(trim($passport_no));

        if (empty($passportNo)) {
            return response()->json([
                'message' => 'لم يتم العثور على سجل بهذا الرقم',
            ], 404);
        }

        $customer = Customer::query()
            ->where('passport_no', $passportNo)
            ->with([
                'orders'          => fn ($q) => $q->orderBy('id'),
                'orders.car',
                'orders.payments' => fn ($q) => $q
                    ->orderBy('payment_date')
                    ->orderBy('id'),
            ])
            ->first();

        if (! $customer) {
            return response()->json([
                'message' => 'لم يتم العثور على سجل بهذا الرقم',
            ], 404);
        }

        return response()->json([
            'data' => new CustomerPassportLookupResource($customer),
        ]);
    }
}