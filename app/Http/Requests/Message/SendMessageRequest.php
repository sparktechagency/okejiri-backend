<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendMessageRequest extends FormRequest
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
            'receiver_id' => 'required|numeric|exists:users,id',
            'message' => 'nullable|string',
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,docx,txt|max:5120',
        ];
    }

    public function messages()
    {
        return [
            'attachments.*.mimes' => 'Each attachment must be a file of type: jpg, jpeg, png, pdf, docx, or txt.',
            'attachments.*.max' => 'Each attachment must not be larger than 5MB.',
            'attachments.max' => 'You can upload a maximum of 5 attachments.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $hasMessage = !empty($this->input('message'));
            $hasAttachments = $this->hasFile('attachments');

            if (!$hasMessage && !$hasAttachments) {
                $validator->errors()->add('message', 'Please provide a message or at least one attachment.');
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
