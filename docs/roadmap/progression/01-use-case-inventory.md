# Progression: inventario de casos de uso

Estado: **Completado para PG1**
Última revisión: 2026-09-17
Dependencias: BDS Progression v0.6; Programs P2; Rules R2; Subscriptions S1;
Modules M3 pendiente como fuente de actividad

## Propósito

Inventariar intenciones y resultados observables para evaluación de actividad,
contribuciones y, de forma diferida, runs y placement. Los nombres son
descriptivos: no obligan todavía a crear una clase, endpoint, contrato o
transacción homónimos.

## Actores

- **Administrador de IB:** consulta evaluaciones, contribuciones, runs y
  resultados; reintenta resultados fallidos. No corrige contribuciones por
  reversa en esta fase.
- **Proceso interno / scheduler:** consulta actividad normalizada, evalúa
  contribuciones y, en PG2, cierra ventanas y ejecuta runs tras el margen
  técnico.
- **Feature Modules:** provee actividad normalizada mediante M3 (pull-only).
- **Feature Subscriptions:** provee suscripción activa, placement histórico al
  instante de ocurrencia y condición de fijación.
- **Feature Programs:** provee ladder vivo y umbrales; el primer umbral es `0`.
- **Feature Rules:** provee asignaciones y versiones publicadas vigentes al
  instante de ocurrencia, con unicidad por programa, módulo y métrica/unidad.
- **Feature Plans:** provee plan activo/inactivo y período de progresión
  obligatorio.
- **Usuario IB:** no consulta artefactos de Progression en esta fase.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Actividad | Consultar actividad normalizada de un módulo | Se obtienen hechos utilizables sin conocer el proveedor. | PG1 | Aceptado; depende de Modules M3 |
| Evaluación | Evaluar actividad elegible | Nace una evaluación durable aceptada y una contribución auditable. | PG1 | Aceptado |
| Evaluación | Excluir actividad no elegible | Nace una evaluación durable excluida con un motivo del catálogo inicial. | PG1 | Aceptado |
| Reglas | Resolver regla de contribución | Se aplica como máximo una asignación activa `points_per_quantity_unit` por programa, módulo y métrica/unidad. | PG1 | Aceptado |
| Agregación | Sumar métricas y módulos distintos | El total de la ventana acumula contribuciones válidas de distintas métricas y módulos. | PG1 | Aceptado |
| Idempotencia | Reprocesar la misma actividad | No se duplica la evaluación ni la contribución para la misma actividad, beneficiario y versión de regla. | PG1 | Aceptado |
| Pausa | Conservar actividad durante pausa | Se conserva en origen; al reanudar se procesa solo si la ventana original sigue abierta. | PG1 | Aceptado |
| Plan | Desactivar y reactivar un plan | Mientras está inactivo no se consulta ni evalúa actividad, no se generan contribuciones ni runs; al reactivar no se recupera actividad previa. | PG1 / PG2 | Aceptado |
| Precisión | Rechazar escala excesiva | Entradas con más de ocho decimales se excluyen sin redondeo silencioso. | PG1 | Aceptado |
| Moneda | Actividad con unidad distinta a la regla | Se excluye; no hay conversión FX. | PG1 | Aceptado |
| Consulta | Consultar evaluaciones y contribuciones | Administración obtiene trazabilidad; el cliente no. | PG1 | Aceptado |
| Tardía | Actividad posterior al cierre del run | Evaluación durable sin puntos. | PG1 / PG2 | Aceptado |
| Fijación | Actividad con placement fijado | Exclusión definitiva de progresión. | PG1 | Aceptado; regla en Subscriptions |
| Run | Cerrar ventana y ejecutar run del plan | Tras el margen de una hora se evalúan las suscripciones del plan para esa ventana. | PG2 | Diferido |
| Run | Resultado por suscripción | Cada suscripción obtiene puntos del período, programa objetivo y efecto, u omisión/fallo. | PG2 | Diferido |
| Run | Fallo parcial | Las demás continúan; solo los fallidos se reintentan; el run completado no se reabre. | PG2 | Diferido |
| Placement | Mover programa por progresión | El placement salta directamente al programa de mayor posición cuyo umbral se cumple. | PG2 | Diferido |
| Placement | Cero puntos en la ventana | Resuelve el primer programa del ladder. | PG2 | Diferido |
| Suscripción | Activación a mitad de ventana | Participa en el cierre actual con actividad desde su activación. | PG2 | Diferido |
| Reversa | Corregir contribución por devolución o anulación | — | Posterior | Diferido: fuera de PG1/PG2 |
| FX | Convertir monedas distintas | — | Posterior | Diferido: fuera de PG1/PG2 |

