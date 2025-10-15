<?php
namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BookingStoreRequest extends FormRequest
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
            'provider_id'        => 'required|numeric',
            'booking_process'    => 'required|in:instant,schedule',
            'booking_type'       => 'required|in:single,group',
            'schedule_date'      => 'required_if:booking_process,schedule|date_format:Y-m-d',
            'schedule_time_slot' => 'required_if:booking_process,schedule',
            'price'              => 'required',
            'number_of_people'   => 'required_if:booking_type,group|numeric|min:2',
            'name'=>'required|string|max:255',
            'email'=>'required|email|max:255',
            'phone'=>'required|string|max:20',
            'address'=>'required|string|max:255',
            'payment_type'=>'required|in:from_balance,make_payment',
            'payment_intent_id'=>'required_if:payment_type,make_payment',
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
