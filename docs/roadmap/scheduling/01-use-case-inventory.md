# Scheduling: inventario

Estado: completado. Última revisión: 2026-10-07.

Administración consulta catálogo, detalle y siguiente disparo; modifica descripción, cron y habilitación con motivo y versión; solicita ejecuciones manuales idempotentes; consulta historial, cambios y salida según permisos independientes.

El scheduler admite tareas vencidas por minuto UTC y registra omisiones por ocupación o gate. El supervisor observa ejecución; la recuperación detecta interrupciones sin desalojar runners vivos. No existen altas/bajas administrativas ni comandos libres.

Evidencia: SchedulingTest y SchedulingRunnerTest. Retención pendiente antes de producción.
