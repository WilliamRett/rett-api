<?php

namespace App\Http\Requests\Collaborator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCollaboratorPatchRequest extends FormRequest
{
    /**
     * Authorize this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize only the fields that are actually present in the PATCH payload.
     * - Do not inject missing keys.
     * - Only sanitize what's being updated.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name'))  { $data['name']  = Str::squish(trim((string) $this->input('name'))); }
        if ($this->has('email')) { $data['email'] = strtolower(trim((string) $this->input('email'))); }
        if ($this->has('cpf'))   { $data['cpf']   = preg_replace('/\D+/', '', (string) $this->input('cpf')); }
        if ($this->has('city'))  { $data['city']  = Str::squish(trim((string) $this->input('city'))); }
        if ($this->has('state')) { $data['state'] = strtoupper(trim((string) $this->input('state'))); }
        if ($this->has('phone')) { $data['phone'] = preg_replace('/\D+/', '', (string) $this->input('phone')); }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Validation rules for a partial update (PATCH).
     * - Only validate fields when they are present (sometimes).
     */
    public function rules(): array
    {
        $userId = (int) optional($this->user())->id;
        $id     = (int) $this->route('id');

        return [
            'name'  => ['sometimes','required','string','max:150'],
            'email' => [
                'sometimes','required','email:rfc,dns','max:150',
                Rule::unique('collaborators','email')
                    ->where(fn($q) => $q->where('user_id', $userId))
                    ->ignore($id),
            ],
            'cpf'   => [
                'sometimes','required','digits:11',
                Rule::unique('collaborators','cpf')->ignore($id),
            ],
            'city'  => ['sometimes','nullable','string','max:120'],
            'state' => ['sometimes','nullable','string','max:30'],
            'phone' => ['sometimes','nullable','string','max:30'],
        ];
    }
}
