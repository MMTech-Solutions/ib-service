# Progression PG1: plan de implementación

Estado: **Sesiones 1–3 y P0.1–P0.3 completadas; sesiones 4–5 pendientes**
Dependencias: `01`–`04` de PG1 completados; contrato M3 definido en
[`06-m3-progression-activity-contract.md`](../modules/06-m3-progression-activity-contract.md);
primer adapter M3 (`deposits` vía fixtures) implementado en la sesión 1;
núcleo y persistencia de evaluaciones/contribuciones en la sesión 2;
P0.1 (Rules), P0.2 (Subscriptions) y P0.3 (Plans) completadas; sesión 3
(evaluación pull-only) completada
Última revisión: 2026-09-17

## Propósito

Implementar PG1 como una única capacidad habilitable: consulta pull-only,
evaluación durable aceptada o excluida, contribución atómica e inspección
administrativa. No se habilita parcialmente antes de completar persistencia,
idempotencia, fronteras y consulta administrativa.

## Fuentes obligatorias

Cada sesión reconstruye su contexto desde documentación versionada, no desde el
resumen de una conversación anterior:

1. [`README.md`](README.md), [`03-vertical-deliveries.md`](03-vertical-deliveries.md)
   y [`04-pg1-data-model.md`](04-pg1-data-model.md).
2. [`06-m3-progression-activity-contract.md`](../modules/06-m3-progression-activity-contract.md).
3. [`progression.bds.md`](../../bds/progression.bds.md) y
   [`plans-and-subscriptions.bds.md`](../../bds/plans-and-subscriptions.bds.md).
4. [`architecture.md`](../../rules/architecture.md),
   [`api-conventions.md`](../../rules/api-conventions.md),
   [`integrations.md`](../../rules/integrations.md),
   [`security.md`](../../rules/security.md), `code-style.md` y
   `programming-best-practices.md` cuando se modifique PHP.
5. Las reglas aplicables de `.ai/rules/`, si existen, y el código/pruebas reales
   dejados por las sesiones anteriores.

El BDS gobierna la semántica. Una contradicción o decisión pendiente no se
resuelve por conveniencia: la sesión se detiene y se registra como bloqueada.

## Restricciones globales

- PG1 es una única capacidad habilitable; ninguna sesión expone o declara una
  entrega parcial como terminada.
- Evaluaciones y contribuciones son inmutables, atómicas e idempotentes. La
  unicidad PostgreSQL es la autoridad ante reintentos concurrentes.
- Los contratos inter-feature son puertos y Data tipados; no se importan
  repositories, Eloquent, SDKs ni DTOs internos de otro feature.
- Plan inactivo no consulta, evalúa, contribuye ni ejecuta runs; reactivar no
  produce backfill. PG1 no implementa runs, placement, FX, reversas ni Rewards.
- InMemory y PostgreSQL deben superar la misma suite contractual. Las carreras,
  FKs, checks e índices se prueban además contra PostgreSQL real.
- HTTP administrativo aplica `UserContext`, `admin_panel`, la forma de `data`
  de las convenciones API y Postman actualizado; no existe superficie customer.

## Protocolo obligatorio por sesión

### Antes de implementar

1. Leer las fuentes obligatorias y usar Graphify antes de explorar código.
2. Comprobar en este documento el estado y la evidencia de **todas** las
   dependencias declaradas para la sesión, incluido P0 si figura en la fila.
   Una sesión bloqueada no se reintenta hasta que cada bloqueo declarado esté
   `Completada` y tenga evidencia verificable.
3. Confirmar la dependencia de entrada y ejecutar las pruebas relevantes antes
   de modificar código cuando exista implementación previa.
4. Limitar el trabajo exclusivamente a la sesión solicitada.

### Durante la implementación

- Seguir precedentes del mismo feature y responsabilidad; no usar `broker-service`
  como autoridad arquitectónica.
- Detener el trabajo si falta un contrato M3, una decisión de dominio, una
  credencial/SDK indispensable o una prueba revela una desalineación. Registrar
  el bloqueo en vez de inventar un adapter, una regla o un fallback.
