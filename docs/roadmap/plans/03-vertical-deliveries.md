# Plans: entregas verticales

Estado: **P1 completado**
Dependencia: etapas 1 y 2

## P1 — Catálogo administrativo de planes

Entrega vertical de HTTP a persistencia:

- identidad mínima del plan y selección de módulos;
- primer puerto público de `Modules` y `Contracts/Data/V1`;
- excepciones públicas de módulo inexistente o inactivo;
- control operativo del plan y archivo lógico;
- desactivación eventual de planes sin módulos operativos, con reintento y
  reconciliación.

Criterio de aceptación: un operador con `ib.plans.manage` crea un plan
inactivo, le asigna módulos operativos, lo activa, lo consulta y lo archiva
solo inactivo. Un módulo inactivo se rechaza en vinculaciones nuevas. Si
`Modules` desactiva el último módulo operativo de un plan activo, el plan
termina inactivo; reactivar el módulo no lo reactiva. `modules:sync --prune`
no borra un módulo referenciado por un plan archivado.

## Fuera de P1

- Publicación y versionado del plan.
- Programs, suscripciones y placements.
- Restauración de planes archivados.
- Configuración de fuente de actividad en la vinculación.
- Eventos Kafka de integración.

## Relación con Modules

P1 origina `ResolveModulesPort`, `ModuleSummaryData` y el evento contractual
`ModuleDeactivated`. Esas piezas se diseñan y verifican en este vertical.

## Criterios de salida

Cada recorrido de P1 funciona de extremo a extremo y tiene evidencia en
[`05-first-delivery-implementation.md`](05-first-delivery-implementation.md).
