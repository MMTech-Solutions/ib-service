<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Requests;

use App\Features\Programs\ProgressionTemplates\Enums\AdminProgressionTemplatePermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class ManageProgressionTemplateRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgressionTemplatePermission::Manage, UserSurface::AdminPanel);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:120', 'regex:/\\S/'], 'description' => ['sometimes', 'nullable', 'string', 'max:5000'], 'lock_version' => ['sometimes', 'required', 'integer', 'min:1'], 'levels' => ['sometimes', 'required', 'array', 'min:1'], 'levels.*.distribution_level' => ['required_with:levels', 'integer', 'min:0', 'distinct'], 'levels.*.weight' => ['required_with:levels', 'string', 'regex:/^(?:0|[1-9]\\d*)(?:\\.\\d{1,8})?$/']];
    }
}
