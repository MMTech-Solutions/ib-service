<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Requests;

use App\Features\Subscriptions\Catalog\Enums\AdminSubscriptionPermission;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\SharedFeatures\Clock\DomainClock;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSubscriptionChangesRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminSubscriptionPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['subscription' => $this->route('subscription')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $utc = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! DomainClock::validUtc($value)) {
                $fail('A real UTC timestamp ending in Z is required.');
            }
        };

        return [
            'subscription' => ['required', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'action' => ['sometimes', Rule::enum(SubscriptionChangeAction::class)],
            'actor_kind' => ['sometimes', Rule::enum(SubscriptionActorKind::class)],
            'occurred_at_from' => ['sometimes', $utc],
            'occurred_at_to' => ['sometimes', $utc, ...($this->has('occurred_at_from') ? ['after:occurred_at_from'] : [])],
        ];
    }
}
