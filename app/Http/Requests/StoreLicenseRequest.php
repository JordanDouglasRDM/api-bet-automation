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
            'start_at'      => 'required_if:lifetime,false|date',
            'expires_at'    => 'required_if:lifetime,false|date',
            'lifetime'      => 'required|boolean',
            'users'         => 'required|array',
            'users.*.code'  => 'required|string',
            'users.*.login' => 'required|string',
        ];
    }

    #[Override]
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Os dados fornecidos são inválidos!',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
