# Scheduling: implementación y evidencia

Estado: implementado y validado localmente; despliegue pendiente. Última revisión: 2026-10-07.

Implementación en Features/Scheduling con DTOs, factories, repositorios memory/PostgreSQL, casos de uso y adaptadores HTTP/CLI/Jobs. Plans incorpora comando de reconciliación propio; su listener por ModuleDeactivated permanece.

API: ocho rutas bajo /api/ib/v1/admin/scheduling para tasks, runs, audits y output. Envelope directo, permisos por operación, reason obligatorio e Idempotency-Key manual. Postman incluye variables SCHEDULING_TASK_CODE, SCHEDULING_RUN_ID y SCHEDULING_IDEMPOTENCY_KEY.

Evidencia focalizada: SchedulingTest, SchedulingRepositoryContractTest, SchedulingRunnerTest y SchedulingProcessIntegrationTest. Se comprueban slots, snapshots, rollback de encolado, permisos, UTC, redacción, códigos de salida, timeout, proceso sin resultado, lock vivo y ausencia de reintentos. La integración real verifica worker/supervisor/runner sobre PostgreSQL de testing, timeout real y captura de stderr nativo.

Validación final 2026-10-07: 73 pruebas aprobadas, 1054 aserciones. Incluye las cuatro suites de Scheduling, LocalRbacSnapshotSeederTest, SettingsPostmanContractTest, PlanCatalogEndpointTest, ProgressionRecoveryTest, Rewards/RewardSettlementTest y tests/Architecture. Pint aplicado y git diff --check sin errores de whitespace. Postman v2.1 cubre las 114 rutas propias enumeradas y /up; contiene las ocho rutas de Scheduling con variables, headers y autorización. schedule:list muestra exclusivamente scheduling:dispatch y scheduling:reconcile cada minuto UTC.

Pendientes externos: retención antes de producción y aceptación en destino con scheduler/worker compartidos. No se aplican migraciones a la base operativa durante las pruebas locales.

Graphify actualizado mediante graphify update . el 2026-10-07. La advertencia sobre archivos JSON sin nodos no afecta la extracción AST de Scheduling.
