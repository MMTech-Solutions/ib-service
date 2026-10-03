# Próxima entrega: E2E Broker, Progression y Rewards con cortes PnL históricos

Estado: **En curso; contrato histórico implementado localmente; runner PnL bloqueado por evidencia S2S Broker/IAM**
Última revisión: 2026-10-02

## Dependencias y evidencia

A1 y A2.1–A2.3 están completadas; no se repite su refactor.
Broker `ResolveNegativePnlPeriodsUseCase` consulta cuentas actualmente operativas,
usa `current_balance` y fija el corte a la hora de consulta. El cashflow se obtiene
localmente mediante `GetAccountCashFlowPeriodService`.

La decisión confirmada usa `margin_level_reads`: última lectura de la cuenta con
`unix_read_at <= occurred_until`, con empate por ID descendente. Se asume vigente
su balance hasta el corte solicitado. Broker conserva `current_balance`; no se
elimina ninguna capacidad por inferencia. Sin corte explícito, el endpoint mantiene
su comportamiento previo. El cashflow local usa `created_at` en `[baseline, corte)`.

La ampliación local incorpora corte solicitado y referencia de lectura, contempla
cuentas desactivadas/archivadas y rechaza baselines ajenas. No hay fallback al balance
actual cuando falta lectura. IB distingue falta de cobertura de error recuperable,
valida la respuesta y conserva snapshots por suscripción/cuenta/grupo/cadencia/corte.
El primer snapshot aceptado se reutiliza sin consultar de nuevo ni sobrescribirse.
Esta capacidad todavía no está conectada a un runner económico o cambio de plan.
La selección no demuestra un historial completo de elegibilidad Trading: usa
pertenencia, existencia al corte y grupo Live actual. Ese límite requiere evidencia
contractual antes de afirmar cobertura histórica completa.

## Incrementos pendientes y salida

1. **Broker–IB e IAM: S2S pendiente.** Fuente y contrato histórico implementados localmente; demostrar S2S
   cierre posterior a cambio de plan y profundidad IAM. Ampliar después contratos,
   documentación, Postman y pruebas de los servicios afectados.
2. **PnL económico: bloqueado por 1.** Formalizar BDS antes de calcular; configuración
   histórica por programa/grupo, cadencia del programa, baselines independientes,
   cierres durables de suscripciones terminadas y períodos en orden. Salida: Strategy
   tipada, evidencia/red/configuración congeladas, unicidad y recuperación aprobadas;
   runner inicialmente deshabilitado.
3. **Rewards/Finance: en curso.** Completar validación contractual, condiciones y
   holds al claim, coordinación administrativa, reconciliación y snapshots completos
   de volumen. Incorporar consultas HTTP con autorización, propiedad y Postman.
   Salida: concurrencia y reintentos sin duplicar obligaciones ni pagos.
4. **Progression: pendiente.** Validar PG1/PG2, contexto histórico, red congelada,
   duplicados, fallos parciales y placement, sin refactor de arquitectura.
5. **Activación: pendiente de 1–4.** Plan/grupo controlado y observación del backlog.
   Salida: CPA/volumen/PnL settled, placement y remuneración exclusiva del tramo
   anterior al cambio de plan. Separar evidencia local, S2S y datos reales.

## Primer cambio independiente

La reversa transmite el nivel original de la Reward a Finance; anteriormente
enviaba siempre `1`. La regresión local usa volumen de nivel 3 y comprueba payload
y transición a `reversed`. No completa el resto de garantías ni el E2E.

Finance aporta depósitos certificados CPA y settlement; Broker aporta cashflow
PnL local. No se reutilizan snapshots IB antiguos. Quedan fuera nuevos módulos,
FX, reversas de puntos, versionado de planes y nuevas notificaciones.

## Evidencia local y bloqueo de entorno

- Broker: 9 pruebas del endpoint histórico, 42 assertions; junto con las suites
  de arquitectura Forms/Scheduling, 27 pruebas y 86 assertions aprobadas.
- IB: regresiones Rewards/Progression y arquitectura, más snapshot PnL:
  91 pruebas y 614 assertions aprobadas. La regresión de pausa cubre que una Reward
  no elegible no consume el límite de pagos ni bloquea la siguiente Reward.
- La URL Broker configurada por defecto usa `broker-app`; la comprobación HTTP
  desde esta sesión falla por host desconocido. Se verificaron después contenedores
  locales activos, la ruta PnL en Broker y las rutas upline en IAM. Esa inspección
  no constituye el escenario S2S controlado exigido para habilitar el runner.
- Profundidad IAM S2S sigue pendiente. No se habilita RWD4.2 ni se declara cierre
  de E2E, validación de datos reales, configuración económica PnL o consultas HTTP
  de runs que todavía no están implementadas.

## Bug conocido: desfase entre lectura y cashflow

Se acepta el balance de la última lectura hasta el corte solicitado, sin antigüedad
máxima. Un movimiento entre ambos instantes puede ser descontado del cashflow antes
de estar reflejado en el balance. Ejemplo: inicio 1.000, lectura 900 a las 23:58,
depósito 100 a las 23:59 y corte 00:00 produce PnL -200; si el depósito ya se aplicó,
el PnL correcto sería -100. Puede originar recompensa inmerecida.

Los depósitos emiten `margin_level_update`, reduciendo la exposición, pero los
retrasos, desorden o pérdidas de eventos no quedan resueltos. `created_at` es el
timestamp aceptado del ledger, sin garantía adicional sobre aplicación en Trading.
Se conservan ID e instante de lectura para auditoría. **No se implementan detección,
tiempo de gracia ni bloqueo por este desfase**, por decisión explícita del usuario.
La ausencia de una lectura es un error distinto y sí impide crear el snapshot.

## Garantías financieras avanzadas

El claim excluye holds de reconciliación y operaciones de cancelación/compensación
incompletas; las operaciones administrativas rechazan una lease de settlement
activa. El runner consulta operabilidad de plan y módulo mediante sus puertos,
libera claims no elegibles y continúa con otras Rewards. No exige que una
suscripción histórica siga activa. Estas medidas no completan por sí solas toda
la coordinación concurrente pendiente. Settlement y reversa validan además nivel
e importe entero devueltos por Finance; reconciliación/cancelación comparten
validación de identidad, moneda, wallet, nivel y origen de reversa. La evidencia
S2S Finance permanece pendiente.

Pendiente contractual de niveles: el adapter IAM de Rewards normaliza nivel 1
de IAM como nivel de distribución 0; Finance exige `network_level >= 1`.
El gateway actual transmite el nivel persistido. No se cambia esa convención por
inferencia: el cierre Finance de volumen/PnL debe fijar y probar la traducción
contractual conservando el nivel económico original en IB y sus reversas.
