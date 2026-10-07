<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\DTOs\AuditData;
use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\ReadResultData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\Exceptions\SchedulingException;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\PresentSchedulingData;

final class ReadSchedulingUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly PresentSchedulingData $present) {}

    public function execute(ReadQueryData $query): ReadResultData
    {
        $repository = $this->repositories->make();
        if ($query->id !== null) {
            $run = $repository->run($query->id) ?? throw SchedulingException::missing();

            return new ReadResultData($query->resource === 'output' ? $this->present->output($run) : $this->present->run($run));
        }
        if ($query->code !== null && $repository->task($query->code) === null) {
            throw SchedulingException::missing();
        }
        $map = function (TaskData|RunData|AuditData $item) use ($repository): array {
            return match (true) {
                $item instanceof TaskData => $this->present->task($item, $repository->page(new ReadQueryData('runs', code: $item->code, per_page: 1))->items[0] ?? null),
                $item instanceof RunData => $this->present->run($item),
                default => $item->toArray(),
            };
        };
        if ($query->resource === 'task') {
            return new ReadResultData($map($repository->task($query->code)));
        }
        $page = $repository->page($query);

        return new ReadResultData(array_map($map, $page->items), $page->total);
    }
}
