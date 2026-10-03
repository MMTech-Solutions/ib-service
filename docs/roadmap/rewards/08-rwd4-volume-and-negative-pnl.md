# RWD4 — volumen tradeado y PnL negativo

Estado: **RWD4.1 implementado; RWD4.2a implementado localmente y pendiente de evidencia S2S; runner económico y cierre RWD4.2 bloqueados**
Dependencias: Modules M5, Rules R2, Programs, Subscriptions, IAM y Broker Service
Última revisión: 2026-10-02

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
  por posición/beneficiario/nivel/regla. RWD-A2 alineará su cálculo mediante factory
  sin cambiar el recorrido por actividad ni afirmar garantías nuevas implementadas.
- Reutilizar el feed M5 para el barrido periódico y el evento Avro V1 como disparador de resolución en Broker. Ambos caminos comparten la misma idempotencia económica.
- Persistir evidencia de la posición y congelar configuración instrumental, template, participación, rates de nivel/personal/master, moneda y precisión en el ledger.
- El procesamiento durable, puertos históricos y creación económica de RWD4.1
  están implementados. La denominación anterior de pipeline no impone una
  arquitectura común; RWD-A1 la sustituye. Contract tests S2S de Broker siguen
  pendientes y no se confunden con evidencia local.

## RWD4.2 — PnL negativo

- RWD4.2a publica el puerto `ResolveNegativePnlPeriodsPort`, Data V1 y adapter HTTP. Broker resuelve balance, cashflow y PnL firmado; IB no replica su contabilidad.
- El runner posterior creará la configuración histórica y los runs por cadencia, persistirá primero cada snapshot proveedor y después aplicará regla, red y tasas congeladas.
- Persistirá referencias de cuenta, balances, totales y referencias opacas de flujo sin copiar movimientos financieros completos.
- El runner económico no forma parte de RWD4.2a. Está **bloqueado** hasta completar
  [RWD-A2](10-rwd-a2-cpa-volume-refactor.md) y demostrar S2S el corte histórico
  Broker–IB y profundidad IAM. Alinear documentación no levanta esos gates.

## Diseño PnL acordado; implementación posterior

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
  formalizarán en BDS antes del código económico; RWD-A2 no cambia cálculos.
- Un runner genera Rewards idempotentes; settlement las asienta posteriormente.
  CPA, volumen y PnL pueden acumularse, con configuración aplicable no ambigua por
  modalidad/contexto. La formalización de esa unicidad pertenece al diseño económico
  previo a RWD4.2, no al refactor A2.

## Dependencia pendiente: corte histórico Broker–IB

El contrato actual recibe usuario/baselines y obtiene balance actual y corte real
de consulta. No solicita un corte final histórico. Ampliarlo para que Broker
resuelva balance y cashflow local en el mismo instante UTC solicitado; no sustituir
balance histórico por `current_balance` ni por snapshots del antiguo IB de Broker,
que será retirado. Finance Service no participa en evidencia PnL; solo settlement.

La fuente histórica de balance no quedó demostrada. El gate exige contrato
versionado/compatible, cobertura de cuentas históricamente elegibles, límites
temporales exactos y error explícito sin Reward/avance de baseline cuando falta
evidencia. La evidencia S2S reproducible debe cubrir cambio de plan y consulta
posterior al cierre. Esta entrega documental no modifica Broker ni su endpoint.

RWD4.0 conserva la evidencia del contrato actual; demostrarlo no basta para cerrar
la ampliación histórica. RWD4.2a mantiene su estado, sin declarar soporte nuevo.

## Criterios de salida

- Tests de contrato contra Broker e IAM; pruebas de rangos UTC, precisión, deduplicación y concurrencia.
- Volumen `event`, `periodic` y `both` producen una sola Reward por identidad económica.
- PnL cubre primera lectura, períodos sucesivos, depósitos, retiros, múltiples cuentas y cambio de cadencia.
- RWD-A2 completada con pruebas de equivalencia; cortes históricos Broker e IAM
  demostrados S2S; cierre pendiente de suscripción terminada no pierde ni duplica
  Rewards y no remunera actividad posterior al cambio.
- La evidencia de IB Lab y la validación S2S real son necesarias para marcar RWD4 como completado.
