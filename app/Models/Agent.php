<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
    ];

    /**
     * The user account linked to this agent (nullable: an agent may not have
     * a login account, e.g. when added manually by an admin).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Customers brought in / managed by this agent.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Orders this agent is associated with (commission-earning orders).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Customer payments collected by this agent ("received_by" = agent).
     */
    public function collectedPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * Full ledger / statement of account: every debit/credit movement for
     * this agent (commissions, remittances to company, etc.).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class);
    }

    /**
     * Current running balance of the agent, taken from the most recent
     * ledger transaction (0 if the agent has no transactions yet).
     */
    public function getCurrentBalanceAttribute(): float
    {
        $last = $this->transactions()->latest('transaction_date')->latest('id')->first();

        return $last ? (float) $last->current_balence : 0.0;
    }
}
