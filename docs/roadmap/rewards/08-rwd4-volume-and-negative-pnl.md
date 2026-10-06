# RWD4 — volumen tradeado y PnL negativo

## Actualización N-PnL (2026-10-06)

La entrega vigente agrega por nivel y moneda y usa selección de regla/plantilla
común por módulo. Sustituye el cálculo por cuenta y selección por grupo descritos
en la evidencia histórica de este documento. Contrato por lotes incompatible,
sin fallback; [entrega y evidencia](13-negative-pnl-level-currency.md) y
[contrato operativo](../../rules/negative-pnl-contract.md).


Estado: **RWD4.1, RWD4.2a, RWD4.2.1 y RWD4.2.2 implementados localmente; cierre local de código completado; S2S y datos reales pendientes**
Dependencias: Modules M5, Rules R2, Programs, Subscriptions, IAM y Broker Service
Última revisión: 2026-10-03

## Decisiones confirmadas

- Volumen y PnL distribuyen Rewards por la red multinivel congelada al iniciar el run.
- La profundidad consultada a IAM se limita al máximo nivel remunerable de la plantilla congelada. El SDK instalado admite `getUpline(user, levels)` y el puerto propietario de Rewards transmite `max_distribution_level + 1`, valida el orden recibido y conserva únicamente niveles remunerables.
- Volumen se configura por programa y vigencia con `event`, `periodic` o `both`; el valor predeterminado es `periodic`. Una posición fuente no puede originar dos Rewards iguales aunque sea observada por ambos caminos.
- PnL se configura por programa y vigencia con cadencia `daily`, `weekly`, `monthly` o `yearly`. La cadencia determina cuándo vence el run; las ventanas son cortes UTC reales encadenados.
- El `server_group` es la autoridad de moneda y precisión para ambas modalidades.
- La base de volumen se calcula desde la posición: fija = `volume × participation_rate`; porcentual = `broker_granted_commission × participation_rate`. La plantilla distribuye esa base por nivel y cada beneficiario aplica su `personal_rate` y, solo si `is_master`, su `master_rate`, todos congelados en la Reward.
- El evento Avro de posición cerrada de la modalidad `event` lo publica Trading directamente a IB y solo funciona como disparador. IB resuelve la posición en Broker por `order_id` + `external_trader_id`/`login`; Broker entrega entonces volumen, comisión otorgada, instrumento, grupo, moneda y precisión congeladas. `broker-service` también entrega esos campos en su feed paginado para `periodic`.
- Existe una carrera conocida: Trading publica el mismo evento para Broker e IB, así que IB puede consultar Broker antes de que Broker haya persistido el cierre. `409 CLOSED_POSITION_NOT_READY`, timeout y 5xx se tratan como recuperables mediante receipts durables. Trading Account Service permanece como mejora futura para eliminar la carrera, no como requisito para habilitar `event`.

## RWD4.0 — contratos que deben confirmarse

1. Trading debe confirmar el evento Avro de posición cerrada con `id` estable y `login` (header o payload coherente). El contrato económico se resuelve en Broker mediante consulta interna por ambos valores; Broker debe confirmar tanto esa consulta como el feed paginado equivalente para `periodic`.
2. Broker publica para PnL cuentas operativas, balance actual, grupo, moneda, precisión, flujo de caja asentado y referencias opacas de evidencia. El primer corte establece baseline sin PnL; los siguientes resuelven el intervalo desde el último corte de IB.
3. Finance no participa directamente en PnL. Broker es autoridad del balance y cashflow de la cuenta de trading; la consulta CPA de depósitos por usuario permanece independiente.
4. El SDK IAM instalado permite limitar `getUpline` por profundidad y el adapter de Rewards ya transmite el límite.

El contrato local de PnL está implementado en Broker e IB; RWD4.0 se cerrará cuando la misma forma quede demostrada mediante evidencia S2S reproducible.

## RWD4.1 — volumen

- El alcance original incluía cálculo, run durable, snapshot de red e identidad
  por posición/beneficiario/nivel/regla. RWD-A2 alineó su cálculo mediante factory
  sin cambiar el recorrido por actividad ni afirmar garantías nuevas implementadas.
- Reutilizar el feed M5 para el barrido periódico y el evento Avro V1 como disparador de resolución en Broker. Ambos caminos comparten la misma idempotencia económica.
- Persistir evidencia de la posición y congelar configuración instrumental, template, participación, rates de nivel/personal/master, moneda y precisión en el ledger.
- El procesamiento durable, puertos históricos y creación económica de RWD4.1
  están implementados. La denominación anterior de pipeline no impone una
  arquitectura común; RWD-A1 la sustituye. Contract tests S2S de Broker siguen
  pendientes y no se confunden con evidencia local.

## RWD4.2 — PnL negativo

