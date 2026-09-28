<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Actions;

use App\Features\Programs\PaymentTemplates\Http\V1\Commands\PaymentTemplateLevelCommandData;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateLevel;
use Illuminate\Support\Str;

final class BuildPaymentTemplateLevelsAction
{
    /**
     * @param  list<PaymentTemplateLevelCommandData>  $levels
     * @return list<PaymentTemplateLevel>
     */
    public function execute(array $levels): array
    {
        return array_map(
            static fn (PaymentTemplateLevelCommandData $level): PaymentTemplateLevel => new PaymentTemplateLevel(
                id: (string) Str::uuid7(),
                distributionLevel: $level->distributionLevel,
                rate: $level->rate,
            ),
            $levels,
        );
    }
}
