# Programs P2: agregados, estados y transacciones

Estado: **Completado para P2 (Listo)**
Dependencia satisfecha: [`07-p2-use-case-inventory.md`](07-p2-use-case-inventory.md)

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Program` | Identidad PR1 más umbral de entrada editable en borrador administrativo. | Raíz administrativa; no sustituye a la versión publicada. |
| `ProgramModuleSelection` | Hijo de selecciones (PR1). | Sigue en el límite del programa borrador. |
| `ProgramConfigurationVersion` | Versión publicada e inmutable: selecciones congeladas, umbral congelado y metadatos de publicación. | Agregado de publicación de P2. |
| `ModuleConfigSnapshot` | Copia inmutable de semántica y capacidades del módulo en el momento de publicar. | Hijo de la versión publicada; uno por módulo seleccionado. |

El ladder de umbrales es una invariante del **conjunto de programas del plan**
(orden + umbrales estrictamente crecientes). Validar el ladder es una
operación plan-scoped al editar umbrales, reordenar o publicar.

## Estados confirmados

P2 no introduce `draft` / `published` / `archived` como estados del programa
como entidad. Distingue dos planos:

| Plano | Condición |
| --- | --- |
| Borrador administrativo | Programa de PR1: editable mientras el plan no esté archivado. Puede tener umbral y selecciones aún no publicadas. |
| Configuración publicada | Cero o más versiones inmutables. La vigente es la de mayor secuencia/tiempo de publicación. |

Un programa puede existir sin ninguna versión publicada. Tras la primera
publicación, el borrador puede divergir hasta la siguiente publicación.

## Invariantes

- Umbral de entrada: entero ≥ 0 por programa (`BR-PROGRAM-010`).
- Umbrales del plan estrictamente crecientes con la posición
  (`BR-PROGRAM-011`).
- Intervalos efectivos contiguos sin solapes (`BR-PROGRAM-012`).
- Publicar exige ≥1 módulo seleccionado habilitado por el plan
  (`BR-PROGRAM-006`).
- Versión publicada inmutable; cambio funcional → nueva versión
  (`BR-PROGRAM-014`).
- Snapshot congela semántica y capacidades; no incluye disponibilidad ni
  `processing_status` (`BR-MODULE-007`–`009`).
- Relación directa al registro del módulo se conserva para consultar el
  estado operativo actual (`BR-MODULE-008`).
- Sin configuración de actividad/scope en P2 (`BR-MODULE-004` diferido).
- Solo plan no archivado admite mutaciones administrativas y publicación
  (`BR-PROGRAM-009`).

## Transacciones

- **Definir/editar umbral** en el borrador: atómico con el programa;
  debe preservar o validar la monotonía del ladder del plan (junto con
  reorden si aplica).
- **Publicar**: atómico. Congela selecciones vigentes, umbral de entrada,
  crea `ProgramConfigurationVersion` y un `ModuleConfigSnapshot` por módulo
  seleccionado leyendo el catálogo de Modules. Rechaza si no hay módulos o
  si el ladder del plan es inválido.
- **Republicar**: crea una nueva versión; no muta versiones anteriores.
- **Consultar vigente / historial**: solo lectura; no abre transacción de
  escritura.
- La desactivación del plan o del módulo no reescribe versiones publicadas;
  el control operativo actual se consulta por la relación directa al
  catálogo.

## Fronteras

| Dirección | Necesidad P2 | Decisión |
| --- | --- | --- |
| Programs → Plans | Plan no archivado y módulos habilitados | Reutiliza `ResolvePlanContextPort` de PR1. |
| Programs → Modules | Semántica y capacidades para snapshot | Puerto mínimo de lectura de módulo/capacidades originado por la publicación; no se inventa contrato hacia Rules o Subscriptions. |
| Rules / Subscriptions → Programs | Lectura de configuración publicada | Diferido hasta el primer consumidor real. |

## Fuera de P2

- Suscripciones, placement ejecutado, anclaje administrativo.
- Actividad, scope de instrumentos, ponderaciones y runs.
- Reglas económicas y rewards.
- Ciclo de vida archivado del programa.
- Nombres definitivos de tablas, rutas o clases.

## Resultado esperado

Agregados, estados, invariantes y límites transaccionales de P2 acordados
para entregas verticales y modelo de datos.
