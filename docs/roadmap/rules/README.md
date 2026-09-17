# Roadmap del feature Rules

Estado: **R1 y R2 completados; extensión de Progression pendiente**
Dependencia satisfecha: `Programs P2` completado
Última revisión: 2026-09-17

## Posición en la secuencia

`Rules` es el catálogo de políticas reutilizables del plan. R1 cubre identidad
administrativa y el ciclo de vida de versiones. R2 añade asignaciones
históricas a programa y módulo.

## Primera entrega: R1

Catálogo administrativo de reglas dentro de un plan: nombre único, slug
estable generado por el servidor, descripción opcional, tipo de estrategia y
versiones `draft` / `published`. Publicar una versión no la activa ni
sustituye asignaciones futuras.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado para R1 |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para R1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | R1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para R1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | R1 completado |

## Segunda entrega: R2

Asignaciones históricas de una versión publicada a programa y módulo, con
scope `all` y vigencia inmediata.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 0. Planificación y dependencias | [`06-second-delivery-planning.md`](06-second-delivery-planning.md) | Completado |
| 1. Inventario de casos de uso | [`07-r2-use-case-inventory.md`](07-r2-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`08-r2-domain-model.md`](08-r2-domain-model.md) | Completado |
| 3. Entregas verticales | [`09-r2-vertical-deliveries.md`](09-r2-vertical-deliveries.md) | R2 completado |
| 4. Modelo de datos | [`10-r2-data-model.md`](10-r2-data-model.md) | Completado |
| 5. Implementación | [`11-r2-implementation.md`](11-r2-implementation.md) | R2 completado |

## Relación con Plans, Programs y Modules

- `BR-RULE-001` y `BR-RULE-008`: la identidad pertenece al plan; nombre y slug
  son únicos dentro de ese plan.
- `BR-RULE-002`, `BR-RULE-007` y `BR-RULE-009`: una versión publicada es
  inmutable y disponible; publicar no cambia asignaciones.
- `BR-RULE-010`–`015`: asignación histórica, unicidad activa, scope `all`.
- Mutaciones exigen un plan no archivado, reutilizando `ResolvePlanContextPort`.
- R2 origina `ResolveProgramContextPort` para validar programa y módulo
  seleccionado.

## Extensión requerida por Progression

[`Progression`](../progression/README.md) es el primer consumidor real de
lectura y evaluación de las asignaciones ya implementadas. Además,
`BR-RULE-016` / `BR-POINTS-016` exigen como máximo una asignación activa
`points_per_quantity_unit` por combinación de programa, módulo y métrica o
unidad. R2 garantiza hoy una asignación activa por regla + programa + módulo,
pero **aún no valida la unicidad por métrica/unidad** entre reglas distintas
del mismo tipo. Esa extensión se diseña con Progression; no reabre R2 como
entrega independiente.

## Fuera de R2

- Evaluación, recompensas, CPA y Kafka (la evaluación de progresión pertenece
  a Progression).
- Scope instrumental explícito (Modules M2).
- Precedencia entre reglas de recompensa concurrentes.
- Estrategias distintas de `points_per_quantity_unit`.

## Próximo paso

Exponer el puerto de lectura/evaluación y la unicidad por métrica/unidad
junto con Progression PG1. Subscriptions S1 ya está cerrada.
