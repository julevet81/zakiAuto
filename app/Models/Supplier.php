<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'notes',
    ];

    /**
     * Import batches purchased from this supplier.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * All cars sourced from this supplier (across all batches).
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Payments made to this supplier.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Sum of all payments made to this supplier (paid so far).
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_local');
    }
}
