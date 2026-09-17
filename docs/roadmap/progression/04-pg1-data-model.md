# Progression PG1: modelo de datos

Estado: **Completado para PG1**
Dependencias: modelo de dominio y entregas verticales PG1 completados; la
implementación continúa condicionada a Modules M3 para la actividad normalizada
Última revisión: 2026-09-17

## Propósito

Definir la persistencia PostgreSQL de la evaluación durable y de su contribución
opcional. El esquema conserva los snapshots necesarios para explicar una decisión
histórica, impide duplicados bajo concurrencia y no incorpora runs ni cambios de
placement, que pertenecen a PG2.

## Tablas

### `progression_activity_evaluations`

Una fila por decisión durable sobre una actividad fuente y un beneficiario.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` PK | Identidad de la evaluación. |
| `module_id` | `uuid` FK a `modules` | Módulo que originó la actividad; `RESTRICT`. |
| `source_activity_id` | `varchar(191)` | Identificador estable y opaco entregado por Modules M3. |
| `beneficiary_external_user_id` | `uuid` | Usuario al que se atribuye la actividad; no es una FK distribuida. |
| `subscription_id` | `uuid` FK nullable a `subscriptions` | Suscripción resuelta al ocurrir; obligatoria si la evaluación es aceptada. |
| `plan_id` | `uuid` FK nullable a `plans` | Plan resuelto al ocurrir; obligatoria si la evaluación es aceptada. |
| `program_id` | `uuid` FK nullable a `programs` | Programa del placement resuelto al ocurrir; obligatorio si la evaluación es aceptada. |
| `occurred_at` | `timestamptz` | Instante de ocurrencia informado por la actividad. |
| `window_starts_at`, `window_ends_at` | `timestamptz` | Ventana UTC derivada del plan; obligatoria cuando existe plan. |
| `metric_code`, `unit_code` | `varchar(64)` | Métrica y unidad nativas. |
| `instrument_reference` | `varchar(191)` nullable | Referencia instrumental entregada por Modules cuando la actividad la tenga. |
| `quantity` | `numeric(24,8)` | Cantidad nativa; no admite una escala mayor de ocho. |
| `status` | `varchar(16)` | Catálogo cerrado: `accepted` o `excluded`. |
| `exclusion_reason` | `varchar(64)` nullable | Obligatorio solo para `excluded`; catálogo BDS cerrado. |
| `evaluated_at` | `timestamptz` | Instante de la decisión durable. |
| `created_at`, `updated_at` | `timestamptz` | Auditoría técnica; la fila no se modifica después de crearse. |

Las referencias a plan, programa, suscripción y módulo usan `RESTRICT` para no
perder evidencia histórica. La actividad fuente permanece en Modules: no se
crea una FK ni una réplica de su payload. El contrato M3 debe aportar
`source_activity_id`, métrica, unidad, cantidad, ocurrencia e instrumento
opcional antes de implementar esta tabla.

### `progression_contributions`

Existe exclusivamente para una evaluación aceptada y guarda el cálculo aplicado.

| Columna | Tipo | Regla |
| --- | --- | --- |
| `id` | `uuid` PK | Identidad de la contribución. |
| `evaluation_id` | `uuid` FK único a `progression_activity_evaluations` | Relación uno a uno; `RESTRICT`. |
| `rule_id`, `rule_version_id`, `rule_assignment_id` | `uuid` FK | Regla, versión y asignación históricas aplicadas; `RESTRICT`. |
| `strategy_type` | `varchar(64)` | Debe ser `points_per_quantity_unit` en PG1. |
| `scope_type` | `varchar(16)` | Debe ser `all` en PG1. |
| `weight` | `numeric(24,8)` | Ponderación histórica aplicada. |
| `points` | `numeric(24,8)` | Resultado exacto de `quantity × weight`. |
| `created_at`, `updated_at` | `timestamptz` | Auditoría técnica; fila inmutable. |

No se persiste `exclusion_explanation`: se genera de forma determinista desde
`exclusion_reason` y el contexto de la evaluación para evitar duplicar un texto
traducible o permitir motivos libres.

## Restricciones PostgreSQL

- `status IN ('accepted', 'excluded')`.
- `exclusion_reason` pertenece al catálogo inicial del BDS cuando no es nulo.
- Una evaluación `accepted` tiene `exclusion_reason IS NULL`; una `excluded`
  tiene un motivo no nulo.
- Para `accepted`, `subscription_id`, `plan_id`, `program_id` y la ventana son
  obligatorios. La aplicación y los contract tests validan la contribución en
  la misma transacción; PostgreSQL refuerza su unicidad con `evaluation_id UNIQUE`.
- `window_ends_at > window_starts_at` cuando ambos valores existen.
- `quantity`, `weight` y `points` admiten como máximo ocho decimales. No se
  redondean entradas fuera de esa escala.
- `strategy_type = 'points_per_quantity_unit'` y `scope_type = 'all'`.
- No hay `lock_version`: ambos registros son append-only e inmutables.

## Índices e idempotencia

- Índice único: `(module_id, source_activity_id, beneficiary_external_user_id)`
  en `progression_activity_evaluations`. Es la autoridad de idempotencia de la
  evaluación, incluso si cambia una regla antes de un reintento.
- Índices administrativos: `(subscription_id, occurred_at DESC, id DESC)`,
  `(plan_id, occurred_at DESC, id DESC)` y
  `(status, exclusion_reason, evaluated_at DESC, id DESC)`.
- Índice en `progression_contributions(rule_version_id)` para auditoría de la
  configuración histórica aplicada.

El flujo de escritura abre una transacción, intenta crear la evaluación y, si es
aceptada, crea su contribución. Una colisión del índice único se trata como
reintento: se lee y devuelve la evaluación canónica sin recalcularla. Todo error
posterior revierte la transacción; no puede persistir una contribución huérfana
ni una evaluación aceptada incompleta.

## Concurrencia y límites temporales

Antes de la inserción se resuelve el contexto en `occurred_at`. Justo antes de
confirmar la evaluación se vuelve a verificar la condición operativa del plan:
si está inactivo, la evaluación se persiste como `excluded` con
`plan_inactive`; no se deja una cola para la reactivación. La actividad ocurrida
antes de la reactivación no es elegible para una nueva evaluación.

PG1 no persiste estado de cierre de ventanas, runs ni una referencia al run que
consumió la contribución. Esa extensión, incluido `late_activity`, se diseña en
PG2 sin reescribir las evaluaciones o contribuciones existentes.

## Migración y pruebas contractuales

- Una migración reversible crea primero `progression_activity_evaluations` y
  después `progression_contributions`; el `down` las elimina en orden inverso.
- No hay backfill: PG1 no tenía evaluaciones ni contribuciones previas.
- El contrato de repositorio se ejecuta contra memoria y PostgreSQL, con casos
  para aceptación, cada exclusión, atomicidad e idempotencia concurrente.
- Las pruebas PostgreSQL verifican `CHECK`, FKs, unicidad y el mapeo de
  violaciones a errores de dominio controlados.

## Fuera de PG1

- Tablas de runs, resultados, cierres de ventana o placement automático.
- Reversas, compensaciones y conversión FX.
- Scope instrumental distinto de `all`.
- Payloads de actividad duplicados, FKs distribuidas o texto libre para motivos.

## Criterios de salida

- Tablas, columnas, tipos, FKs, checks e índices necesarios para PG1 definidos.
- Idempotencia y transacciones cubren reintentos y concurrencia sin alterar
  resultados históricos.
- El esquema permite explicar evaluaciones aceptadas o excluidas sin recalcular.
- PG2 queda aislada como una ampliación aditiva de runs y placement.