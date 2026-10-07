# API interna de Copy Trading como proveedor de actividad de IB Service

**Revisión:** 2026-10-07. **Estado:** productor Broker y consumo IB implementados; productor Copy Trading y aceptación integrada pendientes.

La entrega actual en IB habilita exclusivamente volumen (evento y feed) y su
catálogo instrumental. CPA, Progression y PnL de Copy Trading continúan fuera de
esta entrega; las secciones correspondientes describen solicitudes futuras.

## Propósito

`ib-service` necesita consultar hechos de Copy Trading para calcular progresión y recompensas de CPA, volumen cerrado y pérdidas de trading. La API debe ofrecer catálogo de instrumentos, feed paginado de volumen cerrado, eventos completos de cierres y resultado firmado de posiciones cerradas por cuenta y período.

Copy Trading identifica usuarios, cuentas e instrumentos y entrega datos autoritativos completos. IB determina elegibilidad, congela red y configuración de cada ejecución, calcula contribuciones y recompensas y solicita su liquidación a Finance. El proveedor no recibe niveles de red, tasas ni instrucciones de pago. Los depósitos externos certificados para CPA se consultan directamente en Finance; Copy Trading aporta la evidencia de volumen cerrado.

Referencias: [Rewards](docs/bds/rewards.bds.md), [progresión](docs/bds/progression.bds.md), [contrato de pérdidas por período](docs/rules/negative-pnl-contract.md), [integraciones](docs/rules/integrations.md) y [convenciones HTTP](docs/rules/api-conventions.md).

## Contrato HTTP común

Prefijo propuesto, pendiente de confirmación con Copy Trading:

```text
/api/copy-trading/v1/internal
```

Los paths siguientes son relativos a ese prefijo. Todas las operaciones requieren autenticación S2S y autorización por capacidad:

```http
Accept: application/json
X-Internal-Token: {{INTERNAL_TOKEN}}
X-Internal-Source: mmt-ib-service
```

POST requiere además `Content-Type: application/json`. Los secretos se configuran por entorno y no se exponen en respuestas o logs.

En éxito, `success` vale `true`. `data` es directamente el array de elementos para colecciones o el objeto para resolución individual. Paginación y confirmación de usuarios pertenecen a `meta`. Fechas en ISO 8601 UTC; cantidades e importes como strings decimales sin notación exponencial; precisión monetaria como entero entre `0` y `10`. Las identidades son strings estables y los usuarios corresponden a IAM.

Una colección vacía representa una consulta completada sin resultados. Un fallo no puede representarse como éxito vacío.

## 1. Catálogo de instrumentos

```http
GET /instrument-catalog/{type}
```

Permite navegar y seleccionar instrumentos. `type` admite `platform`, `trading_server`, `server_group`, `security` y `symbol`.

| Entrada query | Descripción |
| --- | --- |
| `page`, `per_page` | Página y tamaño; publicar valores predeterminados y límites |
| `search` | Búsqueda textual opcional |
| `platform_reference` | Filtro opcional por plataforma |
| `trading_server_reference` | Filtro opcional por servidor |
| `server_group_reference` | Filtro opcional por grupo |
| `security_reference` | Filtro opcional por categoría |
| `symbol_reference` | Filtro opcional por símbolo |

Ejemplo de respuesta:

```json
{
  "success": true,
  "data": [
    {
      "reference": "<symbol_uuid>",
      "type": "symbol",
      "name": "EURUSD",
      "parents": {
        "platform": "<platform_uuid>",
        "trading_server": "<server_uuid>",
        "server_group": "<group_uuid>",
        "security": "<security_uuid>"
      },
      "currency_code": "USD"
    }
  ],
  "meta": {
    "message": "Instrument catalog retrieved.",
    "pagination": { "current_page": 1, "per_page": 100, "total": 1, "last_page": 1 }
  }
}
```

`parents` contiene los niveles superiores aplicables al tipo consultado. Informar moneda donde corresponda. La selección instrumental se identifica por **grupo + símbolo**; `security` sirve como filtro de navegación. Las referencias deben ser estables y resolubles en el catálogo.

Namespace propuesto para referencias utilizadas por IB:

```text
copy_trading:server_group:<group_uuid>:symbol:<symbol_uuid>
```

