# Scheduling: entregas verticales

Estado: implementadas localmente. Última revisión: 2026-10-07.

- SC1: catálogo cerrado, sync idempotente, edición versionada, auditoría transaccional y lectura administrativa.
- SC2: dispatcher minuto UTC, admisión/cola atómicas, ejecución manual idempotente, supervisor y runner con mutex.
- SC3: historial filtrado/paginado, salida redactada, checkpoints, recuperación sin reintentos y permisos separados.

Aceptación local: contratos en memoria/PostgreSQL y pruebas HTTP, estados, gates, duplicados, timeout simulado/real, caída de worker y locks. El ciclo real worker/supervisor/runner y la captura de stderr nativo están aprobados. El despliegue compartido sigue pendiente; véase la evidencia de implementación.
