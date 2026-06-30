<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\CustomerPayment
 */
class CustomerPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerMiniResource($this->whenLoaded('customer')),

            'amount' => (float) $this->amount,
            'received_by' => $this->received_by,

            'agent_id' => $this->agent_id,
            'agent' => new AgentMiniResource($this->whenLoaded('agent')),

            'remittance_id' => $this->remittance_id,
            'is_remitted' => $this->remittance_id !== null,

            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? Storage::url($this->attachment) : null,

            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'notes' => $this->notes,

            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
