# Programs P2: agregados, estados y transacciones

Estado: **Completado para P2**
Dependencia satisfecha: [`07-p2-use-case-inventory.md`](07-p2-use-case-inventory.md)

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Program` | Identidad PR1 más umbral de entrada vigente. | Única raíz; no hay versión publicada. |
| `ProgramModuleSelection` | Hijo de selecciones (PR1). | Sigue en el límite del programa. |

El ladder es una invariante del **conjunto de programas del plan** (orden +
umbrales estrictamente crecientes). Validarlo es una operación plan-scoped al
crear, editar umbral o reordenar.

## Estados confirmados

P2 no introduce `draft`, `published` ni `archived` en el programa. La
editabilidad sigue derivada del plan propietario (PR1): no archivado admite
mutaciones; archivado las rechaza.

Un programa nuevo nace con código, nombre, descripción opcional, posición,
`entry_threshold` ≥ 0, selecciones posiblemente vacías y token de
concurrencia 1.

## Invariantes

- Umbral de entrada: entero ≥ 0 por programa (`BR-PROGRAM-010`).
- El umbral es mutable; no se congela (`BR-PROGRAM-014`).
- Umbrales del plan estrictamente crecientes con la posición
  (`BR-PROGRAM-011`).
- Intervalos efectivos contiguos sin solapes (`BR-PROGRAM-012`).
- Crear o editar no exige módulos seleccionados (`BR-PROGRAM-005`,
  `BR-PROGRAM-006`).
- Solo un plan no archivado admite mutaciones (`BR-PROGRAM-009`).

## Transacciones

- **Crear:** atómico. Asigna la última posición. El umbral debe ser estrictamente
  mayor que el del último programa existente, o ≥ 0 si es el primero.
- **Editar umbral o datos administrativos:** atómico con `lock_version`. Antes
  de persistir se valida el ladder completo proyectado.
- **Reordenar y reconfigurar ladder:** atómico sobre la colección completa.
  Cada ítem aporta identificador, umbral y token de concurrencia. El resultado
  deja posiciones contiguas desde 1 y umbrales estrictamente crecientes.
- No existen transacciones de publicación ni de snapshot.

## Fronteras

P2 reutiliza `ResolvePlanContextPort`. No origina puerto hacia Modules.

## Fuera de P2

- Suscripciones, placement ejecutado, anclaje.
- Actividad, scope, ponderaciones y runs.
- Versionado de programas o planes.

## Resultado esperado

Agregados, invariantes y límites transaccionales del ladder vivo acordados.
