<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class CarOwnershipHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'customer' => $this->relationLoaded('customer') && $this->customer 
                ? new CustomerResource($this->customer) 
                : null,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Human-readable Arabic label for the current status.
     */
    protected function statusLabel(): string
    {
        return match ($this->status) {
            \App\Models\Order::STATUS_AVAILABLE => 'متوفر',
            \App\Models\Order::STATUS_SHIPPING => 'قيد الشحن',
            \App\Models\Order::STATUS_IN_SHOW_ROOM => 'وصل إلى صالة العرض',
            \App\Models\Order::STATUS_SOLD => 'مباع',
            \App\Models\Order::STATUS_DELIVERED => 'تم التسليم',
            default => $this->status,
        };
    }
}
