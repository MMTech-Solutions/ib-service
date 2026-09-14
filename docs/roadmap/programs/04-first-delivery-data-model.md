# Programs: modelo de datos de la primera entrega

Estado: **Completado para PR1**
Dependencia: decisiones de PR1 cerradas
Entrega objetivo: PR1 — Catálogo administrativo de programas

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `programs` | Identidad dentro del plan, datos administrativos, posición relativa y concurrencia. |
| `program_module_selections` | Selección programa-módulo, acotada a vinculaciones del plan. |

### `programs`

- `id uuid` PK (UUIDv7)
- `plan_id uuid` FK a `plans` con restrict, no nullable
- `code varchar(64)` no nullable
- `name varchar(120)` no nullable
- `description text` nullable
- `position integer` no nullable
- `lock_version bigint` default `1`
- `created_at`, `updated_at` con zona horaria

Unicidad `(plan_id, code)`. Unicidad `(plan_id, position)`.
Índice de listado: `(plan_id, position)`.
Check: `lock_version > 0`. Check: `position >= 1`.

PR1 no define `is_active` ni `deleted_at` en el programa. El archivo del plan
propietario gobierna la mutabilidad; no elimina filas de programas.

### `program_module_selections`

- `id uuid` PK
- `program_id uuid` FK a `programs` con restrict
- `module_id uuid` FK a `modules` con restrict
- `created_at` con zona horaria
- unicidad `(program_id, module_id)`
- índice por `module_id` para consultas de referencia futuras

La pertenencia del módulo a las vinculaciones del plan se valida en
aplicación vía `ResolvePlanContextPort`, no con una FK compuesta a
`plan_module_bindings`.

## Concurrencia y orden

Las mutaciones administrativas de un programa comparan `lock_version`.
Reordenar los programas de un plan actualiza de forma atómica todas las
posiciones afectadas para dejar una secuencia contigua desde `1`. Si la
colección concurrente cambió, la operación falla de forma controlada.

## Criterios de salida

Columnas, restricciones, índices y estrategia de concurrencia definidos para
implementar migraciones reversibles.
