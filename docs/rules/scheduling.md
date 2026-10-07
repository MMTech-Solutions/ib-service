# Scheduling operativo

Estado: **obligatoria**. Última revisión: 2026-10-07.

## Catálogo y configuración

Scheduling administra únicamente las siete tareas registradas en código. No permite crear tareas, cambiar ejecutores ni introducir argumentos arbitrarios. La descripción, expresión cron de cinco campos y habilitación automática son editables mediante versión esperada y motivo obligatorio. Cambios efectivos incrementan versión y crean auditoría transaccional con actor IAM y snapshots; actualizaciones idénticas no crean otra auditoría.

En Postman, cambiar SCHEDULING_IDEMPOTENCY_KEY para solicitar una nueva corrida; repetir la misma clave conserva el run de la primera solicitud.

Cron usa tiempo real UTC, resolución de un minuto y no reproduce disparos perdidos. Las restricciones de Plans, Progression y Rewards conservan autoridad. El gate PnL utiliza el puerto público vigente de Settings. Scheduling no modifica períodos económicos ni reemplaza runs de dominio.

## Admisión y ejecución

Una tarea admite como máximo un run queued/running. La deduplicación automática se impone por tarea e instante programado; la manual mediante Idempotency-Key, conservado como SHA-256. Reutilizar una clave para otro actor, tarea o motivo produce conflicto. La admisión y el trabajo database en cola scheduling se guardan en la misma transacción y conexión. No usar after_commit ni otra conexión de base para esta cola: rompería la atomicidad.

Deshabilitar solo impide nuevas admisiones automáticas. Los runs ya aceptados terminan y siguen siendo posibles las ejecuciones manuales. Cada run congela configuración y timeout al aceptarse. Un gate de negocio puede impedir ejecutar incluso después de la aceptación.

El worker lanza un supervisor aislado; este controla timeout y heartbeat del runner. El runner adquiere un advisory lock PostgreSQL por tarea en una conexión dedicada y ejecuta el comando cerrado dentro de su propio proceso. El mutex de Progression permanece adicionalmente vigente. La muerte del worker no libera el lock del runner. La recuperación solo marca interrupted cuando puede adquirir ese lock; no interpreta un heartbeat vencido como prueba de terminación.

Estados: queued, running, succeeded, failed, skipped, interrupted. Scheduling no reintenta automáticamente ni aplica rollback económico. El próximo cron o una nueva solicitud manual crean otra ejecución. Progression locked se registra skipped aunque el exit code sea cero. El éxito técnico de Rewards no acredita éxito de todos sus resultados económicos.

## Evidencia y seguridad

Timestamps operativos y auditoría técnica usan tiempo real UTC. Los comandos capturan y liberan su reloj de dominio; el job técnico no inicia el reloj Lab. El historial guarda actor estable, motivo, configuración, tiempos, exit code y outcome técnico.

Stdout/stderr tienen permisos separados de lectura general, límite de 1 MiB por stream y marcas de truncamiento. Se redactan secretos conocidos de configuración, campos sensibles, URLs y correos. Los checkpoints persisten solo líneas completas redactadas; una interrupción puede conservar salida parcial. No publicar mensajes crudos de excepciones. La evidencia económica sigue en el feature propietario.

No hay purga automática. La retención de historial/salida debe definirse antes de producción.

## Operación y despliegue

1. Detener scheduler y workers de la versión anterior.
2. Aplicar migraciones: `php artisan migrate --force --no-interaction`.
3. Sincronizar el catálogo: `php artisan scheduling:sync --no-interaction`. Conserva ediciones existentes y es idempotente.
4. Provisionar los permisos ib.scheduling.read/update/execute/audit/output en IAM; para fixtures locales, ejecutar LocalRbacSnapshotSeeder.
5. Reiniciar workers: `php artisan queue:work scheduling --queue=scheduling --tries=1 --timeout=3660`.
6. Activar el scheduler estándar `php artisan schedule:run` cada minuto y comprobar `php artisan schedule:list`.

El schedule contiene scheduling:dispatch y scheduling:reconcile. Las siete tareas anteriores ya no se registran directamente en providers. No se consulta ni sincroniza persistencia al hacer bootstrap.

SCHEDULING_TIMEOUT_SECONDS define el techo global (3600 por defecto); config scheduling.task_timeouts permite reducirlo por código. La cadena de límites es: ejecución 3600, supervisor 3630, worker 3660, retry_after 3720. Si cambia el techo, ajustar el timeout del worker y reiniciar workers/config cache. Nunca configurar un timeout de tarea mayor al techo.

Para diagnóstico: scheduling:dispatch admite el minuto actual, scheduling:reconcile revisa runs estancados. scheduling:execute y scheduling:supervise son comandos internos por UUID de run ya aceptado; no admiten nombres de comandos desde entrada.

Alertar sobre errores del dispatcher, ausencia de workers, queued antiguos, heartbeats vencidos y failed/interrupted consecutivos. No desplegar si faltan tablas o catálogo: la indisponibilidad aborta, sin ejecutar trabajo sin auditoría.
