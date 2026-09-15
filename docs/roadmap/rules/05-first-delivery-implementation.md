# Rules: implementación de la primera entrega

Estado: **R1 completado**
Dependencia: decisiones y datos de R1 cerrados
Entrega objetivo: R1 — Catálogo administrativo de reglas y versiones

## Orden de implementación

1. Agregado `Rule`, `RuleVersion`, slug, DTOs internos, excepciones y registry.
2. `RuleRepositoryInterface`, InMemory y contract tests.
3. Migraciones PostgreSQL y repositorio persistente; misma suite contractual.
4. Reutilizar `ResolvePlanContextPort` para plan existente y no archivado.
5. Casos de uso HTTP: listar, crear, mostrar y actualizar reglas; listar,
   crear, mostrar, actualizar drafts y publicar.
6. API administrativa anidada, RBAC (`ib.rules.manage`), Postman y pruebas de
   recorrido.
7. Pint, suite PostgreSQL, Graphify y evidencia de cierre.

R1 no introduce asignaciones, puertos públicos ni eventos Kafka.

## Definition of Done

- Recorridos de R1 funcionan de extremo a extremo.
- InMemory y PostgreSQL superan la misma suite contractual.
- Controllers no acceden a repositorios.
- `Rules` no llama a Programs ni al catálogo global de Modules.
- Autorización, concurrencia, rechazo de plan archivado, unicidad de nombre y
  slug, inmutabilidad publicada y configuración inválida tienen pruebas
  positivas y negativas.
- Publicar no selecciona una versión actual.
- El BDS, las reglas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

- Migraciones PostgreSQL de `rules` y `rule_versions`.
- Repositorios InMemory y PostgreSQL bajo `RuleRepositoryContract`.
- Registry cerrado `points_per_quantity_unit`.
- Rutas administrativas:
  - `GET/POST /api/ib/v1/admin/plans/{plan}/rules`
  - `GET/PATCH /api/ib/v1/admin/plans/{plan}/rules/{rule}`
  - `GET/POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions`
  - `GET/PATCH /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions/{version}`
  - `POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/versions/{version}/publish`
- Permiso `ib.rules.manage` en `LocalRbacSnapshotSeeder` y colección Postman
  (`Administration/Rules`).
- Tests acotados a Rules: 29 pruebas y 145 aserciones
  (`RuleRepositoryContract` InMemory/PostgreSQL, `RuleStateTransitionsTest`,
  `PointsPerQuantityUnitStrategyTest` y `RuleCatalogEndpointTest`).
- Suite completa: 141 pruebas y 693 aserciones sobre PostgreSQL real.
