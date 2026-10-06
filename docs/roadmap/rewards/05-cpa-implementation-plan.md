# RWD2: implementación y contract tests
> Nota de vigencia (2026-10-06): el diseño CPA anterior se conserva aquí como
> evidencia histórica. El modelo vigente y su implementación se describen en
> [CPA por puntos](12-cpa-points-refactor.md) y en el BDS de Rewards.
> CPA usa dos umbrales de puntos, contribuciones persistidas y fuentes independientes;
> no requiere una asignación genérica a un módulo único.


Estado: **RV1-RV3 implementados; contract tests pendientes**

RV2 conserva evidencia inmutable por Reward y hecho fuente; no usa el progreso
como historial de intentos ni como ledger de actividad.
Última revisión: 2026-10-02

## Contratos y capas

- Rewards define captura, runner y repositories de contexto, progreso y ledger.
  La prescripción original de registry de ejecución queda sustituida por
  [RWD-A1](09-rwd-a1-documentation-alignment.md): RWD-A2.1 extrajo evaluación CPA
  a Strategy y factory económica; [RWD-A2](10-rwd-a2-cpa-volume-refactor.md)
  completó las factories de proveedores y el cálculo de volumen en A2.2/A2.3.
- Modules publica un puerto Input/Data V1 especializado de evidencia CPA. Su
  query declara módulo, referido externo, intervalo UTC semiabierto, moneda y
  snapshot instrumental; su resultado entrega páginas de hechos normalizados y
  referencias de fuente, nunca sumas finales ni una decisión CPA.
- `Modules/Sources/Broker` implementa ese puerto con dos adapters: Broker
  Service para `closed_trading_volume` y Finance interno para depósitos
  certificados. Ambos tienen timeout, límites, paginación, correlación,
  normalización y errores recuperables propios.
- Progression conserva sin cambios `ListProgressionActivitiesPort` y su flujo
  de actividad M3.

## Seguridad y operación

- Auth, Broker Service y Finance se validan como identidades S2S conocidas.
- El runner no abre transacciones durante I/O remoto y registra correlation ID,
  fuente, corte y resultado sin registrar tokens ni payloads sensibles.
- Las rutas de RV3 usan `UserContext`: cliente limitado por propiedad al IB
  principal; administración autorizada mediante `ib.rewards.manage` y paginada.
  Postman, Resources y pruebas se actualizan en el mismo cambio de rutas.
- El contrato de Auth debe verificarse contra su envelope V1 real antes de
  activar RV1. Las pruebas de Broker Service y Finance son contract tests, no
  sustitutos por fixtures de producción.

## Matriz de pruebas

- Captura: redelivery, contexto inexistente/no aplicable, snapshot inmutable y
  unicidad persistente. La configuración CPA V1 declara explícitamente
  `currency_precision`; IB no intenta inferirla de ICU ni de un proveedor externo.
- Evidencia: cursor, límite, rango, usuario, símbolos/grupos, unidad, decimal,
  timeout, respuesta inválida e indisponibilidad de cada fuente.
- Evaluación: cumplimiento parcial, moneda distinta, ambos requisitos, módulo
  pausado/inactivo, reintento posterior a error y concurrencia.
- Ledger: una sola Reward `pending` por contexto, causa/snapshot intactos y
  ausencia de integración de settlement.
- Lectura: forma `data`, filtros/meta, autorización de cliente y administración
  y ausencia de datos sensibles.

## Criterio de cierre

RWD2 se considerará implementado solo cuando RV1–RV3 funcionen de extremo a
extremo, existan contract tests de los dos proveedores y las rutas de lectura
estén reflejadas en Postman. Settlement conserva un roadmap y evidencia propios.
