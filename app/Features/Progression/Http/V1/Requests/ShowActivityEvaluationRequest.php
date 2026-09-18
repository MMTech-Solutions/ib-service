<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Requests;

use App\Features\Progression\Enums\AdminProgressionPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowActivityEvaluationRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgressionPermission::Read, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activity_evaluation' => $this->route('activity_evaluation')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'activity_evaluation' => ['required', 'uuid'],
        ];
    }
}
