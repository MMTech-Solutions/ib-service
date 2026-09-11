<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\Http\V1\Requests\ShowModuleRequest;
use Spatie\LaravelData\Data;

final class ShowModuleCommand extends Data
{
    public function __construct(public readonly string $moduleId) {}

    public static function fromRequest(ShowModuleRequest $request): self
    {
        $validated = $request->validated();

        return new self(moduleId: (string) $validated['module']);
    }
}
