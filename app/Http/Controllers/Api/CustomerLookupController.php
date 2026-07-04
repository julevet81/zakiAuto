<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerPassportLookupResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLookupController extends Controller
{
    /**
     * Public customer lookup by passport number.
     *
     * Accepts the passport number in TWO ways so the client can choose:
     *
     *   1. URL parameter (RESTful):
     *      GET /api/lookup/customer/AB123456
     *
     *   2. Query string (easier to test in Postman/browser):
     *      GET /api/lookup/customer?passport_no=AB123456
     *
     * Both routes point to this same method.
     */
    public function show(Request $request, ?string $passport_no = null): JsonResponse
    {
        // Accept from URL segment OR query string, URL takes priority.
        $raw = $passport_no ?? $request->query('passport_no', '');

        // Normalise: trim + uppercase.
        $passportNo = strtoupper(trim((string) $raw));

        if ($passportNo === '') {
            return response()->json([
                'message' => 'يرجى إدخال رقم جواز السفر',
                'hint'    => 'GET /api/lookup/customer/{passport_no}  أو  GET /api/lookup/customer?passport_no=AB123456',
            ], 422);
        }

        $customer = Customer::query()
            ->where('passport_no', $passportNo)
            ->with([
                'orders'          => fn($q) => $q->orderBy('id'),
                'orders.car',
                'orders.payments' => fn($q) => $q
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
