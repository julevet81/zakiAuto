<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'agent_id',
        'name',
        'phone',
        'email',
        'national_id',
        'passport_no',
        'address',
    ];

    /**
     * The user account linked to this customer (nullable: a customer may be
     * added by staff without ever logging in to the system).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The agent who referred / manages this customer (nullable: a customer
     * may deal directly with the company without an agent).
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * All orders placed by this customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * All payments made by this customer (across all their orders).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Profile documents uploaded for this customer (passport copy,
     * national ID scan, signed contract, power of attorney, etc.)
     */
    public function customerDocuments(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Total amount this customer has paid so far, across all orders.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Total amount this customer still owes, across all orders.
     */
    public function getTotalRemainingAttribute(): float
    {
        return (float) $this->orders()->sum('remaining_amount');
    }
}
