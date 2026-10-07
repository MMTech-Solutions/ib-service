# Roadmap Settings

Última revisión: 2026-10-07.

Objetivo: configuraciones globales por dominio, personalización segura, sync explícito
y certificación de conexiones reutilizada desde Settings y Modules.

| Etapa | Documento | Estado |
| --- | --- | --- |
| Inventario | [01-use-case-inventory.md](01-use-case-inventory.md) | Completado |
| Modelo | [02-domain-model.md](02-domain-model.md) | Completado |
| Entregas | [03-vertical-deliveries.md](03-vertical-deliveries.md) | Completadas localmente; aceptación S2S/UI pendiente |
| Datos | [04-data-model.md](04-data-model.md) | Completado |
| Implementación | [05-implementation.md](05-implementation.md) | Completada localmente; limitación de regresión conjunta registrada |

Decisiones confirmadas: catálogo explícito rewards/modules/finance sin repository;
dominios Rewards/Modules/Finance; conexiones por proveedor; altas copian respaldo;
sync conserva valores y reset; claves retiradas se eliminan; secretos cifrados y
ocultos; prueba de conexión sobre valores efectivos; mecanismo propietario de Modules.

Fuentes: [BDS](../../bds/settings.bds.md), [reglas técnicas](../../rules/settings.md).
Pendiente externo: botón UI y endpoints internos autenticados Broker/Copy Trading;
aceptación integrada. No se ha certificado conexión real ni migrado la base operativa.
