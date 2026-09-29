# PG2: entregas verticales de distribución, runs y placement

Estado: **Completada (diseño)**  
Última revisión: 2026-09-29

## Propósito

Dividir PG2 en incrementos verificables sin volver a resolver la red durante
reintentos ni runs. La distribución de una actividad queda congelada antes de
crear sus contribuciones.

| Entrega | Valor observable | Criterios de aceptación |
| --- | --- | --- |
| PG2.1 Foundations | La actividad puede resolver upline vigente mediante un adapter propio. | **Implementada:** los tipos IAM no salen del adapter; error, respuesta vacía y éxito se distinguen. |
| PG2.2 Distribución | Una actividad conserva su lista inmutable de beneficiarios y niveles. | Éxito vacío crea distribución vacía; fallo técnico no la crea; no se duplica beneficiario/nivel. |
| PG2.3 Contribuciones | Cada beneficiario recibe evaluación y contribución auditables. | Los reintentos reutilizan la distribución; el fallo de un beneficiario no borra los finales. |
| PG2.4 Runs | Una ventana cerrada suma contribuciones existentes por suscripción. | El run no invoca IAM ni recrea beneficiarios; sus fallos parciales son reintentables. |
| PG2.5 Placement | El resultado mueve al programa de mayor umbral satisfecho. | Cero puntos resuelven el primer programa y un run completado no se reabre. |

## Límites

- La actividad anterior al vínculo de referido no es elegible.
- No hay backfill ni redistribución de actividades ya congeladas.
- Una capacidad histórica futura de IAM no modifica estos snapshots sin una
  decisión de dominio posterior.

## Evidencia de diseño

- BDS Progression v0.8, `BR-POINTS-015`, `BR-POINTS-036` a `BR-POINTS-039`.
- Modelo de dominio PG2 completado.

## Próximo paso

Implementar PG2.1 conforme a `10-pg2-network-foundations.md`.
