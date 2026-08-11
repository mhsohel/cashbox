<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLicenseRequest extends FormRequest
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
            'client_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('clients', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'next_renewal_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
