<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight order reference, used when nesting inside CarResource so a
 * car shows which order (if any) it's currently sold under, without
 * pulling in the full OrderResource and ITS nested relations.
 *
 * @mixin \App\Models\Order
 */
class OrderMiniResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
        ];
    }
}
