<?php

namespace App\Http\Resources;

use App\Http\Resources\CustomerPaymentResource;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,

            'customer_id' => $this->customer_id,
            'customer' => new CustomerMiniResource($this->whenLoaded('customer')),

            'car_id' => $this->car_id,
            'car' => new CarResource($this->whenLoaded('car')),

            'agent_id' => $this->agent_id,
            'agent' => new AgentMiniResource($this->whenLoaded('agent')),

            'status' => $this->status,
            'status_label' => $this->statusLabel(),

            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'shipping_date' => $this->shipping_date?->format('Y-m-d'),
            'arrival_date' => $this->arrival_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),

            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => (float) $this->remaining_amount,

            'notes' => $this->notes,

            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'payments' => CustomerPaymentResource::collection($this->whenLoaded('payments')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Human-readable Arabic label for the current status, for UIs that
     * don't want to maintain their own status->label mapping.
     */
    protected function statusLabel(): string
    {
        return match ($this->status) {
            \App\Models\Order::STATUS_NEW => 'طلب جديد',
            \App\Models\Order::STATUS_PURCHASED => 'تم الشراء',
            \App\Models\Order::STATUS_SHIPPING => 'قيد الشحن',
            \App\Models\Order::STATUS_ARRIVED_AT_PORT => 'وصل إلى الميناء',
            \App\Models\Order::STATUS_READY_FOR_DELIVERY => 'جاهز للتسليم',
            \App\Models\Order::STATUS_DELIVERED => 'تم التسليم',
            default => $this->status,
        };
    }
}
