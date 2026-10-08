# Actividad cerrada V1 para Rewards de volumen

Estado: obligatorio. Revisión: 2026-10-06.

## Publicación

Broker y Copy Trading publican únicamente evidencia completa de un cierre persistido.
Cierre y outbox se guardan en la misma transacción. La recuperación completa evidencia
ausente sin publicar defaults. Reintentos conservan event_id, topic, clave y payload.

Headers: event_name=position_closed y content_type=application/json.
Clave Kafka: activity.source_activity_id. Body:

```json
{
  "event_id": "cccccccc-cccc-4ccc-8ccc-cccccccccccc",
  "schema_version": 1,
  "provider_code": "broker",
  "activity": {
    "source_activity_id": "broker:position:aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
    "subject_external_user_id": "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
    "metric_code": "closed_trading_volume",
    "unit_code": "lot",
    "quantity": "1.5",
    "occurred_at": "2026-10-06T10:00:00.123000Z",
    "server_group_id": "<group_reference>",
    "symbol_id": "<symbol_reference>",
    "currency_code": "USD",
    "currency_precision": 2,
    "broker_granted_commission": "12.5"
  }
}
```

Copy Trading declara provider_code=copy_trading y namespace copy_trading:position:.
Todos los campos son obligatorios. event_id y subject_external_user_id son UUID.
Cantidad y comisión son strings decimales no negativos sin exponente, hasta 20
dígitos enteros y 10 decimales. Moneda: tres letras mayúsculas; precisión: entero
0–10. Fecha: cierre efectivo ISO 8601 UTC con hasta seis decimales temporales.
Grupo/símbolo: referencias no vacías sin ":"; IB construye la referencia
<proveedor>:server_group:<grupo>:symbol:<símbolo>.
broker_granted_commission conserva su nombre y es la comisión del propio proveedor.

## Descubrimiento en capacidades

La definición técnica de closed_trading_volume contiene event_subscriptions
tipadas con module_code, topic, event_name, schema_version y adapter registrado.
El topic pertenece a la declaración; configuración de entorno proporciona su valor.
No se mantiene un mapa manual adicional de proveedores en Rewards/configuración Kafka.

KafkaServiceProvider prepara las definiciones registradas antes de construir el
registro nativo de RBAC, incluidas las pausadas/inactivas. Se agrupa por topic y
se componen los handlers existentes mediante rbac.consumer.handlers. El comando
es el original de la librería, sin wrapper ni opciones duplicadas.

El descubrimiento devuelve declaraciones sin instanciar adapters. La preparación
omite topics vacíos, mal formados o reservados únicamente durante el bootstrap.
Al iniciar rbac:consume-snapshots, CommandStarting ejecuta la validación estricta
de todas las declaraciones antes de conectar Kafka. Routing: topic + evento +
versión. Topic inválido, adapter desconocido, ruta duplicada, asociación incorrecta
o uso del topic reservado RBAC impiden consumir; no se acepta una configuración
parcial. Otros comandos, ayuda, HTTP y consumidor deshabilitado no la exigen.
Cambio de configuración/definiciones requiere reiniciar el consumidor.

- REWARDS_VOLUME_BROKER_TOPIC debe coincidir con config(kafka.app_topic) de Broker.
  La instalación local revisada utiliza broker-service.
- REWARDS_VOLUME_COPY_TRADING_TOPIC es obligatorio y debe ser el topic real del
  productor; no se inventa un nombre.
- COPY_TRADING_BASE_URL, COPY_TRADING_INTERNAL_TOKEN y COPY_TRADING_SOURCE_SERVICE
  configuran catálogo/feed S2S bajo /api/copy-trading/v1/internal.
- modules:sync materializa los módulos y sus capacidades locales.

provider_code se contrasta con la declaración del topic, no elige el módulo.
mmtech/iam-rbac 1.13 no acredita identidad autenticada del productor ni subject/version
Avro resueltos. Esta asociación no sustituye ACLs de Kafka. Se valida la versión
contractual del body deserializado, sin asumir metadatos ausentes del transporte.

## Recepción y procesamiento

IB conserva actividad normalizada completa y procedencia Kafka. La unicidad del
receipt es módulo + source_activity_id. Duplicados idénticos no cambian evidencia
ni estados. Contradicciones quedan en conflict_snapshot sin sustituir el original;
contradicciones con evaluaciones congeladas se rechazan sin recálculo.

Otros eventos se ignoran. Contratos/versiones incompatibles se rechazan con diagnóstico
sanitizado y sin receipt económico. Fallos de persistencia se propagan para impedir
confirmar el offset. Pausa/inactividad conservan captura y aplazan procesamiento.

El worker consume el snapshot, sin consulta individual por orden/login. El feed
periódico entrega la misma evidencia e identidad; se normalizan decimales y fecha
sin perder precisión. event, periodic y both comparten identidad económica.
Cada módulo mantiene runs, leases, cursores y backoff independientes.

## Compatibilidad y aceptación

Entrega de desarrollo sin compatibilidad con eventos Trading ni datos previos.
Las migraciones originales de IB crean directamente los receipts completos.
No ejecutar migrate:fresh sobre la base operativa; las suites usan bases aisladas.

La aceptación local usa HTTP/Kafka simulados. El cierre integrado exige ambos
productores, Kafka real, generación de Rewards y settlement verificables.
El productor Copy Trading debe replicar el mecanismo de Broker; su equipo trabaja
en el contrato y no tenemos acceso a su repositorio. Correcciones de hechos ya congelados
no generan recálculo automático.

CPA/Progression/PnL de Copy Trading se habilitan mediante consumidores HTTP separados; no consumen estos eventos. Copy Trading certifica solo cuentas creadas desde su plataforma. [Contrato completo](copy-trading-provider-contract.md).
