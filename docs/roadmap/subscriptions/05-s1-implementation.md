# Subscriptions S1: plan de implementación

Estado: **En curso; sesión 2 completada**
Dependencias funcionales: `Programs P2` y `Rules R2` completados
Dependencia de implementación: extensión de Plans para `BR-PLAN-017` (sesión 1)
Entrega objetivo: S1 — Suscripciones y placement administrativo
Última revisión: 2026-09-15

## Propósito

Este documento divide la implementación de S1 en cinco sesiones de trabajo
acotadas para reducir la complejidad cognitiva sin fragmentar la capacidad de
negocio. Las sesiones son unidades de ejecución y verificación, no entregas
funcionales independientes.

S1 continúa siendo una única capacidad habilitable. No se libera una
implementación parcial, en particular una superficie que permita crear
solicitudes `pending` sin que administración pueda consultarlas, aprobarlas o
rechazarlas.

## Fuentes obligatorias

Antes de comenzar cada sesión se debe reconstruir el contexto desde las fuentes
versionadas, sin depender de la memoria de una conversación anterior:

1. [`README.md`](README.md), para el estado y las decisiones confirmadas.
2. [`03-vertical-deliveries.md`](03-vertical-deliveries.md), para alcance,
   superficies y criterios de aceptación.
3. [`04-data-model.md`](04-data-model.md), para esquema, transacciones,
   concurrencia y contract tests.
4. [`plans-and-subscriptions.bds.md`](../../bds/plans-and-subscriptions.bds.md).
5. [`progression.bds.md`](../../bds/progression.bds.md) y
   [`rewards.bds.md`](../../bds/rewards.bds.md) cuando la sesión afecte
   placement, suspensión o resolución temporal.
6. [`architecture.md`](../../rules/architecture.md),
   [`api-conventions.md`](../../rules/api-conventions.md),
   [`code-style.md`](../../rules/code-style.md),
   [`programming-best-practices.md`](../../rules/programming-best-practices.md)
   y [`security.md`](../../rules/security.md).
7. Las reglas generadas aplicables en `.ai/rules/`, cuando existan.

Si estas fuentes se contradicen, el BDS gobierna la semántica del negocio y
`docs/rules/` las restricciones técnicas. La sesión no debe resolver
silenciosamente una contradicción ni convertir una decisión pendiente en una
invariante.

## Restricciones globales

Estas condiciones permanecen activas durante las cinco sesiones:

- S1 se habilita y se declara completada únicamente como capacidad integral.
- Cada usuario tiene como máximo una suscripción abierta global, `pending` o
  `active`, incluso bajo concurrencia.
- El requisito de aprobación se captura al solicitar y no se recalcula durante
  la vida de la suscripción.
- `pending` y `rejected` no tienen placement; `active` tiene exactamente un
  intervalo vigente; `ended` conserva historia sin intervalo abierto.
- Los intervalos de placement son semiabiertos
  `[effective_from, effective_until)`, no se solapan y se resuelven por el
  instante de ocurrencia.
- Cambiar de plan termina la suscripción anterior y crea otra de forma
  indivisible, sin transferir puntos ni fijación.
- Una fijación suspende Progression y excluye de progreso la actividad de ese
  intervalo, pero no suspende Rewards.
- Liberar una fijación no cambia inmediatamente el programa.
- El archivo de un plan serializa con solicitud, aprobación y cambio de plan;
  una comprobación previa aislada no satisface esta protección.
- Toda mutación administrativa conserva actor e instante. El rechazo exige un
  motivo no vacío; las demás acciones conservan el motivo cuando se proporciona.
- Los contratos entre features usan puertos y datos tipados. Ningún feature
  importa repositories, factories o modelos internos de otro.
- Los contratos V1 existentes de Plans y Programs no reciben cambios
  incompatibles.
- No se publican anticipadamente contratos de Subscriptions para Progression o
  Rewards.
- InMemory y PostgreSQL deben superar la misma suite contractual. Las
  restricciones y carreras dependientes de PostgreSQL se prueban además contra
  PostgreSQL real.
- Las entradas HTTP obtienen la identidad desde `UserContext`; nunca aceptan
  como autoridad un usuario enviado en el payload.
- La forma de las respuestas, paginación y metadatos respeta
  `api-conventions.md`.

## Fuera de S1

