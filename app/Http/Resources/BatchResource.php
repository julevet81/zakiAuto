<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Batch
 */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierMiniResource($this->whenLoaded('supplier')),
            'batch_number' => $this->batch_number,
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'total_paid_amount_foreign' => (float) $this->total_paid_amount_foreign,
            'exchange_rate' => (float) $this->exchange_rate,
            'status' => $this->status,
            'cars_count' => $this->cars_count ?? $this->whenLoaded('cars', fn () => $this->cars->count()),
            'total_paid_local' => $this->when(
                $this->relationLoaded('payments'),
                fn () => (float) $this->total_paid_local
            ),
            'notes' => $this->notes,
            'cars' => CarResource::collection($this->whenLoaded('cars')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
