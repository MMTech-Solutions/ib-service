# Subscriptions S1: modelo de datos

Estado: **Completado para S1**
Dependencias satisfechas: etapas 1 a 3; `Programs P2` y `Rules R2`
Entrega objetivo: S1 — Suscripciones y placement administrativo
Última revisión: 2026-09-15

## Propósito

Este documento fija el esquema PostgreSQL, las restricciones, los índices y la
estrategia de concurrencia necesarios para implementar S1. El esquema conserva
el ciclo completo de cada suscripción, el placement efectivo por intervalo y
la evidencia funcional de sus mutaciones sin introducir todavía tablas de
Progression, Rewards ni un registro transversal de acciones.

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `plans` | Incorpora la configuración vigente de aprobación de nuevas solicitudes. |
| `subscriptions` | Conserva solicitud, estado, plan, origen y relación de reemplazo. |
| `subscription_placements` | Conserva la historia temporal del programa y de la fijación. |
| `subscription_changes` | Registra de forma inmutable las mutaciones funcionales de una suscripción. |

Todas las claves primarias nuevas son UUIDv7. Las fechas e instantes se
persisten como `timestamp with time zone`. Las foreign keys locales usan
`restrict`; S1 no elimina físicamente suscripciones, placements ni historia.

## Extensión de `plans`

Se añade:

- `requires_approval boolean` no nullable con default `true`.

La migración asigna `true` a los planes existentes sin alterar su
`updated_at`, incrementar `lock_version` ni producir una acción administrativa
ficticia. Crear un plan sin enviar el campo conserva el default. Omitirlo en un
`PATCH` conserva el valor persistido. Una modificación solo afecta solicitudes
posteriores porque cada solicitud originada por el usuario guarda su propio
snapshot.

No se crea `plan_approval_requirement_changes`. La auditoría transversal de
cambios de configuración pertenece a una entrega futura.
`plan_operational_changes` continúa limitado a activación, desactivación y
archivo.

## `subscriptions`

### Columnas

- `id uuid` PK (UUIDv7)
- `external_user_id uuid` no nullable; referencia opaca a IAM, sin foreign key distribuida
- `plan_id uuid` no nullable, FK a `plans` con restrict
- `origin varchar(32)` no nullable: `user_application` o `admin_plan_change`
- `requires_approval boolean` nullable; snapshot aplicable únicamente a `user_application`
- `status varchar(16)` no nullable: `pending`, `active`, `rejected` o `ended`
- `activated_at timestamp with time zone` nullable
- `closed_at timestamp with time zone` nullable
- `replaces_subscription_id uuid` nullable, self-FK con restrict
- `lock_version bigint` no nullable, default `1`
- `created_at`, `updated_at` no nullable y con zona horaria

### Checks

- `lock_version > 0`.
- `origin = user_application` exige `requires_approval IS NOT NULL` y `replaces_subscription_id IS NULL`.
- `origin = admin_plan_change` exige `requires_approval IS NULL` y `replaces_subscription_id IS NOT NULL`.
- `pending` exige `activated_at IS NULL` y `closed_at IS NULL`.
- `active` exige `activated_at IS NOT NULL` y `closed_at IS NULL`.
- `rejected` exige `activated_at IS NULL` y `closed_at IS NOT NULL`.
- `ended` exige `activated_at IS NOT NULL` y `closed_at IS NOT NULL`.
- `activated_at`, cuando existe, no precede a `created_at`.
- `closed_at`, cuando existe, no precede a `created_at` ni a `activated_at` cuando esta última existe.
- `replaces_subscription_id <> id` cuando existe.

La transición inicial también se valida en el caso de uso: una
`user_application` con snapshot `true` nace `pending`; con snapshot `false`
nace `active`. El snapshot no cambia al aprobar, rechazar ni terminar la
suscripción.

### Unicidad e índices

- Índice único parcial por `external_user_id` donde `status IN ('pending', 'active')`: defensa persistente de una única suscripción abierta global.
- Índice único parcial por `replaces_subscription_id` cuando no es null: una suscripción solo puede ser reemplazada una vez.
- Índice `(external_user_id, created_at, id)` para historia administrativa y resolución determinista.
- Índice `(status, created_at, id)` para listados administrativos.
- Índice `(plan_id, status, created_at, id)` para filtros por plan.
- Índice parcial `(plan_id)` donde `status IN ('pending', 'active')` para la salvaguarda de archivo.

## `subscription_placements`

### Columnas

- `id uuid` PK (UUIDv7)
- `subscription_id uuid` no nullable, FK a `subscriptions` con restrict
- `program_id uuid` no nullable, FK a `programs` con restrict
- `is_fixed boolean` no nullable, default `false`
- `effective_from timestamp with time zone` no nullable
- `effective_until timestamp with time zone` nullable
- `created_at`, `updated_at` no nullable y con zona horaria

