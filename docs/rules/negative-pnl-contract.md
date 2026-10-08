# Contrato N-PnL realizado por intervalos

Estado: obligatorio; cambio incompatible, sin fallback. Revisión: 2026-10-06.

## Resolución Broker

`POST /api/broker/v1/internal/accounts/negative-pnl-periods/resolve` exige
`X-Internal-Token` y `X-Internal-Source: mmt-ib-service`.

```json
{"subjects":[{"external_user_id":"usuario-a"},{"external_user_id":"usuario-b"}],"occurred_from":"2026-10-01T00:00:00Z","occurred_until":"2026-10-02T00:00:00Z"}
```

Usuarios únicos, lote no vacío y máximo 100 por defecto. Inicio anterior al fin,
fin no futuro. Se rechazan baselines y el payload antiguo. Broker e IB alinean
`IB_NEGATIVE_PNL_SUBJECT_BATCH_SIZE` y `REWARDS_NEGATIVE_PNL_SUBJECT_BATCH_SIZE`.

Broker encuentra cuentas LIVE/B_BOOK del usuario existentes al corte, incluidas
archivadas o inactivas. Consulta cierres por `unix_closed_at` en milisegundos en
`[inicio, fin)`. Suma exclusivamente `profit`, con ambos signos y escala 10,
sin redondeo por posición. No usa swap, comisión, balance ni cashflow.

`data` es un array plano de resultados de cuenta:

```json
{"success":true,"data":[{"external_user_id":"usuario-a","trading_account_id":"UUID","external_trader_id":"12345","server_group_id":"UUID","currency_code":"USD","currency_precision":2,"occurred_from":"2026-10-01T00:00:00.000000Z","occurred_until":"2026-10-02T00:00:00.000000Z","npnl":"-600.0000000000","position_ids":["UUID-ganadora","UUID-perdedora"]}],"meta":{"completed_subjects":["usuario-a","usuario-b"]}}
```

`npnl` es firmado pese al nombre: positivo, negativo o cero.
`position_ids` contiene todas las posiciones utilizadas, únicas y ordenadas
por ID. Sin cierres, la cuenta devuelve cero y lista vacía. Un usuario sin
cuentas se confirma en `meta.completed_subjects`, exactamente una vez por
usuario solicitado. No se confirma un lote parcialmente resuelto.

IB rechaza usuarios ajenos, cuentas o posiciones duplicadas, intervalos
incompatibles, importes no decimales string y moneda/precisión inválidas.
409, timeout y 5xx son recuperables; otros errores y 2xx incompatibles producen
`evidence_invalid`. La ausencia de lecturas de margen no es un error PnL.

## Persistencia y cálculo IB

El run congela red IAM, niveles, configuración, tasas y mínimo. Usa intervalo
común por contexto suscripción/módulo/cadencia y un cursor temporal consecutivo,
sin baseline por cuenta. Activación y reanudación fijan el inicio; cambios de
plan cierran el tramo anterior y abren el siguiente en el mismo límite.

`negative_pnl_periods` conserva ambos límites, inputs, receipts y outcomes.
`negative_pnl_cut_snapshots` congela por cuenta intervalo, identidades, moneda,
precisión, npnl y position_ids. Los reintentos conservan receipts y consultan
solo referidos pendientes. Evidencia completa y válida habilita el estado
ready y avance del cursor en una transacción; errores posteriores continúan
la misma generación. No se crean obligaciones con evidencia parcial.

IB compensa por nivel y moneda. Precisión contradictoria para una misma moneda
deja el período pendiente con `evidence_invalid`. Solo el agregado negativo
origina Reward pending si cumple mínimo y redondeo. Los resultados positivos,
cero o bajo mínimo viven en `outcomes.aggregates`, sin fila en rewards.
Las contribuciones y sus IDs quedan en el snapshot económico; reward_evidence
vincula las cuentas participantes. Finance conserva payload congelado,
idempotencia y nivel económico + 1. La finalización del run no requiere que
sus Rewards estén settled.

Las lecturas administrativas de períodos incluyen intervalo, receipts y
outcomes. Los futuros reportes podrán comparar los IDs actuales del intervalo
con los congelados. Eso detecta cambios de pertenencia, no cambios de profit
bajo el mismo ID. La reconciliación y correcciones económicas quedan pendientes.

## Configuración y actualización

La selección modules por programa, asignación explícita y plantilla común por
módulo permanecen vigentes. No se introducen filtros económicos por server group.
No hay compatibilidad con balances ni cálculo dual. Las migraciones originales
se ajustan al modelo inicial; se requiere recreación de la base para estos
cambios. Validar instalación limpia únicamente en base de pruebas desechable.
No ejecutar migrate:fresh sobre una base operativa como parte de la entrega.

Pruebas locales no acreditan S2S ni aceptación con datos reales.

## Extensión Copy Trading

Copy Trading implementa el mismo contrato bajo /api/copy-trading/v1/internal/accounts/negative-pnl-periods/resolve. Su selección certificada incluye exclusivamente cuentas creadas desde su plataforma y excluye externas asociadas; IB no aplica el criterio Broker LIVE/B_BOOK al proveedor Copy Trading. Fórmula, completitud, snapshots y errores permanecen iguales. El runner y la captura seleccionan proveedor por módulo; los snapshots nuevos conservan provider_code y la evidencia conserva copy_trading_service. Snapshots anteriores sin provider_code se interpretan como Broker, sin reescribirlos. No hay compensación entre módulos.

Véase [contrato proveedor Copy Trading](copy-trading-provider-contract.md). Implementación local; aceptación S2S pendiente.