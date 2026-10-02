# RWD4 — volumen tradeado y PnL negativo

Estado: **En curso — descubrimiento contractual**  
Dependencias: Modules M5, Rules R2, Programs, Subscriptions, IAM, Broker Service y Finance  
Última revisión: 2026-10-02

## Decisiones confirmadas

- Volumen y PnL distribuyen Rewards por la red multinivel congelada al iniciar el run.
- La profundidad consultada a IAM se limita al máximo nivel remunerable de la plantilla congelada. El SDK actual de IB usa `getUpline` sin ese parámetro; RWD4 necesita un puerto propietario que aplique el límite o lo transmita cuando IAM lo soporte.
- Volumen se configura por programa y vigencia con `event`, `periodic` o `both`. Una posición fuente no puede originar dos Rewards iguales aunque sea observada por ambos caminos.
- PnL se configura por programa y vigencia en períodos UTC `daily`, `weekly`, `monthly` o `yearly`.
- El `server_group` es la autoridad de moneda y precisión para ambas modalidades.

## RWD4.0 — contratos que deben confirmarse

1. Broker debe confirmar el hecho de posición cerrada y la consulta paginada de actividad con ID estable, volumen, grupo, símbolo, instante, moneda, precisión y, para comisión porcentual, la comisión fuente.
2. Broker debe confirmar para PnL cuentas, balances de corte, grupo, moneda y precisión.
3. Finance debe confirmar depósitos y retiros certificados por cuenta e intervalo semiabierto. La consulta CPA de depósitos por usuario no sustituye este contrato.
4. IAM debe confirmar si puede limitar `getUpline` por profundidad. Si no, IB corta localmente el resultado y conserva la limitación como deuda de eficiencia.

No se inventan rutas, payloads ni defaults mientras esos contratos no tengan evidencia S2S.

## RWD4.1 — volumen

- Crear strategy, run durable, snapshot de red y asignación idempotente por posición/beneficiario/nivel/regla.
- Reutilizar el feed M5 solo para el barrido periódico; la entrada `event` se incorpora tras confirmar su contrato.
- Persistir evidencia Broker por posición y congelar configuración instrumental, template, comisión, moneda y precisión en el ledger.
- La comisión fija puede habilitarse cuando el contrato incluya la precisión; la porcentual requiere además la comisión fuente.

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
