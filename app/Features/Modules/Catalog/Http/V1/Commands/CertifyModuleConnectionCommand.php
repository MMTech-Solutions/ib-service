<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\Http\V1\Requests\CertifyModuleConnectionRequest;
use Spatie\LaravelData\Data;

final class CertifyModuleConnectionCommand extends Data
{
    public function __construct(public readonly string $module_id) {}

    public static function fromRequest(CertifyModuleConnectionRequest $request): self
    {
        return new self($request->validated('module'));
    }
}
