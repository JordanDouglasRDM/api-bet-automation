<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Override;

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
            'start_at'   => 'required_if:lifetime,false|date|after_or_equal:today|before_or_equal:expires_at',
            'expires_at' => 'required_if:lifetime,false|date|after_or_equal:start_at',
            'lifetime'      => 'required|boolean',
            'users'         => 'required|array',
            'users.*.code'  => 'required|string',
            'users.*.price'  => 'required|numeric|min:0',
            'users.*.indication'  => 'nullable|string|max:255',
            'users.*.login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $code = data_get($this->users, "$index.code");

                    $exists = \DB::table('users')
                        ->where('code', $code)
                        ->where('login', $value)
                        ->exists();

                    if ($exists) {
                        $fail("Para o código '$code' já existe o login '$value' cadastrado.");
                    }
                }
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'start_at.after_or_equal'   => 'A data inicial não pode ser anterior a hoje.',
            'start_at.before_or_equal'  => 'A data inicial não pode ser maior que a data final.',
            'expires_at.after_or_equal' => 'A data final não pode ser menor que a data inicial.',
        ];
    }

    #[Override]
    protected function failedValidation(Validator $validator)
    {
        $messages = implode('<br>', $validator->errors()->all());
        throw new HttpResponseException(response()->json([
            'status'          => 'error',
            'message'         => 'Os dados fornecidos são inválidos!',
            'errors_imploded' => $messages,
            'errors'          => $validator->errors(),
        ], 422));
    }
}