- Cálculo de contribuciones y ejecución de runs de Progression.
- Cálculo, registro financiero o pago de rewards.
- Eventos Kafka y contratos públicos sin consumidor implementado.
- Versionado futuro de planes y su aplicación a suscripciones existentes.
- Políticas generales de actividad tardía, reversas o correcciones temporales.
- Sistema transversal de registro de acciones.
- Configuración persistida de frecuencia o ejecución de Progression.

Una sesión no incorpora estos elementos para completar preventivamente una
integración futura.

## Protocolo entre sesiones

### Antes de implementar

1. Leer las fuentes obligatorias pertinentes.
2. Inspeccionar el código y las pruebas producidas por las sesiones anteriores.
3. Confirmar que todas las dependencias y criterios de entrada de la sesión
   están satisfechos.
4. Verificar el estado real mediante pruebas; no inferirlo solo desde este
   documento.
5. Limitar el trabajo al alcance de la sesión actual.

### Durante la implementación

- Seguir precedentes del mismo feature y responsabilidad. El código de
  `broker-service` no constituye autoridad arquitectónica.
- Mantener explícitas las invariantes que la sesión debe preservar.
- Si aparece una decisión funcional o de persistencia no cerrada, detener esa
  parte y documentar el bloqueo; no escoger una alternativa por conveniencia.
- Se pueden corregir defectos de una sesión anterior cuando bloqueen el alcance
  actual, dejando evidencia de la corrección. No se adelanta trabajo de una
  sesión posterior.
- No reducir una prueba de concurrencia a un test secuencial que no reproduzca
  la carrera especificada.

### Al cerrar una sesión

1. Ejecutar los tests específicos y la regresión relacionada.
2. Ejecutar Pint si hubo cambios PHP.
3. Actualizar Graphify si hubo cambios estructurales de código.
4. Registrar debajo de la sesión la evidencia concreta: archivos,
   migraciones, contratos, tests y resultados.
5. Cambiar su estado a `Completada` solo cuando se cumpla todo su criterio de
   salida.
6. No cambiar el estado global de S1 a completado antes del cierre de la sesión
   5.

## Estado de ejecución

| Sesión | Alcance | Estado | Dependencia |
| --- | --- | --- | --- |
| 1 | Contextos de Plans y Programs | Completada | Programs P2 y Plans P1 |
| 2 | Núcleo y persistencia | Completada | Sesión 1 |
| 3 | Solicitud, consultas y moderación | No iniciada | Sesiones 1 y 2 |
| 4 | Ciclo de vida administrativo | No iniciada | Sesiones 1 a 3 |
| 5 | Fijación, concurrencia y cierre integral | No iniciada | Sesiones 1 a 4 |

## Sesión 1 — Contextos de Plans y Programs

Estado: **Completada**

### Objetivo

Preparar las dependencias públicas que Subscriptions necesita sin introducir
todavía su agregado ni alterar de forma incompatible los contratos V1
existentes.

### Alcance

- Añadir `requires_approval` a Plans con default `true`.
- Asignar `true` a planes existentes sin alterar artificialmente su
  `updated_at`, `lock_version` ni su historia operacional.
- Aceptar `requires_approval` en creación. Su omisión equivale a `true`.
- Aceptarlo en actualización parcial. Su omisión conserva el valor persistido.
- Incluir el valor en las representaciones administrativas correspondientes.
- Publicar desde Plans un contrato especializado y tipado para resolver
  disponibilidad, archivo y requisito de aprobación.
- Publicar desde Programs un contrato especializado y tipado para validar que
  un programa pertenece al plan o resolver el primero por posición.
- Preservar los contratos V1 que ya consumen Programs y Rules.

Los nombres concretos de puertos y objetos de datos se deciden siguiendo las
convenciones existentes y no amplían su responsabilidad más allá del consumidor
real de S1.

### Pruebas mínimas

- Creación explícita y omitida de `requires_approval`.
- Actualización explícita y actualización que omite el campo.
- Backfill sin efectos administrativos ficticios.
- Resolución de plan activo, inactivo y archivado.
- Validación de programa propio, programa ajeno y resolución determinista del
  primero.
- Compatibilidad de los consumidores existentes de los contratos V1.
- Forma HTTP y Postman de Plans actualizados en el mismo cambio.

### Criterio de salida

Subscriptions puede obtener contextos tipados de plan y programa para todas las
decisiones de S1 sin acceder a internals de otro feature ni romper consumidores
existentes.

### Evidencia

- Migración `2026_09_15_060000_add_requires_approval_to_plans_table` (default
  `true`; no toca `updated_at`, `lock_version` ni `plan_operational_changes`).