## 2. Feed de volumen cerrado

```http
GET /progression-activities
```

Entrega volumen efectivamente cerrado y persistido para progresión, evidencia CPA y recompensas de volumen.

| Entrada query | Requisito |
| --- | --- |
| `from` | Obligatorio; inicio inclusivo |
| `until` | Obligatorio; fin exclusivo, posterior al inicio |
| `limit` | Tamaño de página; soportar al menos 200 elementos |
| `cursor` | Continuación opcional de la consulta |
| `external_user_id` | Filtro opcional por usuario IAM |
| `instrument_references[]` | Filtro opcional por referencias de grupo + símbolo |

Los filtros se combinan. Sin filtro de usuario, IB puede recorrer la actividad del período autorizado. Reutilizar el cursor con el mismo intervalo y filtros; documentar su formato para implementar el adapter de IB.

```json
{
  "success": true,
  "data": [
    {
      "source_activity_id": "copy_trading:position:<position_uuid>",
      "subject_external_user_id": "<iam_user_uuid>",
      "metric_code": "closed_trading_volume",
      "unit_code": "lot",
      "quantity": "1.50",
      "occurred_at": "2026-10-05T10:00:00Z",
      "server_group_id": "<group_uuid>",
      "symbol_id": "<symbol_uuid>",
      "currency_code": "USD",
      "currency_precision": 2,
      "broker_granted_commission": "12.50"
    }
  ],
  "meta": { "message": "Closed trading activities retrieved.", "next_cursor": null }
}
```

| Campo del hecho | Semántica |
| --- | --- |
| `source_activity_id` | Identidad única y estable, con namespace propio |
| `subject_external_user_id` | Usuario IAM propietario de la actividad |
| `metric_code`, `unit_code` | `closed_trading_volume` y `lot` |
| `quantity` | Volumen cerrado, como decimal no negativo |
| `occurred_at` | Fecha efectiva del cierre |
| `server_group_id`, `symbol_id` | Identidades del instrumento |
| `currency_code`, `currency_precision` | Moneda y precisión de la comisión fuente |
| `broker_granted_commission` | Comisión fuente de la posición utilizada en reglas porcentuales de volumen |

Las capacidades económicas habilitadas deben disponer de evidencia suficiente; no completar moneda, precisión o comisión ausentes con valores supuestos. El orden es determinista por fecha de cierre e ID de posición. La continuación evita saltos y duplicaciones de los hechos consultados; `meta.next_cursor` vale `null` al finalizar.

CPA requiere confirmar un intervalo completo, incluso vacío. Debe acordarse cómo se certifica el corte y la garantía de que no se publicarán, corregirán ni retirarán hechos anteriores al corte confirmado. La paginación no acredita por sí sola esa garantía.

## 3. Evento completo de cierre

Copy Trading debe publicar en su propio topic el evento position_closed después de
persistir la evidencia completa. El body contiene event_id estable, schema_version
entero 1, provider_code copy_trading y activity con los campos del hecho de la sección 2.
Headers: event_name=position_closed y content_type=application/json.
Clave Kafka: source_activity_id. La fecha económica es el cierre efectivo.

```json
{
  "event_id": "019a27c0-7e00-7000-8000-000000000001",
  "schema_version": 1,
  "provider_code": "copy_trading",
  "activity": {
    "source_activity_id": "copy_trading:position:019a27c0-7e00-7000-8000-000000000002",
    "subject_external_user_id": "019a27c0-7e00-7000-8000-000000000003",
    "metric_code": "closed_trading_volume",
    "unit_code": "lot",
    "quantity": "1.50",
    "occurred_at": "2026-10-05T10:00:00.123000Z",
    "server_group_id": "019a27c0-7e00-7000-8000-000000000004",
    "symbol_id": "019a27c0-7e00-7000-8000-000000000005",
    "currency_code": "USD",
    "currency_precision": 2,
    "broker_granted_commission": "12.50"
  }
}
```

Todos los campos son obligatorios. Cantidad y comisión son strings decimales
no negativos, sin exponentes, con hasta 20 dígitos enteros y 10 decimales. Moneda:
código de tres letras mayúsculas del grupo; precisión entera 0..10. La fecha es
UTC ISO 8601 y conserva hasta seis decimales. El topic físico se acuerda con el
equipo y se configura en IB mediante REWARDS_VOLUME_COPY_TRADING_TOPIC.

