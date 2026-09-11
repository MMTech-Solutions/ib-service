# Modules: modelo de datos de la primera entrega

Estado: **Completado para M1a**
Dependencia: decisiones de M1a cerradas
Entrega objetivo: M1a — Sincronización y listado

## Propósito

Este documento registra el diseño persistente aprobado e implementado para
M1a. M1b ampliará el modelo cuando se cierre el historial operativo.

## Necesidades de persistencia conocidas

- Identidad estable y datos descriptivos del módulo.
- Disponibilidad mediante `is_active`.
- Capacidades implementadas y su semántica configurable.
- `processing_status`, inicialmente `running` o `paused`.
- Actor, instante y detalle de cada cambio operativo.
- Protección contra actualizaciones concurrentes cuando corresponda.

## Tablas aprobadas para M1a

| Hipótesis | Posible responsabilidad |
| --- | --- |
| `modules` | UUIDv7, código único, datos administrativos, estados, `lock_version` técnico y timestamps UTC. |
| `module_capabilities` | UUIDv7, módulo, código único por módulo, datos gobernados por código, `is_active` y timestamps UTC. |

`processing_status` admite únicamente `running` y `paused`. Los módulos nuevos
nacen activos y en ejecución. `lock_version` soporta concurrencia optimista y
no constituye versionado funcional. El historial operativo y los snapshots de
programas quedan fuera de M1a.

### Columnas

- `modules`: `id uuid` PK; `code varchar(64)` único y no nullable;
  `name varchar(120)`; `description text` nullable; `is_active boolean` default
  `true`; `processing_status varchar(16)` default `running`; `lock_version
  bigint` default `1`; `created_at` y `updated_at` con zona horaria.
- `module_capabilities`: `id uuid` PK; `module_id uuid` FK con cascade al
  eliminar un huérfano autorizado; `code varchar(64)`; `name varchar(120)`;
  `description text` nullable; `is_active boolean` default `true`; timestamps
  con zona horaria.

## Restricciones confirmadas

- UUIDv7 identifica módulos y capacidades; `modules.code` es único e inmutable.
- Las capacidades son únicas por `(module_id, code)` y conservan `is_active`.
- PostgreSQL aplica checks para `processing_status` y `lock_version > 0`.
- `modules:sync --prune` consulta un guard de referencias antes de borrar.
- El índice compuesto de módulos cubre estados y orden por código; las
  capacidades se indexan por módulo, actividad y código.
- M1a no necesita outbox ni tabla de historial operativo.

## Criterios de salida

- Diagrama o descripción relacional aprobada.
- Columnas, tipos PostgreSQL, nullabilidad y defaults documentados.
- Claves, checks, unicidades, índices y acciones de claves foráneas definidos.
- Mapeo claro entre agregados y repositorios.
- Casos de concurrencia y rollback cubiertos por el diseño.
- Migraciones reversibles listas para implementarse.
