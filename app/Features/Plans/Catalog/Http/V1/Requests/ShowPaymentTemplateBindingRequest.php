<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Requests;

use App\Features\Plans\Catalog\Enums\AdminPlanPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowPaymentTemplateBindingRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminPlanPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plan' => $this->route('plan'), 'binding' => $this->route('binding')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['plan' => ['required', 'uuid'], 'binding' => ['required', 'uuid']];
    }
}
