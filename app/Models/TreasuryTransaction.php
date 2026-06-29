<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TreasuryTransaction extends Model
{
    use HasFactory;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const SOURCE_AGENT_REMITTANCE = 'agent_remittance';

    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';

    public const SOURCE_EXPENSE = 'expense';

    public const SOURCE_CUSTOMER_PAYMENT = 'customer_payment';

    protected $fillable = [
        'direction',
        'amount',
        'previous_balence',
        'current_balence',
        'source_type',
        'source_id',
        'transaction_date',
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
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
    public function source(): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($this->source_type) {
            self::SOURCE_CUSTOMER_PAYMENT => CustomerPayment::find($this->source_id),
            self::SOURCE_SUPPLIER_PAYMENT => SupplierPayment::find($this->source_id),
            self::SOURCE_EXPENSE => Expense::find($this->source_id),
            self::SOURCE_AGENT_REMITTANCE => AgentTransaction::find($this->source_id),
            default => null,
        };
    }
}
