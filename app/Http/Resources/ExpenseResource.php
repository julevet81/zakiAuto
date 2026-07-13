<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'car_id' => $this->car_id,
            'order_id' => $this->order_id,
            'service_provider_id' => $this->service_provider_id,
            'service_provider' => $this->whenLoaded('serviceProvider', fn () => $this->serviceProvider ? [
                'id' => $this->serviceProvider->id,
                'name' => $this->serviceProvider->name,
                'provider_type' => $this->serviceProvider->provider_type,
            ] : null),

            'expense_type' => $this->expense_type,
            'amount' => (float) $this->amount,

            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? Storage::disk('public')->url($this->attachment) : null,

            'expense_date' => $this->expense_date?->format('Y-m-d'),
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
