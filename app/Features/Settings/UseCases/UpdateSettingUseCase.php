<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\DTOs\SettingViewData;
use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\Factories\SettingRepositoryFactory;
use App\Features\Settings\Http\V1\Commands\UpdateSettingCommand;
use App\Features\Settings\Services\PresentSettingService;
use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\Features\Settings\Services\SettingValueValidator;
use App\SharedFeatures\Clock\DomainClock;

final class UpdateSettingUseCase
{
    public function __construct(private readonly SettingRepositoryFactory $repositories, private readonly SettingDefinitionRegistry $registry, private readonly SettingValueValidator $validator, private readonly PresentSettingService $presenter, private readonly DomainClock $clock) {}

    public function execute(UpdateSettingCommand $command): SettingViewData
    {
        $definition = $this->registry->find($command->key);
        $this->validator->validate($definition, $command->value);
        $repository = $this->repositories->make();

        return $repository->transaction(function () use ($repository, $command, $definition): SettingViewData {
            $before = $repository->records([$command->key])[$command->key] ?? throw SettingException::unsynchronized($command->key);
            if ($before->definition->toArray() !== $definition->toArray()) {
                throw SettingException::unsynchronized($command->key);
            }
            if ($before->lock_version !== $command->lock_version) {
                throw SettingException::conflict();
            }
            $now = $this->clock->now()->toISOString();
            $after = new SettingRecordData($definition, $command->value, 'stored', $before->lock_version + 1, $before->created_at, $now);
            $repository->save($after, $before->lock_version);
            $redact = $definition->sensitive || $before->definition->sensitive;
            $repository->audit(new SettingAuditData($command->key, $command->actor, 'update', $command->reason, $this->presenter->auditState($before, $redact), $this->presenter->auditState($after, $redact), $now));

            return $this->presenter->view($definition, $after);
        });
    }
}
