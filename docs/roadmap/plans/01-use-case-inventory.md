# Plans: inventario de casos de uso

Estado: **Completado para P1**
Última revisión: 2026-09-14

## Propósito

Este inventario nombra intenciones de negocio y resultados observables. Los
nombres descriptivos no obligan a una clase o ruta homónima.

## Actores

- **Administrador de IB:** crea y mantiene el catálogo de planes, selecciona
  módulos y controla su disponibilidad.
- **Feature Modules:** expone el catálogo seleccionable y notifica
  desactivaciones reales de módulo.
- **Proceso interno:** desactiva planes que quedaron sin módulos operativos y
  reconcilia divergencias.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Catálogo | Crear un plan | Existe un plan inactivo con código, nombre y descripción opcional. | P1 | Aceptado |
| Catálogo | Consultar planes | Se listan planes no archivados con estado, módulos y token de concurrencia. | P1 | Aceptado |
| Catálogo | Consultar un plan | Se obtiene identidad, estado y vinculaciones configuradas. | P1 | Aceptado |
| Catálogo | Editar un plan | Cambian nombre, descripción y, si se envía, el conjunto completo de módulos. | P1 | Aceptado |
| Catálogo | Activar un plan | El plan pasa a activo si tiene al menos un módulo operativo. | P1 | Aceptado |
| Catálogo | Desactivar un plan | El plan pasa a inactivo de forma auditable. | P1 | Aceptado |
| Catálogo | Archivar un plan inactivo | El plan deja de listarse; conserva código y vinculaciones; no se restaura. | P1 | Aceptado |
| Vinculación | Habilitar módulos de un plan | Solo se añaden módulos existentes y operativos. | P1 | Aceptado |
| Vinculación | Retirar módulos de un plan | El reemplazo atómico persiste el conjunto; un plan activo no queda vacío. | P1 | Aceptado |
| Consumo | Resolver módulos para un plan | `Plans` recibe objetos V1 o excepciones públicas de `Modules`. | P1 | Aceptado |
| Operación | Desactivar planes sin módulos operativos | Tras apagar un módulo, los planes afectados quedan inactivos. | P1 | Aceptado |
| Operación | Reconciliar planes divergentes | Una repetición no duplica auditoría ni reactiva planes. | P1 | Aceptado |
| Publicación | Publicar una versión del plan | Las suscripciones existentes reciben una política de aplicación. | Posterior | Diferido: pendiente de BDS |
| Suscripción | Suscribir un usuario | Queda fuera de P1. | Posterior | Diferido |

## Decisiones cerradas

1. P1 es administrativo; no publica ni versiona el plan.
2. El plan nace inactivo y puede crearse sin módulos.
3. Activar exige al menos un módulo operativo; un módulo pausado cuenta.
4. `PATCH` reemplaza atómicamente `module_ids` cuando el campo llega; si se
   omite, conserva vinculaciones.
5. Las vinculaciones existentes sobreviven a la desactivación posterior del
   módulo; las nuevas no pueden crearse contra un módulo inactivo.
6. El archivo es lógico, irreversible en P1 y reserva el código.
7. La desactivación automática es eventual y no revierte el apagado del módulo.
8. Reactivar un módulo no reactiva planes.

## Criterios de salida

- Cada intención de P1 fue aceptada o movida explícitamente.
- El primer puerto público de `Modules` queda determinado por este inventario.
- El alcance permite modelar agregados y transacciones de P1.
