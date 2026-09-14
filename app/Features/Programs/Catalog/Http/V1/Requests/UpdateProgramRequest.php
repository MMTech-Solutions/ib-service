<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Requests;

use App\Features\Programs\Catalog\Enums\AdminProgramPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProgramRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgramPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => $this->route('plan'),
            'program' => $this->route('program'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'uuid'],
            'program' => ['required', 'uuid'],
            'name' => ['sometimes', 'string', 'max:120', 'regex:/\S/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'module_ids' => ['sometimes', 'array', 'max:50'],
            'module_ids.*' => ['uuid', 'distinct'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'code' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->exists('name') && ! $this->exists('description') && ! $this->exists('module_ids')) {
                $validator->errors()->add('name', 'At least one of name, description, or module_ids must be present.');
            }
        });
    }
}
