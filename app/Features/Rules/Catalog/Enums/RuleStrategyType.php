<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Enums;

enum RuleStrategyType: string
{
    case PointsPerQuantityUnit = 'points_per_quantity_unit';
    case CpaFixedAmount = 'cpa_fixed_amount';
    case TradedVolumeCommission = 'traded_volume_commission';
    case NegativePnlShare = 'negative_pnl_share';
}