`effective_from` y `effective_until` describen la vigencia del placement, no
la caducidad de la suscripción. Cada intervalo usa la forma semiabierta
`[effective_from, effective_until)`: una actividad ocurrida exactamente en
`effective_until` pertenece al intervalo siguiente. `effective_until IS NULL`
identifica el placement vigente.

### Restricciones e índices

- Check `effective_until IS NULL OR effective_until >= effective_from`.
- Índice único parcial por `subscription_id` donde `effective_until IS NULL`: solo un placement vigente por suscripción.
- Índice `(subscription_id, effective_from, id)` para historia y resolución temporal determinista.
- Índice `(program_id, effective_from, id)` para referencias históricas del programa.

El programa debe pertenecer al mismo plan que la suscripción. PostgreSQL no
puede expresar esa regla ni la matriz entre estado y placement con un check de
una sola fila; el repository las garantiza bajo lock de la suscripción:

- `pending` y `rejected` no tienen placements;
- `active` tiene exactamente un intervalo abierto;
- `ended` conserva sus intervalos históricos y no tiene ninguno abierto;
- los intervalos de una suscripción no se solapan;
- el programa de cada intervalo pertenece al plan de la suscripción.

No se incorpora `btree_gist`. Toda escritura de placement bloquea primero la
suscripción padre, revalida su estado e historia, cierra el intervalo vigente y
abre el siguiente usando una misma frontera temporal. El índice único parcial
es la defensa final para el intervalo abierto. El placement actual no se
duplica en `subscriptions`.

## `subscription_changes`

La tabla es un ledger inmutable del ciclo funcional de Subscriptions; no
sustituye al futuro sistema transversal de registro de acciones.

### Columnas

- `id uuid` PK (UUIDv7)
- `operation_id uuid` no nullable (UUIDv7), compartido por los efectos de una misma decisión
- `subscription_id uuid` no nullable, FK a `subscriptions` con restrict
- `action varchar(32)` no nullable: `request`, `approve`, `reject`, `cancel`, `change_plan_out`, `change_plan_in`, `change_program`, `fix_placement` o `release_placement`
- `actor_kind varchar(16)` no nullable: `iam` o `system`
- `actor_external_user_id uuid` nullable
- `reason varchar(500)` nullable
- `previous_status varchar(16)` nullable
- `next_status varchar(16)` nullable
- `previous_program_id uuid` nullable, FK a `programs` con restrict
- `next_program_id uuid` nullable, FK a `programs` con restrict
- `previous_is_fixed boolean` nullable
- `next_is_fixed boolean` nullable
- `occurred_at timestamp with time zone` no nullable

### Checks e índices

- `actor_kind = iam` exige `actor_external_user_id IS NOT NULL`; `actor_kind = system` exige que sea null.
- `reject` exige `reason` no null y no vacío después de `trim`; para las demás acciones el motivo es opcional.
- Cada snapshot de placement exige conjuntamente programa y `is_fixed`: ambos son null o ambos tienen valor.
- Los estados presentes pertenecen al catálogo de estados de Subscription.
- Índice `(subscription_id, occurred_at, id)` para el detalle histórico.
- Índice `(operation_id)` para reconstruir decisiones con varios efectos.

Los snapshots nullable permiten representar la ausencia antes de crear una
suscripción o antes de asignar placement. La aplicación valida la matriz de
campos propia de cada acción y siempre escribe el cambio en la misma
transacción que el estado o placement al que describe.

Un cambio de plan escribe `change_plan_out` para la suscripción anterior y
`change_plan_in` para la nueva, con el mismo `operation_id`. La nueva
suscripción referencia a la anterior mediante `replaces_subscription_id`.

## Límites transaccionales

- **Solicitud:** bloquea el plan, revalida que esté activo y no archivado,
  captura `requires_approval` y crea `pending` o `active`. La activación
  automática resuelve y crea el primer placement en la misma transacción.
- **Aprobación:** bloquea el plan solicitado y después la suscripción; revalida
  plan, estado y programa. El paso `pending → active`, el placement inicial y
  la historia son atómicos. Un fallo deja intacta la solicitud.
- **Rechazo:** bloquea la suscripción y persiste `pending → rejected`, cierre e
  historia motivada en una transacción.
- **Cancelación:** bloquea la suscripción, cambia `active → ended`, cierra el
  placement vigente y registra la historia en una transacción.
- **Placement:** cambiar programa, fijar o liberar bloquea la suscripción;
  cierra el intervalo vigente, abre el siguiente y registra la historia con
  una única frontera temporal.
