<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Http\V1\Commands;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\Http\V1\Requests\RequestSchedulingRunRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class RequestSchedulingRunCommand extends Data
{
    public function __construct(public readonly ManualRunData $input) {}

    public static function fromRequest(RequestSchedulingRunRequest $request, UserContext $context): self
    {
        $v = $request->validated();

        return new self(new ManualRunData($v['code'], $context->id(), trim($v['reason']), $v['idempotency_key']));
    }
}
