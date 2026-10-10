<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Progression\Contracts\Data\V1\ResolveProgressionResultReferencesQueryData;
use App\Features\Progression\Contracts\Ports\Input\ResolveProgressionResultReferencesPort;
use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionChangeHistoryData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionHistoryResultData;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionChangesCommand;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;

final class ListSubscriptionChangesUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly PresentSubscriptionAction $presentSubscription,
        private readonly ResolveProgressionResultReferencesPort $progressionReferences,
    ) {}

    public function execute(ListSubscriptionChangesCommand $command): SubscriptionHistoryResultData
    {
        $page = $this->repositoryFactory->make()->paginateChanges($command->query);
        $operationIds = array_values(array_map(static fn (SubscriptionChange $change): string => $change->operationId,
            array_filter($page->entries, static fn (SubscriptionChange $change): bool => $change->action === SubscriptionChangeAction::ProgressionPlacement)));
        $references = $this->progressionReferences->execute(new ResolveProgressionResultReferencesQueryData($command->query->subscriptionId, $operationIds));
        $byResult = [];
        foreach ($references as $reference) {
            $byResult[$reference->run_result_id] = $reference;
        }
        $entries = array_map(function (SubscriptionChange $change) use ($byResult): SubscriptionChangeHistoryData {
            $reference = $change->action === SubscriptionChangeAction::ProgressionPlacement ? ($byResult[$change->operationId] ?? null) : null;

            return SubscriptionChangeHistoryData::from([...$this->presentSubscription->changeData($change)->toArray(), 'run_result_id' => $reference?->run_result_id, 'run_id' => $reference?->run_id]);
        }, $page->entries);

        return new SubscriptionHistoryResultData($entries, $page->total);
    }
}
