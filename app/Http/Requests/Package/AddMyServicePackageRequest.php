<?php
namespace App\Http\Requests\Package;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddMyServicePackageRequest extends FormRequest
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
        $rules = [
            'service_id'          => 'required|exists:services,id|numeric',
            'title'               => 'required|string|max:255',
            'image'               => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price'               => 'required|numeric|min:1',
            'delivery_time'       => 'required|numeric|min:1',
            'service_details'     => 'required|array|min:1',
            'service_details.*'   => 'required|string|max:255',
            'available_time_from' => 'required|array',
            'available_time_to'   => 'required|array',
        ];

        if ($this->has('available_time_from') && is_array($this->available_time_from)) {
            foreach ($this->available_time_from as $index => $from) {
                $rules["available_time_from.$index"] = "nullable|date_format:h:i A|required_with:available_time_to.$index";
                $rules["available_time_to.$index"]   = "nullable|date_format:h:i A|required_with:available_time_from.$index|after:available_time_from.$index";
            }
        }

        return $rules;
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
