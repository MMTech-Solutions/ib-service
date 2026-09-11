# Modules: implementación de la primera entrega

Estado: **Bloqueado**  
Dependencia: cierre de las etapas 2 a 4  
Entrega objetivo: M1 — Catálogo y control operativo

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
- Las respuestas HTTP usan `mmt/api-response-normalizer`.
- Commands y objetos contractuales usan `spatie/laravel-data` cuando
  corresponda.
- El BDS, las reglas técnicas, el roadmap y Graphify reflejan el resultado.

## Evidencia de cierre

Al completar M1, este documento debe enlazar migraciones, contratos, casos de
uso, pruebas ejecutadas y cualquier decisión nueva incorporada a BDS o reglas.
