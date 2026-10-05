# Entrega contractual IB para LAB5

Estado: **implementado localmente; aceptación integrada de ib-labs pendiente**.
Revisión: 2026-10-05. Base de implementación:
81b53613c1ef30f6875f0bdefa43b5a0c338247f.
El SHA de entrega se obtiene del commit que contiene código, documentos,
Postman y fixtures; no utilizar el SHA de base como evidencia de esta entrega.

## Decisión y recuperación

BDS v0.10, BR-POINTS-015 y BR-POINTS-040–043 gobiernan la entrega.
Programs publica CaptureProgressionLadderPort con programas, posiciones y
umbrales exactos. Progression conserva el ladder y participantes con elegibilidad,
puntos exactos y IDs de contribuciones. La preparación se confirma antes de
finalizar resultados, mediante lectura PostgreSQL REPEATABLE READ.

Cada resultado conserva decisión_at (decision_at en el contrato técnico),
puntos y programa objetivo antes de completarse. Una recuperación utiliza esa
decisión; si faltaba, la construye desde el snapshot. Cierre reintenta runs
incompletos anteriores; recuperación no abre ventanas nuevas. Un run completed
no se reabre, aunque tenga placement pendiente. La aplicación de placement y
su registro terminal siguen siendo atómicos.

Las migraciones añaden snapshot y evidencia de decisiones/placement, y una tabla
privada de fallos Lab. No aplicarlas a producción como parte de una ejecución
del runner. Los resultados finalizados anteriores no cambian. Runs incompletos
sin snapshot capturan contexto al primer intento posterior con legacy=true.
Los resultados fallidos antiguos que ya habían sido incluidos se conservan aun
si no aparecen en la lectura actual de participantes. No se afirma que ese
contexto legado reproduzca el cierre histórico. LAB5 usa runs nuevos.

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
completed_at y snapshot con ladder.programs, participants, captured_at y legacy.
Los participants conservan subscription_id, is_evaluable, contribution_ids y
total_points como string decimal.

Resultado: id, run_id, subscription_id, status, total_points (string o null),
target_program_id, attempt_count, failure_code, decision_at, completed_at,
is_evaluable, omission_reason y placement. placement conserva status
(not_ready/pending/failed/completed), outcome, failure_code, attempt_count,
last_attempt_at y applied_at. pending_evaluation indica un resultado todavía
sin intento; los errores recuperables usan retryable_failure sin mensajes crudos.

Run inexistente o resultado ajeno al run: 404; falta de permiso: 403; filtros
inválidos: 422. Evaluaciones existentes se reutilizan, incluida su contribución;
no se crea otro endpoint para cantidad, regla, weight o points.

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

- `php vendor/phpunit/phpunit/phpunit`: 551 pruebas aprobadas, 4.156 aserciones,
  incluidos contratos PostgreSQL, recuperación, reloj y arquitectura.
- `php vendor/bin/pint --dirty --format agent`: completado.
- Postman v2.1: JSON válido, 98 requests; contraste con las 97 rutas de
  `php artisan route:list --except-vendor --json` sin rutas faltantes y con
  endpoints operativos del bootstrap cubiertos.
- `graphify update .`: completado; `git diff --check`: sin errores.

Las migraciones se verificaron en la base aislada de pruebas. La aceptación
integrada y el cambio de baseline de ib-labs permanecen pendientes.

