<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Requests;

use App\Features\Programs\Catalog\Enums\AdminProgramPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderProgramsRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgramPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plan' => $this->route('plan')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'uuid'],
            'programs' => ['required', 'array', 'min:1'],
            'programs.*.id' => ['required', 'uuid', 'distinct'],
            'programs.*.entry_threshold' => ['required', 'integer', 'min:0'],
            'programs.*.lock_version' => ['required', 'integer', 'min:1'],
            'program_ids' => ['prohibited'],
        ];
    }
}
