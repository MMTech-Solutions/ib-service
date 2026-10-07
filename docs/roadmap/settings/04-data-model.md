# Settings: modelo de datos

Estado: completado. Revisión: 2026-10-07.

settings: key (PK varchar 160), definition (JSONB), value (text nullable),
mode (stored/fallback), lock_version (entero positivo), created_at/updated_at (UTC).
Secrets usan ciphertext; valores no sensibles JSON serializado. fallback no tiene value.

setting_audits: id UUID, key indexada sin FK para conservar retiros, actor, action,
reason, before/after JSONB nullable y occurred_at UTC. Datos sensibles se redactan
antes de llegar al repository.

Sync y cambios administrativos comparten advisory lock transaccional 71931007.
Edición/reset exigen lock_version; conflicto 409. El sync conserva versiones cuando
no hay cambios y no vuelve a cifrar registros sin modificaciones.
