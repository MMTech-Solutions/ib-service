# Programs: implementación de la segunda entrega

Estado: **P2 completado**
Dependencia: decisiones y datos de P2 cerrados
Entrega objetivo: P2 — Ladder vivo de umbrales

## Orden de implementación

1. Columna `entry_threshold` en `programs` (entero ≥ 0; backfill `position - 1`).
2. Agregado `Program`, DTOs, `ProgramReorderItem` y `AssertProgramLadderAction`.
3. Repositorios InMemory y PostgreSQL: persistir umbral y reorder atómico.
4. Store, update y reorder HTTP sobre las rutas PR1; sin publicación.
5. Contract tests, `ProgramCatalogEndpointTest`, Postman, Pint y Graphify.

P2 no introduce publicación, versionado, snapshots ni puerto hacia Modules.

## Definition of Done

- Crear un programa exige `entry_threshold` entero no negativo.
- Store, update y reorder validan el ladder completo antes de persistir.
- Reorder recibe `{id, entry_threshold, lock_version}` por cada programa del plan.
- `PROGRAM_LADDER_INVALID` (422) cubre umbral negativo, duplicado o no creciente.
- Conflictos de `lock_version` permanecen 409; conjunto incompleto de reorder, 409.
- Un plan archivado rechaza mutaciones; list/show siguen disponibles.
- InMemory y PostgreSQL superan la misma suite contractual.
- La colección Postman (`Administration/Programs`) refleja el payload de P2.

## Evidencia de cierre

- Migración `2026_09_14_050000_add_entry_threshold_to_programs_table`.
- `AssertProgramLadderAction` y `ProgramLadderInvalidException`.
- Rutas administrativas (sin cambios de path respecto a PR1):
  - `GET/POST /api/ib/v1/admin/plans/{plan}/programs`
  - `GET/PATCH /api/ib/v1/admin/plans/{plan}/programs/{program}`
  - `POST /api/ib/v1/admin/plans/{plan}/programs/reorder`
- Tests acotados a Programs: 26 pruebas y 144 aserciones
  (`ProgramRepositoryContract` InMemory/PostgreSQL,
  `AssertProgramLadderActionTest`, `ProgramStateTransitionsTest` y
  `ProgramCatalogEndpointTest`).
