<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Requests;

use App\Features\Plans\Catalog\Enums\AdminPlanPermission;
use App\Features\Plans\Catalog\Enums\PlanProgressionPeriod;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlanRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminPlanPermission::Manage, UserSurface::AdminPanel);
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
            'name' => ['sometimes', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'module_ids' => ['sometimes', 'array', 'max:50'],
            'module_ids.*' => ['uuid', 'distinct'],
            'requires_approval' => ['sometimes', 'boolean'],
            'progression_period' => ['sometimes', 'string', Rule::enum(PlanProgressionPeriod::class)],
            'lock_version' => ['required', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (
                ! $this->exists('name')
                && ! $this->exists('description')
                && ! $this->exists('module_ids')
                && ! $this->exists('requires_approval')
                && ! $this->exists('progression_period')
            ) {
                $validator->errors()->add(
                    'name',
                    'At least one of name, description, module_ids, requires_approval, or progression_period must be present.',
                );
            }
        });
    }
}
