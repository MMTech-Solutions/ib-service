<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class CertifyModuleConnectionRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        return $context->can('ib.modules.manage', UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['module' => $this->route('module')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['module' => ['required', 'uuid'], 'base_url' => ['missing'], 'internal_token' => ['missing'], 'source_service' => ['missing'], 'url' => ['missing'], 'token' => ['missing']];
    }
}
