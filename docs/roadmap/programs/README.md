# Roadmap del feature Programs

Estado: **Completado**
Dependencia satisfecha: `Plans P1` completado
Última revisión: 2026-09-14

## Posición en la secuencia

`Programs` es el siguiente consumidor de la jerarquía comercial. Un programa
pertenece a un plan y no consulta el catálogo global de módulos: obtiene el
contexto y los módulos habilitados por ese plan.

`Modules M1` y `Plans P1` están cerrados. El puerto público mínimo de `Plans`
nació con esta entrega vertical.

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

## Relación con Plans y Modules

- `BR-PLAN-002`: todo programa pertenece a un único plan.
- `BR-PROGRAM-004`: el código es estable y único dentro del plan.
- `BR-PROGRAM-007`: la selección de módulos es un subconjunto de las
  vinculaciones del plan.
- `BR-PROGRAM-005` y `BR-PROGRAM-006`: vacío permitido antes de publicar;
  publicar exigirá al menos un módulo habilitado por el plan.
- `BR-PROGRAM-008` y `BR-PROGRAM-009`: los cambios de disponibilidad o
  archivo del plan no mutan programas; solo un plan no archivado admite
  mutaciones.
- `BR-MODULE-004`: la configuración de actividad, cuando exista, debe ser
  compatible con las capacidades del módulo. PR1 no introduce esa
  configuración.
- `BR-PROGRAM-003`: el orden entre programas del plan entra en PR1; los
  umbrales concretos permanecen diferidos por alcance del roadmap.
- PR1 origina `ResolvePlanContextPort` y `PlanContextData` V1 en `Plans`.

## Fuera de PR1

- Publicación y versionado de la configuración del programa.
- Snapshot de módulo del programa.
- Umbrales de placement, suscripciones y placements.
- Activación, desactivación o archivo del programa.
- Configuración de actividad o scope de instrumentos.
- Eventos Kafka y puerto inverso Programs → Plans.

## Próximo paso

Rules / Progression / Rewards, o una entrega posterior de Programs que cierre
publicación y umbrales cuando el BDS lo permita.
