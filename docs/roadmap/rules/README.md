# Roadmap del feature Rules

Estado: **R1 completado**
Dependencia satisfecha: `Programs P2` completado
Última revisión: 2026-09-15

## Posición en la secuencia

`Rules` es el catálogo de políticas reutilizables del plan. R1 cubre identidad
administrativa y el ciclo de vida de versiones. Las asignaciones a programa y
módulo, y cualquier evaluación, quedan para R2.

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

## Relación con Plans, Programs y Modules

- `BR-RULE-001` y `BR-RULE-008`: la identidad pertenece al plan; nombre y slug
  son únicos dentro de ese plan.
- `BR-RULE-002`, `BR-RULE-007` y `BR-RULE-009`: una versión publicada es
  inmutable y disponible; publicar no cambia asignaciones.
- Mutaciones exigen un plan no archivado, reutilizando `ResolvePlanContextPort`.
- R1 no origina puertos públicos ni consulta Programs o Modules.

## Fuera de R1

- Asignaciones a programa, módulo, vigencia y scope.
- Sustitución deliberada de la versión asignada.
- Evaluación, recompensas, CPA, Progression y Kafka.
- Estrategias distintas de `points_per_quantity_unit`.

## Próximo paso

R2: asignaciones que seleccionan deliberadamente una versión publicada.