## Catálogo inicial de motivos de exclusión

| Motivo | Condición |
| --- | --- |
| `placement_fixed` | Placement fijado al ocurrir. |
| `plan_inactive` | Plan inactivo en la verificación final de una evaluación; no se difiere ni recupera actividad durante la inactividad. |
| `module_not_selected` | Módulo no seleccionado por el programa vigente al ocurrir. |
| `module_inactive` | Módulo inactivo. |
| `unit_mismatch` | Unidad de la actividad distinta a la de la regla. |
| `scale_exceeded` | Más de ocho decimales. |
| `window_closed_after_pause` | Pausada y, al reanudar, la ventana ya cerró. |
| `late_activity` | Ocurrencia en ventana ya cerrada por un run. |
| `no_active_subscription` | Sin suscripción activa al ocurrir. |
| `no_applicable_rule` | Sin asignación vigente para programa, módulo y métrica/unidad. |

## Decisiones cerradas

1. Ventanas fijas reiniciables según período obligatorio del plan; margen
   técnico global de una hora (`BR-POINTS-018`–`020`, `BR-PLAN-018`,
   `BR-PLAN-019`).
2. Contexto de suscripción, placement y regla por ocurrencia; umbrales vivos
   al run (`BR-POINTS-015`).
3. Unicidad de `points_per_quantity_unit` por programa + módulo + métrica/unidad;
   suma multi-métrica y multi-módulo (`BR-POINTS-016`, `BR-POINTS-017`,
   `BR-RULE-016`).
4. Elegibilidad por módulo seleccionado en el programa vigente
   (`BR-POINTS-003`).
5. Evaluación durable para aceptadas y excluidas; administración consulta;
   cliente no (`BR-POINTS-021`, `BR-POINTS-022`, `BR-POINTS-031`).
6. Pausas: procesar al reanudar solo con ventana abierta (`BR-POINTS-013`).
   Plan inactivo: detener consultas, evaluaciones, contribuciones y runs; la
   reactivación no recupera actividad previa (`BR-POINTS-028`).
7. Retiro de módulo con efecto inmediato (`BR-POINTS-029`, `BR-PLAN-020`).
8. Sin FX ni reversas en PG1/PG2 (`BR-POINTS-030` y fuera de fase).
9. Catálogo inicial de motivos de exclusión cerrado para PG1.
10. PG2: run por plan/ventana, salto directo en el ladder, cero puntos → primer
    programa, fallos parciales reintentables (`BR-POINTS-023`–`027`,
    `BR-PROGRAM-015`).

## Decisiones pendientes

- Política de reversas sobre contribuciones ya registradas.
- Conversión FX y autoridad de la tasa.

## Criterios de salida

- Cada intención de PG1 está aceptada o diferida de forma explícita.
- Las reglas confirmadas tienen trazabilidad al BDS.
- Actores y audiencias de consulta están acordados.
- Modules M3 queda identificado como dependencia de actividad.
- El alcance de PG1 permite comenzar el modelo de dominio sin adelantar runs.
- No se adelantan nombres de implementación ni decisiones de persistencia.
