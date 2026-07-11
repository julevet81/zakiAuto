<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasuryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'amount' => (float) $this->amount,
            'previous_balance' => (float) $this->previous_balence,
            'current_balance' => (float) $this->current_balence,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'source_details' => $this->getSourceDetails(),
        ];
    }

    private function getSourceDetails(): array
    {
        $source = $this->source();
        if (!$source) {
            return [
                'name' => 'نظامي / غير محدد',
                'type_label' => 'معاملة خزينة',
            ];
        }

        switch ($this->source_type) {
            case \App\Models\TreasuryTransaction::SOURCE_CUSTOMER_PAYMENT:
                return [
                    'name' => $source->customer?->name ?? 'عميل غير معروف',
                    'order_number' => $source->order?->order_number,
                    'type_label' => 'دفعة عميل',
                ];

            case \App\Models\TreasuryTransaction::SOURCE_SUPPLIER_PAYMENT:
                return [
                    'name' => $source->supplier?->name ?? 'مورد غير معروف',
                    'batch_number' => $source->batch?->batch_number,
                    'type_label' => 'دفعة لمورد',
                ];

            case \App\Models\TreasuryTransaction::SOURCE_EXPENSE:
                return [
                    'name' => $source->serviceProvider?->name ?? 'مصروف عام',
                    'expense_type' => $source->expense_type,
                    'car_vin' => $source->car?->vin,
                    'type_label' => 'مصروفات',
                ];

            case \App\Models\TreasuryTransaction::SOURCE_AGENT_REMITTANCE:
                return [
                    'name' => $source->agent?->name ?? 'وكيل غير معروف',
                    'type_label' => 'تحويل من وكيل',
                ];

            default:
                return [
                    'name' => 'معاملة خزينة',
                    'type_label' => 'أخرى',
                ];
        }
    }
}
