<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'batch_id',
        'supplier_id',
        'amount_foreign',
        'exchange_rate',
        'amount_local',
        'attachment',
        'payment_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_foreign' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_local' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
