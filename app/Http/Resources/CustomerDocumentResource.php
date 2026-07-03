<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CustomerDocument
 */
class CustomerDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customer_id,
            'title'       => $this->title,
            'file_type'   => $this->file_type,
            'file_size'   => $this->file_size,
            'url'         => $this->url, // via getUrlAttribute()
            'uploaded_by' => $this->uploaded_by,
            'uploader'    => $this->whenLoaded('uploader', fn() => [
                'id'   => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'created_at'  => $this->created_at,
        ];
    }
}
