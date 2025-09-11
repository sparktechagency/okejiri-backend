<?php
namespace App\Http\Requests\Stripe\Connect;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePaymentIntentRequest extends FormRequest
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
            'amount'          => 'required|numeric|min:1',
            'currency'        => 'required|string|size:3',
            'seller_id'       => 'required|integer|exists:users,id',
            'product_id'      => 'required|integer|exists:products,id',
        ];
    }

    public function messages()
    {
        return [
            'currency.size' => 'Currency code must be exactly 3 characters (e.g., USD, BDT).',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
