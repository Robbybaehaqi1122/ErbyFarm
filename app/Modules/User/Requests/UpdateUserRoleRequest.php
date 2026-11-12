<?php

namespace App\Modules\User\Requests;

use App\Modules\User\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Only admins may change roles.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
