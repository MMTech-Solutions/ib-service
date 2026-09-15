<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Requests;

use App\Features\Rules\Catalog\Enums\AdminRulePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'slug' => ['prohibited'],
            'strategy_type' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->exists('name') && ! $this->exists('description')) {
                $validator->errors()->add('name', 'At least one of name or description must be present.');
            }
        });
    }
}
