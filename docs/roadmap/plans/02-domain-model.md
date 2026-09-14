# Plans: agregados, estados y transacciones

Estado: **Completado para P1**
Dependencia satisfecha: `01-use-case-inventory.md` completado

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Plan` | Identidad, datos administrativos, disponibilidad y conjunto de vinculaciones. | Único agregado de P1. |
| `PlanModuleBinding` | Hijo que referencia un módulo del catálogo. | Pertenece al límite transaccional del plan. |
| `PlanOperationalChange` | Evidencia inmutable de activación, desactivación y archivo. | Hijo inmutable; no es evento Kafka. |

## Estados confirmados

Un plan nuevo nace inactivo, sin archivo y con `lock_version` técnico 1.

| `is_active` | Archivado | Condición |
| --- | --- | --- |
| `false` | no | Inactivo y editable; puede no tener vinculaciones. |
| `true` | no | Activo; debe conservar al menos una vinculación. |
| `false` | sí | Archivado; excluido del catálogo vigente. |

Activar y desactivar son idempotentes: un estado ya alcanzado no escribe
historial ni incrementa `lock_version`. El archivo no es idempotente hacia un
segundo archivo; un plan archivado no se restaura en P1.

## Invariantes

- El código es inmutable, único e irreutilizable.
- Activar exige al menos un módulo operativo resuelto vía el puerto de `Modules`.
- Un plan activo no admite un conjunto vacío de vinculaciones.
- Una vinculación nueva exige módulo existente y operativo; una ya persistida
  se conserva aunque el módulo se desactive.
- Un módulo pausado permanece operativo.
- El archivo exige plan inactivo y conserva vinculaciones.
- La desactivación automática usa actor de sistema y causalidad (`event_id`,
  módulo originador y actor IAM iniciador cuando exista).

## Transacciones

- Crear, actualizar, activar, desactivar y archivar son atómicos con su
  historial operativo cuando el estado cambia.
- El reemplazo de vinculaciones ocurre en la misma transacción que el `PATCH`.
- La desactivación de un módulo y la de los planes afectados no comparten
  transacción. `Modules` confirma primero; `Plans` converge después.
- La concurrencia de escrituras administrativas usa `lock_version`. La
  desactivación automática usa la versión vigente leída al aplicar el cambio.

## Frontera con Modules

`Plans` depende de un puerto de entrada de `Modules` para resolver módulos por
identidad y exigir selectabilidad. `Modules` no conoce `Plans`. `Plans` publica
un puerto para saber si un módulo tiene referencias históricas, incluidas las
de planes archivados.

## Resultado esperado

Agregados, estados, invariantes y límites transaccionales de P1 acordados.
