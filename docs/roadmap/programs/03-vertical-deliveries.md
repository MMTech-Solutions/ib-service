# Programs: entregas verticales

Estado: **PR1 completado**
Dependencia: etapas 1 y 2

## PR1 — Catálogo administrativo de programas

Entrega vertical de HTTP a persistencia:

- identidad del programa dentro de un plan (código, nombre, descripción);
- orden relativo contiguo de los programas del plan;
- selección de módulos habilitados por el plan propietario;
- primer puerto público de `Plans` hacia `Programs` y `Contracts/Data/V1`;
- excepciones públicas de plan inexistente, plan archivado o módulo no
  habilitado en el plan;
- concurrencia optimista por programa y reorden atómico de la colección.

Criterio de aceptación: un operador con `ib.programs.manage` crea un programa
bajo un plan no archivado (activo o inactivo), con código único en ese plan y
sin módulos si lo desea. Lo lista en orden no ambiguo, lo consulta, edita
nombre o descripción y, si envía `module_ids`, reemplaza el conjunto completo
con un subconjunto de las vinculaciones del plan. Un módulo no habilitado por
el plan se rechaza. Puede reordenar todos los programas del plan de forma
atómica. Un plan archivado rechaza crear, editar y reordenar. Activar,
desactivar o archivar el plan no muta ni elimina programas.

### Superficie HTTP

- `GET/POST /api/ib/v1/admin/plans/{plan}/programs`
- `GET/PATCH /api/ib/v1/admin/plans/{plan}/programs/{program}`
- `POST /api/ib/v1/admin/plans/{plan}/programs/reorder`

Permiso: `ib.programs.manage` en la surface `admin_panel`.

`PATCH` conserva las selecciones si omite `module_ids`. El cuerpo de
`reorder` envía la lista ordenada completa de identificadores de programa del
plan.

## Fuera de PR1

- Publicación y versionado de la configuración del programa.
- Snapshot de módulo del programa.
- Umbrales de placement, suscripciones y placements.
- Activación, desactivación o archivo del programa.
- Configuración de actividad o scope de instrumentos.
- Eventos Kafka de integración.
- Puerto inverso Programs → Plans.

## Relación con Plans

PR1 origina `ResolvePlanContextPort`, `PlanContextData` V1 y las excepciones
públicas `PLAN_NOT_FOUND`, `PLAN_ARCHIVED` y `MODULE_NOT_ENABLED_ON_PLAN`.
`PlanContextData` expone al menos `id`, `archived` y `enabled_module_ids`.
`Programs` no consulta el catálogo global de `Modules` para validar la
selección administrativa.

## Criterios de salida

Cada recorrido de PR1 funciona de extremo a extremo y tiene evidencia en
[`05-first-delivery-implementation.md`](05-first-delivery-implementation.md).
