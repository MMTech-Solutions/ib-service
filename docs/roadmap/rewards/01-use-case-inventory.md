# Rewards RWD1: inventario de casos de uso

Estado: **Completado y corregido por RWD2**
Dependencias: Rules R2, Subscriptions S1, Programs, Modules M5,
`auth.account.registered` V1 y Finance interno
Última revisión: 2026-10-02

## Propósito

Inventariar CPA como la primera capacidad de Rewards y separar con precisión la
causa económica de IB, la evidencia de módulos y el settlement de Finance.
RWD1 no define el transporte ni implementa pagos.

## Actores

- **auth-service:** emite el hecho de registro CPA con referido e IB.
- **Programs y Rules:** aportan programa, símbolo, regla y requisitos vigentes
  que se congelan al capturar el contexto.
- **Modules/Broker:** obtiene volumen cerrado de Broker Service y depósitos
  certificados de Finance como evidencia separada.
- **Rewards:** captura contexto, evalúa requisitos, mantiene progreso y crea la
  obligación `pending` cuando corresponde.
- **Finance:** certifica depósitos y, en una vertical posterior, confirma el
  settlement; no decide la causa ni elegibilidad de una Reward.
- **Cliente y administración:** consultan progreso según su ámbito autorizado.

## Inventario RWD1

| Área | Intención | Resultado observable | Estado |
| --- | --- | --- | --- |
| Entrada CPA | Consumir `auth.account.registered` V1 | El par referido + IB no crea un segundo contexto ante redelivery. | Aceptado |
| Contexto | Congelar requisitos CPA | Programa, módulo, asignación, versión, símbolos/grupos y requisitos permanecen auditables e inmutables. | Aceptado |
| Evidencia | Obtener volumen y depósito | Broker Service aporta solo volumen cerrado; Modules/Broker consulta Finance para depósito certificado. | Aceptado |
| Evaluación | Verificar requisitos CPA | Volumen y depósito se validan acumulativamente desde la captura, sin pesos ni red. | Aceptado |
| Progreso | Exponer estado actual | Existe un único progreso por contexto, sin historial de intentos. | Aceptado |
| Ledger | Registrar obligación | Al calificar se crea una sola Reward `pending` con causa y snapshot. | Aceptado |
| Settlement | Asentar la obligación | Finance confirma el settlement de la Reward. | Diferido |

## Decisiones cerradas

1. IB es autoridad del contexto, la evaluación y el ledger; Finance es autoridad
   de depósitos certificados y settlement.
2. Los requisitos e importes CPA proceden de la versión de regla congelada.
   RWD-A1 sustituye la selección unificada `module_code + strategy_type` por
   factories independientes de evidencia y cálculo; RWD-A2 implementará esa
   arquitectura sin cambiar la causa económica.
3. `reward_id` pertenece a `cpa_context`. El progreso no crea, copia ni decide
   una Reward.
4. Las consultas de progreso incluyen una superficie cliente restringida al IB
   autenticado y una administrativa paginada y filtrable.

## Pendiente fuera de RWD2

- Contrato de solicitud, confirmación, reversa y compensación de settlement.
- Otras estrategias de recompensa: volumen, PnL y distribución multinivel.
- Política definitiva de reintentos financieros posteriores a `pending`.