El evento y el feed deben entregar la misma identidad y evidencia. El cierre y el
outbox se guardan en una transacción; la entrega se reintenta sin cambiar event_id ni
payload. No publicar información parcial ni completar moneda/comisión con defaults.
Si la evidencia se completa posteriormente, crear el outbox al completarla.

IB declara el topic, evento, versión y adapter en la capacidad del módulo. No resuelve
el cierre por order_id/login ni requiere un endpoint individual para esta modalidad.
La comisión broker_granted_commission representa la comisión del propio Copy Trading.

Referencia vinculante: [actividad cerrada V1](docs/rules/volume-activity-event-contract.md).
Broker implementa el productor de referencia; Copy Trading debe replicar su garantía
transaccional, recuperación y reintento. Su publicación y aceptación S2S siguen pendientes.

### Flujo que debe implementar Copy Trading

1. Consumir el cierre de Trading y resolver sus identidades y datos económicos en
   el propio proveedor. IB no completa la posición mediante consultas por orden/login.
2. Guardar cierre, snapshot económico y mensaje de outbox en la misma transacción.
   Crear una sola entrada por posición y versión contractual; un duplicado no
   sustituye el `event_id`, payload, topic ni clave congelados.
3. Si falta evidencia, conservar el cierre sin emitir un mensaje parcial. Un
   mecanismo recuperable completa la evidencia autoritativa bajo bloqueo y crea
   entonces el outbox. Un cero solo representa un dato confirmado por el proveedor.
4. Publicar exclusivamente después del commit, en el topic propio de Copy Trading.
   Confirmar el envío en el outbox después de publicar; ante fallo o caída, reintentar
   el mensaje guardado. Una caída entre envío y confirmación puede causar duplicados.
5. Mantener recuperación, claims, leases y reintentos. Un worker con token vencido
   no puede confirmar el mensaje reclamado posteriormente por otro worker.

El modo de ejecución del publicador pertenece a Copy Trading: puede intentar el
envío inmediatamente después del commit y conservar recuperación duradera. Broker
actualmente entrega su outbox mediante un comando programado cada minuto; no
publica directamente dentro del handler de cierre ni garantiza envío inmediato.
Copy Trading no necesita replicar el nombre del comando, pero sí sus garantías.

### Referencia implementada en Broker

