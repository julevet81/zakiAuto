<?php

namespace App\Http\Resources;

use App\Http\Resources\AgentTransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Agent
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,

            'customers_count' => $this->when(
                isset($this->customers_count) || $this->relationLoaded('customers'),
                fn() => $this->customers_count ?? $this->customers->count()
            ),
            'orders_count' => $this->when(
                isset($this->orders_count) || $this->relationLoaded('orders'),
                fn() => $this->orders_count ?? $this->orders->count()
            ),

            // The agent's running ledger balance — only computed when
            // explicitly requested, since it queries the full ledger.
            'current_balance' => $this->when(
                $request->boolean('with_stats'),
                fn() => $this->current_balance
            ),

            'customers' => CustomerMiniResource::collection($this->whenLoaded('customers')),
            'orders' => OrderMiniResource::collection($this->whenLoaded('orders')),
            'transactions' => AgentTransactionResource::collection($this->whenLoaded('transactions')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
