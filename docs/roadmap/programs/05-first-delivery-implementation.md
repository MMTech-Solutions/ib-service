# Programs: implementación de la primera entrega

Estado: **PR1 completado**
Dependencia: decisiones y datos de PR1 cerrados
Entrega objetivo: PR1 — Catálogo administrativo de programas

## Orden de implementación

1. Agregado `Program`, selección hija, DTOs internos, excepciones y VOs.
2. `ProgramRepositoryInterface`, InMemory y contract tests.
3. Migraciones PostgreSQL y repositorio persistente; misma suite contractual.
4. Puerto `ResolvePlanContextPort`, `PlanContextData` V1 y excepciones
   públicas de `Plans`; adapter o caso de uso implementador en `Plans`.
5. Casos de uso HTTP: listar, crear, mostrar, actualizar y reordenar.
6. API administrativa anidada, RBAC (`ib.programs.manage`), Postman y
   pruebas de recorrido.
7. Pint, suite PostgreSQL, Graphify y evidencia de cierre.

PR1 no introduce puerto inverso hacia `Plans`, jobs de reconciliación ni
eventos Kafka.

## Definition of Done

- Recorridos de PR1 funcionan de extremo a extremo.
- InMemory y PostgreSQL superan la misma suite contractual.
- Controllers y Resources no acceden a repositorios.
- `Plans` publica solo el contrato exigido por PR1.
- `Programs` no llama a `ResolveModulesPort` para la selección
  administrativa.
- Autorización, concurrencia, rechazo de plan archivado, unicidad de código
  por plan y rechazo de módulos no habilitados tienen pruebas positivas y
  negativas.
- El BDS, las reglas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

- Migraciones PostgreSQL de `programs` y `program_module_selections`.
- Repositorios InMemory y PostgreSQL bajo `ProgramRepositoryContract`.
- Puerto `ResolvePlanContextPort` con `PlanContextData` en
  `Plans/Contracts/Data/V1` y excepciones públicas `PLAN_NOT_FOUND`,
  `PLAN_ARCHIVED` y `MODULE_NOT_ENABLED_ON_PLAN`.
- Rutas administrativas:
  - `GET/POST /api/ib/v1/admin/plans/{plan}/programs`
  - `GET/PATCH /api/ib/v1/admin/plans/{plan}/programs/{program}`
  - `POST /api/ib/v1/admin/plans/{plan}/programs/reorder`
- Permiso `ib.programs.manage` en `LocalRbacSnapshotSeeder` y colección
  Postman (`Administration/Programs`).
- Suite completa: 104 pruebas y 510 aserciones sobre PostgreSQL real
  (incluye contract tests duales y `ProgramCatalogEndpointTest`).