Implementación congelada en el commit
[b25bfd6 de Broker](https://github.com/MMTech-Solutions/MMT-BROKER-SERVICE/commit/b25bfd60cbe0445dfc06c42ba58d2b15b9c1f2b7):

| Responsabilidad | Referencia en Broker |
| --- | --- |
| Transacción del cierre y creación del outbox | `app/Features/Trading/Account/Actions/ClosePositionAndApplyRealizedProfitAction.php` y `RecordPositionClosedOutboxAction.php` en la misma carpeta |
| Completar evidencia pendiente bajo bloqueo | `app/Features/Trading/Account/Actions/RecoverPositionClosedOutboxAction.php` |
| Mensaje persistido y relación con posición | `app/Features/Trading/Account/Models/PositionClosedEventOutbox.php` |
| Unicidad, claims y confirmación condicionada | `app/Features/Trading/Account/Repositories/PositionClosedOutboxRepository.php` |
| Publicación después de persistencia y recuperación | `app/Features/Trading/Account/UseCases/PublishPositionClosedEventsUseCase.php` |
| Proyección compartida con el feed | `app/Features/Trading/Account/Support/InternalProgressionActivityMapper.php` |
| Pruebas de las garantías | `tests/Feature/Trading/PositionClosedOutboxTest.php` |

Broker resuelve su topic mediante `KafkaTopicsEnum::BROKER` y
`config('kafka.app_topic')`. Copy Trading debe usar su propio topic y acordar su
nombre físico con IB; no debe publicar sus cierres en el topic de Broker.

Ejemplo de envío usando el transporte instalado en Broker (`mmtech/iam-rbac v1.13`);
`$message` representa un mensaje ya persistido y reclamado del outbox:

```php
$payload = $message->payload;
$publisher->publish(
    topic: $message->topic,
    payload: $payload,
    key: $payload['activity']['source_activity_id'],
    headers: [
        'event_name' => 'position_closed',
        'content_type' => 'application/json',
    ],
    format: \Mmtech\Rbac\Kafka\SerializationFormat::Json,
);
```

`$publisher` es `Mmtech\Rbac\Kafka\KafkaEventPublisher`. El ejemplo no crea una
identidad nueva durante el envío ni sustituye la lógica de claim/confirmación.
Si Copy Trading utiliza otro transporte, debe producir la misma clave, headers
y estructura JSON, sin wrappers ni doble codificación del body.

### Registro y comportamiento de IB

IB declara topic, `position_closed`, versión `1` y adapter en la capacidad
`closed_trading_volume` de cada proveedor. Los topics físicos provienen de
`modules.sources.broker.topic` y `modules.sources.copy_trading.topic`, configurados
por `REWARDS_VOLUME_BROKER_TOPIC` y `REWARDS_VOLUME_COPY_TRADING_TOPIC`.

`KafkaServiceProvider` prepara el mapa nativo `rbac.consumer.handlers`; el comando
original `rbac:consume-snapshots` descubre ambos topics sin una lista adicional de
proveedores. Antes de consumir, IB valida las declaraciones; luego enruta cada
mensaje por topic + `event_name` + `schema_version`. El módulo se determina desde
la declaración y `provider_code` debe coincidir con ella.

Las declaraciones pausadas o inactivas siguen recibiendo evidencia. IB guarda un
receipt completo, deduplica por módulo + `source_activity_id` y aplaza su cálculo
si el módulo no está operativo. Una contradicción no sobrescribe la evidencia
original. Evento y feed periódico comparten identidad y datos, por lo que `both`
no crea una segunda obligación para el mismo hecho. Elegibilidad, beneficiarios,
importe y settlement siguen siendo responsabilidad de IB.

### Aceptación del productor Copy Trading

- Rollback del cierre: ningún mensaje queda publicable.
- Caída después del commit: el outbox permite recuperar el envío.
- Reintentos y cierres duplicados: conservan identidad, topic, clave y payload.
- Evidencia incompleta: no hay publicación; se recupera al completarse.
- Evento y feed: misma identidad y evidencia económica, conservando precisión temporal.
- Kafka integrado: cierre persistido → evento en topic propio → receipt completo en
  IB → Reward → settlement. Las pruebas locales de Broker/IB no acreditan este flujo
  para Copy Trading ni sustituyen su aceptación integrada.

## 4. Profit cerrado por cuenta y período

```http
POST /accounts/negative-pnl-periods/resolve
```

IB obtiene de IAM los usuarios de la red y solicita el resultado de sus cuentas para un intervalo común. Un usuario puede tener varias cuentas. Copy Trading devuelve una fila por cuenta elegible; no recibe ni devuelve la estructura de la red.

### Entrada

```json
{
  "subjects": [
    { "external_user_id": "<iam_user_uuid_a>" },
    { "external_user_id": "<iam_user_uuid_b>" }
  ],
  "occurred_from": "2026-10-01T00:00:00Z",
  "occurred_until": "2026-10-16T00:00:00Z"
}
```

| Campo | Requisito |
| --- | --- |
| `subjects` | Obligatorio; entre 1 y 100 usuarios únicos |
| `subjects[].external_user_id` | UUID de usuario IAM |
| `occurred_from` | Inicio inclusivo, obligatorio |
| `occurred_until` | Fin exclusivo, obligatorio; posterior al inicio y no futuro |

### Selección y cálculo

El universo requerido comprende cuentas LIVE B-book de los usuarios solicitados, existentes al corte, incluidas cuentas archivadas o inactivas con actividad aplicable. La autoridad de clasificación B-book y el tratamiento temporal de sus cambios requieren acuerdo antes de habilitar la integración.

Seleccionar posiciones cerradas por cuenta mediante `unix_closed_at`, expresado en epoch milliseconds, en `[occurred_from, occurred_until)`. Si el proveedor utiliza otro campo, documentar su equivalencia autoritativa conservando esa semántica temporal.

```text
npnl(cuenta, intervalo) = suma de profit de todas sus posiciones cerradas en el intervalo
```

La única magnitud utilizada es `profit`; no incorporar swap, comisiones ni otros cargos. Incluir posiciones negativas, positivas y de profit cero. Sumar con aritmética decimal exacta a escala 10, sin redondear cada posición a la precisión monetaria.

`npnl` conserva el signo: puede ser negativo, positivo o cero. No filtrar cuentas ganadoras ni transformar pérdidas en valores absolutos.

### Salida

```json
{
  "success": true,
  "data": [
    {
      "external_user_id": "<iam_user_uuid_a>",
      "trading_account_id": "<account_uuid_1>",
      "external_trader_id": "100001",
      "server_group_id": "<group_uuid>",
      "currency_code": "USD",
      "currency_precision": 2,
      "occurred_from": "2026-10-01T00:00:00Z",
      "occurred_until": "2026-10-16T00:00:00Z",
      "npnl": "-1000.0000000000",
      "position_ids": ["<position_uuid_1>", "<position_uuid_2>"]
    },
    {
      "external_user_id": "<iam_user_uuid_a>",
      "trading_account_id": "<account_uuid_2>",
      "external_trader_id": "100002",
      "server_group_id": "<group_uuid>",
      "currency_code": "USD",
      "currency_precision": 2,
      "occurred_from": "2026-10-01T00:00:00Z",
      "occurred_until": "2026-10-16T00:00:00Z",
      "npnl": "600.0000000000",
      "position_ids": ["<position_uuid_3>"]
    }
  ],
  "meta": {
    "message": "Closed profit periods resolved.",
    "completed_subjects": ["<iam_user_uuid_a>", "<iam_user_uuid_b>"]
  }
}
```

| Campo de cuenta | Semántica |
| --- | --- |
| `external_user_id` | Usuario solicitado propietario de la cuenta |
| `trading_account_id` | Identidad estable de la cuenta en Copy Trading |
| `external_trader_id` | Identificador externo de la cuenta en trading |
| `server_group_id` | Grupo de la cuenta |
| `currency_code`, `currency_precision` | Moneda del profit y precisión monetaria explícitas |
| `occurred_from`, `occurred_until` | Mismo intervalo solicitado, sin recortes por cuenta |
| `npnl` | Suma firmada de profit, como string decimal a escala 10 |
| `position_ids` | IDs de todas las posiciones incluidas, únicos y ordenados |

Los IDs incluyen posiciones ganadoras, perdedoras y de profit cero. Deben permitir localizar esas posiciones posteriormente para auditoría; no se solicitan posiciones completas.

Una cuenta elegible sin cierres devuelve `npnl: "0.0000000000"` y `position_ids: []`. Un usuario sin cuentas elegibles no produce filas, pero aparece en `meta.completed_subjects`. Si ninguno tiene cuentas elegibles, `data` es `[]` y se confirman todos los usuarios solicitados.

`completed_subjects` contiene exactamente una vez a cada usuario solicitado. Cada cuenta aparece una sola vez y pertenece a uno de ellos. El lote no se pagina: se completa entero o responde con error. No confirmar usuarios pendientes ni omitir cuentas por fallos de lectura. Publicar límites adicionales de ventana o respuesta antes de integrar; nunca truncar silenciosamente.

### Persistencia, auditoría y pagos en IB

1. IB congela red IAM, niveles, tasas e intervalo del run. El referido directo ocupa el nivel económico `0`.
2. Solicita los usuarios en lotes y valida confirmación completa, cuentas, monedas y evidencia.
3. Persiste resultados por cuenta e intervalo, incluyendo `npnl`, identidades y `position_ids`, vinculados al contexto del run. Son registros de actividad, sin una Reward por cuenta.
4. Suma resultados firmados por suscripción beneficiaria, módulo, período, nivel y moneda. Los grupos de trading no separan el agregado. Monedas, niveles y módulos distintos mantienen agregados independientes; no hay conversión FX.
5. Un agregado negativo que cumple la regla puede generar una Reward agregada. Cero o positivo produce un resultado auditable sin Reward.
6. Conserva contribuciones, tasas y resultado del cálculo. Solicita liquidación a Finance con identidad idempotente; la confirmación del asiento permite marcar la Reward como liquidada.

```text
total_npnl = suma de npnl de las cuentas elegibles del nivel y moneda
importe = abs(total_npnl) × tasa_nivel × personal_rate × master_rate
```

`master_rate` aplica para Master IB. IB evalúa el mínimo antes de un único redondeo half-up a la precisión monetaria. Precisiones contradictorias para una misma moneda impiden completar el cálculo.

Ejemplo: cuentas del mismo nivel, módulo y moneda aportan `-1000`, `600` y `-200`. El agregado es `-600`; con tasa `0.10`, factores aplicables iguales a `1` y mínimo satisfecho, la Reward es `60 USD`. La auditoría conserva la participación de las tres cuentas.

La continuidad de intervalos y recuperación de runs pertenecen a IB. Las consultas no producen pagos en Copy Trading. IB conserva entradas aceptadas para recuperar una ejecución sin duplicar contribuciones ni liquidaciones.

Los IDs persistidos permiten comparar las posiciones incluidas en un run con las consultadas posteriormente para el mismo intervalo y detectar diferencias de pertenencia. No permiten detectar por sí solos un cambio de profit en una posición con el mismo ID. El tratamiento de cierres incorporados tarde y correcciones permanece pendiente de negocio; este contrato no prescribe recálculo automático ni compensación de pagos.

## Errores y operación

| HTTP | Situación |
| --- | --- |
| `401` / `403` | Consumidor no autenticado o sin autorización |
| `404` | Recurso individual inexistente |
| `409` | Evidencia temporalmente no disponible; cierre individual: `CLOSED_POSITION_NOT_READY` |
| `422` | Parámetros, intervalo o referencias inválidos |
| `429` | Límite de solicitudes excedido |
| `5xx` | Fallo técnico del proveedor |

Los errores incluyen código de máquina y mensaje sin datos sensibles. Publicar envelope de error, timeouts, límites y política de reintento en OpenAPI. Las consultas pueden repetirse sin efectos financieros. Una nueva lectura puede revelar cambios autoritativos; no autoriza por sí sola modificar un run aceptado.

## Definiciones necesarias para integrar

- Confirmar prefijo HTTP, namespaces, formato de cursor y límites operativos.
- Definir atribución de posiciones a usuarios IAM y cuentas de Copy Trading.
- Confirmar autoridad LIVE/B-book, criterio temporal y cambios de clasificación.
- Establecer separación económica cuando varios proveedores compartan cuentas o posiciones subyacentes: un namespace distinto no evita pagar dos veces la misma actividad.
- Certificar completitud CPA y acordar consumo de eventos cuando se habilite.
- Implementar adapters, configuración de capacidades y habilitación del módulo en IB, con BDS y pruebas correspondientes. Este documento no acredita una integración desplegada.

## Criterios de aceptación

- [ ] Catálogo con jerarquía, filtros, paginación y referencias estables.
- [ ] Feed con filtros combinados, intervalo semiabierto y continuación determinista, incluidos cierres simultáneos.
- [ ] Feed y resolución individual coinciden; `409 CLOSED_POSITION_NOT_READY` probado.
- [ ] Evidencia económica suficiente para capacidades habilitadas y certificación de completitud CPA.
- [ ] Lotes de usuarios con múltiples cuentas y universo LIVE B-book verificado.
- [ ] Cierre en el inicio incluido y cierre en el fin excluido; timestamps comprobados.
- [ ] Suma exacta de profit negativo, positivo y cero; todos los IDs incluidos.
- [ ] Cuentas sin cierres entregan cero; usuarios sin cuentas quedan confirmados.
- [ ] Confirmación exacta de usuarios, sin cuentas duplicadas, ajenas o resultados parciales.
- [ ] IB conserva resultados e IDs; agregados cero o positivos no generan Rewards.
- [ ] Compensación por nivel y moneda validada con el ejemplo de `60 USD`; reintentos sin pagos duplicados.
- [ ] S2S, autorización y separación económica de origen verificadas.
- [ ] OpenAPI y Postman con entradas, salidas, errores y variables sin secretos; prueba integrada de capacidades habilitadas.

