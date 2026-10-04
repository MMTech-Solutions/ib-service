# Plans P3: vinculación de versiones de plantillas

Estado: **Completada localmente**
Última revisión: 2026-10-04
Dependencias: Plans P1 y catálogos de PaymentTemplates/ProgressionTemplates.

## Objetivo y brecha original

Permitir preparar los planes de ib-labs exclusivamente mediante HTTP autorizado.
Los cierres locales anteriores demostraron consumo de bindings preparados en
fixtures, sin demostrar su creación administrativa. Programs ya contemplaba
selección explícita de una versión publicada; faltaba esta entrega del proveedor.

El BDS de planes v0.16 define BR-TEMPLATE-001–006. Pago y progresión conservan
bindings permanentes, acumulables e idempotentes. No se retiran ni editan;
vincular no cambia configuraciones consumidoras.

## Contrato administrativo

Para cada familia, bajo `/api/ib/v1/admin/plans/{plan}`:

- POST `payment-template-version-bindings` o `progression-template-version-bindings`.
- GET sobre cada colección y GET `/{binding}` para su detalle.
- Entrada POST: `template_version_id` UUID. Resultado: `id`, `plan_id`,
  `template_version_id`, `created_at` UTC, directamente en `data`.
- POST devuelve 201 al crear y 200 al repetir, con idéntico binding y fecha.
- Colecciones: `page` y `per_page` (100 por defecto y máximo), orden por
  fecha e ID ascendentes; array en `data`, paginación en `meta`.
- Usuario gateway con `ib.plans.manage` sobre `admin_panel`.
- Creación en plan archivado o versión borrador: 409; referencia inexistente,
  familia equivocada o binding de otro plan: 404; entrada inválida: 422.
- Consulta de planes archivados permitida; sin edición, retiro o eliminación.

## Implementación y concurrencia

Plans/Catalog gobierna las asociaciones. Programs publica puertos de identidad
y estado de versión por familia; Plans no accede a su persistencia.
Repositories PostgreSQL/memoria se obtienen mediante factories.
Se reutilizan tablas y constraints únicas existentes sin migración.
El bloqueo de fila del plan serializa creación con archivo y otras creaciones;
la unicidad persistente evita duplicados. No se modifica el lock_version del plan.
El puerto previo de resolución de bindings de pago conserva su contrato.

## Aceptación y evidencia

- HTTP autorizado permite publicar, vincular y configurar consumidores sin SQL
  manual para crear bindings.
- Ambas familias cubren repetición, multiplicidad, aislamiento, paginación,
  archivo, borradores, familia incorrecta y autenticación/autorización.
- Contratos compartidos validan memoria y PostgreSQL; dos conexiones demuestran
  exclusión mutua y unicidad.
- Fixtures PnL y Progression crean bindings por API; versiones posteriores
  conservan selecciones y snapshots anteriores.
- Regresiones focalizadas, arquitectura, Pint y Graphify aprobados.
- Postman v2.1 sincronizado con route:list y endpoints operativos del bootstrap.

La validación S2S y operativa con ib-labs permanece independiente y pendiente.

## Evidencia local — 2026-10-04

- Seis rutas administrativas disponibles con permisos gateway existentes.
- PlanTemplateBindingsTest cubre ambas familias, repetición, archivo, aislamiento,
  paginación, errores y configuración instrumental HTTP; vincular otra versión
  conserva la selección anterior y su contexto de progresión.
- PlanTemplateBindingRepositoryContractTest ejecuta el mismo contrato en memoria
  y PostgreSQL para ambas familias.
- PlanTemplateBindingConcurrencyTest usa dos conexiones PostgreSQL: creación y
  archivo competidores esperan el bloqueo del plan; repetir tras el commit
  devuelve el binding original, y archivar impide nuevas creaciones.
- Las fixtures NegativePnlConfigurationTest, NegativePnlProcessingTest y
  EvaluateProgressionActivitiesUseCaseTest crean bindings mediante HTTP.
- Verificación conjunta: **77 pruebas / 1344 assertions**, incluyendo catálogos
  de plantillas, PnL, progresión y arquitectura. Pint aprobado.
- Postman v2.1 válido: **92 requests**, cobertura de **91 rutas propias** de
  route:list --except-vendor y /up configurado en bootstrap, sin rutas faltantes.
- Sin migraciones, cambios de permisos locales ni dependencias.
- Graphify actualizado: **6326 nodos / 15430 relaciones**. La extracción AST
  completó los archivos de código; la documentación mantiene su revisión manual.

Esta evidencia cierra P3 localmente; no acredita ejecución integrada con ib-labs,
transporte S2S ni datos reales.
