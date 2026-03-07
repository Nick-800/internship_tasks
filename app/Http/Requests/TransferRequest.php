<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_wallet_id'      => ['required', 'integer', 'exists:wallets,id'],
            'destination_wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'amount'                => ['required', 'numeric', 'min:0.01'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
