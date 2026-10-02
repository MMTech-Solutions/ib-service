<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Requests;

use App\Features\Rewards\Enums\AdminRewardPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class CancelRewardRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminRewardPermission::Manage, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason_code' => ['required', 'string', 'max:64'], 'reason_label' => ['nullable', 'string', 'max:255']];
    }
}