- No adelantar trabajo de sesiones posteriores. Un defecto previo solo se corrige
  si bloquea la sesión actual y se deja evidencia de ello.
- Una prueba de concurrencia debe reproducir la carrera real; no se sustituye
  por una secuencia de operaciones aisladas.

### Al cerrar una sesión

1. Ejecutar tests específicos y regresión relacionada; usar PostgreSQL real
   cuando aplique.
2. Ejecutar Pint si hubo PHP y `graphify update .` tras cambios de código.
3. Validar Postman y rutas cuando cambie HTTP.
4. Actualizar en este documento la fila de estado, la fecha y la evidencia bajo
   la sesión: archivos, migraciones, contratos, pruebas y resultados reales.
5. Marcar `Completada` solo si se satisface el criterio de salida. Si no, marcar
   `Bloqueada` con causa concreta o conservar `En curso`.
6. Actualizar `progression/README.md` y el índice general solo al cambiar el
   estado global de PG1, nunca para fingir el cierre de una sesión incompleta.

## Estado de ejecución

| Sesión | Alcance | Estado | Dependencia | Última evidencia |
| --- | --- | --- | --- | --- |
| P0 | Contextos proveedores de Progression | Completada | Extensiones en Rules, Subscriptions y Plans | 2026-09-17 — P0.1–P0.3 Completadas; ver Evidencia P0 |
| 1 | M3 y fronteras inter-feature | Completada | Primer adapter M3 | 2026-09-17 — ver Evidencia sesión 1 |
| 2 | Núcleo y persistencia | Completada | Sesión 1 | 2026-09-17 — ver Evidencia sesión 2 |
| 3 | Evaluación pull-only | Completada | P0 y sesiones 1–2 | 2026-09-17 — ver Evidencia sesión 3 |
| 4 | Consulta administrativa | Pendiente | Sesiones 1 a 3 | — |
| 5 | Cierre integral | Pendiente | Sesiones 1 a 4 | — |

## Prerrequisito P0 — Contextos proveedores de Progression

Estado: **Completada**

P0 no es una entrega parcial de PG1 ni autoriza a calcular puntos. Agrupa las
extensiones mínimas que deben publicar los features propietarios para que la
sesión 3 resuelva el contexto histórico sin atravesar fronteras internas. Cada
subentrega se implementa y prueba en su feature; Progression solo consume sus
puertos Input y Data V1 públicos.

| Subentrega | Feature propietario | Contrato / extensión requerida | Estado | Criterio de salida |
| --- | --- | --- | --- | --- |
| P0.1 | Rules | Puerto Input + Data V1 que resuelva, en `occurred_at`, la asignación, regla y versión `points_per_quantity_unit` aplicable a programa, módulo, métrica y unidad; validar `BR-RULE-016`. | Completada | Puerto versionado, binding y pruebas del feature; devuelve contexto o ausencia tipada sin exponer modelos internos. |
| P0.2 | Subscriptions | Puerto Input + Data V1 que resuelva para el beneficiario, en `occurred_at`, suscripción, programa, placement y condición de fijación. | Completada | Puerto versionado, binding y pruebas históricas por instante; sin repositories o Eloquent expuestos. |
| P0.3 | Plans | Implementar el período de progresión obligatorio de `BR-PLAN-018` y exponer en una frontera consumible por Progression el período y el estado operativo del plan. | Completada | Migración/reglas, compatibilidad de contratos y pruebas de período/estado; no se rompe V1 existente. |

Criterio de salida de P0: P0.1, P0.2 y P0.3 están `Completada`, con contratos
publicados, implementaciones del feature propietario y pruebas reales. Entonces
se actualiza esta fila y se reabre la sesión 3; no antes.

### Evidencia

Fecha: 2026-09-17

#### P0.1 — Rules (Completada)

**Contrato público**

- Puerto: `app/Features/Rules/Contracts/Ports/Input/ResolvePointsContributionContextPort.php`
- Data V1:
  - `ResolvePointsContributionContextQueryData` (`program_id`, `module_id`,
    `metric_code`, `unit_code`, `occurred_at`)
  - `PointsContributionContextData` (`rule_id`, `rule_version_id`,
    `rule_assignment_id`, `strategy_type`, `scope_type`, `unit`, `weight`)
  - `ResolvePointsContributionContextResultData` (contexto o ausencia tipada)
