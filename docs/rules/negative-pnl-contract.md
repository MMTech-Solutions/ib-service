# Contrato N-PnL por lotes y agregación económica

Estado: obligatorio; cambio incompatible, sin fallback. Revisión: 2026-10-06.

## Resolución Broker

`POST /api/broker/v1/internal/accounts/negative-pnl-periods/resolve` exige
`X-Internal-Token` y `X-Internal-Source: mmt-ib-service`.

```json
{
  "subjects": [
    {"external_user_id": "usuario-a", "baselines": [
      {"account_id": "00000000-0000-7000-8000-000000000001", "balance_after": "2000.00", "occurred_until": "2026-10-05T00:00:00Z"}
    ]},
    {"external_user_id": "usuario-b", "baselines": []}
  ],
  "occurred_until": "2026-10-06T00:00:00Z"
}
```

`subjects` es una lista no vacía de usuarios únicos. Cada `baselines` es una
lista presente, con máximo 100 cuentas, IDs únicos y decimales como strings.
El corte es obligatorio, no futuro; las baselines lo preceden. La petición
no contiene niveles, tasas ni plantilla. Se retira el payload de usuario único.

El máximo por lote es 100 por defecto: Broker usa
`IB_NEGATIVE_PNL_SUBJECT_BATCH_SIZE`, IB usa `REWARDS_NEGATIVE_PNL_SUBJECT_BATCH_SIZE`.
Configurar ambos al mismo límite positivo. IB divide la red pendiente en lotes
acotados con el mismo corte; no necesita enviar nuevamente receipts congelados.

La respuesta usa `data` como array plano de cuentas. Cada ítem conserva
`status`, `account_id`, `external_user_id`, `server_group_id`, `currency_code`,
`currency_precision`, `balance_before`, `balance_after`, `occurred_from`,
`occurred_until`, `deposits`, `withdrawals`, `cash_flow_net`, `net_pnl`,
`evidence`, `balance_read_id` y `balance_read_at`. `net_pnl` conserva ambos signos;
una cuenta inicial entrega estado `baseline` y PnL nulo.

```json
{"success": true, "data": [], "meta": {"completed_subjects": ["usuario-sin-cuentas"]}}
```

`meta.completed_subjects` contiene exactamente una vez cada usuario solicitado,
incluidos aquellos sin cuentas. El orden no tiene semántica. Una falta de cobertura
impide el éxito del lote completo; no se confirma una consulta parcial.
Se conservan 422 de validación/contrato y `HISTORICAL_PNL_COVERAGE_UNAVAILABLE`;
409, timeout y 5xx son recuperables. IB rechaza cuentas ajenas o duplicadas,
lecturas futuras, aritmética incompatible y baselines omitidas o discontinuas.

## Configuración y cálculo IB

GET/PUT `.../programs/{program}/negative-pnl-configuration` sustituyen `groups`
por `modules`: `{"cadence":"daily","modules":[{"module_id":"UUID","rule_version_id":"UUID"}]}`.
`modules=[]` retira la configuración. No se admite `server_group_id` en la
selección. La regla publicada conserva el binding de plantilla; sus niveles y
tasas son comunes a las cuentas del módulo. Se exige asignación explícita.

IB conserva usuario → nivel desde IAM, asocia las cuentas y agrega por nivel y
`currency_code` dentro de suscripción/módulo/período. La precisión no crea grupos:
se asume conscientemente única por moneda; una contradicción deja el período
pendiente con `evidence_invalid`, sin avanzar baselines ni generar Rewards.

La preparación conserva toda la evidencia antes de avanzar baselines. El cálculo
usa decimales exactos, mínimo antes del único redondeo half-up y las tasas personales
congeladas. Cada total negativo crea una Reward `pending`; cada resultado se conserva
en `outcomes.aggregates[nivel][moneda]`. El snapshot de Reward incluye `aggregate`
(total y contribuciones), `inputs`, importe minor y modo de redondeo. Las referencias
de todas las cuentas contribuyentes están vinculadas en `reward_evidence`.
Las lecturas administrativas conservan el envelope; ya no aceptan filtro server group.
Finance mantiene su contrato y nivel económico + 1; no cambia la solicitud de
obligaciones históricas. La fuente y fórmula del PnL por cuenta permanecen intactas.

## Actualización operativa sin compatibilidad

1. Con la versión anterior, completar períodos abiertos y detener generación PnL.
2. Respaldar datos y resolver selecciones divergentes por módulo explícitamente.
3. Alinear cursores de los trabajos que se consolidarán; ejecutar la migración
   con generación detenida. Rechaza períodos abiertos, selecciones divergentes,
   cursores incompatibles y baselines duplicadas. No decide una regla por defecto.
4. Desplegar Broker e IB con contratos nuevos, límites de lote coincidentes y
   ejecutar verificación controlada antes de reanudar generación.

La migración copia selecciones coincidentes a `program_negative_pnl_modules`,
consolida trabajos/baselines manteniendo el cursor y conserva tablas originales
para auditoría. Finaliza los trabajos anteriores activos; no altera Rewards,
settlements ni períodos históricos. No vuelve a remunerar desde el origen.
No hay dos cálculos ni adaptador de compatibilidad. Tras crear trabajos agregados,
el downgrade exige restauración del respaldo y coordinación operativa; `down`
rechaza convertirlos automáticamente en trabajos por grupo.

Pruebas locales no acreditan S2S ni aceptación con datos reales.
