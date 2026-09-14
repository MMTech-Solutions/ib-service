# Plans: modelo de datos de la primera entrega

Estado: **Completado para P1**
Dependencia: decisiones de P1 cerradas
Entrega objetivo: P1 — Catálogo administrativo de planes

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `plans` | Identidad, datos administrativos, disponibilidad, concurrencia y archivo. |
| `plan_module_bindings` | Vinculación plan-módulo, conservada tras el archivo. |
| `plan_operational_changes` | Historial inmutable de activación, desactivación y archivo. |

### `plans`

- `id uuid` PK (UUIDv7)
- `code varchar(64)` único y no nullable, también tras el archivo
- `name varchar(120)` no nullable
- `description text` nullable
- `is_active boolean` default `false`
- `lock_version bigint` default `1`
- `created_at`, `updated_at` con zona horaria
- `deleted_at` nullable con zona horaria

Índice de listado: `(deleted_at, is_active, code)`. Check: `lock_version > 0`.
Un plan archivado no puede estar activo: check `deleted_at IS NULL OR is_active = false`.

### `plan_module_bindings`

- `id uuid` PK
- `plan_id uuid` FK a `plans` con restrict
- `module_id uuid` FK a `modules` con restrict
- `created_at` con zona horaria
- unicidad `(plan_id, module_id)`
- índice por `module_id` para referencias y reconciliación

### `plan_operational_changes`

- `id uuid` PK
- `plan_id uuid` FK a `plans` con restrict
- `action varchar(16)`: `activate`, `deactivate`, `archive`
- `actor_kind varchar(16)`: `iam` o `system`
- `actor_iam_id uuid` nullable
- `reason varchar(500)`
- `previous_is_active boolean`
- `next_is_active boolean`
- `cause_event_id uuid` nullable
- `cause_module_id uuid` nullable
- `initiating_actor_iam_id uuid` nullable
- `occurred_at` con zona horaria
- índice `(plan_id, occurred_at)`
- check: si `actor_kind = iam` entonces `actor_iam_id` no es null; si
  `actor_kind = system` entonces `actor_iam_id` es null

## Concurrencia y referencias

Las mutaciones administrativas comparan `lock_version`. El archivo no elimina
filas hijas. `Modules` consulta las vinculaciones, incluidas las de planes
archivados, antes de un prune.

## Criterios de salida

Columnas, restricciones, índices y estrategia de concurrencia definidos para
implementar migraciones reversibles.
