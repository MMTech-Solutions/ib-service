# Entrega contractual IB para LAB5

Estado: **implementado localmente; aceptación integrada de ib-labs pendiente**.
Revisión: 2026-10-05. Base de implementación:
81b53613c1ef30f6875f0bdefa43b5a0c338247f.
El SHA de entrega se obtiene del commit que contiene código, documentos,
Postman y fixtures; no utilizar el SHA de base como evidencia de esta entrega.

## Decisión y recuperación

BDS v0.11, BR-POINTS-015 y BR-POINTS-040–043 gobiernan la entrega.
Programs publica CaptureProgressionLadderPort con programas, posiciones y
umbrales exactos. Progression conserva el ladder y participantes con elegibilidad,
puntos exactos y IDs de contribuciones. La preparación se confirma antes de
finalizar resultados, mediante lectura PostgreSQL REPEATABLE READ.

Cada resultado conserva su decisión original antes de completarse. Recuperación
no prepara de nuevo el run: conserva puntos del resultado o del snapshot original,
captura el ladder vigente una vez por plan y ejecución y resuelve otro objetivo.
El mismo intento se reutiliza al finalizar y aplicar; otra ejecución captura los
umbrales nuevamente. No consulta actividad, suma ledger ni incorpora participantes.
Un run completed no se reabre ni cambia completed_at, aunque recupere placement.
Aplicación de placement, registro terminal y outcome del intento son atómicos.

Una migración nueva añade original_decision y recovery_attempts y conserva las
decisiones preparadas existentes. No inventa decisiones ausentes. El snapshot
original permanece intacto. Sin puntos en resultado ni snapshot, se registra
missing_points_evidence; sin ladder utilizable, ladder_unavailable. Ambos fallan
sin aplicar un objetivo histórico ni reconstruir puntos desde el ledger.

### Retirada contractual de legacy (2026-10-05)

LAB5 V1, todavía pendiente de aceptación integrada, retira snapshot.legacy.
Se eliminan la propiedad del modelo/DTO, snapshot_generation y el tratamiento
especial de recuperación. Los snapshots persistidos antiguos se leen tolerando
la clave, pero no se expone ni gobierna el comportamiento. No se reescribe su JSON.
Las migraciones publicadas se conservan; la nueva retira la columna técnica.

Fixtures, pruebas y Postman de IB quedan actualizados en esta entrega. ib-labs
requiere adaptar assertions, comparar ambas decisiones y repetir el gate antes
de cambiar su baseline. Su código está fuera de este repositorio y su adaptación
y aceptación integrada continúan pendientes.

## CLI JSON V1

Comandos:

~~~sh
php artisan progression:evaluate-activities --plan=UUID --module=UUID --from=2026-09-10T00:00:00Z --until=2026-09-11T00:00:00Z --limit=100 --json
php artisan progression:close-windows --json
php artisan progression:recover-runs --run=UUID --json
~~~

Evaluación admite --resuming-after-pause. limit es tamaño de página 1..100,
default 100; se recorren todas las páginas. Los UUID y fechas reales UTC Z son
obligatorios; from < until. No se redondean cantidades ni se cambia deduplicación.

Un único documento stdout:
version=1, operation=evaluate|close|recover, execution_id UUID,
status=executed|locked|invalid|failed, observed_domain_time_utc,
context_id, sequence, outcome, counts e ids. Fecha/contexto/secuencia pueden
ser null si la operación no pudo capturar reloj; en sistema contexto/secuencia
son null. ids contiene evaluation_ids, distribution_ids, run_ids y result_ids.

Evaluación conserva evaluated, skipped_plan_inactive, deferred_module_paused y
rejected_module_inactive. Cierre/recuperación usan outcome=processed.
counts de evaluación: evaluations, accepted, excluded, retryable_failures.
counts de cierre: runs_created, runs_completed, runs_with_errors,
results_completed, results_skipped, results_failed y placements_applied,
placements_unchanged, placements_fixed, placements_not_active, placements_failed.
Recuperación: results_recovered, results_failed, runs_completed y los mismos
contadores de placement.

Exit 0: sin fallo técnico, incluido locked u omisión de negocio. Exit 1:
fallo técnico, retryableFailures, resultados o placements fallidos. Exit 2:
entrada inválida. stderr contiene errores redactados; stdout mantiene counts e
IDs acumulados en una interrupción técnica. Un exit 0 no aprueba el escenario:
el runner debe comprobar status, outcome, counts y lecturas HTTP.
Los tres comandos comparten el advisory mutex de Progression sin adquisición
anidada. Sin --json se conserva salida legible para operación manual.

## Lecturas administrativas V1

Todas requieren gateway, ADMIN_USERINFO, admin_panel e ib.progression.read.
Prefijo /api/ib/v1/admin/. data es el objeto o array directo; filtros y
paginación viven en meta. page >= 1, per_page 1..100 (default 100).
Orden ascendente por created_at e ID; filtros de tiempo [from,to).

| Recurso | Rutas | Filtros |
| --- | --- | --- |
| Distribuciones | progression-distributions y /{id} | module_id, source_activity_id, plan_id, subscription_id, resolved_at_from/to |
| Runs | progression-runs y /{id} | plan_id, status, window_from/to (inicio de ventana) |
| Resultados | progression-runs/{run}/results y /{result} | subscription_id, status |

Distribución: id, module_id, source_activity_id, source_external_user_id,
resolved_at y beneficiaries con beneficiary_external_user_id/distribution_level.
Filtros plan/suscripción usan evaluaciones vinculadas y no duplican distribuciones.

Run: id, plan_id, window_starts_at, window_ends_at, status, started_at,
completed_at y snapshot con ladder.programs, participants, captured_at.
Los participants conservan subscription_id, is_evaluable, contribution_ids y
total_points como string decimal.

