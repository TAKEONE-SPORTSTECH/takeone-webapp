<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description'      => 'required|string|max:255',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'category'         => 'nullable|string|max:255',
            'payment_method'   => 'nullable|in:cash,card,bank_transfer,online,other',
            'reference_number' => 'nullable|string|max:255',
        ];
    }
}
