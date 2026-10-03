<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Requests;

use App\Features\Programs\Catalog\Enums\AdminProgramPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceNegativePnlConfigurationRequest extends FormRequest
{
    public function authorize(UserContext $user): bool
    {
        return $user->can(AdminProgramPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plan' => $this->route('plan'), 'program' => $this->route('program')]);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['plan' => ['required', 'uuid'], 'program' => ['required', 'uuid'], 'cadence' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])], 'groups' => ['present', 'array', 'list'], 'groups.*' => ['required', 'array:module_id,server_group_id,rule_version_id'], 'groups.*.module_id' => ['required', 'uuid'], 'groups.*.server_group_id' => ['required', 'string', 'max:191'], 'groups.*.rule_version_id' => ['required', 'uuid'], 'starts_at' => ['prohibited'], 'ends_at' => ['prohibited']];
    }
}