- Excepción contractual: `AmbiguousPointsContributionRuleException`
- Binding: `RulesServiceProvider` → `ResolvePointsContributionContextPort`
- Implementación: `Assignments/UseCases/ResolvePointsContributionContextUseCase`
  + `ResolvePointsContributionMatchesAction`
- Semántica histórica: intervalo semiabierto `[starts_at, ends_at)` (`BR-RULE-012`)
- Discriminador de matching: `configuration.unit` de la estrategia publicada
  `points_per_quantity_unit` (`strategies.md`); `metric_code` viaja en la query
  como dimensión de actividad del consumidor y no inventa un campo de métrica
  ausente en el schema V1 de la estrategia.
- `BR-RULE-016` en escritura: `AssertUniquePointsPerQuantityUnitAssignmentAction`
  en `StoreRuleAssignmentUseCase` / `ReplaceRuleAssignmentUseCase`; excepción
  `POINTS_PER_QUANTITY_UNIT_ASSIGNMENT_CONFLICT`
- Repositorio: `listEffectiveAt` / `listActiveForProgramModule` (InMemory + PostgreSQL)
- Sin exposición de Eloquent, repositories ni DTOs internos al consumidor

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- `tests/Feature/Rules/ResolvePointsContributionContextPortTest.php`
  — resolución histórica, ausencia por unidad, unicidad entre reglas, ambigüedad
- Contract assignments (incluye efectividad temporal):
  `InMemoryRuleAssignmentRepositoryContractTest`,
  `PostgreSqlRuleAssignmentRepositoryContractTest`
- Regresión assignments HTTP: `RuleAssignmentEndpointTest`
- Arquitectura: `UserContextArchitectureTest` (puerto + Data V1)
- Regresión Progression sesión 1: `ProgressionActivityPortsIntegrationTest`
- Resultados acotados P0.1: **30 tests / 264 aserciones OK**; Progression ports
  **5 tests / 34 aserciones OK**
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Fuera de alcance P0.1:** consumo del puerto desde Progression, evaluación
pull-only, P0.2, P0.3, sesión 3.

#### P0.2 — Subscriptions (Completada)

**Contrato público**

- Puerto: `app/Features/Subscriptions/Contracts/Ports/Input/ResolveSubscriptionContextPort.php`
- Data V1:
  - `ResolveSubscriptionContextQueryData` (`external_user_id`, `occurred_at`)
  - `SubscriptionContextData` (`subscription_id`, `plan_id`, `program_id`,
    `placement_id`, `placement_condition`)
  - `ResolveSubscriptionContextResultData` (contexto o ausencia tipada)
- Excepción contractual: `AmbiguousSubscriptionContextException`
- Binding: `SubscriptionsServiceProvider` → `ResolveSubscriptionContextPort`
- Implementación: `Catalog/UseCases/ResolveSubscriptionContextUseCase`
  + `ResolveSubscriptionContextMatchesAction`
- Semántica histórica: intervalo semiabierto `[effective_from, effective_until)`
  (`BR-SUBSCRIPTION-011`); ausencia tipada si no hay placement que cubra el
  instante (p. ej. `pending` o fuera de vigencia)
- Repositorio: `listPlacementContextsAt` (InMemory + PostgreSQL) además de
  `resolvePlacementAt`
- Sin exposición de Eloquent, repositories ni DTOs internos al consumidor

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- `tests/Feature/Subscriptions/ResolveSubscriptionContextPortTest.php`
  — resolución histórica, frontera en `effective_until`, fijación, historia
  tras cancelar, ausencia en `pending`
- Contract subscriptions (incluye `listPlacementContextsAt`):
  `InMemorySubscriptionRepositoryContractTest`,
  `PostgreSqlSubscriptionRepositoryContractTest`
- Regresión lifecycle HTTP: `SubscriptionLifecycleEndpointTest`
- Arquitectura: `UserContextArchitectureTest` (puerto + Data V1)
- Regresión Progression sesión 1: `ProgressionActivityPortsIntegrationTest`
- Resultados acotados P0.2: **47 tests / 450 aserciones OK**
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Fuera de alcance P0.2:** consumo del puerto desde Progression, evaluación
pull-only, P0.3, sesión 3.

