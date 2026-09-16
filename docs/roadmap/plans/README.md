# Roadmap del feature Plans

Estado: **P1 completado; `requires_approval` y puerto de suscripción añadidos (Subscriptions S1 sesión 1)**
Dependencia satisfecha: `Modules M1` completado
Última revisión: 2026-09-15

## Posición en la secuencia

`Plans` es el primer consumidor inter-feature de `Modules`. El puerto público
mínimo de `Modules` se originó en los casos de uso de P1.

## Primera entrega: P1

Catálogo administrativo de planes: identidad, módulos, activación,
desactivación, archivo lógico y convergencia eventual cuando un plan activo
queda sin módulos operativos.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para P1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | P1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para P1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | P1 completado |

## Relación con Programs y Subscriptions

`Programs PR1` cerró el catálogo administrativo. `Programs P2` cierra el
ladder vivo de umbrales:
[`06-second-delivery-planning.md`](../programs/06-second-delivery-planning.md).

`Subscriptions S1` es el siguiente consumidor real de Plans y requiere que el
plan determine si las nuevas solicitudes necesitan aprobación. El valor
vigente se fija al solicitar y sus cambios solo afectan solicitudes futuras
(`BR-PLAN-017`). La sesión 1 de
[`05-s1-implementation.md`](../subscriptions/05-s1-implementation.md) añadió
`requires_approval` y el puerto `ResolvePlanSubscriptionContextPort` sin
alterar de forma incompatible el V1 usado por Programs y Rules.

## Próximo paso

Ninguno dentro de Plans para la sesión 1 de Subscriptions. Continuar el
resto de S1 desde el roadmap de Subscriptions.
