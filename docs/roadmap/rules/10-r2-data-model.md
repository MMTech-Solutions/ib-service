# Rules R2: modelo de datos

Estado: **Completado para R2**
Dependencia: decisiones de dominio R2 cerradas
Entrega objetivo: R2 — Asignaciones históricas

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `rule_assignments` | Historial de asignaciones de versión a programa y módulo. |

### `rule_assignments`

- `id uuid` PK (UUIDv7)
- `rule_id uuid` FK a `rules` con restrict, no nullable
- `rule_version_id uuid` FK a `rule_versions` con restrict, no nullable
- `program_id uuid` FK a `programs` con restrict, no nullable
- `module_id uuid` FK a `modules` con restrict, no nullable
- `scope_type varchar(16)` no nullable, default `'all'`
- `starts_at timestamptz` no nullable
- `ends_at timestamptz` nullable
- `lock_version bigint` default `1`
- `created_at`, `updated_at` con zona horaria

Checks (PostgreSQL):

- `lock_version > 0`
- `scope_type = 'all'`
- `ends_at IS NULL OR ends_at >= starts_at`

Índices:

- listado por regla: `(rule_id, starts_at DESC, id DESC)`
- filtros: `(rule_id, program_id)`, `(rule_id, module_id)`
- unicidad activa parcial: `UNIQUE (rule_id, program_id, module_id) WHERE ends_at IS NULL`

La monotonía histórica (una sola abierta) se refuerza también en aplicación
e InMemory.

No existen columnas de instrumentos ni de overrides de configuración.

## Concurrencia

Las mutaciones de una asignación activa comparan `lock_version`. Reemplazar
y retirar incrementan el token de la fila que se cierra. La fila nueva nace
con `lock_version = 1`.

## Criterios de salida

Columnas, restricciones, índices y estrategia de concurrencia definidos para
una migración reversible.
