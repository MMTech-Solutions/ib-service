<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Settings\DTOs\SettingViewData;
use App\Features\Settings\Factories\SettingRepositoryFactory;
use App\Features\Settings\Http\V1\Commands\ShowSettingCommand;
use App\Features\Settings\Services\PresentSettingService;
use App\Features\Settings\Services\SettingDefinitionRegistry;

final class ShowSettingUseCase
{
    public function __construct(private readonly SettingRepositoryFactory $repositories, private readonly SettingDefinitionRegistry $registry, private readonly PresentSettingService $presenter) {}

    public function execute(ShowSettingCommand $command): SettingViewData
    {
        $definition = $this->registry->find($command->key);
        $records = $this->repositories->make()->records([$command->key]);

        return $this->presenter->view($definition, $records[$command->key] ?? null);
    }
}
