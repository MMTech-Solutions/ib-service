# Roadmap del feature Plans

Estado: **Completado**
Dependencia satisfecha: `Modules M1` completado
Última revisión: 2026-09-14

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

## Relación con Programs

`Programs` puede comenzar su frontera: todo programa pertenecerá a un plan y
solo configurará módulos habilitados por ese plan.

## Próximo paso

Inventariar Programs a partir de los módulos habilitados por un plan.
