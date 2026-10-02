# RWD4 — volumen tradeado y PnL negativo

Estado: **RWD4.1 implementado; RWD4.2a implementado localmente y pendiente de evidencia S2S; runner RWD4.2 pendiente**
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

- Crear strategy, run durable, snapshot de red y asignación idempotente por posición/beneficiario/nivel/regla.
- Reutilizar el feed M5 para el barrido periódico y el evento Avro V1 como disparador de resolución en Broker. Ambos caminos comparten la misma idempotencia económica.
- Persistir evidencia de la posición y congelar configuración instrumental, template, participación, rates de nivel/personal/master, moneda y precisión en el ledger.
- El pipeline durable, los puertos históricos y la creación económica de RWD4.1 están implementados. Los contract tests S2S de Broker permanecen como evidencia externa pendiente y no bloquean el cierre del vertical reproducible localmente.

## RWD4.2 — PnL negativo

- RWD4.2a publica el puerto `ResolveNegativePnlPeriodsPort`, Data V1 y adapter HTTP. Broker resuelve balance, cashflow y PnL firmado; IB no replica su contabilidad.
- El runner posterior creará la configuración histórica y los runs por cadencia, persistirá primero cada snapshot proveedor y después aplicará regla, red y tasas congeladas.
- Persistirá referencias de cuenta, balances, totales y referencias opacas de flujo sin copiar movimientos financieros completos.
- El runner económico no forma parte de RWD4.2a y permanece pendiente de la evidencia S2S de RWD4.0.

## Criterios de salida

- Tests de contrato contra Broker e IAM; pruebas de rangos UTC, precisión, deduplicación y concurrencia.
- Volumen `event`, `periodic` y `both` producen una sola Reward por identidad económica.
- PnL cubre primera lectura, períodos sucesivos, depósitos, retiros, múltiples cuentas y cambio de cadencia.
- La evidencia de IB Lab y la validación S2S real son necesarias para marcar RWD4 como completado.
