<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ServiceProviderModel
 */
class ServiceProviderResource extends JsonResource
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
            'email' => $this->email,
            'provider_type' => $this->provider_type,
            'address' => $this->address,
            'notes' => $this->notes,
            'expenses_count' => $this->when(
                isset($this->expenses_count),
                fn () => $this->expenses_count
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
