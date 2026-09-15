# Rules: inventario de casos de uso

Estado: **Completado para R1**
Última revisión: 2026-09-15

## Propósito

Este inventario nombra intenciones de negocio y resultados observables. Los
nombres descriptivos no obligan a una clase, ruta o transacción homónima.

La primera entrega es un catálogo administrativo de reglas dentro de un plan,
con versiones draft y publicadas. No adelanta asignaciones ni evaluación.

## Actores

- **Administrador de IB:** crea y mantiene las reglas de un plan y publica
  versiones de configuración.
- **Feature Plans:** provee el contexto del plan propietario y si está
  archivado.

El usuario IB, las suscripciones, Programs y Rewards no son actores de R1.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Catálogo | Crear una regla bajo un plan | Existe una regla con nombre único, slug estable y tipo de estrategia. | R1 | Aceptado |
| Catálogo | Consultar reglas de un plan | Se obtiene la colección de reglas del plan. | R1 | Aceptado |
| Catálogo | Consultar una regla | Se obtiene su identidad, slug, estrategia y versiones. | R1 | Aceptado |
| Catálogo | Editar una regla | Cambian nombre o descripción; el slug permanece. | R1 | Aceptado |
| Catálogo | Rechazar nombre o slug duplicado | La creación o el rename se rechaza como conflicto. | R1 | Aceptado |
| Versiones | Crear una versión draft | Existe una versión editable con configuración validada. | R1 | Aceptado |
| Versiones | Consultar versiones de una regla | Se obtiene la colección de versiones. | R1 | Aceptado |
| Versiones | Editar una versión draft | Cambia la configuración si sigue siendo válida. | R1 | Aceptado |
| Versiones | Publicar una versión | La versión queda inmutable y disponible para asignaciones futuras. | R1 | Aceptado |
| Versiones | Rechazar edición de una publicada | La mutación se rechaza; coexisten varias publicadas. | R1 | Aceptado |
| Consumo | Obtener contexto del plan propietario | Rules opera con el plan, no con el catálogo global. | R1 | Aceptado |
| Asignación | Asignar una versión a programa y módulo | Una versión publicada aplica en ese contexto. | Posterior | Diferido: R2 |
| Evaluación | Evaluar actividad o adquisiciones | Se produce una recompensa o un resultado no elegible. | Posterior | Diferido: Rewards |

## Decisiones cerradas

1. R1 es administrativo: no asigna, no evalúa y no publica contratos públicos.
2. El nombre es obligatorio y único dentro del plan (`BR-RULE-008`).
3. El slug se genera al crear, es único en el plan y no se regenera al
   renombrar. Una colisión de slug o nombre es conflicto, no un sufijo.
4. El tipo de estrategia queda fijado en la regla. R1 admite
   `points_per_quantity_unit`.
5. Una versión draft es editable; una publicada es inmutable (`BR-RULE-002`).
6. Publicar no selecciona una versión “actual” ni cambia asignaciones
   (`BR-RULE-009`).
7. Un plan no archivado admite mutaciones; un plan archivado no.
8. La configuración se valida al guardar y al publicar (`BR-RULE-007`).

## Fuera de R1

- Asignaciones, vigencia, scope e instrumentos.
- CPA, volumen, PnL y pipeline de evaluación.
- Puertos hacia Programs o desde Rules.

## Criterios de salida

- Los actores de R1 están enumerados y no se mezclan con evaluadores.
- Cada intención de R1 está aceptada o diferida de forma explícita.
- Las decisiones cerradas no se presentan como hipótesis.
