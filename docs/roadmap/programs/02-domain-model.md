# Programs: agregados, estados y transacciones

Estado: **Completado para PR1**
Dependencia satisfecha: `01-use-case-inventory.md` completado

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Program` | Identidad dentro del plan, datos administrativos, posición relativa y conjunto de selecciones de módulo. | Agregado raíz de PR1. |
| `ProgramModuleSelection` | Hijo que referencia un módulo habilitado por el plan propietario. | Pertenece al límite transaccional del programa. |

El orden de los programas es una invariante del plan propietario. PR1 la
materializa como posición relativa en cada `Program`; reordenar exige
actualizar de forma atómica la colección del plan para que el orden quede
contiguo y no ambiguo.

## Estados confirmados

PR1 no introduce un ciclo de vida propio del programa. No existen estados
`draft`, `published` ni `archived` en esta entrega.

La editabilidad se deriva del plan propietario:

| Plan propietario | Condición del programa |
| --- | --- |
| No archivado (activo o inactivo) | Editable: admite crear, editar, seleccionar módulos y reordenar. |
| Archivado | No admite crear, editar ni reordenar programas. |

Un programa nuevo nace con código, nombre, descripción opcional, posición en
el orden del plan, selección de módulos posiblemente vacía y token técnico de
concurrencia 1.

## Invariantes

- Todo programa pertenece a exactamente un plan (`BR-PLAN-002`).
- El código es inmutable, único e irreutilizable dentro de su plan
  (`BR-PROGRAM-004`).
- El orden relativo de los programas de un plan es no ambiguo
  (`BR-PROGRAM-003`, solo la parte de orden en PR1).
- La selección de módulos es un subconjunto de las vinculaciones del plan
  (`BR-PROGRAM-007`).
- Un programa puede permanecer sin módulos seleccionados antes de publicarse,
  incluso si el plan está activo (`BR-PROGRAM-005`).
- Publicar exigirá al menos un módulo habilitado por el plan
  (`BR-PROGRAM-006`); esa operación no forma parte de PR1.
- Un módulo no habilitado por el plan se rechaza; no queda seleccionado.
- Solo un plan no archivado admite mutaciones de programas (`BR-PROGRAM-009`).
- Activar, desactivar o archivar el plan no crea, elimina ni altera programas
  (`BR-PROGRAM-008`).
- PR1 no configura actividad ni scope de instrumentos (`BR-MODULE-004`
  diferido).

## Transacciones

- Crear un programa es atómico: identidad, posición inicial y, si se envían,
  selecciones de módulo.
- Editar un programa es atómico: datos administrativos y, cuando el conjunto
  de módulos llega, su reemplazo completo.
- Reordenar los programas de un plan es atómico sobre la colección completa
  del plan; el resultado deja posiciones contiguas y no ambiguas.
- La concurrencia de escrituras administrativas usa un token técnico de
  concurrencia por programa. Un reorden del plan debe fallar o reintentarse
  de forma controlada si la colección concurrente cambió.
- La desactivación automática o administrativa del plan no dispara una
  transacción sobre Programs.

## Frontera con Plans

`Programs` depende de un puerto de entrada de `Plans` para resolver el plan
propietario, saber si está archivado y obtener los módulos que tiene
habilitados. `Plans` no conoce `Programs` en PR1. El diseño concreto del
puerto y de los objetos contractuales nace con la entrega vertical.

`Programs` no consulta el catálogo global de `Modules` para validar la
selección administrativa de PR1.

## Fuera de PR1

- Umbrales de placement (P2).
- Suscripciones, placement, Rules, Progression y Rewards.
- Tablas, endpoints, clases y nombres definitivos de puertos.

## Resultado esperado

Agregados, estados, invariantes y límites transaccionales de PR1 acordados.
