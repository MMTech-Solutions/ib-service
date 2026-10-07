# Scheduling: modelo operativo

Estado: completado. Última revisión: 2026-10-07.

Task es una identidad técnica registrada en código con configuración mutable versionada. Run es un intento técnico con snapshot, origen y evidencia independiente de los runs económicos. Audit registra cada modificación efectiva.

queued transiciona a running; running termina en succeeded, failed o skipped. Los gates y locks pueden producir skipped antes de ejecución. Una interrupción comprobada produce interrupted desde queued/running. Los terminales no se reejecutan ni se recuperan como el mismo run.

La exclusión usa restricción persistente de run activo y advisory lock. Deshabilitar no afecta intentos aceptados. Una programación perdida no genera replay.
