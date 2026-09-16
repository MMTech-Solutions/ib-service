# Subscriptions S1: entregas verticales

Estado: **Completado para S1**
Dependencias satisfechas: etapas 1 y 2; `Programs P2` y `Rules R2`
Última revisión: 2026-09-15

## Principio de corte

S1 es una única entrega funcional de HTTP a persistencia. Puede implementarse
internamente en cinco bloques, pero las solicitudes no se habilitan hasta que
todos estén completos. En particular, no se expone el estado `pending` sin que
administración pueda consultarlo, aprobarlo o rechazarlo.

## S1 — Suscripciones y placement administrativo

### Bloque 1 — Contextos de plan y programa

- `requires_approval` se administra mediante las superficies existentes de
  creación y actualización de Plans.
- Los planes existentes reciben `requires_approval = true`. En una creación,
  omitir el valor equivale a `true`; en una actualización, omitirlo conserva el
  valor vigente.
- Cambiar la configuración solo afecta solicitudes posteriores. Cada solicitud
  conserva el valor leído al originarse.
- Plans publica un contrato especializado para resolver disponibilidad,
  archivo y requisito de aprobación sin cambiar de forma incompatible el
  contrato V1 que ya consumen Programs y Rules.
- Programs publica un contrato especializado para validar un programa del plan
  o resolver el primero por posición cuando no se selecciona uno, sin cambiar
  de forma incompatible su contrato V1 actual.

### Bloque 2 — Núcleo de Subscription

- Un mismo agregado conserva solicitud, suscripción, placement e historia de
  sus estados `pending`, `active`, `rejected` y `ended`.
- Como máximo existe una suscripción abierta global por usuario. Las
  terminales permanecen inmutables y nunca se reutilizan ni eliminan.
- Una suscripción `pending` no tiene placement; una `active` siempre tiene un
  programa del plan y comienza con placement `unfixed`.
- El historial conserva el contexto efectivo de plan, programa y fijación para
  que una entrega posterior pueda resolver actividad por instante de
  ocurrencia.
- Toda mutación administrativa conserva actor e instante. Solo el rechazo
  exige motivo; las demás acciones lo conservan cuando se proporciona.

### Bloque 3 — Solicitud, consultas y moderación

- El usuario solicita únicamente un plan y su identidad procede de
  `UserContext`, nunca del payload.
- Un plan sin aprobación crea directamente una suscripción `active` en el
  primer programa. Si no existe ese programa, la operación no crea registro.
- Un plan con aprobación crea una suscripción `pending`, incluso si todavía no
  existe un programa. La resolución de programa ocurre al aprobar.
- Administración aprueba siempre en el mismo plan solicitado. Puede elegir un
  programa existente del plan o usar el primero; cualquier fallo conserva el
  estado `pending`.
- El rechazo exige motivo, crea historia terminal y no impide una solicitud
  posterior cuando el usuario ya no tiene otra suscripción abierta.
- El usuario consulta solo su suscripción abierta. Administración consulta
  todos los estados y el historial.

### Bloque 4 — Ciclo de vida administrativo

- Cancelar cambia una suscripción `active` a `ended` sin crear reemplazo.
- Cambiar de plan valida un destino activo y no archivado, termina la
  suscripción vigente y crea otra `active` en una única decisión indivisible.
- La nueva suscripción usa el programa elegido o el primero del plan, comienza
  sin puntos transferidos y no hereda una fijación anterior.
- Cambiar de programa dentro del mismo plan conserva la suscripción y la
  condición actual del placement: `fixed` continúa `fixed` y `unfixed`
  continúa `unfixed`.

### Bloque 5 — Fijación y salvaguardas

- Administración fija el placement en un programa del plan, cambia el programa
  fijado o libera la fijación sin recrear la suscripción.
- Mientras está fijado no se ejecuta Progression y la actividad del intervalo
  queda excluida definitivamente de progreso. Rewards puede seguir usando el
  programa fijado.
- Liberar no cambia el programa inmediatamente; el siguiente run ordinario
  puede reevaluarlo usando actividad elegible posterior.
- Subscriptions publica hacia Plans una consulta de existencia de
  suscripciones abiertas. Plans la usa para impedir el archivo cuando exista
  alguna `pending` o `active`.
- La exclusión global y la carrera entre archivo, solicitud y aprobación deben
  quedar protegidas por la estrategia de concurrencia que cierre el modelo de
  datos de S1; una comprobación previa aislada no satisface la entrega.

## Superficie HTTP

### Usuario IB

