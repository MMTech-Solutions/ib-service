# Integraciones

## Estado

Reglas base aprobadas para comunicación externa. Los nombres definitivos de topics, envelopes y catálogos de eventos permanecen marcados como pendientes cuando corresponda.

## Convención financiera de niveles Rewards

El nivel económico de volumen/PnL empieza en cero y se conserva en IB. Para
Finance se traduce a `nivel + 1`; CPA conserva el nivel financiero `1`.
La solicitud se congela antes del primer envío y sus reintentos conservan clave
y payload. Rewards ya intentadas conservan la convención anterior; las reversas
reutilizan el nivel financiero original. Una incompatibilidad histórica exige
hold y no autoriza cambiar payload bajo la misma clave. Moneda, precisión e
importe se validan estrictamente sin convertir tipos recibidos.

## Principios

- Todo sistema externo se consume mediante un puerto de salida y una implementación adapter o repository propiedad del feature que necesita la capacidad.
- Preferir un SDK mantenido para el servicio destino cuando exista y satisfaga la necesidad.
- Los tipos de un SDK no cruzan el adapter o repository: se convierten en `Data`, DTOs u objetos de valor propios.
- La ausencia de SDK autoriza un cliente HTTP interno, no el uso directo del cliente de Laravel desde UseCases, Actions o strategies.
- Transporte, envelope y payload dirigido a una audiencia son responsabilidades separadas.

## IAM y autorización

`auth-service`, repositorio hermano de `ib-service`, es la fuente autoritativa para identidad, autenticación, autorización y red de referidos.

- `mmt/laravel-iam-service-sdk` es la dependencia aprobada para interacción operativa con IAM: consultas de usuarios, red de referidos y demás capacidades expuestas por el SDK.
- `mmtech/iam-rbac` se usa para autorización distribuida, materialización de permisos y las capacidades Kafka que ofrece el paquete.
- `mmtech/iam-rbac` no se usa como sustituto del SDK para consultas operativas que no pertenecen a RBAC.
- Ningún feature propaga objetos de respuesta del SDK. Cada adapter los traduce a contratos propios.
- Si varias features necesitan la misma capacidad IAM, debe decidirse un único propietario interno o fachada antes de duplicar adapters. IAM no se incorpora al Shared Kernel.

## Kafka

Kafka es el transporte aprobado para hechos de integración y mensajes asíncronos. La publicación y el consumo se apoyarán en las capacidades de `mmtech/iam-rbac`, envueltas por contratos propios para impedir que el paquete se propague al dominio.

El topic de salida del servicio tendrá versión mayor. `ib-service.events.v1` es el nombre provisional propuesto; no se considera definitivo hasta validar naming, ownership, ACLs, retención y consumidores.

Notification Service podrá consumir el topic del servicio y seleccionar mensajes por un `event_name` registrado. Los eventos entrantes de proveedores de actividad como Broker Service se consumen mediante handlers por topic y se discriminan por `event_name`; cada handler valida y mapea su payload antes de invocar un UseCase.

### Envelope mínimo

El contrato definitivo está pendiente, pero el diseño debe contemplar al menos:

- `event_id` único e idempotente.
- `event_name` estable y namespaced.
- `schema_version` explícita.
- `occurred_at` en UTC.
- Identidad del productor.
- `correlation_id` y `causation_id` cuando existan.
- Clave de partición adecuada al orden requerido.
- Payload tipado y validable.

No incluir credenciales, objetos serializados de Laravel, modelos Eloquent ni tipos de SDK.

## Eventos internos, eventos de integración y notificaciones

Estas categorías no son intercambiables:

- **Evento de dominio:** hecho interno del feature; expresa algo que ocurrió en el modelo y no conoce Kafka ni una audiencia externa.
- **Evento de integración común:** contrato público del servicio para otros sistemas; representa un hecho de IB y se publica con esquema versionado.
- **Mensaje para Notification Center:** proyección dirigida a notificar uno o más usuarios; cumple el payload que Notification Service espera y se discrimina por `event_name`.
- **Mensaje dirigido a un sistema:** contrato específico solicitado por PropFirm u otro consumidor que no pueda consumir el evento común.

Un hecho interno puede originar varias proyecciones de salida. No se fuerza un payload universal para todas las audiencias.

## Pushers por audiencia

La distinción de audiencia se expresa estructuralmente mediante clases y namespaces, no mediante un pusher universal lleno de condicionales:

```text
Features/<Feature>/
├── Contracts/
│   └── Events/
├── Events/
├── Listeners/
└── Services/
    └── Pushers/
        ├── NotificationCenter/
        │   └── NotificationCenterEventPusher.php
        ├── PropFirm/
        │   └── PropFirmEventPusher.php
        └── Service/
            └── ServiceEventPusher.php

Support/Messaging/
├── Contracts/
└── Kafka/
```

