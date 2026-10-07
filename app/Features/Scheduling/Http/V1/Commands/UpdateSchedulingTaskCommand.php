<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Commands;

use App\Features\Scheduling\DTOs\UpdateTaskData;
use App\Features\Scheduling\Http\V1\Requests\UpdateSchedulingTaskRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class UpdateSchedulingTaskCommand extends Data
{
    public function __construct(public readonly UpdateTaskData $input) {}

    public static function fromRequest(UpdateSchedulingTaskRequest $request, UserContext $context): self
    {
        $v = $request->validated();

        return new self(new UpdateTaskData($v['code'], (int) $v['version'], $context->id(), trim($v['reason']), isset($v['description']) ? trim($v['description']) : null, isset($v['cron_expression']) ? preg_replace('/\\s+/', ' ', trim($v['cron_expression'])) : null, array_key_exists('automatic_enabled', $v) ? (bool) $v['automatic_enabled'] : null));
    }
}
