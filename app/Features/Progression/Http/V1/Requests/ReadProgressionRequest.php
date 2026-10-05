<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Requests;

use App\Features\Progression\Enums\AdminProgressionPermission;
use App\SharedFeatures\Clock\DomainClock;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReadProgressionRequest extends FormRequest
{
    public function authorize(UserContext $user): bool
    {
        return $user->can(AdminProgressionPermission::Read, UserSurface::AdminPanel);
    }

    public function rules(): array
    {
        $rules = ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']];
        $resource = $this->route('read_resource');
        $utc = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! DomainClock::validUtc($value)) {
                $fail('A real UTC timestamp ending in Z is required.');
            }
        };
        if ($resource === 'distributions') {
            $rules += ['module_id' => ['sometimes', 'uuid'], 'source_activity_id' => ['sometimes', 'string', 'max:191'],
                'plan_id' => ['sometimes', 'uuid'], 'subscription_id' => ['sometimes', 'uuid'],
                'resolved_at_from' => ['sometimes', $utc], 'resolved_at_to' => ['sometimes', $utc, ...($this->has('resolved_at_from') ? ['after:resolved_at_from'] : [])]];
        } elseif ($resource === 'runs') {
            $rules += ['plan_id' => ['sometimes', 'uuid'], 'status' => ['sometimes', Rule::in(['pending', 'running', 'completed', 'completed_with_errors'])],
                'window_from' => ['sometimes', $utc], 'window_to' => ['sometimes', $utc, ...($this->has('window_from') ? ['after:window_from'] : [])]];
        } else {
            $rules += ['subscription_id' => ['sometimes', 'uuid'], 'status' => ['sometimes', Rule::in(['completed', 'skipped', 'failed'])]];
        }

        return $rules;
    }
}
