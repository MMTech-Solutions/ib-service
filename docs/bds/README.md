# Business Domain Specifications

Los BDS son la fuente de verdad del dominio IB. Expresan el comportamiento esperado con independencia del framework y de la infraestructura.

## Especificaciones

| Documento | Alcance |
| --- | --- |
| [`plans-and-subscriptions.bds.md`](plans-and-subscriptions.bds.md) | Jerarquía Plan → Programa, módulos habilitados, suscripción y placement. |
| [`progression.bds.md`](progression.bds.md) | Conversión de actividad heterogénea a puntos y evaluación periódica del crecimiento. |
| [`rewards.bds.md`](rewards.bds.md) | Reglas de pago reutilizables, asignaciones, reward ledger y CPA. |

## Convenciones

- Cada regla estable tiene un identificador único y permanente.
- Los términos se reutilizan con el mismo significado entre documentos.
- Las decisiones aún no cerradas aparecen en una sección de pendientes.
- Un BDS no contiene clases, tablas, rutas HTTP, topics ni detalles de despliegue.
- Las reglas técnicas para redactar y mantener estos documentos están en [`../rules/bds.md`](../rules/bds.md).
