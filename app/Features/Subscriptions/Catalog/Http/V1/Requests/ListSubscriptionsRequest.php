<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Requests;

use App\Features\Subscriptions\Catalog\Enums\AdminSubscriptionPermission;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSubscriptionsRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminSubscriptionPermission::Manage, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'plan_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'string', Rule::enum(SubscriptionStatus::class)],
            'external_user_id' => ['sometimes', 'uuid'],
        ];
    }
}
