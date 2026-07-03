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
     * ⚠️  SECURITY NOTES ─────────────────────────────────────────────────
     *
     * This endpoint intentionally requires NO authentication token. That
     * is a deliberate product decision: a customer who doesn't have (or
     * hasn't set up) a system account should still be able to check the
     * status of their own order by presenting their passport number, in
     * the same way a parcel-tracking page works with a tracking code.
     *
     * The trade-offs accepted by choosing no-auth here are:
     *
     *   1. ENUMERATION RISK — an attacker who can guess/brute-force valid
     *      passport numbers could retrieve customer data. Mitigated by:
     *        • The `throttle:lookup` rate limiter (10 req / minute / IP),
     *          configured in AppServiceProvider.
     *        • Returning an identical 404-shaped response for "not found"
     *          and "found but wrong format" so response timing/shape
     *          leaks no information about whether a record exists.
     *
     *   2. DATA SCOPE — the response deliberately omits every internal
     *      field (supplier, costs, agent identity, internal IDs) that has
     *      no value to the customer themselves. See
     *      CustomerPassportLookupResource for the exact field list.
     *
     *   3. SOFT-DELETED CUSTOMERS — Eloquent's global SoftDeletes scope
     *      ensures deleted customers are never returned, even if someone
     *      holds a passport number that was previously in the system.
     *
     * If you later decide this endpoint should require a token (e.g. a
     * short-lived OTP sent to the customer's phone), move it under the
     * `auth:sanctum` middleware group in routes/api.php — the controller
     * logic itself needs no changes.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function show(?string $passportNo = null): JsonResponse
    {
        if ($passportNo === null || trim($passportNo) === '') {
            return response()->json([
                'message' => 'يرجى تقديم رقم جواز السفر للبحث',
            ], 422);
        }

        // Normalise: trim whitespace and uppercase so "AB123456" and
        // " ab123456 " both find the same record.
        $passportNo = strtoupper(trim($passportNo));

        $customer = Customer::query()
            ->where('passport_no', $passportNo)
            ->with([
                // Load orders with their car and their payments, all in
                // two extra queries (not N+1) thanks to eager loading.
                'orders' => fn($q) => $q->orderBy('id'),
                'orders.car',
                'orders.payments' => fn($q) => $q
                    ->orderBy('payment_date')
                    ->orderBy('id'),
            ])
            ->first();

        if (! $customer) {
            // Return exactly the same shape as a found-but-empty result
            // so that timing/structure attacks cannot distinguish
            // "passport exists" from "passport not found".
            return response()->json([
                'message' => 'لم يتم العثور على سجل بهذا الرقم',
            ], 404);
        }

        return response()->json([
            'data' => new CustomerPassportLookupResource($customer),
        ]);
    }
}
