# Rules: agregados, estados y transacciones

Estado: **Completado para R1**
Dependencia satisfecha: `01-use-case-inventory.md` completado

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Rule` | Identidad dentro del plan: nombre, slug, descripción, tipo de estrategia y concurrencia. | Agregado raíz de R1. |
| `RuleVersion` | Configuración versionada, estado `draft` o `published` y concurrencia propia en draft. | Hijo transaccional de la regla. |

No existen versión “actual”, “activa” ni puntero implícito. Varias versiones
publicadas pueden coexistir.

## Estados confirmados

| Versión | Condición |
| --- | --- |
| `draft` | Editable. Configuración validada para el tipo de estrategia de la regla. |
| `published` | Inmutable. Disponible para que una asignación futura la seleccione. |

La editabilidad de la regla se deriva del plan propietario:

| Plan propietario | Condición |
| --- | --- |
| No archivado | Admite crear y editar reglas y versiones, y publicar. |
| Archivado | No admite mutaciones; list y show siguen disponibles. |

## Invariantes

- Toda regla pertenece a exactamente un plan (`BR-RULE-001`).
- Nombre único dentro del plan; slug único derivado del nombre inicial
  (`BR-RULE-008`). El slug no cambia al renombrar.
- El tipo de estrategia de la regla es inmutable.
- Una versión publicada no se modifica (`BR-RULE-002`).
- La configuración debe ser válida para el tipo declarado antes de guardarse
  y de publicarse (`BR-RULE-007`).
- Publicar no altera otras versiones ni introduce selección automática
  (`BR-RULE-009`).
- El número de versión es monotónico por regla.
- Solo un plan no archivado admite mutaciones.

## Transacciones

- Crear una regla es atómico: identidad, slug y token de concurrencia 1.
- Crear una versión asigna el siguiente número bajo bloqueo de la regla.
- Editar una versión draft y publicarla son atómicos sobre esa versión.
- La concurrencia usa `lock_version` en la regla y en cada versión draft.

## Frontera con Plans

`Rules` depende de `ResolvePlanContextPort` para resolver el plan y rechazar
mutaciones si está archivado. No consulta Programs ni Modules en R1.

## Fuera de R1

- Asignaciones.
- Evaluación y recompensas.
- Otras estrategias.
