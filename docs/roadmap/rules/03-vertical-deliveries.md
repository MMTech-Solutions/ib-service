# Rules: entregas verticales

Estado: **R1 completado**
Dependencia: etapas 1 y 2

## R1 — Catálogo administrativo de reglas y versiones

Entrega vertical de HTTP a persistencia:

- identidad de la regla dentro de un plan (nombre, slug generado, descripción);
- tipo de estrategia fijado en la regla;
- versiones draft y publicadas, con validación de configuración;
- reutilización de `ResolvePlanContextPort` para plan inexistente o archivado;
- concurrencia optimista de regla y de versión draft.

Criterio de aceptación: un operador con `ib.rules.manage` crea una regla bajo
un plan no archivado, con nombre único y slug generado. Crea una versión
draft con configuración `points_per_quantity_unit` válida, la edita y la
publica. Puede publicar otra versión; ambas coexisten y ninguna queda marcada
como actual. Un plan archivado rechaza mutaciones y conserva list/show.

### Superficie HTTP

- `GET/POST /api/ib/v1/admin/plans/{plan}/rules`
- `GET/PATCH /api/ib/v1/admin/plans/{plan}/rules/{rule}`
- `GET/POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions`
- `GET/PATCH /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions/{version}`
- `POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions/{version}/publish`

Permiso: `ib.rules.manage` en la surface `admin_panel`.

`PATCH` de regla no acepta `slug` ni `strategy_type`. `PATCH` de versión solo
aplica a `draft`. Publicar no activa ni sustituye otras versiones.

## Fuera de R1

- Asignaciones a programa y módulo.
- Eventos Kafka.
- Puertos públicos de Rules.

## Relación con Plans

R1 reutiliza `ResolvePlanContextPort`, `PlanContextData` V1 y las excepciones
públicas `PLAN_NOT_FOUND` y `PLAN_ARCHIVED`. No origina un puerto de Programs.

## Criterios de salida

Cada recorrido de R1 funciona de extremo a extremo y tiene evidencia en
[`05-first-delivery-implementation.md`](05-first-delivery-implementation.md).
