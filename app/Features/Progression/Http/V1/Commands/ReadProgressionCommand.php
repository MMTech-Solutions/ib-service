<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Commands;

use App\Features\Progression\DTOs\ProgressionReadQueryData;
use App\Features\Progression\Http\V1\Requests\ReadProgressionRequest;
use Spatie\LaravelData\Data;

final class ReadProgressionCommand extends Data
{
    public function __construct(public readonly ProgressionReadQueryData $query) {}

    public static function fromRequest(ReadProgressionRequest $request): self
    {
        $values = $request->validated();
        $page = (int) ($values['page'] ?? 1);
        $perPage = (int) ($values['per_page'] ?? 100);
        unset($values['page'], $values['per_page']);

        return new self(new ProgressionReadQueryData((string) $request->route('read_resource'), $request->route('result') ?? $request->route('id'), $request->route('run'), $values, $page, $perPage));
    }
}
