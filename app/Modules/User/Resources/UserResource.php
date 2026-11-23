<?php

namespace App\Modules\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\User\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'organization' => $this->organization,
            'phone' => $this->phone,
            'address' => $this->address,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getPermissionNames(),
            'created_at' => $this->created_at,
        ];
    }
}
