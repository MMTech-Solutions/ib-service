<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Requests;

use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;

final class RequestSchedulingRunRequest extends FormRequest
{
    public function authorize(UserContext $context): bool
    {
        return $context->can('ib.scheduling.execute', UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => $this->route('code'), 'idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\\S/'], 'idempotency_key' => ['required', 'string', 'max:200', 'regex:/^[\\x21-\\x7E]+$/'], 'command' => ['prohibited'], 'arguments' => ['prohibited']];
    }
}
