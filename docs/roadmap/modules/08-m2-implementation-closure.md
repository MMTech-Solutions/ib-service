# M2: cierre de implementación del catálogo de instrumentos

Estado: **Completada**
Última revisión: 2026-09-28

## Alcance entregado

M2 publica `ListInstrumentCatalogPort` con Data V1 para listar identidades
normalizadas de plataforma, trading server, server group, security y símbolo.
La primera implementación usa un fixture de Broker, filtrable y paginado. Sus
referencias son opacas y propias de Modules; el consumidor no recibe IDs ni
tipos del proveedor.

El endpoint administrativo requiere `ib.modules.manage`. Un módulo inexistente
devuelve `MODULE_NOT_FOUND`, uno inactivo devuelve `MODULE_INACTIVE` y uno
activo sin fuente de catálogo devuelve
`UNSUPPORTED_INSTRUMENT_CATALOG_CAPABILITY` con HTTP 422.

## Frontera y sustitución

`InstrumentCatalogSourceInterface` separa el puerto de salida del adapter de
Broker. Un adapter futuro de Trading Account Service debe respetar la misma
suite contractual sin cambiar `ListInstrumentCatalogPort`, sus Data V1 ni a los
consumidores.

## Evidencia

- `FixtureBrokerInstrumentCatalogAdapterContractTest` valida jerarquía,
  referencias opacas, filtros, búsqueda, paginación y metadatos.
- `ModuleInstrumentCatalogEndpointTest` valida autorización, forma `data[]`,
  validación y errores contractuales.
- `ib-service.postman_collection.json` contiene el request administrativo.
- La implementación inicial se incorporó en `b26e046` y el endurecimiento
  contractual se registra con esta entrega.

## Fuera de alcance

M2 no configura plantillas, weights, scopes instrumentales, puntos,
beneficiarios, placement ni red de referidos. Tampoco integra todavía Trading
Account Service; esa sustitución pertenece a M4.
