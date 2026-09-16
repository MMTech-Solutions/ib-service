<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions\Catalog;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Features\Subscriptions\Catalog\Repositories\InMemory\InMemorySubscriptionRepository;
use Illuminate\Support\Str;
use Tests\Contracts\SubscriptionRepositoryContract;

final class InMemorySubscriptionRepositoryContractTest extends SubscriptionRepositoryContract
{
    private InMemorySubscriptionRepository $repository;

    private string $planId;

    private string $programId;

    private string $alternateProgramId;

    private string $foreignProgramId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planId = (string) Str::uuid7();
        $this->programId = (string) Str::uuid7();
        $this->alternateProgramId = (string) Str::uuid7();
        $this->foreignProgramId = (string) Str::uuid7();

        $this->repository = new InMemorySubscriptionRepository;
        $this->repository->registerProgram($this->programId, $this->planId);
        $this->repository->registerProgram($this->alternateProgramId, $this->planId);
        $this->repository->registerProgram($this->foreignProgramId, (string) Str::uuid7());
    }

    protected function repository(): SubscriptionRepositoryInterface
    {
        return $this->repository;
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function alternateProgramId(): string
    {
        return $this->alternateProgramId;
    }

    protected function foreignProgramId(): string
    {
        return $this->foreignProgramId;
    }
}
