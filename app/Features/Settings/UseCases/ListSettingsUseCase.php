<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\DTOs\SettingsPageData;
use App\Features\Settings\Factories\SettingRepositoryFactory;
use App\Features\Settings\Http\V1\Commands\ListSettingsCommand;
use App\Features\Settings\Services\PresentSettingService;
use App\Features\Settings\Services\SettingDefinitionRegistry;

final class ListSettingsUseCase
{
    public function __construct(private readonly SettingRepositoryFactory $repositories, private readonly SettingDefinitionRegistry $registry, private readonly PresentSettingService $presenter) {}

    public function execute(ListSettingsCommand $command): SettingsPageData
    {
        $definitions = array_values(array_filter($this->registry->all(), static function (SettingDefinitionData $definition) use ($command): bool {
            return ($command->domain === null || $definition->domain === $command->domain)
                && ($command->section === null || $definition->section === $command->section)
                && ($command->provider === null || $definition->provider === $command->provider)
                && ($command->search === null || str_contains(strtolower($definition->key.' '.$definition->name), strtolower($command->search)));
        }));
        $total = count($definitions);
        $definitions = array_slice($definitions, ($command->page - 1) * $command->per_page, $command->per_page);
        $records = $this->repositories->make()->records(array_map(static fn (SettingDefinitionData $definition): string => $definition->key, $definitions));
        $items = [];
        foreach ($definitions as $definition) {
            $items[] = $this->presenter->view($definition, $records[$definition->key] ?? null);
        }

        return new SettingsPageData($items, $total, $command->page, $command->per_page);
    }
}
