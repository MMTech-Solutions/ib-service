# Rules R2: implementación de la segunda entrega

Estado: **R2 completado**
Dependencia: decisiones y datos de R2 cerrados
Entrega objetivo: R2 — Asignaciones históricas
Última revisión: 2026-09-15

## Orden de implementación

1. Puerto `ResolveProgramContextPort` y `ProgramContextData` V1 en Programs.
2. Agregado `RuleAssignment`, DTOs, excepciones y enum de scope.
3. `RuleAssignmentRepositoryInterface`, InMemory y contract tests.
4. Migración PostgreSQL y repositorio persistente; misma suite contractual.
5. Casos de uso HTTP: listar, crear, mostrar, reemplazar y retirar.
6. API administrativa anidada, RBAC (`ib.rules.manage`), Postman y pruebas
   de recorrido.
7. Pint, suite PostgreSQL, Graphify y evidencia de cierre.
8. Corrección posterior: tipado V1 de inputs de Plans/Programs, revalidación
   de módulo al reemplazar y documentación de colaboración entre subfeatures.

R2 no introduce evaluación, Kafka ni puertos públicos de Rules hacia otros
features.

## Definition of Done

- Recorridos de R2 funcionan de extremo a extremo.
- InMemory y PostgreSQL superan la misma suite contractual de asignaciones.
- Controllers no acceden a repositorios.
- Rules consume Programs solo vía `Contracts`; no importa factories ni
  modelos de Programs.
- Assignments puede usar factories/repositories de Catalog porque son
  subfeatures del mismo feature compuesto.
- Autorización, concurrencia, plan archivado, draft, programa ajeno, módulo
  no seleccionado (crear y reemplazar), unicidad activa y reemplazo histórico
  tienen pruebas positivas y negativas.
- Los puertos de contexto aceptan queries tipadas de `Contracts/Data/V1`.
- El BDS, las reglas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

- Migración PostgreSQL `rule_assignments` con índice único parcial activo.
- Repositorios InMemory y PostgreSQL bajo `RuleAssignmentRepositoryContract`.
- Puerto público `ResolveProgramContextPort` + `ProgramContextData` V1 y
  queries tipadas (`ResolveProgramContextQueryData`,
  `AssertSelectedModuleQueryData`).
- Puerto `ResolvePlanContextPort` con queries tipadas
  (`ResolvePlanContextQueryData`, `AssertEnabledModuleIdsQueryData`).
- Reemplazo revalida `BR-RULE-010` antes de cerrar la vigente.
- Rutas administrativas:
  - `GET/POST /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments`
  - `GET /api/ib/v1/admin/plans/{plan}/rules/{rule}/assignments/{assignment}`
  - `POST .../assignments/{assignment}/replace`
  - `POST .../assignments/{assignment}/withdraw`
- Permiso `ib.rules.manage` y colección Postman (`Administration/Rules`).
- Tests acotados a la corrección: 17 pruebas y 125 aserciones
  (`ResolveProgramContextUseCaseTest`, `RuleAssignmentEndpointTest`,
  `UserContextArchitectureTest`).
- Suite completa: 163 pruebas y 863 aserciones sobre PostgreSQL real tras la
  corrección.
- `graphify update .` ejecutado tras el cambio estructural.
