# RWD3.2: correcciones y reconciliación selectiva

Estado: **Implementado; validación contractual pendiente**
Última revisión: 2026-10-03

## Alcance

Administración puede cancelar una Reward no asentada, revertir una comisión asentada y crear una compensación como nueva Reward. Todas requieren `ib.rewards.manage`, motivo e idempotencia; cliente conserva solo lectura por propiedad.

Una cancelación consulta Finance antes de modificar IB. Una reversa crea `commission_type=reversal` contra el evento Finance original. Una compensación conserva el origen de la Reward reversada, usa importe explícito en la misma moneda/precisión y se asienta como comisión independiente de la misma modalidad.

## Reconciliación

`rewards:reconcile-settlements` se programa cada cinco minutos, pero selecciona operaciones incompletas, pagos fallidos inciertos y Rewards con hold de reconciliación. No inspecciona por antigüedad las Rewards `settled` o `reversed` que ya fueron confirmadas.

Una discrepancia contractual activa un hold y bloquea nuevas operaciones. No existe desbloqueo manual ni corrección automática: una consulta posterior consistente a Finance elimina el hold. Finance no modifica unilateralmente comisiones IB asentadas; una auditoría histórica fuera de este flujo requiere una política independiente.

El [cierre local E2E](11-e2e-historical-pnl.md#entrega-de-cierre-local-e2e)
completa la lease compartida por Reward, token y vencimiento, confirmación local
atómica, recuperación de solicitudes congeladas y cancelación incierta sin
cancelar obligaciones que pudieron pagarse. Las operaciones con hold se consultan
por clave; no vuelven a enviar pagos hasta confirmar una respuesta compatible.

## Errores HTTP de operaciones administrativas

Cancelación, reversa y compensación expresan sus rechazos de negocio con excepciones tipadas de Rewards que extienden `ApiException`/`MmtException`: Reward inexistente (`404`), bloqueada por reconciliación (`409`) u operación no permitida por su estado (`422`). Los controllers no convierten esos casos en errores de validación de Laravel; el handler global los normaliza con `ApiResponse::error`. Los fallos reintentables del adapter Finance permanecen internos y se reflejan en el estado de la operación, sin filtrar detalles técnicos.

## Cierre pendiente

La adaptación local del 2026-10-03 al contrato real de Finance recupera moneda
y slug mediante el listado paginado de wallets del beneficiario, enlazado por
`ib_wallet_id` del evento. Conserva el DTO y las validaciones de solicitudes
congeladas; no modifica Finance ni presupone USD. Las pruebas incluyen
paginación, errores de wallet, hold y recuperación consistente, cancelación,
reversa, compensación y vencimiento de la lease durante la consulta.

Evidencia local: `FinanceRewardRecoveryGatewayTest`, `RewardFinancialRecoveryTest`,
`Rewards/RewardFinancialOperationTest`, `Rewards/RewardSettlementTest` y
`tests/Architecture/`: **64 pruebas, 349 assertions aprobadas**. Pint aprobado.

Contract tests y smoke S2S contra Finance real, incluidos permisos de la identidad interna para crear, revertir y consultar comisiones. IB Lab aporta la evidencia reproducible antes del cierre operativo.
