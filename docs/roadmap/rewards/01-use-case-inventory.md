# Rewards RWD1: inventario de casos de uso

Estado: **Completado; primera vertical bloqueada**
Dependencias: [`README.md`](README.md), [`rewards.bds.md`](../../bds/rewards.bds.md),
Rules R2, Subscriptions S1, `auth-service` y Finance
Última revisión: 2026-09-28

## Propósito

Inventariar la primera vertical de Rewards: CPA. No define aún la fórmula
económica ni implementa pagos. Establece el ledger propio de IB, la captura
idempotente del hecho y la frontera que separa obligación de settlement.

## Actores

- **auth-service:** emite el hecho de registro/adquisición CPA.
- **Rewards:** captura el hecho, resuelve contexto, conserva el ledger y solicita
  el pago cuando la obligación es calculable.
- **Rules:** aporta regla, asignación y versión económicas vigentes.
- **Subscriptions:** aporta beneficiario y contexto histórico aplicable.
- **Finance:** recibe la solicitud y confirma el settlement; no decide la causa
  de la recompensa.
- **Administrador de IB:** consulta el ledger y su evidencia; no altera la causa
  ni fuerza un settlement.

## Inventario RWD1

| Área | Intención | Resultado observable | Estado |
| --- | --- | --- | --- |
| Entrada CPA | Consumir `auth.account.registered` V1 | Se valida un hecho con `user_id` e `ib_user_id`; las redeliveries del mismo par no generan una segunda captura. | Aceptado |
| Contexto | Congelar contexto CPA al recibir el hecho | El referido, plan, programa, asignación, versión, scope y condiciones aplicables quedan auditables y no cambian por modificaciones posteriores. | Aceptado |
| Ledger | Registrar obligación CPA | Existe una recompensa idempotente en la tabla `rewards`, ledger de IB, con causa y snapshot inmutables y estado inicial `pending`. | Bloqueado por fórmula CPA |
| Pago | Solicitar settlement a Finance | La solicitud usa una clave idempotente y referencia la recompensa del ledger; Finance recibe el contexto mínimo verificable. | Bloqueado por fórmula y frontera Finance |
| Settlement | Confirmar transacción asentada | Finance confirma el settlement y Rewards cambia el estado de la misma recompensa de `pending` a `settled`, conservando evidencia de la confirmación. | Bloqueado por frontera Finance |
| Administración | Consultar ledger | Administración observa causa, snapshot, estado y referencias financieras sin mutar la recompensa. | Diferido a la vertical CPA |
| Volumen / PnL | Evaluar otras estrategias | Se procesan hechos y métricas propios de cada estrategia. | Diferido |
| Reversas / reintentos | Corregir ciclo financiero | Se establecen estados, compensaciones y política de reintento. | Pendiente de decisión BDS |

## Decisiones cerradas

1. La tabla `rewards` es el ledger de Rewards y pertenece a IB; Finance solo es
   autoridad del settlement (`BR-REWARD-005`).
2. La recompensa se registra `pending` y pasa a `settled` únicamente tras la
   confirmación de Finance (`BR-REWARD-008`).
3. Kafka es la vía primaria de entrada CPA. El contrato requerido es
   `auth.account.registered` V1 con `user_id` e `ib_user_id`, no un endpoint
   HTTP alternativo.
4. El par `user_id` + `ib_user_id` es la clave de idempotencia de la captura
   del hecho Auth. El instante de captura es el de recepción en IB.
5. `Money`, `PositiveMoney` y `Currency` serán value objects del SharedKernel
   de IB: minor units exactas, código ISO 4217 y precisión; no `float` ni FX.
   `Number` se difiere hasta tener semántica transversal independiente.
6. El precedente de `broker-service` solo orienta el patrón de Kafka, snapshots
   e importes minor-unit; no transfiere su fórmula ni sus contratos internos.

## Bloqueos y decisiones pendientes

| Tema | Propietario | Condición de desbloqueo |
| --- | --- | --- |
| Fórmula CPA | Negocio / Rewards | Definir importe, moneda, beneficiario y si existe distribución multinivel. |
| Verificación de depósitos Finance | Rewards + Finance | Diseñar una consulta S2S por usuario y rango temporal que certifique depósitos externos ya asentados. Los endpoints actuales de direcciones crypto no bastan: son paginados y no filtran por fecha. |
| Solicitud y confirmación Finance | Rewards + Finance | Diseñar el puerto/Data V1 de solicitud idempotente y la confirmación de settlement. `transfer-credits` exige `idempotency_key` y rechaza reutilizarlo con parámetros distintos. |
| Estados posteriores | Rewards / BDS | Definir fallo, reintento, reversa y compensación sin alterar la causa ni el snapshot. |

## Criterios de salida

- Actores, intenciones, resultados, dependencias y bloqueos de RWD1 están
  explícitos.
- Se distingue el ledger de obligaciones de IB del settlement de Finance.
- Ninguna fórmula CPA, contrato externo o transición no acordada se presenta
  como decisión cerrada.
