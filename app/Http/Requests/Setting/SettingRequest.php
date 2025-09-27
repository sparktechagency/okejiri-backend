<?php
namespace App\Http\Requests\Setting;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SettingRequest extends FormRequest
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
            'referral_bonus_amount'        => 'nullable|numeric',
            'minimum_withdrawal_threshold' => 'nullable|numeric',
            'three_day_boosting_price'     => 'nullable|numeric',
            'seven_day_boosting_price'     => 'nullable|numeric',
            'fifteen_day_boosting_price'   => 'nullable|numeric',
            'thirty_day_boosting_price'    => 'nullable|numeric',
        ];
    }

    public function messages()
    {
        return [
            //
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
