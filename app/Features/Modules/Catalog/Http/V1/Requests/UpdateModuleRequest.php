<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Requests;

use App\Features\Modules\Catalog\Enums\AdminModulePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateModuleRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminModulePermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['module' => $this->route('module')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'module' => ['required', 'uuid'],
            'name' => ['sometimes', 'required_without:description', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'required_without:name', 'nullable', 'string', 'max:5000'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'is_active' => ['prohibited'],
            'processing_status' => ['prohibited'],
            'capabilities' => ['prohibited'],
        ];
    }
}
