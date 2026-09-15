<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Requests;

use App\Features\Rules\Catalog\Enums\AdminRulePermission;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRuleRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminRulePermission::Manage, UserSurface::AdminPanel);
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
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'strategy_type' => ['required', 'string', Rule::in(array_column(RuleStrategyType::cases(), 'value'))],
            'slug' => ['prohibited'],
            'lock_version' => ['prohibited'],
        ];
    }
}
