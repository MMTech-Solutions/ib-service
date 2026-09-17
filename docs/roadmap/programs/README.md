# Roadmap del feature Programs

Estado: **PR1 y P2 completados; extensión de Progression pendiente**
Dependencia satisfecha: `Plans P1` completado
Última revisión: 2026-09-17

## Posición en la secuencia

`Programs` es el siguiente consumidor de la jerarquía comercial. Un programa
pertenece a un plan y no consulta el catálogo global de módulos: obtiene el
contexto y los módulos habilitados por ese plan.

`Modules M1` y `Plans P1` están cerrados. **P2** es el ladder vivo de umbrales
de entrada: un entero mutable por programa, estrictamente creciente con la
posición. No hay publicación ni snapshots de configuración.

## Primera entrega: PR1

Catálogo administrativo de programas dentro de un plan: código único por plan,
nombre, descripción opcional, orden relativo y selección de módulos
habilitados por el plan propietario.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado para PR1 |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para PR1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | PR1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para PR1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | PR1 completado |

## Segunda entrega: P2

Umbral de entrada vivo y validación atómica del ladder del plan.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 0. Planificación y dependencias | [`06-second-delivery-planning.md`](06-second-delivery-planning.md) | Completado |
| 1. Inventario de casos de uso | [`07-p2-use-case-inventory.md`](07-p2-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`08-p2-domain-model.md`](08-p2-domain-model.md) | Completado |
| 3. Entregas verticales | [`09-p2-vertical-deliveries.md`](09-p2-vertical-deliveries.md) | P2 completado |
| 4. Modelo de datos | [`10-p2-data-model.md`](10-p2-data-model.md) | Completado |
| 5. Implementación | [`11-p2-implementation.md`](11-p2-implementation.md) | P2 completado |

## Relación con Plans y Modules

- `BR-PLAN-002`: todo programa pertenece a un único plan.
- `BR-PROGRAM-004`: el código es estable y único dentro del plan.
- `BR-PROGRAM-007`: la selección de módulos es un subconjunto de las
  vinculaciones del plan.
- `BR-PROGRAM-005` y `BR-PROGRAM-006`: un programa puede existir sin módulos;
  el umbral no exige selecciones.
- `BR-PROGRAM-008` y `BR-PROGRAM-009`: solo un plan no archivado admite
  mutaciones; el ciclo de vida del plan no muta programas.
- `BR-MODULE-004`: configuración de actividad fuera de P2.
- `BR-PROGRAM-003` y `BR-PROGRAM-010`–`014`: ladder vivo, umbral mutable.
- PR1 origina `ResolvePlanContextPort` y `PlanContextData` V1 en `Plans`.

## Extensión requerida por Progression

`BR-PROGRAM-015` exige que el primer programa del ladder declare siempre
umbral de entrada `0`. P2 valida umbrales no negativos y estrictamente
crecientes, pero **el código vigente aún no impone el cero del primer
nivel**. La alineación se realizará junto con
[`Progression`](../progression/README.md); no se presenta P2 como ya
cumplido respecto a esta regla.

## Fuera de P2

- Publicación o versionado de programas o planes.
- Snapshots de módulo, umbral, placement o run.
- Suscripciones y placements.
- Ponderaciones por símbolo, Rules, Progression y Rewards.
- Eventos Kafka y puerto inverso Programs → Plans.

## Próximo paso

Alinear la validación del ladder con `BR-PROGRAM-015` cuando Progression
avance. Mientras tanto, el roadmap continúa en Rules y Subscriptions ya
cerrados, y en Progression.
