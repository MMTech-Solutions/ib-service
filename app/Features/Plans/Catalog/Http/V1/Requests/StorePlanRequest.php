<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Requests;

use App\Features\Plans\Catalog\Enums\AdminPlanPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class StorePlanRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminPlanPermission::Manage, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'module_ids' => ['sometimes', 'array', 'max:50'],
            'module_ids.*' => ['uuid', 'distinct'],
            'is_active' => ['prohibited'],
            'lock_version' => ['prohibited'],
        ];
    }
}