#### P0.3 — Plans (Completada)

**Contrato público**

- Puerto: `app/Features/Plans/Contracts/Ports/Input/ResolvePlanProgressionContextPort.php`
- Data V1:
  - `ResolvePlanProgressionContextQueryData` (`plan_id`, `occurred_at`)
  - `PlanProgressionContextData` (`plan_id`, `is_active`, `progression_period`)
- Binding: `PlansServiceProvider` → `ResolvePlanProgressionContextPort`
- Implementación: `Catalog/UseCases/ResolvePlanProgressionContextUseCase`
- Semántica histórica de `is_active`: último `plan_operational_changes` con
  `occurred_at <= query.occurred_at` (`next_is_active`); sin cambio previo el
  plan nace inactivo (`BR-PLAN-007`)
- `progression_period` vigente del plan (`daily` / `weekly` / `monthly`); no se
  inventó historial de versiones del período (`BR-PLAN-019` rige en el
  consumidor de ventanas)
- `PlanContextData` / `ResolvePlanContextPort` V1 intactos (frontera
  especializada nueva)

**Persistencia y escritura administrativa**

- Migración: `2026_09_17_180000_add_progression_period_to_plans_table.php`
  — columna `NOT NULL`, check PostgreSQL, backfill `monthly` de filas previas,
  sin default permanente
- Enum: `PlanProgressionPeriod`
- HTTP: `progression_period` obligatorio en create; opcional en update
- DTOs/API: `PlanData` / `PlanDetailData` exponen `progression_period`
- Repositorio: `findLastOperationalChangeAtOrBefore` (InMemory + PostgreSQL)
- Postman: create/update de planes actualizados

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- `tests/Feature/Plans/ResolvePlanProgressionContextPortTest.php`
  — estado histórico, período, validación de create, plan inexistente
- Contract plans (incluye historial operativo):
  `InMemoryPlanRepositoryContractTest`,
  `PostgreSqlPlanRepositoryContractTest`
- Regresión HTTP: `PlanCatalogEndpointTest`
- Arquitectura: `UserContextArchitectureTest` (puerto + Data V1)
- Regresión Progression sesión 1: `ProgressionActivityPortsIntegrationTest`
- Regresión afín: Program/Rule/Subscription endpoints y puertos P0.1/P0.2
- Resultados acotados P0.3: **51 tests / 400 aserciones OK**; regresión extra
  Program/Rule/Subscription lifecycle **23 tests / 388 aserciones OK**
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Fuera de alcance P0.3:** consumo del puerto desde Progression, evaluación
pull-only, sesión 3, historial versionado del período, runs, FX, Rewards.

## Sesión 1 — M3 y fronteras inter-feature

Estado: **Completada**

- Implementar en Modules el puerto y Data V1 definidos por M3, con su adapter
  inicial, paginación/cursor, validación y evidencia de módulo inactivo.
- En Progression, crear el puerto de salida de actividad y su adapter hacia
  `ListProgressionActivitiesPort`; consumir también los contratos especializados
  de Plans, Subscriptions, Programs y Rules.
- Prohibir imports de repositories, Eloquent, SDKs o DTOs internos de otro
  feature. Los identificadores externos siguen siendo opacos.

Criterio de salida: una prueba de integración de puertos entrega actividad
normalizada paginada a Progression y cubre los estados `running`, `paused` e
`inactive` del módulo.

### Evidencia

Fecha: 2026-09-17

**Modules M3**

- Puerto: `app/Features/Modules/Contracts/Ports/Input/ListProgressionActivitiesPort.php`
- Data V1: `ListProgressionActivitiesQueryData`, `ProgressionActivityData`,
  `ListProgressionActivitiesResultData`
- Excepciones contractuales: `InvalidProgressionActivityQueryException`,
  `UnsupportedProgressionActivityCapabilityException`
- UseCase: `Catalog/UseCases/ListProgressionActivitiesUseCase.php`
- Allowlist + factory: `Catalog/Factories/ModuleActivitySourceFactory.php`
  (solo `deposits`; `closed_trading_volume` sin adapter no se consulta)
