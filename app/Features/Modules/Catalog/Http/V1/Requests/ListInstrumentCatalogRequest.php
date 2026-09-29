<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Requests;

use App\Features\Modules\Catalog\Enums\AdminModulePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListInstrumentCatalogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'module' => $this->route('module'),
            'type' => $this->route('type'),
        ]);
    }

    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminModulePermission::Manage, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'module' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['platform', 'trading_server', 'server_group', 'security', 'symbol'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'platform' => ['sometimes', 'string', 'max:160'],
            'trading_server' => ['sometimes', 'string', 'max:160'],
            'server_group' => ['sometimes', 'string', 'max:160'],
            'security' => ['sometimes', 'string', 'max:160'],
            'symbol' => ['sometimes', 'string', 'max:200'],
        ];
    }
}
