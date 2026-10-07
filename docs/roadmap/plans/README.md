# Roadmap del feature Plans

Estado: **P1, extensión de Progression (P0.3) y vinculación de plantillas (P3) completados localmente**
Dependencia satisfecha: `Modules M1` completado
Última revisión: 2026-10-07

La reconciliación periódica se administra desde [Scheduling](../scheduling/README.md), con cron inicial cada cinco minutos y ejecución auditada. Plans conserva su comando y listener propietarios.

## Posición en la secuencia

`Plans` es el primer consumidor inter-feature de `Modules`. El puerto público
mínimo de `Modules` se originó en los casos de uso de P1.

## Primera entrega: P1

Catálogo administrativo de planes: identidad, módulos, activación,
desactivación, archivo lógico y convergencia eventual cuando un plan activo
queda sin módulos operativos.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para P1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | P1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para P1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | P1 completado |

## Relación con Programs y Subscriptions

`Programs PR1` cerró el catálogo administrativo. `Programs P2` cierra el
ladder vivo de umbrales:
[`06-second-delivery-planning.md`](../programs/06-second-delivery-planning.md).

`Subscriptions S1` es el siguiente consumidor real de Plans y requiere que el
plan determine si las nuevas solicitudes necesitan aprobación. El valor
vigente se fija al solicitar y sus cambios solo afectan solicitudes futuras
(`BR-PLAN-017`). La sesión 1 de
[`05-s1-implementation.md`](../subscriptions/05-s1-implementation.md) añadió
`requires_approval` y el puerto `ResolvePlanSubscriptionContextPort` sin
alterar de forma incompatible el V1 usado por Programs y Rules.

## Extensión requerida por Progression (P0.3)

Progression exige que cada plan declare un **período de progresión**
obligatorio (`daily`, `weekly` o `monthly`, UTC). El campo es `NOT NULL` desde
el alta: no existe plan sin período ni semántica de ausencia. Un cambio rige
desde la siguiente ventana (`BR-PLAN-018`, `BR-PLAN-019`).

**Estado: Completada (2026-09-17)**

- Columna `progression_period` en `plans` (migración + check PostgreSQL).
- Frontera especializada (sin romper `PlanContextData` V1):
  `ResolvePlanProgressionContextPort` + Data V1
  (`plan_id`, `is_active` histórico en `occurred_at`, `progression_period`).
- Evidencia y pruebas en
  [`../progression/05-pg1-implementation.md`](../progression/05-pg1-implementation.md)
  (sección Evidencia P0.3).

## Próximo paso

P3: [vinculación administrativa de versiones de plantillas](06-template-version-bindings.md),
completada localmente. Cierra la creación y consulta HTTP de bindings de pago y
progresión. Siguiente validación: preparación integrada de escenarios con ib-labs.

Ninguno en Plans para PG1. Progression puede ejecutar la sesión 3 de
evaluación pull-only consumiendo el puerto publicado.
