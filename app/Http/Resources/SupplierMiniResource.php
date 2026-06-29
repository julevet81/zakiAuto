<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight supplier representation used when nesting inside other
 * resources (Batch, Car, SupplierPayment) to avoid pulling in the full
 * SupplierResource (and its own nested relations) every time.
 *
 * @mixin \App\Models\Supplier
 */
class SupplierMiniResource extends JsonResource
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
