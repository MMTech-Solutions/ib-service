# Modules: modelo de datos de la primera entrega

Estado: **Bloqueado**  
Dependencia: cierre de la etapa 2  
Entrega objetivo: M1 — Catálogo y control operativo

## Propósito

Este documento alojará el diseño persistente de M1 después de acordar sus
agregados. No deben crearse migraciones a partir de las hipótesis actuales.

## Necesidades de persistencia conocidas

- Identidad estable y datos descriptivos del módulo.
- Disponibilidad mediante `is_active`.
- Capacidades implementadas y su semántica configurable.
- `processing_status`, inicialmente `running` o `paused`.
- Actor, instante y detalle de cada cambio operativo.
- Protección contra actualizaciones concurrentes cuando corresponda.

## Tablas candidatas, no aprobadas

| Hipótesis | Posible responsabilidad |
| --- | --- |
| `modules` | Identidad, datos permitidos, `is_active` y `processing_status`. |
| `module_capabilities` | Capacidades implementadas por módulo. |
| `module_operational_control_history` | Historial inmutable de cambios. |

La separación, los nombres y las relaciones pueden cambiar al definir el
agregado. Las tablas de snapshots de programas quedan fuera de M1.

## Decisiones obligatorias antes de migrar

- Tipo de identificador y código natural único.
- Tipo del código estable y restricciones de inmutabilidad.
- Valor inicial de `is_active` para módulos nuevos.
- Cardinalidad y unicidad de capacidades.
- Restricción PostgreSQL para valores de `processing_status`.
- Estrategia de concurrencia optimista o bloqueo.
- Protección de referencias durante `modules:sync --prune` y retención de
  auditoría.
- Índices requeridos por las consultas de M1.
- Necesidad de outbox para eventos de cambio.

## Criterios de salida

- Diagrama o descripción relacional aprobada.
- Columnas, tipos PostgreSQL, nullabilidad y defaults documentados.
- Claves, checks, unicidades, índices y acciones de claves foráneas definidos.
- Mapeo claro entre agregados y repositorios.
- Casos de concurrencia y rollback cubiertos por el diseño.
- Migraciones reversibles listas para implementarse.
