# Modules: implementación de la primera entrega

Estado: **M1 completado**
Dependencia: decisiones y datos de M1 cerrados
Entrega objetivo: M1 — Sincronización, catálogo y control operativo

## Orden de implementación propuesto

1. Definir objetos `Data`, VOs y excepciones internos requeridos por M1, sin
   anticipar contratos inter-feature.
2. Definir el registro cerrado de módulos, capacidades y claves de
   implementación.
3. Definir repositories y factories requeridos por M1.
4. Implementar el repositorio en memoria y su factory.
5. Escribir una suite de contract tests independiente de la implementación.
6. Crear migraciones, modelos de solo lectura fuera del repositorio y el
   repositorio PostgreSQL.
7. Ejecutar la misma suite contractual contra memoria y PostgreSQL.
8. Implementar `modules:sync` con reconciliación idempotente y `--prune`
   protegido.
9. Implementar los casos de uso internos consumidos por los adapters HTTP y
   Console.
10. Añadir Commands, Requests, Controllers y Resources sin acceso a
   repositorios.
11. Registrar bindings explícitos en `ModulesServiceProvider`.
12. Integrar autorización, auditoría y publicación de eventos si el alcance
    acordado los requiere.
13. Añadir pruebas HTTP y de comportamiento para los recorridos de M1.
14. Ejecutar formato, pruebas pertinentes y `graphify update .`.

## Matriz mínima de contract tests

La suite debe poder aplicarse a cualquier `ModuleRepositoryInterface` producido
por su factory y comprobar, al menos:

- creación y recuperación por identidad estable;
- rechazo de identidades o códigos duplicados;
- persistencia de capacidades y estados acordados;
- cambios permitidos y rechazo de transiciones inválidas;
- preservación de campos administrativos durante la sincronización;
- desactivación de implementaciones ausentes y pruning exclusivo de huérfanos;
- semántica equivalente ante ausencia de resultados;
- aislamiento entre agregados;
- comportamiento acordado ante escrituras concurrentes.

Los detalles se ajustarán al contrato definitivo; esta lista no anticipa firmas
de métodos.

## Definition of Done de M1

- Los criterios de aceptación aprobados para M1 funcionan de extremo a extremo.
- M1 no publica puertos ni objetos inter-feature sin un consumidor real.
- Ningún Controller o Resource accede directamente a repositorios.
- Los repositorios en memoria y PostgreSQL superan la misma suite contractual.
- Las reglas de autorización y la auditoría tienen pruebas negativas y
  positivas.
- Las operaciones mutables son transaccionales e idempotentes donde aplique.
- Las respuestas HTTP usan el trait `ApiResponse` aportado por `mmt/laravel-feature-scaffold`.
- Los errores HTTP esperados se expresan con `ApiException` / `MmtException`.
- Commands y DTOs internos usan `spatie/laravel-data` y viven en
  `Http/V1/Commands` o `DTOs/`. M1 no publica objetos en `Contracts/Data`
  sin un consumidor inter-feature real; el versionado por directorio aplica
  solo a `Contracts/Data` y `Contracts/Events`.
- El BDS, las reglas técnicas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

Al completar M1, este documento debe enlazar migraciones, contratos, casos de
uso, pruebas ejecutadas y cualquier decisión nueva incorporada a BDS o reglas.

### Evidencia de M1a

- Migraciones PostgreSQL de `modules`, `module_capabilities` y RBAC.
- Registro técnico inicial de Broker y caso de uso `SyncModulesUseCase`.
- Repositorios en memoria y PostgreSQL bajo la misma suite contractual.
- Comando `modules:sync` y ruta `GET /api/ib/v1/admin/modules` verificados.
- Suite: 29 pruebas y 92 aserciones sobre PostgreSQL real, incluidas las reglas
  automáticas de arquitectura para el acceso al usuario autenticado.

### Evidencia de M1b y cierre de M1

- Migración PostgreSQL de `module_operational_changes` con acción, actor IAM,
  motivo, instante y snapshots anterior/posterior.
- Entidad inmutable `OperationalControlChange`, estados ortogonales e
  idempotentes y persistencia atómica junto con `Module`.
- Repositorios InMemory y PostgreSQL validados por el mismo contrato para
  búsqueda por UUID, edición, estados, historial, aislamiento, concurrencia y
  rollback.
- API administrativa verificada para detalle, edición,
  activación/desactivación, pausa/reanudación e historial paginado.
- Las ocho rutas de Modules están sincronizadas con
  `ib-service.postman_collection.json`.
- Suite completa: 62 pruebas y 272 aserciones sobre PostgreSQL real.
- Pruebas positivas y negativas de autorización, concurrencia, idempotencia,
  auditoría, validación y recorrido HTTP principal.
- BR-MODULE-015 continúa vigente; su evidencia ejecutable se trasladó a M3,
  junto al primer consumidor real de actividad.
- No se publicaron puertos inter-feature; el siguiente vertical es `Plans P1`.
