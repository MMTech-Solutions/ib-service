<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Settings\Contracts\Data\V1\ResolvedSettingsData;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\Settings\Factories\SettingRepositoryFactory;
use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\Features\Settings\Services\SettingValueValidator;
use Illuminate\Contracts\Config\Repository;

final class ResolveSettingsUseCase implements ResolveSettingsPort
{
    public function __construct(private readonly SettingRepositoryFactory $repositories, private readonly SettingDefinitionRegistry $registry, private readonly SettingValueValidator $validator, private readonly Repository $config) {}

    public function execute(array $keys): ResolvedSettingsData
    {
        $definitions = [];
        foreach (array_unique($keys) as $key) {
            $definitions[$key] = $this->registry->find($key);
        }
        $records = $this->repositories->make()->records(array_keys($definitions));
        $values = [];
        foreach ($definitions as $key => $definition) {
            $record = $records[$key] ?? null;
            $value = $record !== null && $record->mode === 'stored' ? $record->value : $this->config->get($key);
            $this->validator->validate($definition, $value);
            $values[$key] = $value;
        }

        return new ResolvedSettingsData($values);
    }
}
