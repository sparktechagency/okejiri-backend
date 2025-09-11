<?php
namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SocialLoginRequest extends FormRequest
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
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'google_id'   => 'nullable|string|required_without_all:facebook_id,twitter_id,apple_id',
            'facebook_id' => 'nullable|string|required_without_all:google_id,twitter_id,apple_id',
            'twitter_id'  => 'nullable|string|required_without_all:google_id,facebook_id,apple_id',
            'apple_id'    => 'nullable|string|required_without_all:google_id,facebook_id,twitter_id',
            'photo'       => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            if (
                empty($data['google_id']) &&
                empty($data['facebook_id']) &&
                empty($data['twitter_id']) &&
                empty($data['apple_id'])
            ) {
                $validator->errors()->add('social_id', 'At least one of google_id, facebook_id, twitter_id, or apple_id is required.');
            }
        });
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
