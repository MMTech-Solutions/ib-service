# Plans: implementación de la primera entrega

Estado: **P1 completado**
Dependencia: decisiones y datos de P1 cerrados
Entrega objetivo: P1 — Catálogo administrativo de planes

## Orden de implementación

1. Agregado `Plan`, DTOs internos, excepciones y VOs.
2. `PlanRepositoryInterface`, InMemory y contract tests.
3. Migraciones PostgreSQL y repositorio persistente; misma suite contractual.
4. Puerto `ResolveModulesPort`, `ModuleSummaryData` V1, excepciones públicas y
   evento `ModuleDeactivated`.
5. Puerto `IsModuleReferencedPort` de Plans y adapter de `Modules`.
6. Casos de uso HTTP y de reconciliación; job reintentable y schedule.
7. API administrativa, RBAC, Postman y pruebas de recorrido.
8. Pint, suite PostgreSQL, Graphify y evidencia de cierre.

## Definition of Done

- Recorridos de P1 funcionan de extremo a extremo.
- InMemory y PostgreSQL superan la misma suite contractual.
- Controllers y Resources no acceden a repositorios.
- `Modules` publica solo el contrato exigido por P1.
- Autorización, concurrencia, idempotencia, archivo y reconciliación tienen
  pruebas positivas y negativas.
- El BDS, las reglas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

- Migraciones PostgreSQL de `plans`, `plan_module_bindings` y
  `plan_operational_changes`.
- Repositorios InMemory y PostgreSQL bajo `PlanRepositoryContract`.
- Puerto `ResolveModulesPort` con `ModuleSummaryData` en
  `Modules/Contracts/Data/V1` y excepciones públicas `MODULE_NOT_FOUND` /
  `MODULE_INACTIVE`.
- Evento contractual `ModuleDeactivated` y job
  `DeactivatePlansWithoutOperationalModulesJob` con reconciliación cada 5
  minutos.
- `IsModuleReferencedPort` cubre vinculaciones de planes archivados para
  `modules:sync --prune`.
- Rutas administrativas:
  - `GET/POST /api/ib/v1/admin/plans`
  - `GET/PATCH/DELETE /api/ib/v1/admin/plans/{plan}`
  - `POST /api/ib/v1/admin/plans/{plan}/activate`
  - `POST /api/ib/v1/admin/plans/{plan}/deactivate`
- Permiso `ib.plans.manage` en `LocalRbacSnapshotSeeder` y colección Postman.
- Suite completa: 86 pruebas y 404 aserciones sobre PostgreSQL real.
