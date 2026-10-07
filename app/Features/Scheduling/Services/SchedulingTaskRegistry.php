<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\DTOs\TaskDefinitionData;
use App\Features\Scheduling\Exceptions\SchedulingException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;

final class SchedulingTaskRegistry
{
    /** @return list<TaskDefinitionData> */
    public function all(): array
    {
        $timeout = (int) config('scheduling.timeout_seconds', 3600);
        $definitions = [
            new TaskDefinitionData('plans.reconcile-without-operational-modules', 'Reconcile plans without operational modules', '*/5 * * * *', 'plans:reconcile-without-operational-modules', timeout_seconds: $timeout),
            new TaskDefinitionData('progression:close-windows', 'Close due progression windows', '*/5 * * * *', 'progression:close-windows', ['--json' => true], $timeout),
            new TaskDefinitionData('rewards:process-negative-pnl', 'Process due negative PnL rewards', '* * * * *', 'rewards:process-negative-pnl', timeout_seconds: $timeout),
            new TaskDefinitionData('rewards:verify-cpa', 'Verify CPA contexts', '*/5 * * * *', 'rewards:verify-cpa', timeout_seconds: $timeout),
            new TaskDefinitionData('rewards:settle-pending', 'Settle pending rewards', '* * * * *', 'rewards:settle-pending', timeout_seconds: $timeout),
            new TaskDefinitionData('rewards:reconcile-settlements', 'Reconcile reward settlements', '*/5 * * * *', 'rewards:reconcile-settlements', timeout_seconds: $timeout),
            new TaskDefinitionData('rewards:process-volume', 'Process volume rewards', '* * * * *', 'rewards:process-volume', timeout_seconds: $timeout),
        ];

        return array_map(static function (TaskDefinitionData $definition) use ($timeout): TaskDefinitionData {
            $overrides = config('scheduling.task_timeouts', []);
            $taskTimeout = (int) ($overrides[$definition->code] ?? $timeout);
            if ($taskTimeout < 1 || $taskTimeout > $timeout) {
                throw new \LogicException('Task timeout must be between 1 and the Scheduling worker timeout ceiling.');
            }

            return new TaskDefinitionData($definition->code, $definition->description, $definition->cron_expression, $definition->command, $definition->arguments, $taskTimeout);
        }, $definitions);
    }

    public function find(string $code): TaskDefinitionData
    {
        foreach ($this->all() as $definition) {
            if ($definition->code === $code) {
                return $definition;
            }
        }
        throw SchedulingException::missing();
    }

    public function available(string $code): bool
    {
        $this->find($code);

        return $code !== 'rewards:process-negative-pnl' || (bool) app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.enabled'])->get('rewards.negative_pnl.enabled');
    }
}