- `NotificationCenterEventPusher` modela destinatario, plantilla o escenario y payload esperado por Notification Service.
- `ServiceEventPusher` modela eventos comunes de IB destinados a consumidores generales.
- `PropFirmEventPusher` solo se crea cuando exista un contrato de PropFirm distinto del evento común.
- Los pushers no contienen reglas de negocio; reciben resultados ya resueltos y construyen el contrato de salida.
- `Support/Messaging/Kafka` adapta el publisher de `mmtech/iam-rbac` y desconoce planes, rewards, usuarios o PropFirm.
- Topics y nombres de eventos se obtienen desde configuración o enums cerrados, nunca desde input de usuario.

Cuando se implemente outbox, el UseCase persiste el cambio y el evento de salida en la misma transacción. Un worker o listener entrega posteriormente el mensaje mediante el pusher correspondiente.

## Consumo de eventos

```text
Kafka topic
    ↓
Topic handler
    ↓ filtra event_name y versión
Inbound Data propio del transporte
    ↓ valida y normaliza
UseCase
```

- Cada topic tiene handlers registrados explícitamente.
- Un handler rechaza o deriva a dead-letter los schemas o versiones incompatibles según la política que se defina.
- Los mensajes Kafka no construyen ni reutilizan `Http/V1/Commands`.
- Los consumidores son idempotentes y asumen entrega al menos una vez.
- El offset solo se confirma después de completar la operación o persistir de forma recuperable su recepción.

## SDKs y clientes HTTP propios

Las conexiones de Modules se certifican con GET internal_prefix/health protegido
por el mismo token y autorización de origen de las rutas internas de actividad.
Modules posee el mecanismo y Settings lo consume por puerto. Un /up público no
certifica credenciales. Contrato y resultados: [Settings](settings.md).

Si existe SDK:

1. El adapter o repository recibe el SDK por constructor.
2. Invoca únicamente las capacidades requeridas.
3. Traduce éxito, ausencia y error a resultados o excepciones propias.
4. Mapea la respuesta a un DTO/Data propio antes de salir de la integración.

Si no existe SDK:

- El cliente HTTP vive dentro de `Services/Adapters` del feature propietario de la necesidad.
- Base URL, timeout, autenticación y retry se leen desde configuración.
- No se mantienen transacciones de base de datos abiertas durante la llamada.
- Retries solo aplican a operaciones seguras o idempotentes.
- El cliente aplica límites, timeouts, observabilidad y redacción de secretos.
- Capacidades HTTP puramente técnicas y reutilizables pueden vivir en `app/Support/Http`, sin conocer endpoints ni lenguaje de negocio.

Una consulta remota que se comporta como colección de hechos puede implementarse como repository. Comandos externos o capacidades con efectos laterales usan un puerto/adapter explícito y no se disfrazan de repository.

En `Modules`, las integraciones compartidas por varios módulos se centralizan según el sistema fuente:

```text
Modules/Sources/
├── TradingAccounts/
├── Broker/
├── PropFirm/
└── CopyTrading/
```

El adapter hacia `broker-service` implementa el contrato mínimo de Modules. No expone el modelo completo de Broker y conserva los puertos y Data utilizados por los módulos si en el futuro cambia el proveedor.

### Evidencia CPA de Broker

La capacidad CPA de `Modules/Broker` compone dos consultas de solo lectura: los hechos normalizados de `closed_trading_volume` de `broker-service` y los depósitos externos certificados de Finance. `broker-service` no recibe ni devuelve depósitos CPA, importes, decisiones de elegibilidad o recompensas.

Rewards consume un puerto V1 de Modules especializado en evidencia CPA. El adapter de Modules pagina, limita y normaliza las dos fuentes, conserva la trazabilidad de sus referencias y traduce indisponibilidad, timeout y contrato inválido a errores tipados. No mantiene una transacción local abierta durante ninguna llamada. Finance se invoca desde IB con identidad S2S y la moneda se filtra de forma exacta antes de entregar evidencia a Rewards.

### Evidencia RWD4 de Broker y Finance

El puerto de actividad de volumen de RWD4 es distinto de `ListProgressionActivitiesPort`: conserva el contrato M3 sin cambios y declara los campos económicos que necesita una Reward. Mientras Broker no publique moneda, precisión y, para porcentaje, comisión fuente, Modules puede transportar la ausencia como contrato incompleto pero Rewards no puede crear una obligación económica.

Los proveedores de volumen publican eventos position_closed V1 en sus topics propios después de persistir evidencia completa. Cierre y outbox se guardan transaccionalmente; la recuperación publica la misma identidad y payload. IB consume esa evidencia y no consulta posiciones individuales por orden/login.

