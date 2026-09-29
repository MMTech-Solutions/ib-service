# M4: catálogo Broker por S2S

Estado: **Implementado**
Última revisión: 2026-09-29

## Decisiones

- IB consulta `broker-service` mediante rutas internas autenticadas con el token
  RBAC y el origen exclusivo `mmt-ib-service`.
- El catálogo se recorre de forma paginada por plataforma, trading server,
  server group, security y símbolo.
- Broker es la fuente de la jerarquía viva; no se replica ni se snapshottea la
  configuración de los grupos.
- Las referencias V1 de Modules incluyen el namespace `broker` y están
  acopladas a los UUIDs S2S de Broker. Un símbolo se identifica para selección
  por el par `server_group + symbol`; `security` es un filtro de navegación.
- Si Broker recrea un registro, una selección que conserve su referencia debe
  reconfigurarse. M4 no añade compatibilidad histórica ni inicia PG2.

## Evidencia y límites

- El adapter de IB mantiene `ListInstrumentCatalogPort` y
  `InstrumentCatalogSourceInterface`; el fixture deja de ser la composición
  productiva de Broker.
- Fallos de transporte, 5xx o respuestas inválidas de Broker se muestran como
  indisponibilidad técnica del catálogo (`503`) en IB.
- Las pruebas cubren la autorización S2S, filtros jerárquicos, búsqueda,
  paginación y traducción V1. No se crean adapters para PropFirm o Copy Trading
  sin contratos S2S aprobados.
