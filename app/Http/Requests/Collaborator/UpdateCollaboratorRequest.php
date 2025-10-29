<?php

namespace App\Http\Requests\Collaborator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCollaboratorRequest extends FormRequest
{
    /**
     * Authorize this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize inputs BEFORE validation.
     * For PUT we want a "full representation":
     * - Ensure every expected key is present (even if null) so "present" rules can pass.
     * - Sanitize/normalize values when provided.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => Str::squish(trim((string) ($this->input('name')))),
            'email' => strtolower(trim((string) ($this->input('email')))),
            'cpf'   => preg_replace('/\D+/', '', (string) ($this->input('cpf'))),
            'city'  => $this->exists('city')  ? Str::squish(trim((string) $this->input('city'))) : null,
            'state' => $this->exists('state') ? strtoupper(trim((string) $this->input('state'))) : null,
            'phone' => $this->exists('phone') ? preg_replace('/\D+/', '', (string) $this->input('phone')) : null,
        ]);
    }

    /**
     * Validation rules for a full replacement (PUT).
     * - Required fields: must be provided and valid.
     * - Optional fields: must be present (key exists) but may be null.
     */
    public function rules(): array
    {
        $userId = (int) optional($this->user())->id;
        $id     = (int) $this->route('id');

        return [
            'name'  => ['required','string','max:150'],
            'email' => [
                'required','email:rfc,dns','max:150',
                Rule::unique('collaborators','email')
                    ->where(fn($q) => $q->where('user_id', $userId))
                    ->ignore($id),
            ],
            'cpf'   => [
                'required','digits:11',
                Rule::unique('collaborators','cpf')->ignore($id),
            ],
            'city'  => ['present','nullable','string','max:120'],
            'state' => ['present','nullable','string','max:30'],
            'phone' => ['present','nullable','string','max:30'],
        ];
    }
}
