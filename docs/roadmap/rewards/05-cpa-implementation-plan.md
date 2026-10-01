# RWD2: implementación y contract tests

Estado: **RV1 en curso**
Última revisión: 2026-09-30

## Contratos y capas

- Rewards define el caso de captura, el runner, repositories de contexto,
  progreso y ledger, y el registry de estrategias de ejecución.
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
- Las rutas de RV3 usan `UserContext`: cliente limitado al IB principal;
  administración autorizada y paginada. Postman, Resources y pruebas se
  actualizan en el mismo cambio de rutas.
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