- Adapter inicial con fixtures:
  `Sources/Broker/Services/Adapters/FixtureBrokerDepositsActivityAdapter.php`
- Cursor opaco: `Catalog/Support/ProgressionActivityCursor.php`
- Evidencia técnica de rechazo inactivo (log + recorder en memoria de proceso;
  forma durable de BR-MODULE-015 sigue pendiente en BDS):
  `Catalog/Services/ModuleActivityRejectionEvidence.php`
- Límites técnicos: `config/modules.php` (`activity.*`)
- Binding: `ModulesServiceProvider` → `ListProgressionActivitiesPort`

**Progression — fronteras**

- Feature nuevo bajo `app/Features/Progression/`
- Puerto de salida: `Contracts/Ports/Output/FetchProgressionActivitiesPort.php`
- Data propia: `FetchProgressionActivitiesQueryData`, `NormalizedActivityData`,
  `FetchProgressionActivitiesResultData`
- Adapter: `Services/Adapters/ModulesFetchProgressionActivitiesAdapter.php`
  (solo Contracts de Modules)
- Consumo de contratos publicados existentes vía
  `Services/ProgressionInterFeatureGateways.php`:
  `ResolvePlanContextPort`, `ResolvePlanSubscriptionContextPort`,
  `ResolveProgramContextPort`, `ResolveProgramSubscriptionContextPort`,
  `HasOpenSubscriptionsForPlanPort`
- Rules: **sin puerto Input publicado** para lectura/evaluación; no se inventó
  contrato (extensión pendiente documentada en `rules/README.md`). Queda para
  la sesión de evaluación cuando se defina el puerto especializado.
- Provider: `ProgressionServiceProvider` registrado en `bootstrap/providers.php`

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- `tests/Feature/Progression/ProgressionActivityPortsIntegrationTest.php`
  — running (paginación + cursor), paused (consulta sin rechazo), inactive
  (sin invocar provider + evidencia), gateways inter-feature, cursor opaco
- Arquitectura actualizada:
  `tests/Architecture/UserContextArchitectureTest.php` (contratos M3)
- Resultados: Progression 5 tests / 34 aserciones OK; Modules + Progression +
  Architecture 31 tests / 149 aserciones OK
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Migraciones:** ninguna (sesión 1 no toca persistencia de evaluaciones).

## Sesión 2 — Núcleo y persistencia

Estado: **Completada**

- Crear las migraciones y checks de `04-pg1-data-model.md`.
- Implementar agregados inmutables de evaluación y contribución, enums de estado
  y motivo de exclusión, objetos de valor decimal y ventana.
- Implementar el contrato de repositorio y sus versiones InMemory/PostgreSQL.
- Hacer atómica la creación de evaluación aceptada y contribución; la colisión
  de `(module_id, source_activity_id, beneficiary_external_user_id)` devuelve
  el resultado canónico sin recalcular.

Criterio de salida: ambas implementaciones superan la misma suite contractual;
PostgreSQL además prueba FKs, checks, unicidad y rollback.

### Evidencia

Fecha: 2026-09-17

**Migración**

- `database/migrations/2026_09_17_140000_create_progression_tables.php`
  — `progression_activity_evaluations` + `progression_contributions`
  — FKs `RESTRICT` a `modules`, `subscriptions`, `plans`, `programs`, `rules`,
    `rule_versions`, `rule_assignments`
  — CHECKs de `status`, catálogo de `exclusion_reason`, forma accepted/excluded,
    contexto obligatorio de accepted, orden de ventana, `strategy_type` y
    `scope_type`
  — índice único de idempotencia
    `(module_id, source_activity_id, beneficiary_external_user_id)`
  — índices administrativos y `evaluation_id UNIQUE` en contribuciones

**Dominio**

- Enums: `EvaluationStatus`, `ExclusionReason` (catálogo BDS cerrado),
  `ContributionStrategyType`, `ContributionScopeType`
- VOs: `ExactDecimal` (escala máx. 8, sin redondeo de entradas),
  `ProgressionWindow` (`endsAt > startsAt`)
