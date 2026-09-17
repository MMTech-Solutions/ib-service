<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Services;

use Illuminate\Support\Facades\Log;

/**
 * Technical rejection evidence for inactive-module activity queries (BR-MODULE-015).
 * Durable audit shape remains a BDS pending decision; this records structured logs only.
 */
final class ModuleActivityRejectionEvidence
{
    /** @var list<array{module_id: string, context: array<string, mixed>}> */
    private array $recorded = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordInactiveModuleRejection(string $moduleId, array $context = []): void
    {
        $this->recorded[] = [
            'module_id' => $moduleId,
            'context' => $context,
        ];

        Log::info('modules.activity.rejected_inactive', [
            'module_id' => $moduleId,
            'rejection_code' => 'module_inactive',
            ...$context,
        ]);
    }

    /**
     * @return list<array{module_id: string, context: array<string, mixed>}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
