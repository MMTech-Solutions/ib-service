# Rules R2: entregas verticales

Estado: **R2 completado**
Dependencia: inventario y dominio R2

## R2 — Asignaciones históricas

Entrega vertical de HTTP a persistencia:

- crear una asignación activa con versión publicada, programa y módulo
  seleccionado;
- listar historial paginado con filtros;
- mostrar una asignación;
- reemplazar la versión (cerrar + crear);
- retirar la asignación activa;
- reutilizar `ResolvePlanContextPort` y el nuevo `ResolveProgramContextPort`;
- concurrencia optimista de la asignación activa.

Criterio de aceptación: un operador con `ib.rules.manage` crea una
asignación bajo un plan no archivado hacia un programa que selecciona el
módulo, con una versión publicada. Puede listarla, reemplazar la versión
conservando historia y retirarla. Draft, programa ajeno, módulo no
seleccionado, duplicado activo y plan archivado se rechazan.

### Superficie HTTP

- `GET/POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments`
- `GET /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments/{assignment}`
- `POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments/{assignment}/replace`
- `POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments/{assignment}/withdraw`

Permiso: `ib.rules.manage` en la surface `admin_panel`.

`POST` de creación recibe `program_id`, `module_id` y `rule_version_id`.
`scope_type`, `starts_at` y `ends_at` los fija el servidor. `replace`
recibe `rule_version_id` y `lock_version`. `withdraw` recibe
`lock_version`.

Filtros de listado: `program_id`, `module_id`, `active` (boolean).
Paginación y filtros viven en `meta`.

### Contratos entre features

- Reutiliza `ResolvePlanContextPort`.
- Origina `ResolveProgramContextPort` + `ProgramContextData` V1 en Programs.
- No publica puertos de Rules.

## Fuera de R2

- Eventos Kafka.
- Scope instrumental.
- Evaluación y Rewards.

## Criterios de salida

Cada recorrido de R2 funciona de extremo a extremo y tiene evidencia en
[`11-r2-implementation.md`](11-r2-implementation.md).
