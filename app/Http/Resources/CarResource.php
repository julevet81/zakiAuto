<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Car
 */
class CarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Cost-sensitive fields (purchase price, supplier identity, expense
        // breakdown, profit) are only exposed to users who can see supplier
        // data. A customer or agent with mere `cars.view` access can browse
        // and pick a car by its sale price, but never its purchase cost.
        $canSeeCosts = $request->user()?->can('suppliers.view') ?? false;

        return [
            'id' => $this->id,
            'batch_id' => $this->when($canSeeCosts, $this->batch_id),
            'supplier_id' => $this->when($canSeeCosts, $this->supplier_id),
            'container_opener_id' => $this->when($canSeeCosts, $this->container_opener_id),
            'supplier' => $this->when($canSeeCosts, fn () => new SupplierMiniResource($this->whenLoaded('supplier'))),
            'container_opener' => $this->when($canSeeCosts, fn () => new ContainerOpenerResource($this->whenLoaded('containerOpener'))),

            'brand' => $this->brand,
            'model' => $this->model,
            'finition' => $this->finition,
            'manufacture_year' => $this->manufacture_year,
            'color' => $this->color,
            'vin' => $this->vin,

            'foreign_purchase_price' => $this->when($canSeeCosts, (float) $this->foreign_purchase_price),
            'sale_price' => (float) $this->sale_price,

            'tracking_number' => $this->tracking_number,
            'container_no' => $this->container_no,
            'shipping_date' => $this->shipping_date?->format('Y-m-d'),
            'arrival_date' => $this->arrival_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),

            'status' => $this->status,
            'notes' => $this->notes,

            // Financial summary - only computed when expenses are loaded,
            // to keep plain listing endpoints fast, AND only for staff who
            // can already see cost data.
            'total_expenses' => $this->when(
                $canSeeCosts && $this->relationLoaded('expenses') && $this->relationLoaded('generalExpenses'),
                fn () => $this->total_expenses
            ),
            'estimated_profit' => $this->when(
                $canSeeCosts && $this->relationLoaded('expenses') && $this->relationLoaded('generalExpenses'),
                fn () => $this->estimated_profit
            ),

            'is_sold' => $this->isSold(),

            'expenses' => $this->when($canSeeCosts, fn () => CarExpenseResource::collection($this->whenLoaded('expenses'))),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'order' => new \App\Http\Resources\OrderMiniResource($this->whenLoaded('order')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