- **Cambio de plan:** obtiene primero los identificadores sin lock, bloquea los
  planes origen y destino en orden ascendente de UUID, bloquea la suscripción
  origen y revalida lo leído. Después termina y cierra la anterior, crea la
  nueva `active` sin snapshot de aprobación, establece su placement inicial y
  escribe ambas entradas de historia. Cualquier fallo revierte todo.
- **Archivo de plan:** bloquea el plan y consulta mediante el puerto público de
  Subscriptions la existencia de `pending` o `active`; si existe alguna,
  rechaza el archivo.

Solicitud, aprobación y entrada a un plan por cambio administrativo bloquean
el plan destino antes de crear o activar la suscripción. Así serializan con el
archivo. Cuando una operación necesita más de un plan, el orden ascendente de
UUID evita locks cruzados. Las mutaciones de una suscripción comparan e
incrementan `lock_version`; un valor obsoleto produce conflicto de
concurrencia. El índice único parcial por usuario sigue siendo la última
defensa ante solicitudes simultáneas.

## Contratos derivados

La implementación de S1 publica únicamente puertos con consumidor real:

- Plans expone a Subscriptions un contexto especializado de elegibilidad que
  incluye disponibilidad, archivo y `requires_approval`.
- Programs expone a Subscriptions la validación de pertenencia y la resolución
  del primer programa del ladder.
- Subscriptions expone a Plans una consulta de existencia de suscripciones
  abiertas por plan para proteger el archivo.

La resolución de placement por instante pertenece inicialmente al contrato del
repository de Subscriptions. No se publica todavía como puerto inter-feature;
se promoverá a `Contracts/Data/V1` junto con el primer consumidor real de
Progression o Rewards.

Ningún contrato expone modelos Eloquent, repositories internos ni tipos de
otro feature. Los puertos públicos existentes mantienen su V1 sin cambios
incompatibles.

## Estrategia de pruebas

La implementación deberá compartir contract tests entre los repositories en
memoria y PostgreSQL, y añadir pruebas específicas de constraints y carreras
reales de PostgreSQL.

### Estados y restricciones

- Matriz válida e inválida de `status`, `activated_at` y `closed_at`.
- Origen, snapshot de aprobación y reemplazo coherentes.
- Una sola suscripción abierta por `external_user_id`.
- Una suscripción reemplazada como máximo una vez.
- Rechazo con motivo obligatorio; motivo opcional en las demás acciones.
- Foreign keys restrictivas e imposibilidad de eliminación física accidental.

### Placement temporal

- `pending` y `rejected` sin placement; `active` con exactamente uno vigente;
  `ended` únicamente con historia cerrada.
- Un único intervalo abierto y ausencia de solapamientos bajo el lock padre.
- Programa perteneciente al plan de la suscripción.
- Cambio, fijación y liberación con fronteras contiguas.
- Una actividad en `effective_until` resuelve el intervalo siguiente.
- La condición fijada se conserva al cambiar de programa y no se transporta a
  una suscripción nueva.

### Transacciones y concurrencia

- Solicitud automática y `pending` según el snapshot de aprobación.
- Aprobación que revalida el mismo plan y revierte sin alterar la `pending` si
  el plan o programa dejan de ser elegibles.
- Solicitudes simultáneas del mismo usuario: solo una confirma.
- Archivo concurrente con solicitud, aprobación o cambio de plan: nunca
  confirma un plan archivado con una suscripción abierta creada en la carrera.
- `lock_version` obsoleto rechazado en toda mutación administrativa.
- Cambio de plan completamente confirmado o completamente revertido, sin dos
  abiertas, puntos transferidos ni fijación heredada.
- Dos cambios de placement concurrentes dejan una única historia no solapada.

## Límites de esta etapa

- Migraciones, modelos Eloquent, repositories, casos de uso, endpoints y
  payloads; se implementan en la etapa siguiente.
- Tablas de puntos, contribuciones, runs, rewards o settlement.
- Contratos públicos de Progression y Rewards y eventos Kafka.
- Tabla genérica de configuración y frecuencia de ejecución de Progression;
  inicialmente esa frecuencia vivirá en archivos de configuración y su
  persistencia se diseñará con Progression.
- Sistema transversal de registro de acciones.
- Versionado futuro de planes y políticas generales de actividad tardía,
  reversas o reanudación de runs.

## Criterios de salida

- Columnas, tipos, checks, foreign keys e índices de S1 están definidos.
- La diferencia entre vigencia del placement y ciclo de la suscripción es
  explícita.
- Las invariantes no expresables con constraints de fila tienen lock,
  transacción y contract test definidos.
- La exclusión global y la carrera con archivo tienen una defensa persistente
  y un orden de locks concreto.
- El alcance permite abrir la implementación completa de S1 sin decisiones de
  persistencia pendientes.
