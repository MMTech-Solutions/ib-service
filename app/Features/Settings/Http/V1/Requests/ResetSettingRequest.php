<?php

declare(strict_types=1);

namespace App\Features\Settings\Http\V1\Requests;

use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ResetSettingRequest extends FormRequest
{
    public function authorize(UserContext $context, SettingDefinitionRegistry $registry): bool
    {
        if (! $context->can('ib.settings.manage', UserSurface::AdminPanel)) {
            return false;
        }

        return ! $registry->find((string) $this->route('key'))->sensitive || $context->can('ib.settings.secrets.manage', UserSurface::AdminPanel);
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
            'lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000', 'regex:/\\S/'],
            'definition' => ['missing'],
            'mode' => ['missing'],
            'domain' => ['missing'],
            'type' => ['missing'],
            'sensitive' => ['missing'],
            'value' => ['missing'],
        ];
    }
}
