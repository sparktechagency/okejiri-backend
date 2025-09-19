<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompletePersonalizationRequest extends FormRequest
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
            'phone'=>'required|string|max:20',
            'address'=>'required|string',
            'role'=>'required|in:USER,PROVIDER',
            'provider_type' => 'required_if:role,PROVIDER|in:Individual,Company',
            'about'=>'required_if:role,PROVIDER|string',
            'service_id'=>'required_if:role,PROVIDER|array|min:1',
            'business_logo'=>'sometimes|image|mimes:png,jpg,jpeg|max:10240',
            'business_name'=>'required_if:provider_type,Company|string|max:255',
            'business_location'=>'required_if:provider_type,Company|string|max:255',
            'about_business'=>'required_if:provider_type,Company|string',
            'emp_no'=>'required_if:provider_type,Company|numeric',
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
            'errors' => $validator->errors(),
        ], 422));
    }
}
