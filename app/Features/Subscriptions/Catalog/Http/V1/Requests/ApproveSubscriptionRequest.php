<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Requests;

use App\Features\Subscriptions\Catalog\Enums\AdminSubscriptionPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ApproveSubscriptionRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminSubscriptionPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['subscription' => $this->route('subscription')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subscription' => ['required', 'uuid'],
            'program_id' => ['sometimes', 'nullable', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['prohibited'],
            'plan_id' => ['prohibited'],
            'external_user_id' => ['prohibited'],
        ];
    }
}
