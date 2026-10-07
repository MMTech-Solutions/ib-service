<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ListSettingsRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        if (! $context->can('ib.settings.read', UserSurface::AdminPanel)) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'domain' => ['sometimes', 'in:Rewards,Modules,Finance'],
            'section' => ['sometimes', 'string', 'max:100'],
            'provider' => ['sometimes', 'in:broker,copy_trading'],

        ];
    }
}
