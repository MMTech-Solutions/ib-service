<?php

declare(strict_types=1);

namespace App\Features\Progression\Support;

use App\Features\Progression\Enums\ExclusionReason;

/**
 * Genera el texto de solo lectura para administración a partir del catálogo cerrado.
 * No se persiste; no admite texto libre ni motivos ad hoc.
 */
final class ExclusionExplanation
{
    public static function for(ExclusionReason $reason): string
    {
        return match ($reason) {
            ExclusionReason::PlacementFixed => 'La actividad no aporta puntos porque el placement de la suscripción estaba fijado cuando ocurrió.',
            ExclusionReason::PlanInactive => 'La actividad no aporta puntos porque el plan estaba inactivo en la verificación final. Progression no la difiere ni la recupera al reactivar el plan.',
            ExclusionReason::ModuleNotSelected => 'La actividad no aporta puntos porque el módulo no estaba seleccionado en el programa vigente de la suscripción.',
            ExclusionReason::ModuleInactive => 'La actividad no aporta puntos porque el módulo estaba inactivo.',
            ExclusionReason::UnitMismatch => 'La actividad no aporta puntos porque su unidad no coincide con la unidad de la regla aplicable.',
            ExclusionReason::ScaleExceeded => 'La actividad no aporta puntos porque la cantidad o la ponderación tiene más de ocho decimales admitidos.',
            ExclusionReason::WindowClosedAfterPause => 'La actividad no aporta puntos porque se procesó al reanudar el módulo y su ventana original ya había cerrado.',
            ExclusionReason::LateActivity => 'La actividad no aporta puntos porque pertenece a una ventana que un run ya cerró.',
            ExclusionReason::NoActiveSubscription => 'La actividad no aporta puntos porque el beneficiario no tenía una suscripción activa cuando ocurrió.',
            ExclusionReason::NoApplicableRule => 'La actividad no aporta puntos porque no había una regla vigente aplicable para el programa, módulo, métrica y unidad.',
        };
    }
}
