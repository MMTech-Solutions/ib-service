# Programs P2: entregas verticales

Estado: **P2 completado**
Dependencia: inventario y dominio P2

## P2 — Ladder vivo de umbrales

Entrega vertical sobre las rutas administrativas de PR1:

- `entry_threshold` entero ≥ 0 en el programa;
- validación del ladder del plan en crear, editar y reordenar;
- reconfiguración atómica de posiciones y umbrales en reorder;
- rechazo `PROGRAM_LADDER_INVALID` cuando el ladder quedaría inválido.

Criterio de aceptación: un operador con `ib.programs.manage` crea programas
con umbral en un plan no archivado, los lista y muestra con ese valor, edita
un umbral si el ladder permanece creciente y reconfigura orden y umbrales en
una sola operación. Umbrales negativos, duplicados o no crecientes se
rechazan. Un plan archivado rechaza las mutaciones.

### Superficie HTTP

Sin endpoints de publicación. Rutas PR1:

- `POST /api/ib/v1/admin/plans/{plan}/programs` exige `entry_threshold`.
- `PATCH /api/ib/v1/admin/plans/{plan}/programs/{program}` admite
  `entry_threshold` junto con los campos de PR1.
- `POST /api/ib/v1/admin/plans/{plan}/programs/reorder` recibe la colección
  completa `{id, entry_threshold, lock_version}`.
- List y show incluyen `entry_threshold` en el recurso.

Permiso: `ib.programs.manage` en `admin_panel`.

### Contratos entre features

Reutiliza `ResolvePlanContextPort`. No origina puerto hacia Modules.

## Fuera de P2

- Publicación, versionado y snapshots.
- Configuración de actividad o scope (`BR-MODULE-004`).
- Ponderaciones por símbolo (Rules/Progression).
- Suscripciones y placement ejecutado.

## Criterios de salida

El recorrido funciona de extremo a extremo y queda evidenciado en
[`11-p2-implementation.md`](11-p2-implementation.md).
