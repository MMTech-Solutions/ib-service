<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Requests;

use App\Features\Progression\Enums\AdminProgressionPermission;
use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListActivityEvaluationsRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgressionPermission::Read, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'plan_id' => ['sometimes', 'uuid'],
            'subscription_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'string', Rule::enum(EvaluationStatus::class)],
            'exclusion_reason' => ['sometimes', 'string', Rule::enum(ExclusionReason::class)],
            'occurred_at_from' => ['sometimes', 'date'],
            'occurred_at_to' => ['sometimes', 'date', 'after_or_equal:occurred_at_from'],
        ];
    }
}
