<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'title',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Full public URL of the file (works dynamically with the current request host and port).
     */
    public function getUrlAttribute(): string
    {
        return url('storage/' . $this->file_path);
    }
}
