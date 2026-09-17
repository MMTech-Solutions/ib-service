<?php

declare(strict_types=1);

namespace App\Features\Progression\Enums;

enum ExclusionReason: string
{
    case PlacementFixed = 'placement_fixed';
    case PlanInactive = 'plan_inactive';
    case ModuleNotSelected = 'module_not_selected';
    case ModuleInactive = 'module_inactive';
    case UnitMismatch = 'unit_mismatch';
    case ScaleExceeded = 'scale_exceeded';
    case WindowClosedAfterPause = 'window_closed_after_pause';
    case LateActivity = 'late_activity';
    case NoActiveSubscription = 'no_active_subscription';
    case NoApplicableRule = 'no_applicable_rule';
}