Resultado: id, run_id, subscription_id, status, total_points (string o null),
target_program_id, attempt_count, failure_code, decision_at, completed_at,
is_evaluable, omission_reason, placement, original_decision y recovery_attempts. placement conserva status
(not_ready/pending/failed/completed), outcome, failure_code, attempt_count,
last_attempt_at y applied_at. pending_evaluation indica un resultado todavía
sin intento; los errores recuperables usan retryable_failure sin mensajes crudos.

Run inexistente o resultado ajeno al run: 404; falta de permiso: 403; filtros
inválidos: 422. Evaluaciones existentes se reutilizan, incluida su contribución;
no se crea otro endpoint para cantidad, regla, weight o points.

original_decision es null si nunca existió; en otro caso conserva total_points,
target_program_id y decision_at. Su ladder original está en snapshot.ladder.
Los campos superiores target_program_id y decision_at representan la decisión
efectiva más reciente; total_points nunca cambia por un nuevo umbral.
recovery_attempts es un array ordenado de todos los intentos, con id, attempted_at,
ladder (programs con program_id, position y entry_threshold), total_points,
target_program_id, stage (finalize|placement), outcome y failure_code.
Un intento comienza prepared; una omisión original conserva outcome skipped sin resolver ladder. Termina completed en finalización o con el outcome
terminal de placement (applied|unchanged|fixed|not_active); failed conserva código
sanitizado. Una interrupción puede dejar prepared. Si pasa por ambas etapas,
conserva la misma identidad/decisión y muestra su última etapa/outcome.
Configuración, puntos y objetivo ausentes son null. El detalle y los listados
administrativos exponen estos campos con el envelope V1 habitual.

## Ejecución Lab y gate

Provisionar base IB y volumen exclusivos; aplicar migraciones y snapshots RBAC
locales existentes. Mantener scheduler detenido para escenarios manuales.
Configurar APP_ENV=local, IB_LAB_PROFILE=true, IB_DOMAIN_CLOCK=controlled,
IB_LAB_CLOCK_FILE con path privado e IB_LAB_FAILURES_ENABLED=true.

~~~sh
php artisan ib-lab:clock --context=UUID --sequence=0 --now=2026-09-10T10:00:00Z
php artisan progression:lab-arm-failure --id=UUID --context=UUID --operation=close --stage=before_result_finalize --subscription=UUID
php artisan progression:lab-arm-failure --id=UUID --context=UUID --operation=close --stage=before_placement --result=UUID
~~~

El archivo se inicializa con el comando antes de cualquier request controlado.
Cada armado admite exactamente uno de --subscription o --result.
before_result_finalize falla después de persistir puntos/objetivo; before_placement
falla antes de la transacción que aplica placement. Se consume solo el elemento
seleccionado y las demás suscripciones continúan. Las opciones --context y --id
son UUID de control; no son usuarios ni credenciales.

La evidencia Lab debe crear configuración y suscripciones por HTTP en T0,
evaluar actividad posterior, cerrar en fin+1h, provocar y observar fallos,
reiniciar, cambiar umbrales y recuperar. Verificar mismos IDs/puntos/objetivo,
éxitos conservados, historial, consumo durable y aplicación única.
Broker/IAM reales, fixtures externos y scheduler compartido requieren la
revisión y ejecución de ib-labs. No hay cambios en ib-labs, settlement o Finance.
AUTH-8 permanece accepted-open.

## Evidencia local y fixtures

- tests/Feature/Lab5ContractTest.php: HTTP/CLI temporal, publicación/binding en T0,
  mutex ocupado, fallo técnico tras una página, dos fallos internos durables,
  recuperación con umbral cambiado, actividad tardía y rechazo del reloj.
- tests/Feature/Progression/ y tests/Unit/ProgressionWindowClosingUseCaseTest.php:
  precisión, idempotencia, repositorios, concurrencia y compatibilidad legado.
- tests/Architecture/: fronteras y pureza de presentación.
- ib-service.postman_collection.json: seis lecturas nuevas en Administration/Progression.
- [Fixtures sanitizados](fixtures/lab5-contract-fixtures.json): documentos reales
  capturados de la prueba integrada y normalizados a UUID sintéticos. Son
  ejemplos completos de contrato, no seed de una instancia ni evidencia S2S.

La captura opcional IB_LAB5_CAPTURE=1 al ejecutar Lab5ContractTest escribe un
artefacto temporal en storage/framework/testing, sin modificar fixtures
versionados. Los fixtures de la entrega se sanitizan explícitamente.
No congelar baseline de ib-labs hasta revisar el SHA y repetir el gate integrado.

Validación de entrega local (2026-10-05):

- `php vendor/phpunit/phpunit/phpunit`: 560 pruebas aprobadas, 4.257 aserciones,
  incluidos contratos PostgreSQL, recuperación, reloj y arquitectura.
- Validación final de Progression, LAB5 y arquitectura: 87 pruebas aprobadas, 814 aserciones. Incluye los 12 escenarios de ProgressionRecoveryTest (umbrales, intentos, terminales, omisión, evidencia ausente y migración).
- `php vendor/bin/pint --dirty --format agent`: completado.
- Postman v2.1: JSON válido, 98 requests; contraste con las 97 rutas de
  `php artisan route:list --except-vendor --json` sin rutas faltantes y con
  endpoints operativos del bootstrap cubiertos.
- `graphify update .`: completado; `git diff --check`: sin errores.

Las migraciones se verificaron en la base aislada de pruebas. La aceptación
integrada y el cambio de baseline de ib-labs permanecen pendientes.

