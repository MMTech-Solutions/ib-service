<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Requests;

use App\Features\Subscriptions\Catalog\Enums\CustomerSubscriptionPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ApplyForSubscriptionRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(CustomerSubscriptionPermission::Apply, UserSurface::CustomerApp);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid'],
            'external_user_id' => ['prohibited'],
            'program_id' => ['prohibited'],
            'status' => ['prohibited'],
            'requires_approval' => ['prohibited'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
