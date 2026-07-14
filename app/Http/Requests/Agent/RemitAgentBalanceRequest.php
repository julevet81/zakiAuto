<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RemitAgentBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // لم يعد هناك route model binding لوكيل — الشرط الوحيد أن
        // المستخدم المصادق مرتبط فعليًا بسجل Agent (أي هو وكيل).
        return $this->user()->agent !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['nullable', 'date'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // authorize() يفشل أصلاً قبل الوصول هنا، لكن تبقى للتوضيح
        ];
    }
}
