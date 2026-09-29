# Operación y release de Progression (MMTECH-240)

Estado: **Implementado; pendiente de evidencia de despliegue**

## Operación

- El scheduler ejecuta `progression:close-windows` cada cinco minutos con
  `onOneServer`, `withoutOverlapping` y un advisory lock PostgreSQL compartido
  por scheduler e invocaciones manuales.
- El cierre crea runs de ventanas vencidas, reintenta únicamente resultados
  fallidos y aplica placements finales pendientes. No consulta IAM ni recalcula
  distribuciones o contribuciones.
- `progression:recover-runs` recupera resultados `failed` y applications de
  placement pendientes sin abrir ventanas nuevas. `--run=<uuid>` limita la
  operación a un run concreto.

## Procedimiento de recuperación

1. Revisar los logs `progression.close_windows.completed`,
   `progression.result.retryable_failure` y
   `progression.placement.retryable_failure` por `run_id` y `run_result_id`.
2. Confirmar en PostgreSQL los resultados `failed` o los `completed` sin fila
   en `progression_placement_applications`.
3. Ejecutar `php artisan progression:recover-runs --run=<uuid>`; para una
   reconciliación global usar `php artisan progression:recover-runs`.
4. Verificar que no quedan fallos recuperables ni placements pendientes. Los
   outcomes `applied`, `unchanged`, `fixed` y `not_active` son terminales.

## Observabilidad y release

- Los logs JSON incluyen contadores de runs, resultados, reintentos, placements
  por outcome y fallos, además de IDs técnicos y clase de excepción. No incluyen
  usuarios externos, tokens ni mensajes crudos de excepción.
- Alertar cuando existan `results_failed` o `placements_failed` consecutivos, o
  cuando permanezcan placements pendientes tras la siguiente ejecución.
- Antes de release: migraciones aplicadas, `CACHE_STORE=database` o un store
  compartido atómico, scheduler activo, y validación de la suite PostgreSQL.

## Evidencia

- Scheduler: `php artisan schedule:list`.
- Cierre manual: `php artisan progression:close-windows`.
- Recuperación: `php artisan progression:recover-runs --run=<uuid>`.
- Calidad: suite PostgreSQL focalizada, Pint, `graphify update .` y
  `git diff --check`.
