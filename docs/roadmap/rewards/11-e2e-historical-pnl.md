# Próxima entrega: E2E Broker, Progression y Rewards con cortes PnL históricos

Estado: **En curso; contrato histórico, RWD4.2.1 y RWD4.2.2 completados localmente; operatividad/S2S pospuestas para activación y cierre**
Última revisión: 2026-10-03

## Entrega de cierre local E2E

Decisión vigente: generación y settlement PnL habilitados por defecto al completar
el código, con controles independientes para desactivarlos explícitamente.
La validación integrada posterior usará ib-labs como fuente y generador de
actividad cuando ese proyecto esté listo. No bloquea desarrollo ni habilitación;
sigue pendiente para declarar completo el E2E. Esta decisión sustituye las
prescripciones anteriores de runner deshabilitado y gate S2S de activación,
que se conservan abajo como evidencia de entregas anteriores.

| Incremento | Estado | Salida |
| --- | --- | --- |
| Contrato financiero y settlement PnL | Completado localmente | Solicitud congelada, niveles traducidos y controles independientes |
| Coordinación financiera | Completado localmente | Leases compartidas, recuperación y cancelación incierta |
| Snapshots de volumen | Completado localmente | Entradas completas antes de efectos parciales |
| Consultas HTTP | Pendiente | Rewards por propietario y jobs/períodos administrativos |
| Progression y regresión integrada | Pendiente | Flujo local de actividad a placement y settlement |

Primer incremento: se incorpora solicitud financiera congelada y selección PnL
condicionada por `rewards.negative_pnl.settlement_enabled` (default `true`).
Generación `rewards.negative_pnl.enabled` también tiene default `true`.
Pruebas focalizadas de settlement: 12 pruebas / 42 assertions aprobadas.
Verificación del primer incremento: 45 pruebas / 220 assertions, incluida
arquitectura; Pint y Graphify completados. Commit `bf262d6`.

Las operaciones financieras comparten token y vencimiento de la Reward. Sus
intenciones y solicitudes quedan conservadas antes del envío; cancelación incierta,
reversa y compensación se recuperan mediante el reconciliador existente, sin
invocar un UseCase desde otro. Una compensación se crea y enlaza atómicamente;
sus reintentos rechazan cambios de importe o clave. La cancelación expone outcome
`cancelled` o `rejected_already_settled`. Primeras regresiones: 19 pruebas /
64 assertions aprobadas para settlement, operaciones y recuperación.
Coordinación financiera: 49 pruebas / 235 assertions con arquitectura, Pint
completado; commit `6a838f6`.

Volumen conserva actividad y distribución por posición en una evaluación durable,
con preparaciones separadas para evento y barrido que mantienen sus diferencias
de elegibilidad. La consulta IAM cubre la profundidad máxima remunerable de ambos
canales, sin volver a resolverla al reintentar. Todas las entradas económicas y
el mínimo quedan congelados antes de crear obligaciones. Las Rewards y outcomes
se confirman bajo lease; recuperación reutiliza la preparación y la unicidad
económica original. Regresiones de volumen y arquitectura: 37 pruebas /
228 assertions aprobadas; Pint completado. Campos económicos aún ausentes no
constituyen una obligación; una comisión fuente inicialmente ausente puede
completarse antes de conservar una preparación, sin reescribir cantidades válidas.

## Dependencias y evidencia

A1 y A2.1–A2.3 están completadas; no se repite su refactor.
El precedente Broker `ResolveNegativePnlPeriodsUseCase` consultaba cuentas operativas,
usa `current_balance` y fija el corte a la hora de consulta. El cashflow se obtiene
localmente mediante `GetAccountCashFlowPeriodService`.

La decisión confirmada usa `margin_level_reads`: última lectura de la cuenta con
`unix_read_at <= occurred_until`, con empate por ID descendente. Se asume vigente
su balance hasta el corte solicitado. Broker conserva `current_balance`; no se
elimina ninguna capacidad por inferencia. Sin corte explícito, el endpoint mantiene
su comportamiento previo. El cashflow local usa `created_at` en `[baseline, corte)`.

