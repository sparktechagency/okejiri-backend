<?php

namespace App\Http\Requests\Package;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateServicePackageRequest extends FormRequest
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
            'title'               => 'required|string|max:255',
            'image'               => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'price'               => 'required|numeric|min:1',
            'delivery_time'       => 'required|numeric|min:1',

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
