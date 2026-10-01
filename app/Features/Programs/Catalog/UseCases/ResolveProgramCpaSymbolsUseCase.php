<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Contracts\Data\V1\ProgramCpaSymbolData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramCpaSymbolsQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramCpaSymbolsPort;
use Illuminate\Database\ConnectionInterface;

final class ResolveProgramCpaSymbolsUseCase implements ResolveProgramCpaSymbolsPort
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(ResolveProgramCpaSymbolsQueryData $query): array
    {
        return $this->connection->table('program_symbol_configurations')
            ->where('program_id', $query->program_id)
            ->where('module_id', $query->module_id)
            ->where('use_for_cpa', true)
            ->where('starts_at', '<=', $query->occurred_at)
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $query->occurred_at))
            ->orderBy('server_group_reference')
            ->orderBy('symbol_reference')
            ->get(['symbol_reference', 'server_group_reference', 'currency_code'])
            ->map(static fn (object $symbol): ProgramCpaSymbolData => new ProgramCpaSymbolData(
                (string) $symbol->symbol_reference,
                (string) $symbol->server_group_reference,
                (string) $symbol->currency_code,
            ))->all();
    }
}
