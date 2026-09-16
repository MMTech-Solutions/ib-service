# Roadmap del feature Plans

Estado: **P1 completado; extensión requerida por Subscriptions S1**
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
(`BR-PLAN-017`). La entrega vertical y el contrato especializado quedaron
definidos en
[`03-vertical-deliveries.md`](../subscriptions/03-vertical-deliveries.md); su
modelo de datos quedó cerrado en
[`04-data-model.md`](../subscriptions/04-data-model.md) y la implementación
permanece pendiente.

## Próximo paso

Implementar con Subscriptions S1 la persistencia y compatibilidad de
`requires_approval` y el contrato público especializado que lo expone al
consumidor.
