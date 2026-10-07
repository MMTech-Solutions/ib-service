# Eventos de proveedores para volumen

Última revisión: 2026-10-06.
Estado: implementación local de IB y productor Broker; aceptación integrada pendiente.
Copy Trading: catálogo/feed/adapters de IB implementados; productor pendiente en su repositorio.

## Decisiones e implementación

La capacidad de volumen declara topic, evento, versión y adapter. Al iniciar
rbac:consume-snapshots se descubren topics y se preservan handlers existentes.
Broker y Copy Trading pueden usar position_closed en topics distintos.

Refactor de arranque: el comando es RbacConsumeSnapshotsCommand de mmtech/iam-rbac
v1.13; se retiró el wrapper local. KafkaServiceProvider prepara idempotentemente
rbac.consumer.handlers antes de construir el registro, y CommandStarting valida
todas las suscripciones antes del consumo. Modules publica descubrimiento y
validación separados. Ayuda, comandos ajenos, HTTP y consumidor deshabilitado no
requieren topics completos; el cache conserva únicamente configuración estática.

IB recibe evidencia completa, persiste receipts inmutables, detecta conflictos
y calcula sin resolución HTTP individual. Ambos módulos admiten event, periodic
y both con identidad económica común y runs/cursores independientes. Pausa e
inactividad conservan la captura y aplazan cálculos.

Broker guarda cierre y outbox en una transacción, recupera evidencia pendiente
y publica JSON en su topic. trading:publish-position-closed-events está programado
cada minuto con leases y backoff. Copy Trading replicará esa publicación.

Contrato: [actividad cerrada V1](../../rules/volume-activity-event-contract.md).
No hay compatibilidad con datos anteriores ni reinicio de la base operativa.
CPA, Progression y PnL de Copy Trading no se habilitan en esta entrega.

## Evidencia y cierre

Pruebas locales: contratos inválidos, recepción pausada, normalización,
duplicados/conflictos, leases, equivalencia evento/feed, modalidad fija/porcentual
Copy Trading e idempotencia entre canales. Broker cubre rollback, recuperación
y fallos/reintentos Kafka con el mismo payload. Instalación limpia validada en
bases de pruebas: PostgreSQL aislado para IB y SQLite en memoria para Broker.

La verificación final incluye regresiones de Rewards/Modules/configuración,
arquitectura, Pint y Graphify. Resultados: IB, 151 pruebas / 907 assertions en la suite focalizada y una prueba adicional de composición de handlers aprobada (29 / 81 en la suite de eventos). Broker, 21 pruebas / 152 assertions; el feed rechaza snapshots incompletos con 409 sin fabricar precisión cero. Pint aprobado y Graphify actualizado en ambos repositorios. Colección Broker v2.1 válida y feed contrastado con route:list. El equipo de Copy Trading implementa su productor sin acceso local a su repositorio.

Pendientes: configurar topics reales/credenciales, productor Copy Trading y
demostrar los flujos Kafka hasta settlement. Pruebas locales no acreditan S2S.

Evidencia del refactor de registro nativo: 165 pruebas / 960 assertions aprobadas, incluyendo 13 pruebas de arranque, configuración inválida, consumidor deshabilitado, HTTP y cache aislado. Pint aplicado; actualización Graphify requerida y ejecutada al cerrar el cambio. No se modificaron contratos de mensajes, migraciones ni rutas HTTP.