- RWD4.2a publica el puerto `ResolveNegativePnlPeriodsPort`, Data V1 y adapter HTTP. Broker resuelve balance, cashflow y PnL firmado; IB no replica su contabilidad.
- RWD4.2.1 incorpora la configuración histórica y el cálculo puro. RWD4.2.2 procesa períodos por cadencia, reutiliza snapshots proveedor y aplica regla, red y tasas congeladas.
- Conserva referencias de cuenta, balances, totales y referencias opacas de flujo sin copiar movimientos financieros completos.
- El runner económico no forma parte de RWD4.2a. [RWD-A2](10-rwd-a2-cpa-volume-refactor.md)
  está completada. Por decisión del usuario, las pruebas operativas y S2S de corte
  histórico Broker–IB e IAM se posponen: no bloquean desarrollo de código, pero
  siguen siendo requisitos de cierre E2E, sin bloquear la habilitación del código.

### RWD4.2.1 — configuración histórica y cálculo (completada localmente)

GET/PUT administrativos del programa consultan/reemplazan una cadencia común y
sus grupos. Se conservan actor, vigencias UTC con microsegundos, revisión anterior,
asignación explícita, regla/versión y binding/versión de plantilla con niveles/tasas.
La consulta pública interna permite programa, módulo, grupo e instante; no resuelve
otra versión vigente al leer historia. Un reemplazo idéntico no crea revisión;
grupos vacíos retiran la vigente. HTTP usa `data` directamente y `[]` sin configuración.

La persistencia nueva usa `program_negative_pnl_configuration_revisions` y
`program_negative_pnl_groups`, con bloqueo de programa antes de la primera revisión
y unicidad persistente. Se conserva la tabla preliminar anterior, sin lectores
económicos; no se elimina ni interpreta como configuración publicada.

Rules verifica versión publicada PnL del plan y una única asignación efectiva.
Plans resuelve pertenencia del binding; PaymentTemplates resuelve la versión exacta
publicada y sus niveles. No se consulta el catálogo remoto del grupo.
La Strategy y factory económica PnL son puras, separadas del proveedor existente;
no crean Rewards. Mínimo explícito previo al redondeo; el runner RWD4.2.2 transmite
el mínimo de configuración cuyo default sigue `0.01`.

RWD4.2.2 reutiliza RWD4.2.1 y los snapshots locales del contrato
histórico: períodos vencidos en orden, baselines,
recuperación, cierres durables al cambiar de plan, red/configuración congeladas y
unicidad de Rewards. Salida: pruebas locales de idempotencia y recuperación.
Generación y settlement PnL están habilitados por defecto; el cierre E2E requiere
S2S Broker/IAM/Finance y evidencia operativa posterior con ib-labs.

Evidencia RWD4.2.1: regresiones CPA/volumen/cortes PnL, templates y arquitectura,
112 pruebas y 638 assertions aprobadas. Tras ajustar la proyección HTTP y su
Command, verificación focalizada final: 42 pruebas y 290 assertions aprobadas.
Pint completado; Graphify actualizado (5758 nodos, 13760 relaciones).
Postman v2.1 válido: 78 requests, cobertura de las 77 rutas de
`route:list --except-vendor`, incluido `/up` del bootstrap. Solo pruebas locales;
sin ejecución operativa, S2S ni datos reales.

### RWD4.2.2 — runner, recuperación y generación (completada localmente)

