<?php

namespace App\Modules\User\Requests;

use App\Modules\User\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Only admins and developers may create new accounts.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([Role::Admin->value, Role::Developer->value]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'in:admin,operator,viewer,developer'],
        ];
    }
}
