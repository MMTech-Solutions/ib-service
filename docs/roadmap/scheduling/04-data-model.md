# Scheduling: datos

Estado: implementado. Última revisión: 2026-10-07.

scheduling_tasks usa code estable como primary key; conserva description, cron_expression, automatic_enabled, version y updated_at real.

scheduling_runs usa UUID y FK task_code; registra snapshot JSONB, origen/estado, actor/motivo, hashes de idempotencia, tiempos operativos, timeout, código/outcome y salida redactada limitada.

scheduling_audits usa UUID/FK task_code, actor/motivo, instante y snapshots before/after JSONB.

Índices únicos: idempotency_key global, tarea con estado queued/running, tarea/instante para origen automatic. Índices de consultas: tarea/fecha, origen/fecha, estado/heartbeat y auditoría tarea/fecha. Admisión serializa filas de tarea y reserva claves manuales con advisory lock transaccional. Los repositories devuelven DTOs; la cola database comparte la transacción del repositorio.
