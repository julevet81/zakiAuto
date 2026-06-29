<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'car_id' => $this->car_id,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'url' => $this->file_path ? \Illuminate\Support\Facades\Storage::url($this->file_path) : null,
            'created_at' => $this->created_at,
        ];
    }
}
