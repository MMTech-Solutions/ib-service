# Programs P2: inventario de casos de uso

Estado: **Completado para P2 (Listo)**
Última revisión: 2026-09-14
Dependencia: [`06-second-delivery-planning.md`](06-second-delivery-planning.md);
BDS v0.9 con `BR-PROGRAM-010`–`014`

## Propósito

Inventariar las intenciones de la segunda entrega: publicación versionada de
la configuración del programa, snapshots de módulo y umbrales de entrada del
ladder. No adelanta suscripciones, Rules, Progression ni Rewards.

## Actores

- **Administrador de IB:** publica y republica configuraciones, define
  umbrales de entrada y consulta la versión vigente o el historial.
- **Feature Plans:** contexto del plan no archivado y módulos habilitados
  (ya resuelto en PR1 vía puerto público).
- **Feature Modules:** fuente de semántica y capacidades para el snapshot al
  publicar. Disponibilidad y `processing_status` actuales no entran en el
  snapshot (`BR-MODULE-008`, `BR-MODULE-009`).

El usuario IB, las suscripciones y la evaluación de runs no son actores de P2.

## Inventario P2

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Umbral | Definir umbral de entrada de un programa | El programa tiene un entero ≥ 0 editable en borrador administrativo. | P2 | Aceptado |
| Umbral | Validar ladder del plan | Los umbrales son estrictamente crecientes con la posición; se rechaza cualquier violación. | P2 | Aceptado |
| Publicación | Publicar configuración de un programa | Queda una versión inmutable con selecciones, umbral congelado y snapshots de módulo. | P2 | Aceptado |
| Publicación | Rechazar publicar sin módulos | No se publica si no hay al menos un módulo seleccionado y habilitado por el plan. | P2 | Aceptado |
| Versionado | Republicar tras cambio funcional | Se crea una nueva versión; la anterior permanece inmutable e consultable. | P2 | Aceptado |
| Snapshot | Congelar semántica y capacidades | Cada módulo seleccionado aporta un snapshot inmutable en la versión publicada. | P2 | Aceptado |
| Consulta | Obtener configuración vigente | Se obtiene la última versión publicada del programa, si existe. | P2 | Aceptado |
| Consulta | Listar historial de versiones | Se enumeran las versiones publicadas del programa en orden cronológico. | P2 | Aceptado |
| Configuración | Configurar actividad o scope | Compatible con capacidades del módulo. | Posterior | Diferido: `BR-MODULE-004` fuera de P2 |
| Suscripción | Asignar placement inicial | Usuario en un programa del plan. | Posterior | Diferido: feature Subscriptions |
| Recompensas | Asignar reglas a programa y módulo | Versión de regla aplicable. | Posterior | Diferido: feature Rules |
| Progresión | Evaluar run de progresión | Sube, baja o permanece. | Posterior | Diferido: feature Progression; usa `BR-PROGRAM-013` |

## Decisiones cerradas

1. P2 publica y versiona la configuración del programa; no introduce ciclo de
   vida `archived` propio del programa.
2. Umbral = un entero no negativo por programa (`BR-PROGRAM-010`). Se descarta
   min-max explícito y `max` nulo en el último nivel.
3. Los umbrales del plan son estrictamente crecientes con la posición
   (`BR-PROGRAM-011`); el intervalo efectivo es contiguo y sin solapes
   (`BR-PROGRAM-012`).
4. Publicar exige ≥1 módulo seleccionado habilitado por el plan
   (`BR-PROGRAM-006`).
5. La versión publicada congela selecciones, umbral y snapshots; un cambio
   funcional crea otra versión (`BR-PROGRAM-014`, `BR-MODULE-007`).
6. Disponibilidad y procesamiento del módulo no van en el snapshot
   (`BR-MODULE-009`).
7. `BR-MODULE-004` (actividad/scope) queda fuera de P2.
8. P2 no publica puertos hacia Rules ni Subscriptions; el consumidor real
   originará el contrato mínimo cuando exista.
9. El umbral vive en el borrador administrativo del programa y se copia
   inmutable a la versión al publicar.

## Criterios de salida

- Cada intención de P2 está aceptada o diferida de forma explícita.
- El alcance permite modelar agregados y transacciones de publicación,
  snapshot y ladder.
- Las reglas de umbral citadas existen en el BDS vigente.
