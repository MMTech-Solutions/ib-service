<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Requests;

use App\Features\Scheduling\ValueObjects\CronExpression;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSchedulingTaskRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        return $context->can('ib.scheduling.update', UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => $this->route('code')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:120'],
            'version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\\S/'],
            'description' => ['sometimes', 'required_without_all:cron_expression,automatic_enabled', 'string', 'max:5000', 'regex:/\\S/'],
            'cron_expression' => ['sometimes', 'required_without_all:description,automatic_enabled', 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! CronExpression::valid($value)) {
                    $fail('A valid five-field cron expression is required.');
                }
            }],
            'automatic_enabled' => ['sometimes', 'required_without_all:description,cron_expression', 'boolean'],
            'command' => ['prohibited'], 'arguments' => ['prohibited'], 'timezone' => ['prohibited'], 'timeout_seconds' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasAny(['description', 'cron_expression', 'automatic_enabled'])) {
                $validator->errors()->add('configuration', 'At least one editable configuration field is required.');
            }
        });
    }
}
