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
        $totalCost = (float) ($this->total_cost_foreign ?? 0);
        $totalPaid = (float) $this->total_paid_amount_foreign;

        return [
            'id'           => $this->id,
            'batch_number' => $this->batch_number,
            'supplier_id'  => $this->supplier_id,
            'supplier'     => new SupplierMiniResource($this->whenLoaded('supplier')),
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),

            // User-entered: the total agreed purchase price from the
            // supplier's pro-forma/invoice (required for exchange_rate
            // to be calculable).
            'total_cost_foreign' => $totalCost > 0 ? $totalCost : null,

            // Auto-computed: sum of all supplier_payments.amount_foreign
            // for this batch (kept in sync by SupplierPaymentController).
            'total_paid_amount_foreign' => $totalPaid,

            // Remaining unpaid foreign amount (null when total_cost_foreign
            // not yet set).
            'remaining_foreign' => $totalCost > 0
                ? max(round($totalCost - $totalPaid, 2), 0)
                : null,

            // Auto-computed: weighted-average exchange_rate derived from
            // all supplier_payments (see Batch::recomputeExchangeRate()).
            // NULL when no payments exist yet or total_cost_foreign is
            // not set. NOT user-editable.
            'exchange_rate' => $this->exchange_rate !== null
                ? (float) $this->exchange_rate
                : null,

            'status'     => $this->status,
            'cars_count' => $this->cars_count ?? $this->whenLoaded(
                'cars',
                fn () => $this->cars->count()
            ),
            'total_paid_local' => $this->when(
                $this->relationLoaded('payments'),
                fn () => (float) $this->total_paid_local
            ),
            'notes'    => $this->notes,
            'cars'     => CarResource::collection($this->whenLoaded('cars')),
            'payments' => SupplierPaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
