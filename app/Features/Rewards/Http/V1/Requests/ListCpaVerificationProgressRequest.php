<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Requests;

use App\Features\Rewards\Actions\ResolveCpaProgressReadAccessAction;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCpaVerificationProgressRequest extends FormRequest
{
    public function authorize(UserContext $userContext, ResolveCpaProgressReadAccessAction $access): bool
    {
        $access->resolve($userContext, $this->surface());

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $administrative = $this->surface() === UserSurface::AdminPanel;

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'qualified', 'error', 'expired'])],
            'ib_user_id' => $administrative ? ['sometimes', 'uuid'] : ['prohibited'],
            'referred_user_id' => $administrative ? ['sometimes', 'uuid'] : ['prohibited'],
            'program_id' => $administrative ? ['sometimes', 'uuid'] : ['prohibited'],
            'module_id' => $administrative ? ['sometimes', 'uuid'] : ['prohibited'],
        ];
    }

    private function surface(): UserSurface
    {
        return UserSurface::from((string) $this->route('user_surface'));
    }
}
