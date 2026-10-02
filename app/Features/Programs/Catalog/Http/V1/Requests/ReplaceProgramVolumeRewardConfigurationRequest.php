<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Requests;

use App\Features\Programs\Catalog\Enums\AdminProgramPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceProgramVolumeRewardConfigurationRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgramPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plan' => $this->route('plan'), 'program' => $this->route('program')]);
    }

    public function rules(): array
    {
        return ['plan' => ['required', 'uuid'], 'program' => ['required', 'uuid'], 'mode' => ['sometimes', 'string', Rule::in(['periodic', 'event', 'both'])]];
    }
}
