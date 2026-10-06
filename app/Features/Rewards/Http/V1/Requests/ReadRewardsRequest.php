<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Requests;

use App\Features\Rewards\Actions\ResolveRewardReadAccessAction;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReadRewardsRequest extends FormRequest
{
    public function authorize(UserContext $user, ResolveRewardReadAccessAction $access): bool
    {
        $scope = $access->resolve($user, UserSurface::from((string) $this->route('user_surface')));

        return $this->route('read_resource') === 'rewards' || $scope->include_audit;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $admin = $this->route('user_surface') === UserSurface::AdminPanel->value;
        $resource = $this->route('read_resource');
        $statuses = match ($resource) {
            'jobs' => ['pending', 'processing', 'failed', 'completed'],
            'periods' => ['preparing', 'ready', 'completed'],
            default => ['pending', 'failed', 'settled', 'cancelled', 'reversal_pending', 'reversal_failed', 'reversed'],
        };

        return [
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'commission_type' => $resource === 'rewards' ? ['sometimes', Rule::in(['cpa', 'volume', 'pnl'])] : ['prohibited'],
            'plan_id' => ['sometimes', 'uuid'], 'program_id' => ['sometimes', 'uuid'], 'module_id' => ['sometimes', 'uuid'],
            'beneficiary_id' => $admin ? ['sometimes', 'uuid'] : ['prohibited'],
            'subscription_id' => $admin && $resource !== 'rewards' ? ['sometimes', 'uuid'] : ['prohibited'],
            'job_id' => $admin && $resource === 'periods' ? ['sometimes', 'uuid'] : ['prohibited'],
            'server_group_id' => ['prohibited'],
            'occurred_from' => ['sometimes', 'date'],
            'occurred_until' => ['sometimes', 'date', Rule::when($this->filled('occurred_from'), 'after:occurred_from')],
        ];
    }
}
