<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    /**
     * Lifecycle states for an import batch.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FULLY_PAID = 'fully_paid';

    public const STATUS_COST_ALLOCATED = 'cost_allocated';

    protected $fillable = [
        'supplier_id',
        'batch_number',
        'purchase_date',
        'total_paid_amount_foreign',
        'exchange_rate',
        'status',
        'cars_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total_paid_amount_foreign' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Cars that were imported as part of this batch.
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Payments made towards this batch's supplier.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Total amount paid (local currency) for this batch so far.
     */
    public function getTotalPaidLocalAttribute(): float
    {
        return (float) $this->payments()->sum('amount_local');
    }
}
