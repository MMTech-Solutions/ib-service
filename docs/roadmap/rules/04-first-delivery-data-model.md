# Rules: modelo de datos de la primera entrega

Estado: **Completado para R1**
Dependencia: decisiones de R1 cerradas
Entrega objetivo: R1 — Catálogo administrativo de reglas y versiones

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `rules` | Identidad dentro del plan, slug, estrategia y concurrencia. |
| `rule_versions` | Configuración versionada, estado y concurrencia de draft. |

### `rules`

- `id uuid` PK (UUIDv7)
- `plan_id uuid` FK a `plans` con restrict, no nullable
- `name varchar(120)` no nullable
- `slug varchar(64)` no nullable
- `description text` nullable
- `strategy_type varchar(64)` no nullable
- `lock_version bigint` default `1`
- `created_at`, `updated_at` con zona horaria

Unicidad `(plan_id, name)`. Unicidad `(plan_id, slug)`.
Índice de listado: `(plan_id, name)`.
Check: `lock_version > 0`.
No existe `normalized_name`, `is_current` ni `active_version_id`.

### `rule_versions`

- `id uuid` PK (UUIDv7)
- `rule_id uuid` FK a `rules` con restrict
- `version_number integer` no nullable
- `status varchar(16)` no nullable (`draft` o `published`)
- `schema_version integer` no nullable
- `configuration jsonb` no nullable
- `published_at timestamptz` nullable
- `lock_version bigint` default `1`
- `created_at`, `updated_at` con zona horaria

Unicidad `(rule_id, version_number)`.
Índice de listado: `(rule_id, version_number)`.
Check: `lock_version > 0`. Check: `version_number >= 1`.
Check: `schema_version >= 1`.
Check: `status` in (`draft`, `published`).
Check: si `status = published` entonces `published_at` no es nulo.

## Concurrencia

Las mutaciones administrativas de una regla comparan `lock_version`.
Crear una versión bloquea la regla, asigna el siguiente `version_number` y
persiste el draft. Editar o publicar una versión draft compara su propio
`lock_version`. Una versión publicada no se actualiza.

## Criterios de salida

Columnas, restricciones, índices y estrategia de concurrencia definidos para
implementar migraciones reversibles.
