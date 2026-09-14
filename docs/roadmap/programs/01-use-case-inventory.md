# Programs: inventario de casos de uso

Estado: **Completado para PR1**
Última revisión: 2026-09-14

## Propósito

Este inventario nombra intenciones de negocio y resultados observables. Los
nombres descriptivos no obligan a una clase, ruta o transacción homónima.

La primera entrega es un catálogo administrativo de programas dentro de un
plan. No adelanta publicación, snapshots, umbrales, suscripciones ni contratos
públicos.

## Actores

- **Administrador de IB:** crea y mantiene los programas de un plan, su orden
  y la selección de módulos habilitados por ese plan.
- **Feature Plans:** provee el contexto del plan propietario y los módulos
  que tiene habilitados. Programs no recorre el catálogo global de módulos
  para decidir esa selección.
- **Feature Modules:** permanece detrás de Plans para la operatividad ya
  resuelta en P1. Una necesidad directa de capacidades solo surgirá cuando
  exista configuración de actividad; no se inventa un puerto en esta etapa.

El usuario IB, las suscripciones y el placement no son actores de PR1.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega | Estado |
| --- | --- | --- | --- | --- |
| Catálogo | Crear un programa bajo un plan | Existe un programa con código único en ese plan, nombre y descripción opcional. | PR1 | Aceptado |
| Catálogo | Consultar programas de un plan | Se obtiene la colección ordenada de programas de ese plan. | PR1 | Aceptado |
| Catálogo | Consultar un programa | Se obtiene su identidad, plan propietario y módulos seleccionados. | PR1 | Aceptado |
| Catálogo | Editar un programa | Cambian nombre, descripción y, si se envía, el conjunto de módulos. | PR1 | Aceptado |
| Catálogo | Reordenar programas de un plan | El orden relativo queda no ambiguo para un placement futuro. | PR1 | Aceptado |
| Vinculación | Seleccionar módulos de un programa | El conjunto es un subconjunto de los módulos habilitados por el plan; puede quedar vacío. | PR1 | Aceptado |
| Vinculación | Rechazar un módulo no habilitado | La selección se rechaza; el programa no queda asociado a ese módulo. | PR1 | Aceptado |
| Consumo | Obtener contexto del plan propietario | Programs opera con el plan y sus módulos habilitados, no con el catálogo global. | PR1 | Aceptado |
| Publicación | Publicar una configuración de programa | Queda un snapshot de semántica y capacidades del módulo. | P2 | Aceptado en [`07-p2-use-case-inventory.md`](07-p2-use-case-inventory.md) |
| Umbrales | Definir umbrales de progresión | Los umbrales permiten resolver el placement. | P2 | Aceptado: enteros de entrada; ver BDS `BR-PROGRAM-010`–`013` |
| Configuración | Configurar actividad o scope de instrumentos | El programa declara actividad compatible con capacidades del módulo. | Posterior | Diferido: `BR-MODULE-004` vigente; sin payload en PR1 |
| Suscripción | Asignar placement inicial | El usuario queda en un programa del plan suscrito. | Posterior | Diferido: suscripciones fuera de PR1 |
| Operación | Modificar un placement administrativamente | Cambia el programa actual según una política de anclaje. | Posterior | Diferido: pendiente de BDS |
| Recompensas | Asignar reglas a un programa y módulo | Una versión de regla aplica en ese contexto. | Posterior | Diferido: feature Rules |
| Progresión | Evaluar un run de progresión | El usuario sube, baja o permanece de programa. | Posterior | Diferido: feature Progression |

`PR1` es la primera entrega de este feature. Las intenciones posteriores
pertenecen a otros roadmaps o a decisiones de dominio aún abiertas.

## Decisiones cerradas

1. PR1 es administrativo: no publica, no versiona y no congela snapshots.
2. Un programa pertenece a un único plan (`BR-PLAN-002`).
3. El código del programa es estable, único dentro del plan e irreutilizable
   dentro de ese plan; el programa también tiene nombre y descripción
   opcional (`BR-PROGRAM-004`).
4. La selección de módulos es un subconjunto de las vinculaciones del plan
   (`BR-PROGRAM-007`). `BR-PLAN-003` y `BR-PLAN-006` siguen limitando
   contribuciones y recompensas a módulos habilitados.
5. Antes de publicarse, un programa puede existir sin módulos, incluso si el
   plan está activo (`BR-PROGRAM-005`). La publicación exigirá al menos un
   módulo habilitado por el plan (`BR-PROGRAM-006`), pero esa publicación no
   forma parte de PR1.
6. El orden entre programas del mismo plan entra en PR1; los umbrales
   concretos de `BR-PROGRAM-003` no. Diferir umbrales es decisión de alcance
   del roadmap, no una redefinición de la regla.
7. PR1 no define un ciclo de vida propio del programa. Mientras el plan no
   esté archivado, el programa permanece editable (`BR-PROGRAM-009`).
8. Un plan activo o inactivo no archivado admite crear, editar y reordenar
   programas. Un plan archivado no (`BR-PROGRAM-009`).
9. Activar, desactivar o archivar el plan no muta ni elimina programas
   (`BR-PROGRAM-008`).
10. `BR-MODULE-004` no se implementa como configuración de actividad en PR1.
    La incompatibilidad observable de esta entrega es seleccionar un módulo
    que el plan no tiene habilitado.
11. La frontera Plans → Programs se identifica como necesidad de PR1 y se
    diseñará con la entrega vertical, no en este inventario.
12. La representación técnica del orden (atributo del programa u otra forma
    plan-scoped) se decide en el modelo de dominio; la invariante de negocio
    es que el orden del plan sea no ambiguo.

## Fuera de PR1

- Publicación y versionado → documentado en P2.
- Snapshot de módulo del programa → documentado en P2.
- Umbrales de placement → documentado en P2 (`BR-PROGRAM-010`–`013`).
- Suscripciones, placement inicial y cambios de programa.
- Reglas de contribución, puntos, runs, rewards y CPA.
- Restauración o archivo de programas, si llegaran a definirse.
- Tablas, endpoints, clases y contratos públicos de implementación P2.

## Criterios de salida

- Los actores de PR1 están enumerados y no se mezclan con suscripciones ni
  placement.
- Cada intención de PR1 está aceptada o diferida de forma explícita.
- La primera entrega es seleccionable: catálogo administrativo dentro de un
  plan, con orden y selección acotada a módulos habilitados.
- Las decisiones cerradas no se presentan como hipótesis.
- Las necesidades de frontera están identificadas sin nombrar puertos,
  objetos `Contracts/Data` ni adaptadores.
