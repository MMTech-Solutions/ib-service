# Rules R2: inventario de casos de uso

Estado: **Completado para R2**
Última revisión: 2026-09-15
Dependencia: [`06-second-delivery-planning.md`](06-second-delivery-planning.md);
BDS v0.5 con `BR-RULE-010`–`015`

## Propósito

Inventariar las intenciones de la segunda entrega: asignaciones históricas
de versiones publicadas a programa y módulo. No adelanta evaluación ni
Rewards.

## Actores

- **Administrador de IB:** crea, consulta, reemplaza y retira asignaciones.
- **Feature Plans:** contexto del plan no archivado.
- **Feature Programs:** contexto del programa y módulos seleccionados.
- **Feature Rules/Catalog:** identidad de la regla y versiones publicadas.

El usuario IB, Progression y Rewards no son actores de R2.

## Inventario R2

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Asignación | Crear una asignación | Existe una asignación activa con versión publicada, programa, módulo y scope `all`. | R2 | Aceptado |
| Asignación | Consultar asignaciones de una regla | Se obtiene el historial paginado, con filtros por programa, módulo o vigencia activa. | R2 | Aceptado |
| Asignación | Consultar una asignación | Se obtiene su identidad, versión, vigencia y scope. | R2 | Aceptado |
| Asignación | Reemplazar la versión | La vigente se cierra y nace otra activa con la nueva versión. | R2 | Aceptado |
| Asignación | Retirar una asignación | La vigente queda con `ends_at` y deja de estar activa. | R2 | Aceptado |
| Validación | Rechazar draft u otra regla | Solo versiones publicadas de la misma regla. | R2 | Aceptado |
| Validación | Rechazar programa o módulo inválidos | Programa del plan; módulo habilitado y seleccionado. | R2 | Aceptado |
| Validación | Rechazar duplicado activo | Una sola activa por regla/programa/módulo. | R2 | Aceptado |
| Validación | Rechazar plan archivado | Las mutaciones fallan; list/show siguen disponibles. | R2 | Aceptado |
| Evaluación | Evaluar actividad | — | — | Diferido: Rewards |
| Scope | Scope instrumental explícito | — | — | Diferido: Modules M2 |

## Decisiones cerradas

1. R2 es administrativo: no evalúa ni publica contratos de Rules.
2. Crear activa de inmediato (`BR-RULE-012`).
3. Reemplazo y retiro son operaciones explícitas (`BR-RULE-014`).
4. Scope fijo `all` (`BR-RULE-015`).
5. Unicidad activa por regla/programa/módulo (`BR-RULE-013`).
6. El módulo debe estar seleccionado por el programa (`BR-RULE-010`).
7. R2 origina `ResolveProgramContextPort` en Programs.

## Criterios de salida

- Cada intención de R2 está aceptada o diferida de forma explícita.
- El alcance permite modelar el agregado y la superficie HTTP.
