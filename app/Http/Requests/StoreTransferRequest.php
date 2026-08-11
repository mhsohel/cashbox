<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
            ],
            'to_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                })->whereNot('id', $this->from_account_id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Customize error messages.
     */
    public function messages(): array
    {
        return [
            'to_account_id.where_not' => 'The destination account must be different from the source account.',
        ];
    }
}
