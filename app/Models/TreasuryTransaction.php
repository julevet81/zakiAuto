<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
//use Illuminate\Database\Eloquent\Relations\MorphTo;

class TreasuryTransaction extends Model
{
    use HasFactory;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const SOURCE_AGENT_REMITTANCE = 'agent_remittance';

    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_CUSTOMER_PAYMENT = 'customer_payment';

    /**
     * A pending transaction (e.g. a payment staged for transfer into the
     * general treasury) does NOT count toward the running balance chain
     * yet — previous_balence/current_balence stay null until it's
     * approved. Every other existing flow in the app creates rows that
     * default straight to APPROVED, so nothing else changes behavior.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'direction',
        'amount',
        'previous_balence',
        'current_balence',
        'source_type',
        'source_id',
        'transaction_date',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'previous_balence' => 'decimal:2',
            'current_balence' => 'decimal:2',
            'transaction_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The staff member who approved a pending transaction (e.g. a general
     * treasury transfer). Null for transactions that never needed
     * approval (the overwhelming majority — anything created APPROVED).
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope: only transactions that actually count toward the treasury's
     * real balance. Always use this — never the bare table — when
     * computing "the latest current_balence", or a pending row (whose
     * balance columns are still null) will corrupt the chain.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Polymorphic-style accessor to the originating record (a customer
     * payment, supplier payment, expense, or agent remittance).
     *
     * NOTE: `source_type` stores a short logical key (see SOURCE_* consts),
     * not a fully-qualified class name, so this is NOT a true Eloquent
     * morphTo() — it's implemented manually via the source() accessor below
     * to keep the column human-readable in the database.
     */
    public function source(): ?Model
    {
        return match ($this->source_type) {
            self::SOURCE_CUSTOMER_PAYMENT => CustomerPayment::query()->find($this->source_id),
            self::SOURCE_SUPPLIER_PAYMENT => SupplierPayment::query()->find($this->source_id),
            self::SOURCE_EXPENSE => Expense::query()->find($this->source_id),
            self::SOURCE_AGENT_REMITTANCE => AgentTransaction::query()->find($this->source_id),
            default => null,
        };
    }
}