- Persistencia y representación administrativa: `Plan`, `PlanData`,
  `PlanDetailData`, repositories InMemory/PostgreSQL, factory `PlanRecord`.
- HTTP: `StorePlanRequest`/`UpdatePlanRequest`, Commands y UseCases; respuestas
  incluyen `requires_approval`.
- Puerto Plans → Subscriptions: `ResolvePlanSubscriptionContextPort` +
  `PlanSubscriptionContextData` / `ResolvePlanSubscriptionContextQueryData`
  (`ResolvePlanSubscriptionContextUseCase`).
- Puerto Programs → Subscriptions: `ResolveProgramSubscriptionContextPort` +
  `ProgramSubscriptionContextData`, `AssertProgramBelongsToPlanQueryData`,
  `ResolveFirstProgramByPositionQueryData`,
  `ProgramNotAvailableForPlanException`
  (`ResolveProgramSubscriptionContextUseCase`).
- V1 intactos: `ResolvePlanContextPort` / `PlanContextData` y
  `ResolveProgramContextPort` / `ProgramContextData`.
- Postman `Administration/Plans` (create/update/show) actualizado.
- Tests ejecutados (50 passed, 271 assertions):
  `ResolvePlanSubscriptionContextUseCaseTest`,
  `ResolveProgramSubscriptionContextUseCaseTest`,
  `ResolveProgramContextUseCaseTest` (compatibilidad V1),
  `PlanCatalogEndpointTest`,
  `PlanStateTransitionsTest`,
  `InMemoryPlanRepositoryContractTest`,
  `PostgreSqlPlanRepositoryContractTest`,
  `ProgramCatalogEndpointTest`,
  `UserContextArchitectureTest`.
- Pint (`vendor/bin/pint --dirty`) y `graphify update .` aplicados.

## Sesión 2 — Núcleo y persistencia

Estado: **Completada**

### Objetivo

Implementar el agregado y las defensas persistentes completas antes de exponer
sus flujos HTTP.

### Alcance

- Incorporar los tipos de estado, origen, condición de placement, actor y
  acción administrativa definidos para S1.
- Implementar `Subscription` como raíz, placement como parte interna e
  historia administrativa como hijos inmutables.
- Crear las migraciones de `subscriptions`, `subscription_placements` y
  `subscription_changes` exactamente con los checks, foreign keys e índices de
  `04-data-model.md`.
- Implementar el contrato del repository de Subscriptions.
- Implementar repositories InMemory y PostgreSQL bajo el mismo contrato.
- Representar creación, transición de estado, cierre y reemplazo de
  suscripción, además de apertura y cierre de intervalos de placement.
- Mantener la historia en la misma transacción que el estado o placement que
  describe.
- Aplicar `operation_id` compartido a los dos efectos de un cambio de plan.
- Incorporar optimistic locking mediante `lock_version`.
- Mantener la resolución temporal dentro del repository; no publicarla aún
  como puerto inter-feature.

### Pruebas mínimas

- Matriz válida e inválida de estado, origen, timestamps y reemplazo.
- Unicidad persistente de suscripción abierta por usuario.
- Una suscripción reemplazada como máximo una vez.
- Matriz de campos de cada acción de historia.
- Rechazo con motivo y motivos opcionales en las demás acciones.
- Restricciones de eliminación y foreign keys.
- Matriz estado-placement.
- Un solo intervalo abierto, ausencia de solapamientos y fronteras contiguas.
- Programa perteneciente al plan.
- Resolución de una actividad exactamente en `effective_until`.
- Misma suite contractual para InMemory y PostgreSQL.
- Tests específicos de constraints sobre PostgreSQL real.

### Criterio de salida

Todas las transiciones y representaciones temporales requeridas por S1 pueden
persistirse y reconstruirse sin HTTP, y ambos repositories satisfacen el mismo
contrato observable.

### Evidencia

- Migración `2026_09_15_120000_create_subscriptions_tables` (`subscriptions`,
  `subscription_placements`, `subscription_changes`) con checks, FKs `restrict`,
  índices parciales de unicidad abierta/reemplazo/placement vigente y catálogo
  de acciones.
- Enums: `SubscriptionStatus`, `SubscriptionOrigin`, `PlacementCondition`,
  `SubscriptionActorKind`, `SubscriptionChangeAction`.
- Agregado de dominio: `Subscription`, `SubscriptionPlacement`,
  `SubscriptionChange` (matriz de historia e invariantes de forma).
