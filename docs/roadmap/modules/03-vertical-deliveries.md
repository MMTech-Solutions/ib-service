# Modules: roadmap por entregas verticales

Estado: **M1 completado**
Dependencia para aprobación: etapas 1 y 2

## Principio de corte

Cada entrega debe producir un comportamiento observable atravesando entrada,
caso de uso, dominio y persistencia cuando corresponda. Una carpeta o una capa
aislada no constituye una entrega.

## Secuencia propuesta

### M1a — Sincronización y listado del catálogo

Entrega el registro cerrado inicial de Broker, persistencia PostgreSQL,
repositorio en memoria, `modules:sync` y el listado administrativo paginado.
Incluye capacidades activas e inactivas y autorización RBAC.

Criterio de aceptación: un despliegue sincroniza Broker con `deposits` y
`closed_trading_volume`; una repetición no produce cambios y un operador con
`ib.modules.manage` consulta el catálogo mediante la API versionada.

### M1b — Catálogo y control operativo

Completa la consulta y administración del catálogo y permite pausar o reanudar
su procesamiento con trazabilidad.

Completa M1 con:

- API administrativa versionada con autorización.
- detalle con capacidades embebidas, sin endpoint exclusivo;
- edición de datos administrativos permitidos y endpoints dedicados para
  activar, desactivar, pausar y reanudar;
- objetos `Data`/Commands de entrada y respuestas normalizadas;
- casos de uso internos invocados por los adapters HTTP y Console de M1;
- modelo de dominio acordado para catálogo y control;
- historial auditable de cambios operativos;
- contract tests ejecutados contra ambas implementaciones de repositorio;
- pruebas del recorrido HTTP principal.

Criterio de aceptación: el despliegue sincroniza un módulo con sus
capacidades; un operador autorizado lo consulta, lo pausa indicando un motivo y
observa la condición y el historial. Al desactivarlo, el módulo queda inactivo
con evidencia operativa y los módulos independientes no cambian.

BR-MODULE-015 permanece vigente. La evidencia de actividad rechazada se
verifica en M3, cuando exista un consumidor real de actividad.

Evidencia de cierre: migraciones, rutas y suites ejecutadas se registran en
[`05-first-delivery-implementation.md`](05-first-delivery-implementation.md).

M1 no publica puertos inter-feature anticipando consumidores futuros. Sus
endpoints y el comando son adapters de entrada del propio feature, no evidencia
suficiente para diseñar el contrato que necesitará otro feature.

## Primera entrega consumidora: Plans P1

`Plans P1` no es una entrega de Modules. Es el siguiente vertical del producto
y el primer consumidor real del catálogo. Cuando se inventaríe su caso de uso,
`Modules` expondrá el puerto mínimo que esa necesidad justifique. El puerto
pertenecerá a `Modules` y su implementación será un caso de uso de `Modules`,
pero se diseñará y validará como parte del vertical de Plans.

No se avanza a Programs antes de completar P1: un programa pertenece a un plan
y solo puede usar módulos habilitados por ese plan.

### M2 — Catálogo externo de instrumentos

Define el puerto de salida y el adaptador temporal hacia Broker para consultar
server groups, símbolos e instrumentos. Devuelve identidades contractuales
normalizadas y conserva la frontera necesaria para sustituir al proveedor.

Criterio de aceptación preliminar: un caso de uso de `Modules` obtiene símbolos
por módulo y contexto sin exponer IDs internos de Broker al consumidor.

### M3 — Fuentes de actividad

M3 publica para Progression una consulta pull-only paginada y versionada de
actividad normalizada. El contrato, campos, cursor, operatividad y límites de
seguridad están definidos en
[`06-m3-progression-activity-contract.md`](06-m3-progression-activity-contract.md).

Criterio de aceptación: Progression obtiene hechos contractuales equivalentes
para una capability soportada aunque cambie el proveedor externo; un módulo
inactivo no realiza consulta externa y deja evidencia técnica.

### M4 — Sustitución y endurecimiento operativo

Conecta Trading Account Service cuando esté disponible, valida compatibilidad
contractual, completa observabilidad, resiliencia e idempotencia, y retira el
adaptador temporal sin cambios para los consumidores.

## Dependencias

- M1 necesita cerrar agregados, concurrencia, trabajos en curso y forma de
  auditoría.
- Plans P1 depende de M1 y origina el primer puerto público de Modules.
- Programs depende de Plans P1 y de sus módulos habilitados.
- M2 necesita un consumidor real que defina la necesidad de server groups e
  instrumentos.
- M3 necesita consumidores y vocabulario de actividad para puntos o
  recompensas.
- M4 depende de contratos reales del futuro Trading Account Service.

## Regla de revisión

La secuencia puede cambiar al cerrar el inventario. Todo cambio debe preservar
entregas verticales y registrar qué dependencia o aprendizaje lo motivó.
