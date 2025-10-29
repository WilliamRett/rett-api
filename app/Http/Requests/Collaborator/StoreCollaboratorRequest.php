<?php

namespace App\Http\Requests\Collaborator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCollaboratorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => $this->name ? Str::squish(trim($this->name)) : null,
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'cpf'   => $this->cpf ? preg_replace('/\D+/', '', (string) $this->cpf) : null,
            'city'  => $this->city ? Str::squish(trim($this->city)) : null,
            'state' => $this->state ? strtoupper(trim($this->state)) : null,
            'phone' => $this->phone ? preg_replace('/\D+/', '', (string) $this->phone) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = (int) optional($this->user())->id;

        return [
            'name'  => ['required','string','max:150'],
            'email' => [
                'required','email:rfc,dns','max:150',
                Rule::unique('collaborators','email')->where(fn($q) => $q->where('user_id', $userId)),
            ],
            'cpf'   => ['required','digits:11','unique:collaborators,cpf'],
            'city'  => ['required','string','max:120'],
            'state' => ['required','string'],
            'phone' => ['nullable','string','max:30'],
        ];
    }
}
