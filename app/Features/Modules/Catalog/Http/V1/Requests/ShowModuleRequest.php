<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Requests;

use App\Features\Modules\Catalog\Enums\AdminModulePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowModuleRequest extends FormRequest
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
        ];
    }
}