- Contrato `SubscriptionRepositoryInterface` + factory + config
  `subscriptions.repository`; provider `SubscriptionsServiceProvider`.
- Repositories InMemory y PostgreSQL con `create`, `save`, `replace`,
  `resolvePlacementAt`, optimistic locking y pertenencia programa→plan.
- Tests ejecutados (37 passed, 145 assertions):
  `SubscriptionDomainInvariantTest`,
  `InMemorySubscriptionRepositoryContractTest`,
  `PostgreSqlSubscriptionRepositoryContractTest`,
  `PostgreSqlSubscriptionConstraintTest`.
- Pint (`vendor/bin/pint --dirty`) y `graphify update .` aplicados.

## Sesión 3 — Solicitud, consultas y moderación

Estado: **No iniciada**

### Objetivo

Implementar el recorrido completo desde la solicitud del usuario hasta su
activación o rechazo administrativo, junto con las consultas de ambas
audiencias.

### Alcance

- Solicitud del usuario usando su identidad de `UserContext` y únicamente el
  plan seleccionado.
- Captura inmutable del requisito de aprobación.
- Creación `pending` cuando se requiere aprobación.
- Activación automática con el primer programa cuando no se requiere
  aprobación.
- Ausencia total de efectos si una activación automática no puede resolver el
  programa.
- Consulta customer de la única suscripción abierta y respuesta de ausencia
  definida.
- Listado administrativo paginado de todos los estados.
- Detalle administrativo con historia.
- Aprobación en el mismo plan solicitado, con programa elegido o primero por
  posición.
- Revalidación del plan y programa durante aprobación; cualquier fallo conserva
  intacta la solicitud `pending`.
- Rechazo terminal con motivo obligatorio.
- Requests, Commands, UseCases, Resources, Controllers y autorización de estos
  recorridos.

La presencia técnica de rutas durante el desarrollo no autoriza liberar S1
parcialmente. La capacidad permanece no habilitable hasta completar la sesión
5.

### Pruebas mínimas

- Solicitud automática y solicitud pendiente según el snapshot.
- Plan inactivo o archivado.
- Activación sin programa disponible.
- Dos solicitudes simultáneas del mismo usuario: solo una confirma.
- Consulta customer limitada a la identidad autenticada y a estado abierto.
- Aislamiento de audiencias y permisos.
- Listado, filtros, paginación y detalle administrativo.
- Aprobación con programa elegido, programa por omisión, programa ajeno y plan
  que dejó de ser elegible.
- Rechazo sin motivo, con motivo y nueva solicitud posterior.
- Rollback completo de cada fallo.

### Criterio de salida

El recorrido `apply → pending → approve/reject` y la activación automática
funcionan de extremo a extremo, sin permitir dos abiertas ni exponer datos de
otra identidad o audiencia.

### Evidencia

Pendiente.

## Sesión 4 — Ciclo de vida administrativo

Estado: **No iniciada**

### Objetivo

Completar las mutaciones administrativas que terminan o reemplazan una
suscripción activa y las que cambian su programa sin cambiar su identidad.

### Alcance

- Cancelar una suscripción activa, cerrar su placement vigente y registrar la
  historia en una misma transacción.
- Cambiar de plan terminando la suscripción anterior y creando otra activa de
  forma indivisible.
- Resolver el programa elegido o el primero del plan destino.
- No aplicar aprobación al cambio administrativo de plan.
- No transferir puntos ni heredar fijación o identidad de la suscripción
  anterior.
- Compartir `operation_id` entre `change_plan_out` y `change_plan_in`.
- Bloquear planes origen y destino en orden ascendente de UUID antes de la
  suscripción origen.
- Cambiar de programa dentro del mismo plan conservando la condición `fixed` o
  `unfixed`.
- Exigir y comparar `lock_version` en toda mutación administrativa.
- Implementar sus entradas HTTP, autorización e historia observable.

### Pruebas mínimas

- Cancelación completa y rechazo de estados no válidos.
- Cambio de plan con programa elegido y por omisión.
- Destino inactivo, archivado, sin programa o con programa ajeno.
- Confirmación o rollback total del cambio de plan.
- Ausencia de dos abiertas, puntos transferidos y fijación heredada.
- Historia enlazada por `operation_id` y referencia de reemplazo.
- Cambio de programa que conserva la condición de placement.
- Conflicto por `lock_version` obsoleto para todas las mutaciones incorporadas.
- Actor, instante y motivo opcional preservados.

