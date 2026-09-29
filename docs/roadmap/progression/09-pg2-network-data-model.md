# PG2: modelo de datos de distribución, runs y placement

Estado: **Completado (diseño)**  
Última revisión: 2026-09-29

## Propósito

Definir la persistencia necesaria para que una actividad se distribuya una sola
vez y los runs consuman resultados locales, sin una consulta de red posterior.

## Persistencia propuesta

| Artefacto | Datos mínimos | Restricciones |
| --- | --- | --- |
| Distribución de actividad | Identidad de actividad, referido fuente, instante de resolución y resultado final. | Una distribución final por actividad; vacío permitido solo como respuesta satisfactoria. |
| Destinatario de distribución | Distribución, IB beneficiario y nivel. | Único por distribución, beneficiario y nivel. |
| Evaluación/contribución | Destinatario, contexto en `occurred_at`, configuración aplicada y puntos. | Idempotencia que reutiliza el destinatario congelado. |
| Run | Plan, inicio y fin de ventana, estado y cierre. | Único por plan y ventana. |
| Resultado de run | Run, suscripción, puntos, programa objetivo y outcome. | Único por run y suscripción; solo los fallidos se reintentan. |
| Aplicación de placement | Resultado `completed`, outcome de aplicación e instante. | PK/FK única por resultado; `outcome` es `applied`, `unchanged`, `fixed` o `not_active`. |

## Concurrencia y recuperación

- La llamada remota ocurre fuera de la transacción local.
- La transacción que finaliza una distribución persiste su cabecera y todos sus
  destinatarios; una colisión devuelve la distribución canónica.
- Si el proceso falla antes de finalizar esa transacción, no existe snapshot y
  el reintento puede consultar IAM de nuevo.
- Si ya existe snapshot, ningún reintento, contribución ni run consulta IAM.
- PG2.5 bloquea la fila del resultado `completed` con `FOR UPDATE`, verifica que
  no exista aplicación y confirma en la misma transacción el placement, su
  auditoría de Subscriptions y la aplicación. Un fallo revierte los tres y deja
  el resultado disponible para reintento.
- `skipped` y `failed` no crean aplicaciones de placement. Los outcomes
  `unchanged`, `fixed` y `not_active` consumen definitivamente el resultado sin
  cambiar el placement.

## Índices administrativos

La implementación prevé consulta por actividad fuente, referido, beneficiario,
instante de resolución, plan, ventana, suscripción y estado de resultado.

## Próximo paso

Preparar la operación productiva de Progression tras el cierre de PG2.
