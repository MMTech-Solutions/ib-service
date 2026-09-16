<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Requests;

use App\Features\Subscriptions\Catalog\Enums\CustomerSubscriptionPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCurrentSubscriptionRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(CustomerSubscriptionPermission::Read, UserSurface::CustomerApp);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