### Criterio de salida

Cancelar, cambiar de plan y cambiar de programa no dejan estados parciales,
respetan la historia temporal y rechazan escrituras administrativas obsoletas.

### Evidencia

Pendiente.

## Sesión 5 — Fijación, concurrencia y cierre integral

Estado: **No iniciada**

### Objetivo

Completar el placement administrativo, proteger el archivo de planes y validar
S1 como una sola capacidad lista para habilitarse.

### Alcance

- Fijar el placement en el programa actual o en otro programa del mismo plan.
- Cambiar el programa mientras el placement permanece fijado.
- Liberar la fijación sin cambiar inmediatamente el programa.
- Cerrar y abrir intervalos usando una única frontera temporal.
- Exponer desde Subscriptions hacia Plans una consulta tipada de existencia de
  suscripciones abiertas.
- Impedir el archivo de un plan con suscripciones `pending` o `active`.
- Aplicar el orden de locks definido en `04-data-model.md` para serializar
  archivo, solicitud, aprobación y cambio de plan.
- Completar rutas, Requests, Commands, UseCases, Resources y Controllers
  pendientes de S1.
- Completar permisos customer y administrativos, sus snapshots reproducibles y
  la colección Postman.
- Contrastar rutas propias y operativas con la colección Postman.
- Ejecutar el cierre documental y técnico de la entrega.

### Pruebas mínimas

- Fijar, cambiar fijación y liberar con programa válido o ajeno.
- Fronteras contiguas y una única historia abierta.
- Liberación que conserva el programa.
- Dos cambios concurrentes de placement sin solapamientos.
- Archivo concurrente con solicitud, aprobación y cambio de plan: nunca
  confirma un plan archivado con una suscripción abierta creada en la carrera.
- `lock_version` obsoleto en las mutaciones restantes.
- Recorridos completos customer y administrativos con RBAC.
- Formas de respuesta, filtros, paginación y ausencia según contrato HTTP.
- Suite contractual completa InMemory/PostgreSQL.
- Suite completa del servicio sobre PostgreSQL real.
- Validación de Postman como colección v2.1 importable.

### Criterio de salida

S1 satisface todos los criterios de aceptación de
`03-vertical-deliveries.md`, todas las defensas y carreras de
`04-data-model.md`, y existe evidencia verificable de implementación,
autorización, contrato HTTP y persistencia.

Solo entonces se actualizan este documento, el README de Subscriptions y el
índice general del roadmap para declarar S1 completada.

### Evidencia

Pendiente.

## Auditoría final recomendada

Después de completar la sesión 5 se recomienda una revisión separada, sin
añadir capacidades, que contraste:

- cada regla BDS aplicable con código y pruebas;
- cada criterio de aceptación de la entrega vertical;
- cada check, índice, lock, rollback y carrera del modelo de datos;
- las fronteras entre Plans, Programs y Subscriptions;
- las superficies, permisos, Postman y forma de salida HTTP;
- el alcance implementado contra la lista explícita de elementos fuera de S1.

Un hallazgo de esta auditoría reabre la sesión propietaria del defecto; no se
resuelve ampliando silenciosamente S1.

## Directiva reutilizable para las sesiones 1 a 4

Usar una petición explícita como la siguiente, sustituyendo el número:

> Implementa exclusivamente la sesión N definida en
> `docs/roadmap/subscriptions/05-s1-implementation.md`. Reconstruye primero el
> contexto leyendo todas las fuentes obligatorias aplicables que indica el
> documento. Verifica las dependencias y la evidencia de las sesiones
> anteriores. Respeta las restricciones globales, el alcance y las exclusiones.
> No avances a sesiones posteriores ni declares S1 completada. Finaliza
> únicamente cuando se cumpla el criterio de salida y estén ejecutadas las
> pruebas exigidas para esta sesión. Registra en el documento el estado y la
> evidencia verificable obtenida.

## Directiva reutilizable para la sesión 5

> Implementa exclusivamente la sesión 5 y ejecuta el cierre integral de S1
> definido en `docs/roadmap/subscriptions/05-s1-implementation.md`.
> Reconstruye primero el contexto desde sus fuentes obligatorias y verifica la
> evidencia real de las sesiones 1 a 4. No declares S1 completada si falta
> cualquier criterio global, carrera PostgreSQL, permiso, recorrido HTTP,
> sincronización Postman o evidencia documental. No añadas capacidades
> declaradas fuera de S1.
