<?php

declare(strict_types = 1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreInstanceRequest extends FormRequest
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
            'nome' => [
                'required',
                'string',
                Rule::unique('instancias')->where(fn ($query) => $query->where('auth_id', $this->header('x-auth-id') ?? $this->input('auth_id'))),
            ],
            'auth_id'          => 'required|string',
            'usuarios'         => 'required|array',
            'usuarios.*.id'    => 'required|integer',
            'usuarios.*.login' => 'required|string',
            'usuarios.*.saldo' => 'required|numeric|min:0.01',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auth_id' => $this->header('x-auth-id'),
        ]);
    }

    #[Override]
    #[\Override]
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'Os dados fornecidos são inválidos!',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