La implementación y evidencia quedan registradas en el
[plan E2E existente](11-e2e-historical-pnl.md#rwd422-runner-pnl-recuperacion-y-generacion-de-rewards).
Reutiliza RWD4.2.1 y snapshots históricos locales: selección de períodos vencidos,
referidos IAM, baselines, cierres durables al cambiar de plan, snapshots congelados,
leases y creación idempotente de Rewards `pending`.
El comando se programa cada minuto. La entrega original dejó generación y settlement
PnL deshabilitados; el cierre local E2E sustituye esa restricción por controles
habilitados por defecto e independientes para desactivarlos explícitamente.
Aceptación local aprobada: 47 pruebas/652 assertions focalizadas y de arquitectura;
169 pruebas/1098 assertions en regresiones CPA/volumen/cortes PnL y suscripciones.
Pint y Graphify completados (5971 nodos, 14383 relaciones).
Settlement, traducción de niveles Finance y consultas HTTP están implementados
localmente en el cierre E2E; S2S/operatividad siguen pendientes para su cierre.

## Diseño PnL acordado y límites vigentes

- La cadencia pertenece al programa beneficiario, no al referido ni al cron.
  Planes/suscripciones/programas con período vencido originan la selección de IBs,
  referidos y cuentas; las baselines son evidencia, no el scheduler de negocio.
- Configuración por server group y cortes separados por cadencia. Programa/regla
  y rates del corte final remuneran el período completo; cambios de programa
  dentro del plan no exigen dividirlo. CPA conserva su contexto persistido.
- Cambio de plan termina la suscripción anterior y crea otra. PnL cierra el tramo
  anterior en el instante del cambio, anclado a la suscripción vieja y al contexto
  inmediatamente anterior al cierre. Se recupera después aunque ya no esté activa.
  No incluye actividad posterior; la nueva suscripción establece su baseline.
- Incorporación, cambio de cadencia y reanudación establecen baseline sin remunerar
  intervalo previo; reiniciar un contexto no reinicia otros beneficiarios.
- Fórmula acordada: `abs(pnl_neto) × tasa_nivel × personal_rate × master_rate`
  (último factor solo para Master IB). Decimales exactos, mínimo configurable antes
  de un único redondeo final half-up a precisión del grupo. Estas decisiones se
  formalizaron en BDS; RWD4.2.1 implementa el cálculo. RWD-A2 no cambió cálculos.
- Un runner genera Rewards idempotentes; settlement las asienta posteriormente.
  CPA, volumen y PnL pueden acumularse, con configuración aplicable no ambigua por
  modalidad/contexto. La configuración de RWD4.2.1 fija referencias explícitas;
  la unicidad persistente de Rewards corresponde al runner posterior.

## Dependencia pendiente: corte histórico Broker–IB

El contrato precedente recibía usuario/baselines y obtenía balance actual y corte real
de consulta. La ampliación local admite un corte final histórico para que Broker
resuelva balance y cashflow local en el mismo instante UTC solicitado; no sustituir
balance histórico por `current_balance` ni por snapshots del antiguo IB de Broker,
que será retirado. Finance Service no participa en evidencia PnL; solo settlement.

La fuente histórica seleccionada es la última lectura de margen anterior o igual
al corte solicitado, asumiendo continuidad hasta ese corte. El desfase posible
con cashflow es un bug conocido aceptado, sin detección ni gracia; véase el
[plan E2E actualizado](11-e2e-historical-pnl.md). La ampliación está implementada
localmente y el cierre E2E todavía exige evidencia S2S del contrato
versionado/compatible, cobertura de cuentas históricamente elegibles, límites
temporales exactos y error explícito sin Reward/avance de baseline cuando falta
evidencia. La evidencia S2S reproducible debe cubrir cambio de plan y consulta
posterior al cierre. La evidencia local no acredita S2S ni datos reales.

RWD4.0 conserva la evidencia del contrato actual; demostrarlo no basta para cerrar
la ampliación histórica. RWD4.2a mantiene su estado, sin declarar soporte nuevo.

## Criterios de salida

### Ampliación histórica local del contrato

`occurred_until` opcional selecciona el corte solicitado; omitirlo conserva el
contrato previo. Las respuestas históricas incorporan `balance_read_id` y
`balance_read_at`, independientes del corte económico. Se conservan fracciones
temporales en el contrato histórico. El ledger usa `[baseline, corte)` y la lectura
se elige con tiempo menor o igual al corte, sin antigüedad máxima.

`422 HISTORICAL_PNL_COVERAGE_UNAVAILABLE` indica ausencia de lectura; se distingue
de un `409`, error 5xx o fallo de transporte recuperable. Baselines ajenas o no
anteriores al corte son inválidas. IB valida pertenencia, corte, continuidad y
aritmética; rechaza lecturas futuras y cuentas duplicadas. La selección histórica
no acredita por sí sola todas las vigencias operativas de Trading.

Los snapshots locales conservan la cuenta externa y el contexto de suscripción,
grupo y cadencia sin FK distribuida. El primer corte persistido prevalece en
reintentos. RWD4.2.2 conecta esta capacidad al runner económico.

- Tests de contrato contra Broker e IAM; pruebas de rangos UTC, precisión, deduplicación y concurrencia.
- Volumen `event`, `periodic` y `both` producen una sola Reward por identidad económica.
- PnL cubre primera lectura, períodos sucesivos, depósitos, retiros, múltiples cuentas y cambio de cadencia.
- RWD-A2 completada con pruebas de equivalencia; cortes históricos Broker e IAM
  demostrados S2S; cierre pendiente de suscripción terminada no pierde ni duplica
  Rewards y no remunera actividad posterior al cambio.
- La evidencia de IB Lab y la validación S2S real son necesarias para marcar RWD4 como completado.

## Refactor PnL realizado — 2026-10-06

El contrato vigente utiliza exclusivamente profit de posiciones cerradas por intervalo, con position_ids completos y snapshots por cuenta en IB. Sustituye el cálculo anterior por balances, cashflows y baselines monetarias descrito en entregas históricas de este documento. Se conserva compensación por nivel/moneda y settlement. Implementación completada localmente: IB 115 pruebas / 1246 assertions; Broker 18 pruebas / 146 assertions. Pint aprobado y Graphify actualizado en ambos servicios. Postman v2.1 válido; IB cubre las 100 rutas propias, incluido /up; la ruta interna Broker conserva método y headers S2S. Instalación limpia validada por las suites sobre bases aisladas de testing; no se reinició la base operativa. S2S y datos reales pendientes. Reconciliación de cierres incorporados o corregidos después del run pendiente de decisión. Véase [contrato vigente](../../rules/negative-pnl-contract.md).
