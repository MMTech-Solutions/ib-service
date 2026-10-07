# Settings: implementación y evidencia

Revisión: 2026-10-07. Estado: implementación local completada; aceptación integrada pendiente.

Settings sigue UseCase → factory → repository. ResolveSettingsPort V1 expone snapshots.
Modules publica CertifyProviderConnectionPort V1 y comparte un servicio interno entre
su entrada de catálogo y el puerto consumido por Settings.

Pruebas nuevas: SettingsTest, SettingsRepositoryContractTest,
ModuleConnectionCertificationTest y SettingsPostmanContractTest.
PostgreSQL exclusivo mmt_ib_settings_testing para evitar carreras con suites concurrentes.

Validación final: 70 pruebas y 982 aserciones aprobadas, incluyendo las cuatro suites
nuevas, tests/Architecture, SchedulingRunnerTest y SchedulingTest. Postman verifica
las rutas propias y /up, headers, variables y snapshots de identidad. Pint aprobado;
graphify update . completado (7423 nodos, 18343 relaciones).

Regresión completa: 665 pruebas, 659 aprobadas, 5460 aserciones; seis fallos en
SchedulingRunnerTest/SchedulingTest. Esas mismas suites pasan en la ejecución
aislada anterior. La ejecución conjunta mantiene una limitación de aislamiento/orden
que debe investigarse en Scheduling; no se declara la regresión completa aprobada.
No se ejecutaron migraciones ni sync sobre la base operativa.

S2S pendiente: proveedores implementan GET internal_prefix/health autenticado.
Probar token válido/inválido y origen no autorizado en aceptación integrada.