- `POST /api/ib/v1/customer/subscriptions`
- `GET /api/ib/v1/customer/subscriptions/current`

Permisos en la surface `customer_app`:

- `ib.subscriptions.apply` para solicitar;
- `ib.subscriptions.read` para consultar la abierta.

La consulta `current` devuelve `404` cuando el usuario no tiene una suscripción
`pending` ni `active`. No existe superficie de usuario para consultar historia
ni mutar la suscripción después de solicitarla.

### Administración

- `GET /api/ib/v1/admin/subscriptions`
- `GET /api/ib/v1/admin/subscriptions/{subscription}`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/approve`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/reject`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/cancel`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/change-plan`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/placement/change`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/placement/fix`
- `POST /api/ib/v1/admin/subscriptions/{subscription}/placement/release`

Permiso: `ib.subscriptions.manage` en la surface `admin_panel`.

El listado administrativo es paginado y permite consultar todos los estados.
El detalle incluye el historial del registro. El motivo es obligatorio y no
vacío únicamente en `reject`; las demás mutaciones pueden recibirlo y lo
conservan cuando está presente.

`POST /api/ib/v1/admin/plans` y
`PATCH /api/ib/v1/admin/plans/{plan}` incorporan `requires_approval` con las
reglas de omisión definidas en el bloque 1.

## Contratos entre features

- Subscriptions consume contratos especializados de Plans y Programs para sus
  decisiones de solicitud, aprobación, cambio de plan y placement.
- Plans consume una consulta pública de Subscriptions para proteger el archivo.
- Ningún feature accede al repository o a los modelos internos de otro.
- S1 no publica contratos especulativos para Progression o Rewards. Esos
  contratos se diseñan con su primer consumidor real.
- S1 no incorpora eventos Kafka. Los eventos de negocio del BDS se conservan
  como vocabulario y evidencia interna hasta que exista un consumidor externo.

## Trazabilidad

| Bloque | Reglas principales |
| --- | --- |
| 1. Plan y programa | `BR-PLAN-017`, `BR-PROGRAM-001`, `BR-SUBSCRIPTION-004`, `005`, `018` |
| 2. Núcleo | `BR-SUBSCRIPTION-001`–`003`, `010`, `011`, `021` |
| 3. Solicitud y moderación | `BR-SUBSCRIPTION-003`–`005`, `018`–`021` |
| 4. Ciclo de vida | `BR-SUBSCRIPTION-002`, `006`–`013`, `017`, `020` |
| 5. Fijación y salvaguardas | `BR-SUBSCRIPTION-014`–`017`, `022`, `023` |

## Criterio de aceptación

S1 queda aceptada cuando, de extremo a extremo:

- una solicitud produce `pending` o `active` según el requisito capturado y no
  permite dos suscripciones abiertas aun bajo concurrencia;
- planes inactivos o archivados se rechazan, y la ausencia de programa impide
  una activación automática o una aprobación sin programa explícito;
- aprobar revalida el mismo plan solicitado y cualquier fallo deja intacta la
  solicitud pendiente;
- el rechazo es terminal, exige motivo y una adhesión posterior crea otro
  registro;
- cada audiencia observa solo la información autorizada;
- cancelar, cambiar de plan y cambiar de programa no dejan estados parciales;
- cambiar de plan no transfiere puntos ni fijación;
- cambiar, fijar y liberar placement conserva una historia temporal coherente;
- la fijación suspende Progression y no Rewards ni la captura de actividad para
  otros propósitos;
- ningún plan con suscripciones abiertas puede archivarse, incluida la carrera
  con una solicitud o aprobación concurrente.

## Fuera de S1

- Tablas, restricciones, índices y mecanismo concreto de locking, que se
  cierran en el modelo de datos siguiente.
- Ejecución de runs, cálculo de contribuciones y cálculo o pago de Rewards.
- Contratos públicos de Progression y Rewards y eventos Kafka.
- Versiones futuras de planes y su aplicación a suscripciones existentes.
- Ventanas, reversas y correcciones temporales de actividad tardía.

## Criterios de salida de la etapa

- Los cinco bloques forman una única capacidad habilitable y cada uno tiene
  comportamiento observable y trazabilidad al BDS.
- Las superficies, audiencias, permisos y respuestas de ausencia están
  definidas.
- Las fronteras públicas necesarias tienen un consumidor real y preservan los
  contratos V1 existentes.
- El alcance permite diseñar restricciones, historia efectiva y concurrencia
  sin decisiones funcionales pendientes.
