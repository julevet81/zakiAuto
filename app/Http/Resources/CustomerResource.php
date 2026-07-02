<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'user_id'  => $this->user_id,
            'agent_id' => $this->agent_id,
            'agent'    => new AgentMiniResource($this->whenLoaded('agent')),

            'name'        => $this->name,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'national_id' => $this->national_id,
            'passport_no' => $this->passport_no,
            'address'     => $this->address,

            'orders_count' => $this->when(
                isset($this->orders_count) || $this->relationLoaded('orders'),
                fn () => $this->orders_count ?? $this->orders->count()
            ),

            // Financial summary - only computed for staff who can see
            // payment data, and only when explicitly requested.
            'total_paid' => $this->when(
                $request->boolean('with_stats') && $request->user()?->can('customer_payments.view'),
                fn () => (float) $this->total_paid
            ),
            'total_remaining' => $this->when(
                $request->boolean('with_stats') && $request->user()?->can('orders.view'),
                fn () => (float) $this->total_remaining
            ),

            // Uses OrderWithCarResource (not OrderMiniResource) so that
            // each order includes its car details (brand, model, price,
            // status...) — the data that's actually useful when viewing
            // a customer's profile. OrderMiniResource is kept for other
            // embedding contexts (e.g. inside CarResource) where
            // recursion must be avoided.
            'orders'   => OrderWithCarResource::collection($this->whenLoaded('orders')),
            'payments' => CustomerPaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
