<?php

declare(strict_types=1);

namespace Tests\Feature\Programs\Catalog;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use Illuminate\Support\Str;
use Tests\Contracts\ProgramRepositoryContract;

final class InMemoryProgramRepositoryContractTest extends ProgramRepositoryContract
{
    private InMemoryProgramRepository $programRepository;

    private string $planId;

    /** @var list<string> */
    private array $moduleIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->programRepository = new InMemoryProgramRepository;
        $this->planId = (string) Str::uuid7();
        $this->moduleIds = [(string) Str::uuid7(), (string) Str::uuid7()];
    }

    protected function repository(): ProgramRepositoryInterface
    {
        return $this->programRepository;
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function moduleIds(): array
    {
        return $this->moduleIds;
    }
}
