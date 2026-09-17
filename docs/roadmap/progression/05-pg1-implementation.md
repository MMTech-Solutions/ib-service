# Progression PG1: plan de implementación

Estado: **Sesiones 1–2 completadas; sesiones 3–5 pendientes**
Dependencias: `01`–`04` de PG1 completados; contrato M3 definido en
[`06-m3-progression-activity-contract.md`](../modules/06-m3-progression-activity-contract.md);
primer adapter M3 (`deposits` vía fixtures) implementado en la sesión 1;
núcleo y persistencia de evaluaciones/contribuciones en la sesión 2
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
2. Comprobar en este documento el estado y la evidencia de las sesiones previas.
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
| 1 | M3 y fronteras inter-feature | Completada | Primer adapter M3 | 2026-09-17 — ver Evidencia sesión 1 |
| 2 | Núcleo y persistencia | Completada | Sesión 1 | 2026-09-17 — ver Evidencia sesión 2 |
| 3 | Evaluación pull-only | Pendiente | Sesiones 1 y 2 | — |
| 4 | Consulta administrativa | Pendiente | Sesiones 1 a 3 | — |
| 5 | Cierre integral | Pendiente | Sesiones 1 a 4 | — |
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

Estado: **Pendiente**

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

Pendiente. Al cerrar, registrar archivos, migraciones, contratos y resultados de pruebas reales.

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
> código y verifica las dependencias y evidencia de las sesiones anteriores. No
> avances a otra sesión, no inventes contratos o decisiones pendientes y no
> declares PG1 completada. Si aparece un bloqueo, detente y regístralo. Antes de
> cerrar, ejecuta las verificaciones exigidas, actualiza la tabla de estado y la
> sección Evidencia de esta sesión con archivos y resultados reales, y solo
> entonces cambia su estado a Completada.

## Fuera de PG1

Runs, resultados y placement automático; Kafka como vía primaria; reversas, FX,
scopes instrumentales distintos de `all`, rewards y pagos.