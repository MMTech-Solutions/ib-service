<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Requests;

use App\Features\Rules\Catalog\Enums\AdminRulePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowRuleRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminRulePermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => $this->route('plan'),
            'rule' => $this->route('rule'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'uuid'],
            'rule' => ['required', 'uuid'],
        ];
    }
}
