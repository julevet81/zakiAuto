<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight customer representation used when nesting inside other
 * resources (Order, Agent, CustomerPayment) to avoid pulling in the full
 * CustomerResource and its own nested relations every time.
 *
 * @mixin \App\Models\Customer
 */
class CustomerMiniResource extends JsonResource
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
        ];
    }
}
