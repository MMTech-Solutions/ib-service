<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Requests;

use App\Features\Programs\Catalog\Enums\AdminProgramPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ReplaceProgramSymbolConfigurationsRequest extends FormRequest
{
    public function authorize(UserContext $userContext): bool
    {
        return $userContext->can(AdminProgramPermission::Manage, UserSurface::AdminPanel);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plan' => $this->route('plan'), 'program' => $this->route('program')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'uuid'], 'program' => ['required', 'uuid'], 'symbols' => ['required', 'array', 'max:100'],
            'symbols.*.module_id' => ['required', 'uuid'], 'symbols.*.instrument_reference' => ['required', 'string', 'max:200', 'distinct'],
            'symbols.*.use_for_progression' => ['required', 'boolean'], 'symbols.*.plan_progression_template_version_binding_id' => ['nullable', 'uuid'],
            'symbols.*.use_for_volume_reward' => ['required', 'boolean'], 'symbols.*.plan_payment_template_version_binding_id' => ['nullable', 'uuid'],
            'symbols.*.commission_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])], 'symbols.*.commission_value' => ['nullable', 'decimal:0,8'],
            'symbols.*.use_for_cpa' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('symbols', []) as $index => $symbol) {
                if (($symbol['use_for_progression'] ?? false) && empty($symbol['plan_progression_template_version_binding_id'])) {
                    $validator->errors()->add("symbols.$index.plan_progression_template_version_binding_id", 'A progression template binding is required when progression is enabled.');
                }
                if (($symbol['use_for_volume_reward'] ?? false) && empty($symbol['plan_payment_template_version_binding_id'])) {
                    $validator->errors()->add("symbols.$index.plan_payment_template_version_binding_id", 'A payment template binding is required when volume rewards are enabled.');
                }
                if (($symbol['use_for_volume_reward'] ?? false) && ! in_array($symbol['commission_type'] ?? null, ['fixed', 'percentage'], true)) {
                    $validator->errors()->add("symbols.$index.commission_type", 'A fixed or percentage commission type is required when volume rewards are enabled.');
                }
                if (($symbol['use_for_volume_reward'] ?? false) && (! isset($symbol['commission_value']) || bccomp((string) $symbol['commission_value'], '0', 8) !== 1)) {
                    $validator->errors()->add("symbols.$index.commission_value", 'A positive commission value is required when volume rewards are enabled.');
                }
                if (! ($symbol['use_for_volume_reward'] ?? false) && (($symbol['plan_payment_template_version_binding_id'] ?? null) !== null || ($symbol['commission_type'] ?? null) !== null || ($symbol['commission_value'] ?? null) !== null)) {
                    $validator->errors()->add("symbols.$index.use_for_volume_reward", 'Volume reward configuration must be omitted when volume rewards are disabled.');
                }
            }
        });
    }
}