- Agregados inmutables: `ActivityEvaluation` (`accepted` / `excluded`),
  `Contribution` (`points = quantity × weight`)
- Excepciones: `InvalidExactDecimalException`, `InvalidProgressionWindowException`,
  `InvalidActivityEvaluationException`, `ActivityEvaluationNotFoundException`

**Persistencia**

- Contrato: `Contracts/Repositories/ActivityEvaluationRepositoryInterface`
  (`transaction`, `findById`, `findByIdempotencyKey`, `record`)
- InMemory: `Repositories/InMemory/InMemoryActivityEvaluationRepository`
- PostgreSQL: `Repositories/PostgreSql/PostgreSqlActivityEvaluationRepository`
  + records Eloquent internos; `record()` usa SAVEPOINT y trata
  `UniqueConstraintViolationException` como reintento canónico
- Factory: `Factories/ActivityEvaluationRepositoryFactory`
- Config: `config/progression.php`; bindings en `ProgressionServiceProvider`

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- Contrato compartido: `tests/Contracts/ActivityEvaluationRepositoryContract.php`
  — aceptación atómica, cada motivo de exclusión, idempotencia sin recálculo,
    rollback de transacción externa
- `tests/Feature/Progression/InMemoryActivityEvaluationRepositoryContractTest.php`
- `tests/Feature/Progression/PostgreSqlActivityEvaluationRepositoryContractTest.php`
- Constraints/FK/unicidad/rollback/colisión:
  `tests/Feature/Progression/PostgreSqlActivityEvaluationConstraintTest.php`
- Unitarios VO: `tests/Unit/Progression/ExactDecimalAndWindowTest.php`
- Regresión sesión 1:
  `tests/Feature/Progression/ProgressionActivityPortsIntegrationTest.php`
- Resultados: 27 tests / 152 aserciones OK (Unit Progression + contract
  InMemory/PostgreSQL + constraints + ports sesión 1)
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Fuera de alcance (sesión 2):** evaluación pull-only, HTTP admin, runs,
placement, FX, Rewards, Kafka.

## Sesión 3 — Evaluación pull-only

Estado: **Completada**

- Crear el caso de uso interno que recorre páginas M3 y evalúa cada hecho.
- Resolver contexto en `occurred_at`, ventana UTC, suscripción, placement,
  plan, selección de módulo, regla y versión vigentes.
- Crear `accepted` con contribución o `excluded` con un motivo del catálogo,
  incluidos fijación, unidad, escala, regla ausente, módulo inactivo y plan
  inactivo.
- Revalidar la condición del plan antes del commit; no recuperar actividad previa
  a una reactivación ni dejarla en cola.

Criterio de salida: los escenarios de elegibilidad y exclusión del BDS producen
una única evidencia durable por actividad y beneficiario.

### Evidencia

Fecha: 2026-09-17

**Dependencias verificadas**

- P0.1–P0.3 `Completada` (puertos Rules, Subscriptions, Plans).
- Sesiones 1–2 `Completada`.
- Regresión previa + suite Progression tras el cambio: **34 tests / 239 aserciones OK**.

**Caso de uso y decisión**

- Input: `DTOs/EvaluateProgressionActivitiesData.php`
- Resultado: `DTOs/EvaluateProgressionActivitiesResult.php`
  (`evaluated` | `skipped_plan_inactive` | `deferred_module_paused` |
  `rejected_module_inactive`)
- UseCase: `UseCases/EvaluateProgressionActivitiesUseCase.php`
  — comprueba plan activo antes de consultar M3; pagina actividad; no evalúa
  si el módulo está `paused` o `inactive`; idempotencia vía
  `findByIdempotencyKey` + `record`
- Acción: `Actions/BuildActivityEvaluationDecisionAction.php`
  — suscripción/placement (P0.2), plan/período/ventana (P0.3),
  módulo activo, habilitado en plan, seleccionado en programa, regla (P0.1),
  revalidación `is_active` antes de aceptar
- Ventana UTC: `Support/DeriveProgressionWindowFromPeriod.php`
  (`daily` / `weekly` / `monthly`)