La capacidad closed_trading_volume declara topic, event_name, schema_version y adapter. KafkaServiceProvider prepara el registro rbac.consumer.handlers desde esas declaraciones antes de construir TopicHandlerRegistry, agrupando handlers y preservando suscripciones existentes. El comando rbac:consume-snapshots es el original de la librería; CommandStarting valida las declaraciones antes de consumir. El descubrimiento es independiente de la validación estricta; ayuda, HTTP, comandos ajenos y consumidor deshabilitado no exigen configuración Kafka completa. No se invalida el registro ni se cachean bindings dinámicos. Broker y Copy Trading pueden compartir nombre de evento en topics diferentes. Topics vacíos, rutas ambiguas y adapters desconocidos impiden iniciar el consumo.

mmtech/iam-rbac 1.13 entrega el body deserializado y no acredita identidad autenticada del productor ni subject/version Avro resueltos. IB contrasta topic/proveedor y valida schema_version del body; no afirma metadatos ausentes. Las ACLs deben autorizar a cada productor sobre su topic. El receipt conserva evento, topic, partición y offset junto con evidencia normalizada. Duplicados no cambian estados ni snapshots; una contradicción queda registrada sin sobrescribir evidencia. Pausa/inactividad conservan captura y aplazan cálculo.

Contrato y configuración: [actividad cerrada V1](volume-activity-event-contract.md).

PnL usa una capacidad diferente y propiedad de Rewards. Broker recibe usuarios
e intervalo común y suma exclusivamente profit de posiciones cerradas por cuenta.
Devuelve resultados firmados e identidades de todas las posiciones utilizadas.
No intervienen balances, lecturas de margen, cashflows ni Finance.

El puerto V1 de Rewards procesa lotes y confirma usuarios en meta. El runner
congela red, contexto y receipts antes de calcular, agrega por nivel/moneda y
reutiliza snapshots en reintentos. Evidencia parcial no avanza el cursor temporal.
Véase el [contrato vigente](negative-pnl-contract.md). El tratamiento de cierres
incorporados o corregidos después del run permanece pendiente.

El puerto específico de referidos PnL usa `getDownline(user, max_distribution_level + 1)`;
su adapter normaliza IAM 1 a nivel económico 0, conserva solo niveles remunerables
y rechaza identidades UUID incompatibles, autorreferencias, duplicados o niveles
inválidos. No propaga perfiles ni objetos SDK. El snapshot no acredita una red
histórica. El cálculo posterior consume exclusivamente Data propios congelados.

La evidencia S2S Broker/IAM sigue pendiente para cierre E2E; las pruebas locales
no acreditan validación con datos reales.

## Recuperación de comisiones Finance

La recuperación consulta `GET /api/finance/v1/ib/commission-events` por
`idempotency_key`. Su colección contiene `ib_wallet_id`, importe y precisión,
pero no moneda ni slug de wallet. Si hay un evento, el adapter consulta
`GET /api/finance/v1/ib/wallets` con `ib_user_id` del evento, `per_page=100`
y páginas secuenciales. Selecciona el ID exacto, comprueba beneficiario y
precisión, y completa moneda y slug desde la wallet. No existe un endpoint
de detalle `/ib/wallets/{id}` para esta resolución.

Ambas consultas reutilizan `X-Internal-Token` y `X-Internal-Source`. Una ausencia
válida del evento no consulta wallets; una wallet ausente, payload inválido o
paginación incoherente constituye `finance_contract_invalid`. Los fallos de
conexión/5xx son `finance_unavailable`; otros HTTP no exitosos son
`finance_rejected`. Un fallo de resolución no se convierte en ausencia del pago.
No se asume USD ni se usa un POST como fallback de la consulta de wallets.
La validación financiera conserva la solicitud congelada como autoridad.

## Decisiones pendientes

- Confirmar `ib-service.events.v1` como nombre del topic de salida.
- Definir ACLs, particiones, clave de partición, retención y estrategia de replay.
- Cerrar el envelope común y su compatibilidad con `mmtech/iam-rbac` 1.13.
- Decidir JSON Schema, Avro u otro mecanismo y el uso de Schema Registry.
- Definir política de retry, dead-letter y mensajes no manejados.
- Crear el catálogo inicial de `event_name` y sus responsables.
- Cerrar el payload requerido por Notification Center y su estrategia de bootstrap.
- Determinar si PropFirm necesita topic/esquema propio o puede consumir eventos comunes.
- Decidir el propietario interno o fachada de capacidades IAM compartidas por varias features.
- Completar la evidencia S2S del contrato de PnL de Broker y del límite de profundidad de IAM.
