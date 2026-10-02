# RWD4 — volumen tradeado y PnL negativo

Estado: **RWD4.1 implementado; RWD4.2 pendiente de contratos**
Dependencias: Modules M5, Rules R2, Programs, Subscriptions, IAM, Broker Service y Finance  
Última revisión: 2026-10-02

## Decisiones confirmadas

- Volumen y PnL distribuyen Rewards por la red multinivel congelada al iniciar el run.
- La profundidad consultada a IAM se limita al máximo nivel remunerable de la plantilla congelada. El SDK actual de IB usa `getUpline` sin ese parámetro; RWD4 necesita un puerto propietario que aplique el límite o lo transmita cuando IAM lo soporte.
- Volumen se configura por programa y vigencia con `event`, `periodic` o `both`; el valor predeterminado es `periodic`. Una posición fuente no puede originar dos Rewards iguales aunque sea observada por ambos caminos.
- PnL se configura por programa y vigencia en períodos UTC `daily`, `weekly`, `monthly` o `yearly`.
- El `server_group` es la autoridad de moneda y precisión para ambas modalidades.
- La base de volumen se calcula desde la posición: fija = `volume × participation_rate`; porcentual = `broker_granted_commission × participation_rate`. La plantilla distribuye esa base por nivel y cada beneficiario aplica su `personal_rate` y, solo si `is_master`, su `master_rate`, todos congelados en la Reward.
- El evento Avro de posición cerrada de la modalidad `event` lo publica Trading directamente a IB y solo funciona como disparador. IB resuelve la posición en Broker por `order_id` + `external_trader_id`/`login`; Broker entrega entonces volumen, comisión otorgada, instrumento, grupo, moneda y precisión congeladas. `broker-service` también entrega esos campos en su feed paginado para `periodic`.
- Existe una carrera conocida: Trading publica el mismo evento para Broker e IB, así que IB puede consultar Broker antes de que Broker haya persistido el cierre. `409 CLOSED_POSITION_NOT_READY`, timeout y 5xx se tratan como recuperables mediante receipts durables. Trading Account Service permanece como mejora futura para eliminar la carrera, no como requisito para habilitar `event`.

## RWD4.0 — contratos que deben confirmarse

1. Trading debe confirmar el evento Avro de posición cerrada con `id` estable y `login` (header o payload coherente). El contrato económico se resuelve en Broker mediante consulta interna por ambos valores; Broker debe confirmar tanto esa consulta como el feed paginado equivalente para `periodic`.
2. Broker debe confirmar para PnL cuentas, balances de corte, grupo, moneda y precisión.
3. Finance debe confirmar depósitos y retiros certificados por cuenta e intervalo semiabierto. La consulta CPA de depósitos por usuario no sustituye este contrato.
4. IAM debe confirmar si puede limitar `getUpline` por profundidad. Si no, IB corta localmente el resultado y conserva la limitación como deuda de eficiencia.

No se inventan rutas, payloads ni defaults mientras esos contratos no tengan evidencia S2S.

## RWD4.1 — volumen

- Crear strategy, run durable, snapshot de red y asignación idempotente por posición/beneficiario/nivel/regla.
- Reutilizar el feed M5 para el barrido periódico y el evento Avro V1 como disparador de resolución en Broker. Ambos caminos comparten la misma idempotencia económica.
- Persistir evidencia de la posición y congelar configuración instrumental, template, participación, rates de nivel/personal/master, moneda y precisión en el ledger.
- El pipeline durable, los puertos históricos y la creación económica de RWD4.1 están implementados. Los contract tests S2S de Broker permanecen como evidencia externa pendiente y no bloquean el cierre del vertical reproducible localmente.

## RWD4.2 — PnL negativo

- Crear configuración histórica de períodos por programa y runner por período cerrado.
- Modules compone balances Broker y flujos Finance; Rewards aplica la fórmula, la red y las tasas congeladas.
- Persistir referencias de cuenta, balances y flujos como evidencia sin copiar movimientos financieros individuales.
- PnL no inicia implementación económica hasta cerrar los contratos de RWD4.0.

## Criterios de salida

- Tests de contrato contra Broker, Finance e IAM; pruebas de cursor, rangos UTC, precisión, deduplicación y concurrencia.
- Volumen `event`, `periodic` y `both` producen una sola Reward por identidad económica.
- PnL cubre primera lectura, períodos sucesivos, depósitos, retiros, múltiples cuentas y cambio de cadencia.
- La evidencia de IB Lab y la validación S2S real son necesarias para marcar RWD4 como completado.
