# Roadmap del feature Programs

Estado: **PR1 completado; P2 Listo**
Dependencia satisfecha: `Plans P1` completado
Última revisión: 2026-09-14

## Posición en la secuencia

`Programs` es el siguiente consumidor de la jerarquía comercial. Un programa
pertenece a un plan y no consulta el catálogo global de módulos: obtiene el
contexto y los módulos habilitados por ese plan.

`Modules M1` y `Plans P1` están cerrados. El puerto público mínimo de `Plans`
nació con PR1. **P2** (publicación, snapshots y umbrales) está documentado
hasta Listo y es prerrequisito de Rules, Subscriptions, Progression y Rewards.

## Primera entrega: PR1

Catálogo administrativo de programas dentro de un plan: código único por plan,
nombre, descripción opcional, orden relativo y selección de módulos
habilitados por el plan propietario.

PR1 no publica, no versiona, no congela snapshots ni define umbrales. Un
programa puede existir sin módulos antes de publicarse, incluso si el plan
está activo.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado para PR1 |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para PR1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | PR1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para PR1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | PR1 completado |

## Segunda entrega: P2

Publicación y versionado de la configuración del programa, snapshot de
módulos y umbrales de entrada (entero, ladder estrictamente creciente).

| Etapa | Documento | Estado |
| --- | --- | --- |
| 0. Planificación y dependencias | [`06-second-delivery-planning.md`](06-second-delivery-planning.md) | Listo |
| 1. Inventario de casos de uso | [`07-p2-use-case-inventory.md`](07-p2-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`08-p2-domain-model.md`](08-p2-domain-model.md) | Completado |
| 3. Entregas verticales | [`09-p2-vertical-deliveries.md`](09-p2-vertical-deliveries.md) | Listo |
| 4. Modelo de datos | [`10-p2-data-model.md`](10-p2-data-model.md) | Listo |
| 5. Implementación | pendiente | No iniciado |

## Relación con Plans y Modules

- `BR-PLAN-002`: todo programa pertenece a un único plan.
- `BR-PROGRAM-004`: el código es estable y único dentro del plan.
- `BR-PROGRAM-007`: la selección de módulos es un subconjunto de las
  vinculaciones del plan.
- `BR-PROGRAM-005` y `BR-PROGRAM-006`: vacío permitido antes de publicar;
  publicar exige al menos un módulo habilitado por el plan.
- `BR-PROGRAM-008` y `BR-PROGRAM-009`: los cambios de disponibilidad o
  archivo del plan no mutan programas; solo un plan no archivado admite
  mutaciones.
- `BR-MODULE-004`: configuración de actividad fuera de P2.
- `BR-PROGRAM-003` y `BR-PROGRAM-010`–`013`: orden (PR1) y umbrales de
  entrada enteros del ladder (P2).
- `BR-PROGRAM-014` y `BR-MODULE-007`–`009`: publicación inmutable y
  snapshots.
- PR1 origina `ResolvePlanContextPort` y `PlanContextData` V1 en `Plans`.

## Fuera de PR1 / cubierto o diferido en P2

- Publicación y versionado → P2 (Listo documental).
- Snapshot de módulo → P2 (Listo documental).
- Umbrales de placement → P2 (Listo documental).
- Suscripciones y placements → feature `Subscriptions` tras P2 implementado.
- Activación, desactivación o archivo del programa.
- Configuración de actividad o scope de instrumentos.
- Eventos Kafka y puerto inverso Programs → Plans.

## Próximo paso

Implementar P2 según
[`09-p2-vertical-deliveries.md`](09-p2-vertical-deliveries.md) y
[`10-p2-data-model.md`](10-p2-data-model.md). Después, Rules y Subscriptions
pueden abrir inventario; Progression y Rewards dependen de ambos.
