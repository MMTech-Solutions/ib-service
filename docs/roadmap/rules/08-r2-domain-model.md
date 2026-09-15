# Rules R2: agregados, estados y transacciones

Estado: **Completado para R2**
Dependencia satisfecha: [`07-r2-use-case-inventory.md`](07-r2-use-case-inventory.md)

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `RuleAssignment` | Enlace histórico de una versión publicada a programa y módulo, con vigencia y scope. | Agregado raíz de R2. |
| `Rule` / `RuleVersion` | Identidad y versiones (R1). | Consultados para pertenencia y estado `published`. |
| `Program` | Contexto y selecciones. | Accedido solo vía puerto público. |

## Estados confirmados

| Condición | Significado |
| --- | --- |
| Activa | `ends_at` es nulo; aplica desde `starts_at`. |
| Histórica | `ends_at` no nulo; intervalo `[starts_at, ends_at)`. |

No existen estados `draft` ni `scheduled` en la asignación. El scope es
siempre `all`.

La editabilidad se deriva del plan propietario:

| Plan propietario | Condición |
| --- | --- |
| No archivado | Admite crear, reemplazar y retirar. |
| Archivado | No admite mutaciones; list y show siguen disponibles. |

## Invariantes

- Toda asignación pertenece a una regla del plan (`BR-RULE-001`).
- La versión es `published` y de la misma regla (`BR-RULE-011`).
- El programa pertenece al mismo plan.
- El módulo está habilitado por el plan y seleccionado por el programa
  (`BR-RULE-005`, `BR-RULE-010`).
- Scope = `all` (`BR-RULE-015`).
- Como máximo una activa por `(rule_id, program_id, module_id)`
  (`BR-RULE-013`).
- `version_id` de una fila histórica no se modifica (`BR-RULE-011`).
- Solo un plan no archivado admite mutaciones.

## Transacciones

- **Crear:** atómico. Valida contexto, versión publicada y unicidad activa.
  `starts_at = ahora`, `ends_at = null`, `lock_version = 1`.
- **Reemplazar:** atómico. Revalida que el módulo siga seleccionado por el
  programa (`BR-RULE-010`), cierra la vigente (`ends_at = ahora`) bajo
  `lock_version` y crea la nueva activa con la versión solicitada. Si la
  revalidación falla, no se cierra la vigente ni se crea historia.
- **Retirar:** atómico. Cierra la vigente bajo `lock_version`.
- **Listar / mostrar:** lectura; no mutan.

## Fronteras

- `ResolvePlanContextPort` con queries tipadas V1 (`ResolvePlanContextQueryData`,
  `AssertEnabledModuleIdsQueryData`) para plan existente y no archivado.
- `ResolveProgramContextPort` con queries tipadas V1
  (`ResolveProgramContextQueryData`, `AssertSelectedModuleQueryData`) para
  programa del plan y módulo seleccionado.
- `RuleRepositoryFactory` interno de Catalog para cargar regla y versión:
  colaboración permitida entre subfeatures del mismo feature `Rules`.
- Sin puertos públicos de Rules hacia otros features. Sin acceso directo a
  Modules.

## Fuera de R2

- Evaluación y recompensas.
- Scope instrumental.
- Precedencia entre reglas.
- Eventos Kafka.
