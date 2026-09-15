<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Requests;

use App\Features\Rules\Assignments\Enums\AdminRuleAssignmentPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ReplaceRuleAssignmentRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminRuleAssignmentPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => $this->route('plan'),
            'rule' => $this->route('rule'),
            'assignment' => $this->route('assignment'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'uuid'],
            'rule' => ['required', 'uuid'],
            'assignment' => ['required', 'uuid'],
            'rule_version_id' => ['required', 'uuid'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'program_id' => ['prohibited'],
            'module_id' => ['prohibited'],
            'scope_type' => ['prohibited'],
        ];
    }
}
