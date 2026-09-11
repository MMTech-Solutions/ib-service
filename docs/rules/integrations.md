# Integraciones

## Estado

Reglas base aprobadas para comunicación externa. Los nombres definitivos de topics, envelopes y catálogos de eventos permanecen marcados como pendientes cuando corresponda.

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

Notification Service podrá consumir el topic del servicio y seleccionar mensajes por un `event_name` registrado. Los eventos entrantes de servicios como el futuro Trading Account Service se consumen mediante handlers por topic y se discriminan por `event_name`; cada handler valida y mapea su payload antes de invocar un UseCase.

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

El adapter temporal hacia `broker-service` implementa el contrato mínimo previsto para Trading Account Service. No expone el modelo completo de Broker. Cuando el nuevo servicio esté disponible, la factory cambia la implementación de salida conservando los puertos y Data utilizados por los módulos.

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
