<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Actions;

use App\Features\Rules\Catalog\DTOs\RuleDetailData;
use App\Features\Rules\Catalog\Models\Rule;

final class PresentRuleAction
{
    public function toDetail(Rule $rule): RuleDetailData
    {
        return $rule->toDetailData();
    }
}
