# Programs P2: modelo de datos

Estado: **Completado para P2**
Dependencia: decisiones de dominio P2 cerradas
Entrega objetivo: P2 — Ladder vivo de umbrales

## Tablas

Solo se extiende `programs`. No existen tablas de versiones ni snapshots.

### Extensión de `programs`

- `entry_threshold integer` no nullable
- Check: `entry_threshold >= 0`

Backfill de filas existentes: `entry_threshold = position - 1` antes de
imponer `NOT NULL`. Produce un ladder válido sin fingir umbrales comerciales;
aún no hay suscripciones ni progresión.

La monotonía estricta respecto a `position` dentro de `plan_id` se valida en
aplicación al crear, editar umbral o reordenar.

`program_module_selections` no cambia.

## Concurrencia

Las mutaciones administrativas siguen usando `lock_version` de `programs`.
El reorder actualiza posiciones y umbrales de toda la colección en una
transacción; si algún token no coincide, la operación falla de forma
controlada.

## Criterios de salida

Columna, restricción, backfill y estrategia de concurrencia definidos para
una migración reversible.
