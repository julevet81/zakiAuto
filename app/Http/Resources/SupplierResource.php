<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,

            // Aggregates - only computed when explicitly requested via
            // ?with_stats=1, to keep list endpoints cheap by default.
            'batches_count' => $this->when(
                $this->relationLoaded('batches') || isset($this->batches_count),
                fn () => $this->batches_count ?? $this->batches->count()
            ),
            'cars_count' => $this->when(
                $this->relationLoaded('cars') || isset($this->cars_count),
                fn () => $this->cars_count ?? $this->cars->count()
            ),
            'total_paid' => $this->when(
                $request->boolean('with_stats'),
                fn () => (float) $this->total_paid
            ),

            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'cars' => CarResource::collection($this->whenLoaded('cars')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