La ampliación local incorpora corte solicitado y referencia de lectura, contempla
cuentas desactivadas/archivadas y rechaza baselines ajenas. No hay fallback al balance
actual cuando falta lectura. IB distingue falta de cobertura de error recuperable,
valida la respuesta y conserva snapshots por suscripción/cuenta/grupo/cadencia/corte.
El primer snapshot aceptado se reutiliza sin consultar de nuevo ni sobrescribirse.
RWD4.2.2 conecta esta capacidad al runner económico y al cierre durable por cambio de plan.
La selección no demuestra un historial completo de elegibilidad Trading: usa
pertenencia, existencia al corte y grupo Live actual. Ese límite requiere evidencia
contractual antes de afirmar cobertura histórica completa.

## Incrementos pendientes y salida

1. **Broker–IB e IAM: S2S pendiente.** Fuente y contrato histórico implementados localmente; demostrar S2S
   cierre posterior a cambio de plan y profundidad IAM. Ampliar después contratos,
   documentación, Postman y pruebas de los servicios afectados.
2. **PnL económico: completado localmente hasta Rewards `pending`.** RWD4.2.1
   completa configuración histórica y Strategy pura;
   [RWD4.2.2](#rwd422-runner-pnl-recuperacion-y-generacion-de-rewards) completa
   baselines independientes, cierres durables de suscripciones terminadas,
   períodos ordenados, evidencia/red/configuración congeladas y recuperación
   idempotente. El runner permanece deshabilitado por defecto y PnL queda fuera
   del claim automático de settlement. S2S y datos reales continúan pendientes
   para activación y cierre; no se declaran pagos PnL.
3. **Rewards/Finance: en curso.** Completar validación contractual, condiciones y
   holds al claim, coordinación administrativa, reconciliación y snapshots completos
   de volumen. Incorporar consultas HTTP con autorización, propiedad y Postman.
   Salida: concurrencia y reintentos sin duplicar obligaciones ni pagos.
4. **Progression: pendiente.** Validar PG1/PG2, contexto histórico, red congelada,
   duplicados, fallos parciales y placement, sin refactor de arquitectura.
5. **Activación: pendiente de 1–4.** Plan/grupo controlado y observación del backlog.
   Salida: CPA/volumen/PnL settled, placement y remuneración exclusiva del tramo
   anterior al cambio de plan. Separar evidencia local, S2S y datos reales.

## Primer cambio independiente

La reversa transmite el nivel original de la Reward a Finance; anteriormente
enviaba siempre `1`. La regresión local usa volumen de nivel 3 y comprueba payload
y transición a `reversed`. No completa el resto de garantías ni el E2E.

Finance aporta depósitos certificados CPA y settlement; Broker aporta cashflow
PnL local. No se reutilizan snapshots IB antiguos. Quedan fuera nuevos módulos,
FX, reversas de puntos, versionado de planes y nuevas notificaciones.

## Evidencia local y bloqueo de entorno

- Broker: 9 pruebas del endpoint histórico, 42 assertions; junto con las suites
  de arquitectura Forms/Scheduling, 27 pruebas y 86 assertions aprobadas.
- IB: regresiones Rewards/Progression y arquitectura, más snapshot PnL:
  91 pruebas y 614 assertions aprobadas. La regresión de pausa cubre que una Reward
  no elegible no consume el límite de pagos ni bloquea la siguiente Reward.
- La URL Broker configurada por defecto usa `broker-app`; la comprobación HTTP
  desde esta sesión falla por host desconocido. Se verificaron después contenedores
  locales activos, la ruta PnL en Broker y las rutas upline en IAM. Esa inspección
  no constituye el escenario S2S controlado exigido para activar el runner.
- Profundidad IAM S2S sigue pendiente. La decisión del usuario pospone pruebas
  operativas/S2S sin bloquear código. RWD4.2.1 añade configuración económica PnL
  y RWD4.2.2 genera Rewards `pending` localmente. Settlement PnL, cierre E2E,
  datos reales y consultas de runs siguen pendientes.

## RWD4.2.1 — configuración y cálculo

Completada localmente en IB: GET/PUT administrativos, revisiones con actor y vigencia
semiabierta, retirada e idempotencia del reemplazo, contratos de resolución histórica
y contexto económico congelado. La factory económica selecciona
`negative_pnl_share`; su Strategy aplica mínimo antes de un único redondeo half-up.
No se cambia el schema publicado de Rules ni las convenciones de niveles Finance.
Esta entrega RWD4.2.1 no incluyó runner, generación de Rewards, cierres de
suscripción ni pagos PnL; RWD4.2.2 incorpora los tres primeros, manteniendo pagos pendientes.

Los incrementos 3 y 4 conservan alcance y criterios; las pruebas locales continúan.
La etapa 1 permanece pendiente operativa, y la etapa 5 exige su evidencia para activación.

Evidencia RWD4.2.1: 112 pruebas/638 assertions en regresiones ampliadas;
42 pruebas/290 assertions en el cierre focalizado posterior a la proyección HTTP.
Pint y Graphify completados (5758 nodos, 13760 relaciones). Postman v2.1
validado y contrastado con las 77 rutas propias (78 requests, sin rutas faltantes).

<a id="rwd422-runner-pnl-recuperacion-y-generacion-de-rewards"></a>

## RWD4.2.2: runner PnL, recuperación y generación de Rewards

Estado: **Completada localmente**.
Última revisión: 2026-10-03.
Dependencias satisfechas localmente: configuración histórica y Strategy/factory
económica RWD4.2.1, y cortes/snapshots históricos del contrato Broker–IB.
Alcance implementado en IB Service; las pruebas descritas abajo son locales.
No acredita evidencia operativa, S2S ni datos reales.

### Selección y contexto

- Publicar consultas tipadas de configuraciones y suscripciones relevantes,
  incluidas las terminadas. Seleccionar planes → programas con períodos vencidos
  → suscripciones beneficiarias → referidos → cuentas.
- Añadir un puerto específico de referidos mediante IAM y su adapter, reutilizando
  `getDownline(..., levels)` del SDK. Limitar la profundidad a niveles remunerables
  y normalizar los niveles dentro del adapter.
- Mantener contextos independientes por suscripción beneficiaria, cuenta, módulo,
  grupo y cadencia. Programa y rates del corte final remuneran el período completo;
  para cambio de plan, usar el contexto inmediatamente anterior a `closed_at`.

### Períodos, baselines y cierre por cambio de plan

- Añadir persistencia aditiva de contextos, períodos, progreso y leases.
  La baseline inicial no remunera historia previa. Procesar límites UTC diarios,
  semanales, mensuales y anuales en orden; el retraso no fusiona períodos hasta “ahora”.
- Incorporación, cambio de cadencia, moneda/grupo y reanudación establecen una nueva
  baseline. Persistir cortes aceptados y reutilizarlos; la falta de evidencia válida
  conserva la baseline y registra el motivo pendiente.
- Avanzar baseline junto con un período durable recuperable. Los fallos posteriores
  continúan ese período sin reconstruir ni sustituir sus entradas congeladas.
- Registrar el cierre pendiente mediante un puerto propietario de Rewards en la
  misma transacción local del cambio de plan, sin consultar Broker ni IAM.
  Reconciliar cierres faltantes con `closed_at` e historial, aun con suscripción terminada.
- Recuperar períodos vencidos anteriores y luego el tramo final hasta `closed_at`;
  establecer la baseline de la nueva suscripción en ese límite. Si el cambio coincide
  con un corte de cadencia, deduplicar el cierre y evitar un tramo vacío.

### Generación y controles del runner

- Congelar evidencia, red, asignación, configuración y rates antes de crear
  obligaciones. Invocar la factory `negative_pnl_share` existente con el mínimo
  explícito de configuración, cuyo default permanece `0.01`.
- Registrar resultados auditables sin Reward para baseline inicial, PnL
  cero/positivo e importe descartado por mínimo o redondeo.
- Crear Rewards `pending` con unicidad persistente por cuenta, período, beneficiario,
  nivel, asignación y versión; recuperar creación parcial sin duplicar obligaciones.
- Excluir Rewards PnL del settlement automático hasta completar su habilitación
  financiera. Generación y settlement conservan responsabilidades independientes.
- Crear `rewards:process-negative-pnl`, con scheduler cada minuto, deshabilitado
  por defecto, lotes acotados, leases, recuperación de leases expiradas y protección
  contra solapamiento. Un contexto bloqueado no detiene los demás.
- Registrar contadores de períodos procesados, cierres pendientes, Rewards
  creadas y errores. El desfase lectura/cashflow sigue como bug conocido,
  sin detección, tiempo de gracia ni bloqueo automático.

### Verificación, aceptación y trabajo posterior

Cubrir localmente baselines y límites temporales, ejecución diferida, múltiples
cuentas/grupos/cadencias, cambios de programa/rates/plan y pausa, profundidad IAM
y normalización con fixtures, caídas tras persistir evidencia, creación parcial,
concurrencia, leases, reintentos y exclusión del settlement automático.
Ejecutar regresiones CPA/volumen/cortes PnL y suscripciones, suite de arquitectura,
Pint y `graphify update .` al implementar código.

**Salida:** Rewards PnL `pending` generadas localmente con períodos y cierres
recuperables, sin duplicados, y evidencia de pruebas registrada.

### Implementación y evidencia local

- Programs publica consulta paginada de revisiones, con filtros históricos por
  programa e intervalo. Subscriptions publica suscripciones relevantes, segmentos
  de placement y contexto por identidad de suscripción e instante, incluidos los
  registros terminados. Los cambios de cadencia entre runs se detectan consultando
  esa historia; no se infieren únicamente del contexto vigente.
- `negative_pnl_jobs` conserva contexto beneficiario, cursor, controles y leases;
  `negative_pnl_baselines` mantiene cada referido/cuenta/moneda por separado.
  `negative_pnl_periods` conserva estados `preparing`, `ready`, `completed`, inputs,
  receipts por referido y resultados auditables. Baseline y estado `ready` se
  confirman atómicamente; la recuperación de Rewards tiene prioridad sobre el
  siguiente corte, incluso si ese corte todavía no venció.
  El snapshot del corte y su receipt se confirman juntos después de consultar
  Broker; un fallo local revierte ambos y permite reintentar sin perder evidencia.
- `negative_pnl_pending_closures` se escribe dentro de la transacción del cambio
  de plan. Usa el `closed_at` persistido por Subscriptions, sin ampliar ni asumir
  otra precisión temporal. El descubrimiento reconcilia registros faltantes
  mediante suscripción reemplazada, operación e historial. Solo después de
  completar el barrido relevante puede confirmarse el cierre, evitando declarar
  terminado un contexto mientras faltan otros por descubrir.
- El cursor durable de descubrimiento y el orden de claims evitan que los primeros
  errores oculten otras suscripciones. Las leases validan token y vencimiento en
  cada confirmación local; PostgreSQL impone unicidad de contexto, período y origen
  económico de Rewards. La evidencia y el cálculo permanecen fuera de las
  transacciones de cambio de plan y las consultas remotas fuera de transacciones
  de persistencia. Se comparte el servicio interno de captura de snapshots;
  ningún UseCase invoca directamente otro UseCase.
- El runner usa la factory `negative_pnl_share` existente. Una decisión de nueva
  baseline se conserva separada de la respuesta original Broker, sin reescribir
  PnL, balance, cashflow ni referencias. Rewards conservan también tasas, red,
  regla/asignación/versión, plantilla, mínimo y nivel económico.
- `rewards:process-negative-pnl --limit=100 --discovery-limit=100` limita trabajo
  y descubrimiento; scheduler cada minuto con `onOneServer`/`withoutOverlapping`.
  Comando y scheduler respetan `rewards.negative_pnl.enabled`, cuyo default es
  `false`. No se activa en esta entrega. Contadores: contextos, períodos, cierres
  procesados, cierres pendientes, Rewards creadas y errores sanitizados.
- El claim de settlement excluye `commission_type = pnl` expresamente, sin usar
  holds ni alterar CPA/volumen. No se modifica Broker, Finance, HTTP, Postman,
  scheduler de las otras modalidades ni Progression.

Verificación final: **47 pruebas / 652 assertions** focalizadas y de arquitectura;
**169 pruebas / 1098 assertions** en regresiones CPA/volumen/cortes PnL y
suscripciones. Incluye concurrencia real entre dos conexiones PostgreSQL, lease
expirada y trabajador obsoleto; recuperación de evidencia y Rewards parciales;
cuatro cadencias; cambios de rates/programa/plan, moneda y pausa; cierre atómico,
reconciliación y coincidencia con límite UTC; profundidad/normalización IAM;
resultados sin Reward y exclusión del settlement.
La fixture CPA de regresión reutiliza el módulo Broker previamente sembrado para
mantener aislamiento frente al orden de suites. Pint aprobado y Graphify
actualizado: **5971 nodos / 14383 relaciones**.

La implementación se limita a IB Service; no modifica Broker ni Finance.
Settlement, traducción de niveles IB → Finance y consultas HTTP de Rewards/runs
permanecen en entregas posteriores. S2S, operatividad y datos reales no bloquean
desarrollo de código, pero siguen pendientes para activar y declarar completo el E2E.

## Bug conocido: desfase entre lectura y cashflow

Se acepta el balance de la última lectura hasta el corte solicitado, sin antigüedad
máxima. Un movimiento entre ambos instantes puede ser descontado del cashflow antes
de estar reflejado en el balance. Ejemplo: inicio 1.000, lectura 900 a las 23:58,
depósito 100 a las 23:59 y corte 00:00 produce PnL -200; si el depósito ya se aplicó,
el PnL correcto sería -100. Puede originar recompensa inmerecida.

Los depósitos emiten `margin_level_update`, reduciendo la exposición, pero los
retrasos, desorden o pérdidas de eventos no quedan resueltos. `created_at` es el
timestamp aceptado del ledger, sin garantía adicional sobre aplicación en Trading.
Se conservan ID e instante de lectura para auditoría. **No se implementan detección,
tiempo de gracia ni bloqueo por este desfase**, por decisión explícita del usuario.
La ausencia de una lectura es un error distinto y sí impide crear el snapshot.

## Garantías financieras avanzadas

El claim excluye holds de reconciliación y operaciones de cancelación/compensación
incompletas; las operaciones administrativas rechazan una lease de settlement
activa. El runner consulta operabilidad de plan y módulo mediante sus puertos,
libera claims no elegibles y continúa con otras Rewards. No exige que una
suscripción histórica siga activa. Estas medidas no completan por sí solas toda
la coordinación concurrente pendiente. Settlement y reversa validan además nivel
e importe entero devueltos por Finance; reconciliación/cancelación comparten
validación de identidad, moneda, wallet, nivel y origen de reversa. La evidencia
S2S Finance permanece pendiente.

Pendiente contractual de niveles: el adapter IAM de Rewards normaliza nivel 1
de IAM como nivel de distribución 0; Finance exige `network_level >= 1`.
El gateway actual transmite el nivel persistido. No se cambia esa convención por
inferencia: el cierre Finance de volumen/PnL debe fijar y probar la traducción
contractual conservando el nivel económico original en IB y sus reversas.
