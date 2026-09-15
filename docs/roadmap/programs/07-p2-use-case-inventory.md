# Programs P2: inventario de casos de uso

Estado: **Completado para P2**
Última revisión: 2026-09-14
Dependencia: [`06-second-delivery-planning.md`](06-second-delivery-planning.md);
BDS v0.10 con `BR-PROGRAM-010`–`014`

## Propósito

Inventariar las intenciones de la segunda entrega: umbral de entrada vivo y
ladder estrictamente creciente. No adelanta publicación, snapshots,
suscripciones, Rules, Progression ni Rewards.

## Actores

- **Administrador de IB:** define y reconfigura umbrales de entrada junto con
  el orden de los programas de un plan.
- **Feature Plans:** contexto del plan no archivado y módulos habilitados
  (PR1 vía puerto público).

El usuario IB, las suscripciones y la evaluación de runs no son actores de P2.

## Inventario P2

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Umbral | Crear un programa con umbral de entrada | El programa nace con un entero ≥ 0 y queda al final del ladder si este permanece creciente. | P2 | Aceptado |
| Umbral | Editar el umbral de un programa | El valor vigente cambia; el ladder del plan permanece estrictamente creciente. | P2 | Aceptado |
| Umbral | Validar ladder del plan | Se rechaza umbral negativo, duplicado o no creciente respecto a la posición. | P2 | Aceptado |
| Orden | Reconfigurar orden y umbrales juntos | Una mutación atómica deja posiciones y umbrales coherentes. | P2 | Aceptado |
| Consulta | Listar o mostrar un programa | La respuesta incluye el `entry_threshold` vigente. | P2 | Aceptado |
| Publicación | Publicar o versionar configuración | — | — | Rechazado: programas no se versionan |
| Snapshot | Congelar umbral o semántica de módulo | — | — | Rechazado: evaluación lee valores vigentes |
| Configuración | Configurar actividad o scope | Compatible con capacidades del módulo. | Posterior | Diferido: `BR-MODULE-004` |
| Suscripción | Asignar placement inicial | Usuario en un programa del plan. | Posterior | Diferido: Subscriptions |
| Recompensas | Asignar reglas a programa y módulo | Versión de regla aplicable. | Posterior | Diferido: Rules |
| Progresión | Evaluar run de progresión | Sube, baja o permanece. | Posterior | Diferido: Progression; usa `BR-PROGRAM-013` |

## Decisiones cerradas

1. P2 no publica ni versiona el programa.
2. Umbral = un entero no negativo por programa (`BR-PROGRAM-010`), mutable
   (`BR-PROGRAM-014`).
3. Los umbrales del plan son estrictamente crecientes con la posición
   (`BR-PROGRAM-011`); el intervalo efectivo es contiguo y sin solapes
   (`BR-PROGRAM-012`).
4. Un programa puede existir sin módulos; el umbral no exige selecciones
   (`BR-PROGRAM-005`, `BR-PROGRAM-006`).
5. `BR-MODULE-004` (actividad/scope) y ponderaciones por símbolo quedan fuera
   de P2.
6. P2 no origina puertos hacia Modules, Rules ni Subscriptions.

## Criterios de salida

- Cada intención de P2 está aceptada, rechazada o diferida de forma explícita.
- El alcance permite modelar el ladder vivo sin agregados de publicación.
