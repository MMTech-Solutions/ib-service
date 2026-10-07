<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class CertifySettingsConnectionRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        return $context->can('ib.settings.manage', UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['provider' => ['required', 'string'], 'base_url' => ['missing'], 'internal_token' => ['missing'], 'source_service' => ['missing'], 'url' => ['missing'], 'token' => ['missing']];
    }
}
