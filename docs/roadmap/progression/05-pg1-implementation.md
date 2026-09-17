# Progression PG1: plan de implementación

Estado: **Preparado; bloqueado por la implementación de Modules M3**
Dependencias: `01`–`04` de PG1 completados; contrato M3 definido en
[`06-m3-progression-activity-contract.md`](../modules/06-m3-progression-activity-contract.md)
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
| 1 | M3 y fronteras inter-feature | Bloqueada | Primer adapter M3 | — |
| 2 | Núcleo y persistencia | Pendiente | Sesión 1 | — |
| 3 | Evaluación pull-only | Pendiente | Sesiones 1 y 2 | — |
| 4 | Consulta administrativa | Pendiente | Sesiones 1 a 3 | — |
| 5 | Cierre integral | Pendiente | Sesiones 1 a 4 | — |
## Sesión 1 — M3 y fronteras inter-feature

Estado: **Bloqueada por adapter M3**

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

Pendiente. Al cerrar, registrar archivos, migraciones, contratos y resultados de pruebas reales.

## Sesión 2 — Núcleo y persistencia

Estado: **Pendiente**

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

Pendiente. Al cerrar, registrar archivos, migraciones, contratos y resultados de pruebas reales.

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