- Gateways actualizados: `Services/ProgressionInterFeatureGateways.php`
  (+ `ResolvePlanProgressionContextPort`, `ResolveSubscriptionContextPort`,
  `ResolvePointsContributionContextPort`)

**Motivos cubiertos en pruebas**

- `accepted` + contribución atómica e idempotente
- `no_active_subscription`, `placement_fixed`, `no_applicable_rule`,
  `unit_mismatch` (contexto Rules con unidad distinta), `scale_exceeded`,
  `module_not_selected`, `window_closed_after_pause` (`resuming_after_pause`)
- Plan inactivo al inicio → `skipped_plan_inactive` (sin consulta diferida)
- Módulo `paused` → `deferred_module_paused` (actividad permanece en origen)

**Pruebas (PostgreSQL `_testing` / PHPUnit)**

- `tests/Feature/Progression/EvaluateProgressionActivitiesUseCaseTest.php`
- `tests/Unit/Progression/DeriveProgressionWindowFromPeriodTest.php`
- Gateways: `ProgressionActivityPortsIntegrationTest`
- Regresión sesión 2: contract InMemory/PostgreSQL, constraints, ExactDecimal
- Resultados Progression: **34 tests / 239 aserciones OK**
- Pint: `vendor/bin/pint --dirty --format agent`
- Graphify: `graphify update .` (grafo reconstruido)

**Migraciones:** ninguna (reutiliza tablas de sesión 2).

**Fuera de alcance (sesión 3):** HTTP admin, runs, placement automático, FX,
Rewards, Kafka como vía primaria, `late_activity` efectivo (PG2).

## Sesión 4 — Consulta administrativa

Estado: **Pendiente**

- Exponer listado y detalle administrativos de evaluaciones y contribuciones,
  con paginación, filtros `plan_id`, `subscription_id`, estado, motivo y rango
  de ocurrencia.
- Aplicar permiso `ib.progression.read` en `admin_panel`; no crear ruta customer.
- `data` es directamente la colección u objeto; filtros y paginación van en
  `meta`. El detalle expone `exclusion_reason` y genera
  `exclusion_explanation`, sin persistir texto libre.
- Actualizar Postman bajo `Administration/Progression` y sus variables sin
  secretos reales.

Criterio de salida: la administración observa el contexto y resultado auditables
de aceptaciones y exclusiones; una identidad customer no puede consultarlos.

### Evidencia

Pendiente. Al cerrar, registrar archivos, migraciones, contratos y resultados de pruebas reales.

## Sesión 5 — Cierre integral

Estado: **Pendiente**

- Ejecutar la suite contractual contra memoria y PostgreSQL, tests de casos de
  uso, HTTP, autorización, paginación y Postman JSON válido.
- Probar reintentos concurrentes, rollback de contribución, pausa, desactivación
  y reactivación de plan, y ausencia de cálculo de runs o placement.
- Ejecutar Pint, `graphify update .` tras cambios de código y registrar evidencia
  real en este documento antes de cambiar el estado global de PG1.

### Evidencia

Pendiente. Al cerrar, registrar las suites completas, la validación de Postman,
Pint, Graphify y la evidencia que permite cambiar el estado global de PG1.

## Directiva reutilizable para Cursor Auto

> Implementa exclusivamente la sesión N definida en
> `docs/roadmap/progression/05-pg1-implementation.md`. Reconstruye el contexto
> leyendo todas sus fuentes obligatorias, consulta Graphify antes de explorar
> código y verifica todas las dependencias declaradas en la tabla, incluido P0.
> Si alguna dependencia está `Bloqueada`, no reintentes la sesión: valida la
> evidencia, conserva su estado y detente. No avances a otra sesión, no inventes
> contratos o decisiones pendientes y no declares PG1 completada. Si aparece un
> bloqueo nuevo, regístralo. Antes de cerrar, ejecuta las verificaciones exigidas,
> actualiza la tabla de estado y la sección Evidencia de esta sesión con archivos
> y resultados reales, y solo entonces cambia su estado a Completada.

## Fuera de PG1

Runs, resultados y placement automático; Kafka como vía primaria; reversas, FX,
scopes instrumentales distintos de `all`, rewards y pagos.