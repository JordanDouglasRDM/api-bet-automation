<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Override;

class UpdateLicenseRequest extends FormRequest
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
        $status = $this->status;
        if ($status) {
            return [
                'id'     => 'required|integer|exists:licenses,id',
                'status' => 'required|in:revoke,renew',
            ];
        }


        $id = $this->route('license');
        return [
            'id'         => 'required|integer|exists:licenses,id',
            'start_at'   => 'required_if:lifetime,false|date|after_or_equal:today|before_or_equal:expires_at',
            'expires_at' => 'required_if:lifetime,false|date|after_or_equal:start_at',
            'lifetime'   => 'required|boolean',
            'code'       => 'required|string',
            'login'      => [
                'required',
                'string',
                Rule::unique('users', 'login')
                    ->where(fn($q) => $q->where('code', $this->code))
                    ->ignore($id, 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'login.unique' => 'Para o código informado, este login já está cadastrado.',
            'start_at.after_or_equal'   => 'A data inicial não pode ser anterior a hoje.',
            'start_at.before_or_equal'  => 'A data inicial não pode ser maior que a data final.',
            'expires_at.after_or_equal' => 'A data final não pode ser menor que a data inicial.',
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'id' => $this->route('license'),
        ]);
    }

    #[Override]
    protected function failedValidation(Validator $validator)
    {
        $messages = implode('<br>', $validator->errors()->all());
        throw new HttpResponseException(response()->json([
            'status'          => 'error',
            'message'         => 'Os dados fornecidos são inválidos!',
            'errors'          => $validator->errors(),
            'errors_imploded' => $messages,
        ], 422));
    }
}
