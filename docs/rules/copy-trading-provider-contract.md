# Contrato proveedor Copy Trading — V1

Estado: contrato requerido; consumidores IB implementados localmente, aceptación S2S pendiente.

Copy Trading certifica exclusivamente actividad de cuentas creadas desde su plataforma. Las cuentas externas asociadas quedan excluidas. IB no reconstruye el origen ni selecciona cuentas dentro del proveedor. Usuario fuente es el dueño de la cuenta; IAM resuelve beneficiarios. Broker y Copy Trading conservan identidad y contribuciones independientes por módulo.

## Transporte y configuración

Prefijo `/api/copy-trading/v1/internal`; headers `Accept: application/json`, `X-Internal-Token` y `X-Internal-Source: mmt-ib-service`. POST añade `Content-Type: application/json`. URL, prefijo, token, source y timeout proceden de `modules.sources.copy_trading.*` en Settings. No se utilizan credenciales Broker.

La colección raíz `ib-service.postman_collection.json`, carpeta `Provider Contracts / Copy Trading`, contiene requests y ejemplos sintéticos del contrato requerido. Estos endpoints pertenecen al proveedor; no son rutas de IB ni acreditan su disponibilidad.

## Catálogo

`GET /instrument-catalog/{type}`, tipos `platform`, `trading_server`, `server_group`, `security`, `symbol`. Query: `page`, `per_page`, `search` y filtros nativos `platform_reference`, `trading_server_reference`, `server_group_reference`, `security_reference`, `symbol_reference`.

`data` es una lista de `{type, reference, name, parents, currency_code?}`. Identificadores nativos no vacíos, sin `:`; parents contiene referencias nativas de platform/trading_server/server_group/security. Un symbol exige parent server_group y moneda para configuración económica. `meta.pagination` exige enteros `total >= 0`, `current_page >= 1`, `per_page >= 1`.

IB normaliza referencias a `copy_trading:<type>:<id>` y símbolos a `copy_trading:server_group:<grupo>:symbol:<símbolo>`. Los filtros del catálogo se traducen a IDs nativos; el feed recibe referencias canónicas completas.

## Feed compartido

`GET /progression-activities`: `from`, `until` UTC, intervalo `[from, until)`, `limit`, `cursor` opcional; `external_user_id` opcional filtra un usuario y `instrument_references[]` opcional filtra símbolos canónicos. CPA usa ambos filtros y agota páginas antes de confirmar el corte; Progression consulta actividad del módulo; volumen usa el mismo feed y sus propios cursores.

Cada actividad contiene `source_activity_id=copy_trading:position:<id>`, `subject_external_user_id`, `metric_code=closed_trading_volume`, `unit_code=lot`, `quantity` decimal string, `occurred_at` UTC, `server_group_id`, `symbol_id`, `currency_code`, `currency_precision` y `broker_granted_commission`. Los dos IDs instrumentales son nativos, sin `:`. El último campo conserva su nombre contractual y representa comisión del proveedor Copy Trading.

CPA/Progression admiten cantidades hasta ocho decimales; volumen admite hasta diez conforme a su contrato. No redondear silenciosamente para adaptar una actividad. Comisión hasta diez decimales, moneda de tres letras mayúsculas y precisión entera 0–10. IDs de usuario y evento de volumen son UUID. Identidad de cierre es estable.

Orden estable por cierre e ID. Para Progression, el cursor codifica en base64 JSON `{"closed_at": <milisegundos Unix>, "id": "<ID nativo posición>"}` y selecciona estrictamente después del par. El proveedor conserva el mismo criterio temporal al emitir y consumir el cursor. `meta.next_cursor` es obligatorio: string no vacío para continuar, null al finalizar. Una página vacía final confirma ausencia de actividad. No repetir cursores ni identidades entre páginas. El limit solicitado se respeta.

Copy Trading garantiza que no incorpora, corrige ni retira hechos anteriores a un corte CPA confirmado; contradicciones dejan error de contrato y no reescriben aportes. CPA y Progression son HTTP periódico; no consumen el evento Kafka de volumen.

## PnL por intervalos

`POST /accounts/negative-pnl-periods/resolve` conserva el [contrato N-PnL](negative-pnl-contract.md), sustituyendo origen y selección de cuentas por Copy Trading. Entrada: `subjects: [{external_user_id}]`, `occurred_from`, `occurred_until`. Usuarios únicos, lote completo, límite común configurado en IB/proveedor, fin no futuro.

`data` contiene resultados por cuenta: `external_user_id`, `trading_account_id`, `external_trader_id`, `server_group_id`, `currency_code`, `currency_precision`, ambos límites, `npnl` decimal firmado y `position_ids` completos. Resultado suma exclusivamente profit de cierres con ambos signos, sin swap, comisiones o fees, en `[inicio, fin)`. Cuentas elegibles existentes al corte incluyen archivadas/inactivas. Sin cierres: cero y posiciones vacías. Usuarios sin cuentas elegibles: presentes en `meta.completed_subjects`, sin fila de cuenta. Todos los usuarios solicitados se confirman una vez; no confirmar lotes parciales.

IB conserva snapshots y compensa por suscripción, módulo, período, nivel y moneda. No compensa Broker con Copy Trading. Precisión contradictoria dentro de una moneda invalida el período; no hay FX. Solo el agregado negativo elegible origina Reward pending mediante procesamiento periódico. Profit y todas las posiciones, también ganadoras, quedan congelados; no se consultan balances ni baselines.

Timeout, 409 y 5xx son recuperables; otros errores o respuestas incompatibles producen evidence_invalid. No avanzar cursores con evidencia parcial. Correcciones posteriores a períodos congelados no originan recálculo automático.

## Kafka: exclusivamente volumen

El proveedor debe implementar [actividad cerrada V1](volume-activity-event-contract.md): topic real configurado mediante REWARDS_VOLUME_COPY_TRADING_TOPIC, headers event_name=position_closed/content_type=application/json, clave activity.source_activity_id, body event_id/schema_version=1/provider_code=copy_trading/activity completa.

Cierre y outbox se persisten atómicamente; reintentos mantienen identidad, topic, clave y payload. Evidencia HTTP y Kafka representa la misma actividad. Kafka captura evidencia; el worker económico crea Reward pending cuando corresponde. CPA y PnL crean sus Rewards mediante HTTP periódico; Progression solo genera puntos/placement.

## Aceptación

Pruebas locales simuladas no acreditan S2S. Equipo proveedor debe demostrar selección de cuentas, paginación, cortes CPA, resultado PnL completo, publicación Kafka y flujos integrados con IAM/Finance hasta settlement. IB requiere configuración administrativa explícita de módulos, símbolos, reglas y plantillas; agregar soporte no habilita remuneración automáticamente.
