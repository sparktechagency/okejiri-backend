<?php
namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
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
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'role'          => 'required|in:USER,PROVIDER',
            'password'      => 'required|string|min:4|confirmed',
            'referral_code' => [
                'nullable',
                'numeric',
                Rule::exists('users', 'referral_code')->where(function ($query) {
                    $role = $this->input('role');
                    $query->where('role', $role);
                }),
            ],
            'role'          => 'required|in:PROVIDER,USER,ADMIN',
            'provider_type' => 'required_if:role,PROVIDER|in:Individual,Company',
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
