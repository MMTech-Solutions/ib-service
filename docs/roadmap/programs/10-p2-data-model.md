# Programs P2: modelo de datos

Estado: **Listo** (pendiente de migraciones)
Dependencia: decisiones de dominio P2 cerradas
Entrega objetivo: P2 — Publicación, snapshots y umbrales

Los nombres de tabla y columna son tentativos de roadmap; no sustituyen al
BDS ni obligan la migración final.

## Tablas

| Tabla | Responsabilidad |
| --- | --- |
| `programs` | Extiende PR1 con `entry_threshold` editable en borrador. |
| `program_module_selections` | Sin cambio de responsabilidad respecto a PR1. |
| `program_configuration_versions` | Versión publicada inmutable por programa. |
| `program_module_config_snapshots` | Snapshot de semántica/capacidades por módulo en una versión. |

### Extensión de `programs`

- `entry_threshold integer` no nullable, default `0`
- Check: `entry_threshold >= 0`

La monotonía estricta respecto a `position` dentro de `plan_id` se valida en
aplicación al editar umbral, reordenar o publicar (no solo con un check de
fila aislada).

### `program_configuration_versions`

- `id uuid` PK
- `program_id uuid` FK a `programs` con restrict
- `version_number integer` no nullable (secuencia monótona por programa)
- `entry_threshold integer` no nullable (copia congelada)
- `published_at` con zona horaria
- `published_by` referencia opaca al actor administrativo (sin FK distribuida)
- `created_at` con zona horaria

Unicidad `(program_id, version_number)`.
Índice de vigente: `(program_id, version_number DESC)` o equivalente.
Check: `version_number > 0`. Check: `entry_threshold >= 0`.

Filas inmutables tras insert: no hay `updated_at` de mutación de negocio.

### `program_module_config_snapshots`

- `id uuid` PK
- `program_configuration_version_id uuid` FK a versiones con restrict
- `module_id uuid` FK a `modules` con restrict (relación directa vigente)
- `module_code varchar` congelado
- `module_name varchar` congelado (o campos de semántica acordados)
- `capabilities jsonb` (o representación tipada equivalente) congelado
- `created_at` con zona horaria

Unicidad `(program_configuration_version_id, module_id)`.
Índice por `module_id` para auditoría y referencias.

No persiste `is_active` ni `processing_status` dentro del snapshot.

### Selecciones congeladas

Las selecciones de la versión pueden materializarse:

- como filas hijas `program_configuration_version_modules (version_id, module_id)`, o
- derivarse del conjunto de snapshots de esa versión.

Decisión de implementación: preferir el conjunto de snapshots como fuente de
los módulos congelados si cada selección publicada exige snapshot; evita
duplicar la misma membresía.

## Concurrencia

- El borrador administrativo sigue usando `lock_version` de `programs` (PR1).
- Publicar toma un snapshot consistente del borrador bajo la misma
  concurrencia optimista del programa.
- Las versiones publicadas no se actualizan; la “vigente” es la de mayor
  `version_number`.

## Criterios de salida

Columnas, restricciones, índices e inmutabilidad definidos para implementar
migraciones reversibles cuando arranque el código de P2.
