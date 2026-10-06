<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Contracts\Repositories;

use App\Features\Rules\Assignments\DTOs\CpaConfigurationData;
use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;

interface CpaConfigurationRepositoryInterface
{
    public function resolve(string $programId, string $at): CpaRuleContextData;

    public function current(string $programId): ?CpaConfigurationData;

    public function replace(string $programId, string $ruleId, string $versionId, string $actorId, string $at): CpaConfigurationData;

    public function withdraw(string $programId, string $actorId, string $at): void;

    /** @return array{rule_id:string, configuration:array<string,mixed>}|null */
    public function publishedVersion(string $planId, string $versionId): ?array;
}
