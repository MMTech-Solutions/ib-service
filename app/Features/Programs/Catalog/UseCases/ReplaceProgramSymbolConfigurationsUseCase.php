<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class ReplaceProgramSymbolConfigurationsUseCase
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ResolveProgramContextPort $programs,
        private readonly ListInstrumentCatalogPort $catalog,
    ) {}

    /** @param list<array<string, mixed>> $symbols @return list<array<string, mixed>> */
    public function execute(string $planId, string $programId, array $symbols): array
    {
        $resolved = array_map(fn (array $symbol): array => $this->resolveSymbol($planId, $programId, $symbol), $symbols);

        return $this->connection->transaction(function () use ($programId, $resolved): array {
            $now = app(DomainClock::class)->now();
            $active = $this->connection->table('program_symbol_configurations')->where('program_id', $programId)->whereNull('ends_at')->lockForUpdate()->get()->keyBy(fn ($row): string => $row->module_id.'|'.$row->server_group_reference.'|'.$row->symbol_reference);
            $keys = [];
            foreach ($resolved as $symbol) {
                $key = $symbol['module_id'].'|'.$symbol['server_group_reference'].'|'.$symbol['symbol_reference'];
                $keys[] = $key;
                $existing = $active->get($key);
                if ($existing !== null && $this->same($existing, $symbol)) {
                    continue;
                }
                if ($existing !== null) {
                    $this->connection->table('program_symbol_configurations')->where('id', $existing->id)->update(['ends_at' => $now, 'updated_at' => $now]);
                }
                $this->connection->table('program_symbol_configurations')->insert([...$symbol, 'id' => (string) Str::uuid7(), 'program_id' => $programId, 'starts_at' => $now, 'ends_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($active as $key => $existing) {
                if (! in_array($key, $keys, true)) {
                    $this->connection->table('program_symbol_configurations')->where('id', $existing->id)->update(['ends_at' => $now, 'updated_at' => $now]);
                }
            }

            return $this->connection->table('program_symbol_configurations')->where('program_id', $programId)->whereNull('ends_at')->orderBy('symbol_reference')->get()->map(static fn ($row): array => (array) $row)->all();
        });
    }

    /** @param array<string, mixed> $symbol @return array<string, mixed> */
    private function resolveSymbol(string $planId, string $programId, array $symbol): array
    {
        $this->programs->assertSelectedModule(new AssertSelectedModuleQueryData($planId, $programId, (string) $symbol['module_id']));
        $reference = (string) $symbol['instrument_reference'];
        $parts = explode(':', $reference);
        if (count($parts) !== 5 || $parts[0] !== 'broker' || $parts[1] !== 'server_group' || $parts[3] !== 'symbol') {
            throw new \InvalidArgumentException('Instrument reference must be a canonical Broker symbol reference.');
        }
        $page = $this->catalog->execute(new ListInstrumentCatalogQueryData((string) $symbol['module_id'], 'symbol', ['server_group' => 'broker:server_group:'.$parts[2], 'symbol' => $reference], 1, 1));
        if ($page->items === [] || $page->items[0]->reference !== $reference || $page->items[0]->currency_code === null) {
            throw new \InvalidArgumentException('Instrument reference is not available in the Broker catalogue.');
        }
        $this->assertTemplateBindingsBelongToPlan($planId, $symbol);

        return [
            'module_id' => (string) $symbol['module_id'], 'symbol_reference' => $reference, 'server_group_reference' => 'broker:server_group:'.$parts[2], 'currency_code' => $page->items[0]->currency_code,
            'use_for_progression' => (bool) $symbol['use_for_progression'], 'plan_progression_template_version_binding_id' => $symbol['plan_progression_template_version_binding_id'] ?? null,
            'use_for_volume_reward' => (bool) $symbol['use_for_volume_reward'], 'plan_payment_template_version_binding_id' => $symbol['plan_payment_template_version_binding_id'] ?? null,
            'commission_type' => $symbol['commission_type'] ?? null, 'commission_value' => $symbol['commission_value'] ?? null, 'use_for_cpa' => (bool) $symbol['use_for_cpa'],
        ];
    }

    /** @param array<string, mixed> $symbol */
    private function assertTemplateBindingsBelongToPlan(string $planId, array $symbol): void
    {
        $progressionBinding = $symbol['plan_progression_template_version_binding_id'] ?? null;
        if ($progressionBinding !== null && ! $this->connection->table('plan_progression_template_version_bindings')->where('id', $progressionBinding)->where('plan_id', $planId)->exists()) {
            throw new \InvalidArgumentException('Progression template binding does not belong to the plan.');
        }
        $paymentBinding = $symbol['plan_payment_template_version_binding_id'] ?? null;
        if ($paymentBinding !== null && ! $this->connection->table('plan_payment_template_version_bindings')->where('id', $paymentBinding)->where('plan_id', $planId)->exists()) {
            throw new \InvalidArgumentException('Payment template binding does not belong to the plan.');
        }
    }

    /** @param object $existing @param array<string, mixed> $symbol */
    private function same(object $existing, array $symbol): bool
    {
        foreach ($symbol as $key => $value) {
            if ((string) ($existing->{$key} ?? '') !== (string) ($value ?? '')) {
                return false;
            }
        }

        return true;
    }
}
