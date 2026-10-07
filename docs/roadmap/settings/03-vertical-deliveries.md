# Settings: entregas verticales

Revisión: 2026-10-07. ST1–ST4 completadas localmente; evidencia y limitación de
regresión conjunta en [05-implementation.md](05-implementation.md).

- ST1: catálogo agrupado, persistencia PostgreSQL/InMemory, cifrado, resolución y sync.
- ST2: administración HTTP, concurrencia, permisos, auditoría y Postman.
- ST3: consumidores reales y certificación compartida Modules/Settings.
- ST4: validación local y entrega documental; aceptación S2S/UI posterior.

No hay dependencia nueva. Aplicar migraciones, settings:sync --dry-run, settings:sync,
y reiniciar procesos que construyen topics. No ejecutar sync desde un binario antiguo.
