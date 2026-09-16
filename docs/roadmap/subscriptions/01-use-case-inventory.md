# Subscriptions: inventario de casos de uso

Estado: **Completado para S1**
Última revisión: 2026-09-15

## Propósito

Inventariar intenciones y resultados observables para suscripciones, placement
y fijación administrativa. Los nombres son descriptivos: no obligan todavía a
crear una clase, endpoint, contrato o transacción homónimos.

## Actores

- **Administrador de IB:** aprueba o rechaza solicitudes, cancela o cambia la
  suscripción de plan, selecciona o modifica el placement, administra su
  fijación y consulta todo el historial.
- **Usuario IB:** solicita adhesión a un plan y consulta únicamente su
  suscripción abierta. No gestiona la solicitud después de crearla ni accede al
  historial.
- **Feature Plans:** provee la condición activa y no archivada del plan.
- **Feature Programs:** provee el ladder y valida que el programa pertenezca al
  plan de la suscripción.
- **Progression:** consumidor posterior que mueve placements no fijados.
- **Rewards:** consumidor posterior del programa vigente o histórico sin quedar
  suspendido por una fijación.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Solicitud | Solicitar adhesión a un plan | Nace la única suscripción abierta del usuario para un plan activo y no archivado; el usuario no elige programa. | S1 | Aceptado |
| Aprobación | Activar sin revisión | Si el plan no exige aprobación, la solicitud nace `active` con el primer programa; si no existe, no se crea. | S1 | Aceptado |
| Aprobación | Aprobar una solicitud | Administración revalida el plan y activa la `pending` con un programa existente elegido o con el primero por omisión; si no puede resolverlo, conserva `pending`. | S1 | Aceptado |
| Aprobación | Rechazar una solicitud | La solicitud pasa a `rejected` con motivo y no puede reabrirse. | S1 | Aceptado |
| Consulta | Consultar la suscripción propia | El usuario obtiene únicamente su registro `pending` o `active`. | S1 | Aceptado |
| Consulta | Consultar suscripciones administrativamente | Administración consulta todos los estados y el historial. | S1 | Aceptado |
| Plan | Cambiar al usuario de plan | La anterior queda `ended` y nace la nueva activa en una decisión indivisible, sin transferir puntos. | S1 | Aceptado |
| Suscripción | Cancelar la suscripción | Administración cambia la activa a `ended` y no crea reemplazo. | S1 | Aceptado |
| Placement | Cambiar administrativamente de programa | Cambia el placement dentro del mismo plan sin recrear la suscripción. | S1 | Aceptado |
| Fijación | Fijar el placement | El programa seleccionado permanece efectivo y Progression deja de ejecutar runs para la suscripción. | S1 | Aceptado |
| Fijación | Cambiar el programa fijado | Otro programa del mismo plan pasa a ser el placement efectivo sin liberar la fijación. | S1 | Aceptado |
| Fijación | Retirar la fijación | El placement no cambia de inmediato; el siguiente run ordinario puede modificarlo. | S1 | Aceptado |
| Progresión | Recibir actividad durante la fijación | La actividad se excluye definitivamente del progreso y no se recupera al liberar. | Progression | Aceptado; implementación posterior |
| Progresión | Evaluar después de liberar | El siguiente run usa solo actividad elegible ocurrida después de la liberación. | Progression | Aceptado; implementación posterior |
| Rewards | Evaluar actividad durante la fijación | Rewards opera con el programa fijado y las reglas vigentes. | Rewards | Aceptado; implementación posterior |
| Historia | Procesar actividad tardía | Se usa la suscripción, plan y placement vigentes cuando ocurrió la actividad. | Progression / Rewards | Aceptado; política temporal posterior |
| Historia | Completar rewards después de terminar | Los rewards originados continúan hasta settlement con su suscripción histórica. | Rewards | Aceptado; implementación posterior |

## Decisiones cerradas

1. Solo puede existir una suscripción abierta global por usuario
   (`BR-SUBSCRIPTION-003`).
2. El usuario solicita únicamente el plan activo y no archivado; el requisito
   de aprobación queda fijado al solicitar (`BR-PLAN-017`,
   `BR-SUBSCRIPTION-004`, `005`).
3. Administración decide el placement al aprobar; la activación automática y
   cualquier omisión administrativa usan el primer programa del ladder.
4. Cambio de plan, reingreso, reinicio de puntos y cancelación siguen
   `BR-SUBSCRIPTION-006`–`010`.
5. La ocurrencia de la actividad fija el contexto histórico; terminar no
   cancela rewards originados (`BR-SUBSCRIPTION-011`, `012`).
6. Solo administración puede fijar, cambiar o liberar una fijación. Cada acción
   deja actor e instante; el motivo es opcional y se conserva cuando se
   proporciona (`BR-SUBSCRIPTION-014`).
7. Una fijación impide runs y excluye su actividad de Progression, sin afectar
   Rewards (`BR-SUBSCRIPTION-015`, `016`).
8. Liberar no mueve inmediatamente el placement; el próximo run solo utiliza
   actividad elegible posterior (`BR-SUBSCRIPTION-017`).
9. Aprobar, rechazar, cancelar y toda gestión posterior son administrativas;
   el rechazo exige motivo y nunca se reabre (`BR-SUBSCRIPTION-018`–`020`).
10. El usuario ve solo su suscripción abierta; administración ve el historial.
    Desactivar el plan suspende Progression y Rewards sin terminar registros, y
    archivarlo exige no tener suscripciones abiertas
    (`BR-SUBSCRIPTION-021`–`023`).

## Decisiones pendientes

- Tratamiento de suscripciones existentes ante versiones futuras del plan.
- Ventanas y correcciones temporales de actividad tardía en los consumidores.

## Criterios de salida

- Cada intención de S1 está aceptada y los consumidores posteriores están
  diferenciados.
- Las reglas confirmadas tienen trazabilidad al BDS.
- Actores, audiencias y consultas de S1 están acordados.
- El alcance de S1 permite comenzar el modelo de dominio.
- No se adelantan nombres de implementación ni decisiones de persistencia.
