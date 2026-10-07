<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ShowSettingRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        if (! $context->can('ib.settings.read', UserSurface::AdminPanel)) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['key' => $this->route('key')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:160'],

        ];
    }
}
