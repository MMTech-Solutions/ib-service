<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReadSchedulingRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        $permission = match ($this->route('scheduling_resource')) {
            'audits' => 'ib.scheduling.audit', 'output' => 'ib.scheduling.output', default => 'ib.scheduling.read'
        };

        return $context->can($permission, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => $this->route('code') ?? $this->query('task_code'), 'id' => $this->route('id')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:120'], 'id' => ['nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(['queued', 'running', 'succeeded', 'failed', 'skipped', 'interrupted'])],
            'origin' => ['sometimes', Rule::in(['automatic', 'manual'])],
            'from' => ['sometimes', 'date'], 'until' => ['sometimes', 'date', 'after:from'],
        ];
    }
}
