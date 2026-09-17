# Modules M3: contrato de actividad normalizada para Progression

Estado: **Contrato listo; implementación pendiente**
Consumidor real: `Progression PG1`
Última revisión: 2026-09-17

## Propósito

M3 publica la consulta pull-only que Progression necesita para obtener hechos de
actividad sin conocer Broker, Prop Firm, Copy Trading ni Trading Account Service.
La normalización describe el hecho externo; no decide plan, suscripción,
placement, regla, elegibilidad ni puntos.

## Frontera pública

Modules publica `ListProgressionActivitiesPort` en `Contracts/Ports/Input` y
Data versionado V1. Progression no llama repositories, SDKs ni modelos internos
de Modules: implementa su propio puerto de salida y un adapter que invoca esta
frontera.

La consulta recibe:

- `module_id`, `occurred_from` inclusivo y `occurred_until` exclusivo, siempre
  UTC;
- cursor opaco opcional y límite acotado; el cursor ordena de forma estable por
  `occurred_at` y `source_activity_id`;
- ningún plan, programa, regla, placement ni beneficiario decidido por IB.

Devuelve una página con condición operacional vigente del módulo y hechos
`ProgressionActivityData`. Cada hecho contiene exclusivamente:

- `module_id`, `source_activity_id` estable e idempotente;
- `subject_external_user_id`, la identidad externa atribuible que Progression
  resolverá como beneficiario;
- `metric_code`, `unit_code`, `quantity` decimal de hasta ocho decimales;
- `occurred_at` UTC e `instrument_reference` opcional;
- cursor siguiente, cuando existan más hechos.

No contiene payloads de proveedor, secretos, URLs, modelos Eloquent, plan,
suscripción, programa, placement, regla ni puntos. Cada adapter traduce su SDK o
HTTP a este contrato; cambiar Broker por Trading Account Service solo cambia el
adapter.

## Operatividad, seguridad e idempotencia

- Módulo activo y `running`: Modules consulta y devuelve actividad normalizada.
- Módulo activo y `paused`: Modules puede consultar y devolver actividad para
  preservar la ventana; Progression no evalúa ni concede puntos hasta reanudar,
  conforme a `BR-MODULE-010` y `BR-POINTS-013`.
- Módulo inactivo: Modules no invoca el proveedor, rechaza la consulta con un
  resultado tipado y registra evidencia técnica de rechazo (`BR-MODULE-015`).
  No existe replay automático al reactivarlo.
- Los adapters validan identidad de productor, estructura, tipos, escala,
  unidades, timestamps y pertenencia al contexto autorizado. Aplican timeout,
  límite de página y rango máximo; nunca abren una transacción local durante la
  llamada remota.
- El cursor, el rango temporal y `source_activity_id` permiten relecturas. La
  deduplicación de negocio permanece en Progression mediante su índice único;
  Modules no crea un ledger duplicado.

## Primer adapter y pruebas

La primera implementación se limita a capacidades efectivamente registradas en
M1. El adapter concreto se selecciona por capability mediante una allowlist y se
prueba contra fixtures de su proveedor; una capability sin adapter no se anuncia
como consultable para Progression. M2, símbolos y scope instrumental distinto de
`all` no forman parte de M3.

M3 queda lista cuando:

- el puerto V1 y sus Data son consumibles por Progression sin imports internos;
- un adapter devuelve páginas equivalentes y deterministas para una capability
  soportada, y sus errores se traducen a resultados tipados;
- módulo inactivo no hace llamadas externas y deja evidencia técnica;
- módulo pausado conserva la consulta sin conceder cálculo alguno;
- pruebas de contrato validan el mismo resultado normalizado para los adapters
  implementados y cubren cursor, escala, timeout, actividad repetida e
  inactividad.

## Fuera de M3

- Ingesta Kafka como vía principal, outbox/inbox y replay de transporte.
- Catálogo externo de símbolos (M2), configuración de reglas o cálculo de puntos.
- Persistencia local del payload de actividad, runs y cambios de placement.
- Recompensas y pagos.