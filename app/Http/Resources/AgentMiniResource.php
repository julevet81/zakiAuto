<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight agent representation used when nesting inside other
 * resources (Customer, Order, CustomerPayment) to avoid pulling in the
 * full AgentResource and its own nested relations every time.
 *
 * @mixin \App\Models\Agent
 */
class AgentMiniResource extends JsonResource
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
