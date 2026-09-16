# Planes, programas y suscripciones IB — BDS

- **Versión:** 0.13
- **Estado:** base inicial; P1 cierra el ciclo de vida administrativo del plan; PR1 cierra identidad y selección administrativa del programa; P2 cierra el umbral de entrada vivo y el ladder del plan; S1 cierra las reglas de solicitud, aprobación, suscripción, placement y fijación administrativa

**Propósito:** definir la jerarquía comercial y de progresión del dominio IB.

## Contexto

Un Plan IB es el producto al que se suscribe un usuario. El plan define qué módulos pueden generar actividad recompensable y contiene programas que representan niveles de crecimiento. Los módulos son independientes: la indisponibilidad o ausencia de uno no debe impedir que los restantes contribuyan.

## Glosario

| Concepto | Definición |
| --- | --- |
| Plan IB | Producto comercial y elemento de mayor jerarquía de la suscripción. |
| Plan dedicado | Plan cuya configuración favorece la actividad de un módulo o perfil de negocio concreto. |
| Plan mixto | Plan que combina de forma deliberada contribuciones y recompensas de varios módulos. |
| Programa IB | Nivel de crecimiento dentro de un plan. Tiene código estable, nombre y descripción opcional. |
| Código del programa | Identidad de negocio del programa, estable y única dentro de su plan. |
| Selección de módulo del programa | Relación que asocia al programa un módulo ya habilitado por su plan. Puede permanecer vacía. |
| Módulo | Dominio externo que produce actividad, por ejemplo Broker, Copy Trading, Prop Firm o Hedge Fund. |
| Catálogo de módulos | Registro duradero de los módulos que IB reconoce, su disponibilidad y las capacidades que implementa cada uno. |
| Capacidad de módulo | Vocabulario de actividad o comportamiento implementado por un módulo, como trades, depósitos o compras de challenges. No es una facultad concedida administrativamente. |
| Disponibilidad de módulo | Condición activa o inactiva que determina si el módulo puede seleccionarse y participar en cualquier procesamiento. |
| Estado de procesamiento | Condición `running` o `paused` que permite operar normalmente o suspender cálculos y pagos sin detener la captura de actividad. |
| Vinculación de módulo | Relación que habilita un módulo para un plan. Identifica el módulo reconocido por el catálogo; la fuente de actividad concreta permanece fuera de esta fase. |
| Módulo operativo | Módulo activo en el catálogo, aunque su procesamiento esté pausado. |
| Disponibilidad del plan | Condición activa o inactiva del plan. No equivale a publicación ni a una versión. |
| Archivo del plan | Retiro lógico e irreversible en esta fase de un plan inactivo. Conserva identidad, código y vinculaciones. |
| Desactivación automática del plan | Paso a inactivo cuando un plan activo queda sin módulos operativos porque el catálogo desactivó módulos. |
| Requisito de aprobación | Configuración del plan que determina si una nueva solicitud necesita decisión administrativa antes de activarse. |
| Suscripción | Registro histórico que comienza con la solicitud de adhesión del usuario a un plan y conserva su ciclo completo. |
| Suscripción abierta | Suscripción `pending` o `active`. Un usuario solo puede tener una abierta globalmente. |
| Suscripción pendiente | Solicitud que espera aprobación o rechazo administrativo y todavía no tiene placement. |
| Suscripción activa | Suscripción aprobada con placement que admite nueva actividad de progresión o recompensa. |
| Suscripción rechazada | Solicitud terminada por decisión administrativa motivada; no puede reabrirse. |
| Suscripción terminada | Suscripción inmutable que conserva el contexto histórico después de una cancelación o cambio de plan. |
| Placement | Programa actual del usuario dentro del plan suscrito. Puede estar libre para Progression o fijado administrativamente. |
| Fijación administrativa | Bloqueo explícito del placement en un programa, atribuible por administrador e instante, que suspende Progression sin suspender Rewards. Puede conservar un motivo cuando se proporciona. |
| Actividad elegible para progresión | Actividad ocurrida mientras la suscripción estaba activa y su placement no estaba fijado. |
| Umbral de entrada | Entero no negativo asociado a un programa: suelo de puntos a partir del cual ese nivel es alcanzable. |
| Ladder de programas | Secuencia ordenada de programas de un plan cuyos umbrales de entrada son estrictamente crecientes con la posición. Define intervalos contiguos sin solapes. |

## Relaciones

```mermaid
erDiagram
    MODULE_CATALOG ||--o{ MODULE_BINDING : referenced_by
    PLAN ||--|{ PROGRAM : contains
    PLAN ||--|{ MODULE_BINDING : enables
    PLAN ||--o{ PLAN_OPERATIONAL_CHANGE : records
    PROGRAM ||--o{ PROGRAM_MODULE_SELECTION : selects
    MODULE_BINDING ||--o{ PROGRAM_MODULE_SELECTION : constrains
    PLAN ||--o{ SUBSCRIPTION : receives
    SUBSCRIPTION ||--|| PLACEMENT : has
    PLACEMENT }o--|| PROGRAM : current_level
```

## Reglas de dominio

| ID | Regla |
| --- | --- |
| BR-PLAN-001 | El Plan IB es la unidad a la que se suscribe el usuario. El usuario no se suscribe directamente a un módulo ni a un programa. |
| BR-PLAN-002 | Un programa pertenece a un único plan y representa un nivel de crecimiento dentro de él. |
| BR-PLAN-003 | El plan determina explícitamente qué módulos están habilitados para producir progresión o recompensas. |
| BR-PLAN-004 | Un plan puede ser dedicado a un módulo o combinar varios módulos; ambos utilizan el mismo modelo de dominio. |
| BR-PLAN-005 | La participación de Copy Trading, Prop Firm o cualquier módulo futuro no depende de que Broker esté habilitado o disponible. |
| BR-PLAN-006 | Un módulo no habilitado por el plan no puede generar contribuciones ni recompensas para sus suscripciones. |
| BR-PLAN-007 | Un plan nace inactivo. Puede existir temporalmente sin vinculaciones. |
| BR-PLAN-008 | El código del plan es una identidad de negocio estable, única e irreutilizable, también después de archivarse. |
| BR-PLAN-009 | Activar un plan exige al menos una vinculación cuyo módulo esté operativo en ese momento. |
| BR-PLAN-010 | Un plan activo puede editarse si conserva al menos una vinculación. Un conjunto vacío de vinculaciones solo es válido mientras el plan está inactivo. |
| BR-PLAN-011 | Una vinculación nueva solo puede referenciar un módulo operativo. Las vinculaciones ya existentes se conservan aunque el módulo deje de estar operativo. |
| BR-PLAN-012 | Un módulo pausado sigue siendo operativo para nuevas vinculaciones y para mantener un plan activo. |
| BR-PLAN-013 | Si un plan activo queda sin módulos operativos porque el catálogo desactiva módulos, el plan se desactiva. La desactivación del módulo no espera a ese ajuste. |
| BR-PLAN-014 | Reactivar un módulo no reactiva planes desactivados automática o administrativamente. |
| BR-PLAN-015 | Solo un plan inactivo puede archivarse. El archivo conserva las vinculaciones y no se revierte en esta fase. |
| BR-PLAN-016 | Un cambio de disponibilidad del plan es atribuible: actor administrativo, o actor de sistema con la causa que lo originó. |
| BR-PLAN-017 | Cada plan determina si una nueva solicitud de suscripción requiere aprobación administrativa. El valor vigente al solicitar queda asociado a esa solicitud; cambiarlo solo afecta solicitudes posteriores. |
| BR-MODULE-001 | IB mantiene un catálogo duradero y autoritativo de los módulos que reconoce. |
| BR-MODULE-002 | Cada módulo tiene una identidad estable, una disponibilidad, un estado de procesamiento y un conjunto explícito de capacidades implementadas. |
| BR-MODULE-003 | Una nueva vinculación de plan solo puede referenciar un módulo activo y reconocido por el catálogo. |
| BR-MODULE-004 | Un programa solo puede configurar actividad compatible con las capacidades implementadas por el módulo correspondiente. |
| BR-MODULE-005 | El catálogo describe capacidades del módulo; no define ponderaciones, progresión ni reglas económicas del programa. |
| BR-MODULE-006 | El catálogo de módulos no se versiona como un agregado completo. |
| BR-MODULE-007 | El programa no congela semántica ni capacidades del módulo. Conserva únicamente la selección como referencia al registro del catálogo. |
| BR-MODULE-008 | Cualquier consumidor posterior consulta semántica, capacidades, disponibilidad y estado de procesamiento vigentes en el catálogo de módulos. |
| BR-MODULE-009 | Un cambio de disponibilidad o procesamiento del módulo no versiona el programa ni su umbral de entrada. Planes y programas conservan identidad estable y no se versionan. |
| BR-MODULE-010 | Pausar el procesamiento de un módulo detiene sus cálculos y pagos, pero mantiene la ingesta de eventos y las consultas de actividad necesarias para conservar el período configurado. |
| BR-MODULE-011 | Un módulo con procesamiento pausado continúa activo y puede seleccionarse en configuraciones nuevas. |
| BR-MODULE-012 | Desactivar un módulo prevalece sobre selecciones y programas existentes: impide nuevas selecciones, ingesta de eventos, consultas de actividad externa, cálculos y pagos. |
| BR-MODULE-013 | Un módulo que haya sido referenciado permanece en el catálogo y conserva sus relaciones e historial cuando se desactiva; no se elimina física ni lógicamente. |
| BR-MODULE-014 | Una capacidad solo puede declararse para un módulo cuando existe una implementación que la respalda; una acción administrativa no puede conceder capacidades. |
| BR-MODULE-015 | Todo evento rechazado por inactividad del módulo deja evidencia auditable, aunque no se incorpore como actividad del dominio. |
| BR-MODULE-016 | Un módulo incorporado por primera vez al catálogo nace activo y con procesamiento en ejecución. Una sincronización posterior nunca reactiva automáticamente un módulo desactivado. |
| BR-MODULE-017 | Una capacidad retirada de su implementación permanece en el catálogo como inactiva para conservar trazabilidad. Si vuelve a estar respaldada por una implementación, se reactiva automáticamente. |
| BR-MODULE-018 | Un cambio de disponibilidad o procesamiento afecta a los trabajos que comiencen después del cambio. Los trabajos que ya habían comenzado pueden completar sus efectos conforme al estado que validaron al iniciar. |
| BR-PROGRAM-001 | Todo placement pertenece a una suscripción y señala un programa del mismo plan de esa suscripción. |
| BR-PROGRAM-002 | La progresión automática solo cambia el programa del usuario dentro del plan al que está suscrito. |
| BR-PROGRAM-003 | Los programas del plan mantienen un orden y umbrales no ambiguos para determinar el placement. |
| BR-PROGRAM-004 | El código del programa es una identidad de negocio estable, única dentro de su plan e irreutilizable dentro de ese plan. |
| BR-PROGRAM-005 | Un programa puede existir sin módulos seleccionados, incluso si su plan está activo. |
| BR-PROGRAM-006 | El umbral de entrada y el orden del programa no dependen de que haya módulos seleccionados. |
| BR-PROGRAM-007 | La selección de módulos de un programa es siempre un subconjunto de las vinculaciones del plan propietario. |
| BR-PROGRAM-008 | Activar, desactivar o archivar el plan no crea, elimina ni altera por sí solo los programas de ese plan. |
| BR-PROGRAM-009 | Solo un plan no archivado admite crear, editar o reordenar sus programas. |
| BR-PROGRAM-010 | Cada programa declara un único umbral de entrada: un entero no negativo. No se modelan rangos min-max ni un máximo nulo en el último nivel. |
| BR-PROGRAM-011 | Los umbrales de entrada de los programas de un mismo plan son estrictamente crecientes con la posición del ladder. |
| BR-PROGRAM-012 | El intervalo efectivo del programa en posición `i` es `[umbral_i, umbral_{i+1})`. El del último programa es `[umbral_n, ∞)`. Los intervalos son contiguos y no se solapan. |
| BR-PROGRAM-013 | El placement por progresión sitúa al IB en el programa de mayor posición del plan tal que `puntos >= umbral_de_entrada` de ese programa. |
| BR-PROGRAM-014 | El umbral de entrada es mutable. Un cambio válido del ladder afecta a las evaluaciones posteriores que lo lean; no se conserva un snapshot de umbral por usuario, placement ni run. |
| BR-SUBSCRIPTION-001 | Una suscripción activa es requisito para tener placement y para que nueva actividad origine progresión o recompensas. La terminación posterior no invalida actividad, contribuciones o recompensas originadas mientras estaba activa. |
| BR-SUBSCRIPTION-002 | Cambiar de programa no sustituye ni recrea la suscripción al plan. |
| BR-SUBSCRIPTION-003 | Un usuario IB puede tener como máximo una suscripción abierta en todo el sistema: `pending` o `active`. Puede conservar cualquier cantidad de suscripciones `rejected` o `ended`. |
| BR-SUBSCRIPTION-004 | El usuario solicita adhesión únicamente a un plan activo y no archivado; no elige programa. Si ya tiene una suscripción abierta, la nueva solicitud se rechaza. |
| BR-SUBSCRIPTION-005 | Si el plan no exige aprobación, la solicitud pasa directamente a `active` con el primer programa del ladder. Si no existe ese programa, la solicitud falla y no crea registro. Si exige aprobación, nace `pending` y no tiene placement hasta la decisión administrativa. |
| BR-SUBSCRIPTION-006 | Solo un administrador puede cambiar de plan una suscripción activa. La operación exige un plan destino activo y no archivado, termina la anterior y crea otra `active` dentro de una misma decisión indivisible; el administrador elige el programa o se usa el primero del ladder. No existe un intervalo con dos suscripciones abiertas. |
| BR-SUBSCRIPTION-007 | Volver a un plan anterior crea una suscripción nueva. Una suscripción terminada nunca se reactiva. |
| BR-SUBSCRIPTION-008 | Una suscripción nueva comienza sin puntos transferidos desde suscripciones anteriores. Los puntos y contribuciones históricos permanecen asociados a la suscripción que los originó. |
| BR-SUBSCRIPTION-009 | Solo un administrador puede cancelar una suscripción activa. Cancelar la cambia a `ended` sin crear otra. |
| BR-SUBSCRIPTION-010 | `rejected` y `ended` son estados terminales, inmutables y permanentes. No existe `retiring`; ningún registro de suscripción se reactiva ni se elimina. |
| BR-SUBSCRIPTION-011 | La suscripción, plan y placement aplicables a una actividad son los vigentes cuando la actividad ocurrió. Una actividad recibida o procesada después de la terminación conserva ese contexto histórico. |
| BR-SUBSCRIPTION-012 | Terminar una suscripción no cancela contribuciones ni recompensas ya originadas. Las recompensas calculadas o pendientes continúan su ciclo hasta settlement usando la suscripción histórica, sujetas a la condición operativa vigente del plan. |
| BR-SUBSCRIPTION-013 | Un cambio administrativo de programa dentro del mismo plan conserva la suscripción y pasa a ser el placement efectivo desde ese momento. Progression puede volver a moverlo desde el siguiente run salvo que el administrador lo fije. |
| BR-SUBSCRIPTION-014 | Solo un administrador puede fijar el placement, cambiar el programa fijado o retirar la fijación. Cada acción conserva actor e instante; el motivo es opcional y se conserva cuando se proporciona. |
| BR-SUBSCRIPTION-015 | Mientras el placement está fijado no se ejecutan runs de Progression para esa suscripción. La actividad ocurrida durante la fijación queda excluida definitivamente de progresión y no se acumula ni se recupera al retirar la fijación. |
| BR-SUBSCRIPTION-016 | La fijación solo afecta a Progression. La actividad puede originar recompensas conforme al programa fijado y a las reglas vigentes. |
| BR-SUBSCRIPTION-017 | Retirar la fijación no cambia inmediatamente el placement. El siguiente run ordinario puede modificarlo usando únicamente actividad elegible ocurrida después de la liberación. Una fijación no se transporta a otra suscripción. |
| BR-SUBSCRIPTION-018 | Solo un administrador puede aprobar o rechazar una solicitud `pending`. Aprobar exige que el plan siga activo y no archivado; el administrador elige un programa existente del plan o se utiliza el primero del ladder. Si no puede resolverse el programa, la aprobación falla sin cambiar el estado. |
| BR-SUBSCRIPTION-019 | Rechazar una solicitud exige un motivo y la cambia a `rejected`. Una adhesión posterior crea otro registro; nunca modifica ni reabre el rechazado. |
| BR-SUBSCRIPTION-020 | El usuario solo inicia la solicitud de adhesión. Toda gestión posterior —aprobar, rechazar, cancelar, cambiar plan o programa y administrar la fijación— corresponde a un administrador. |
| BR-SUBSCRIPTION-021 | El usuario solo consulta su suscripción abierta y nunca accede mediante este dominio a su historial `rejected` o `ended`. Administración puede consultar todos los estados y el historial. |
| BR-SUBSCRIPTION-022 | Desactivar un plan no termina sus suscripciones abiertas, pero impide ejecutar para ellas nuevos runs de Progression y nuevos cálculos o pagos de Rewards mientras el plan permanezca inactivo. |
| BR-SUBSCRIPTION-023 | Un plan no puede archivarse mientras conserve alguna suscripción `pending` o `active`. |

## Ladder de umbrales

El ladder no usa un par min-max por programa. Un solo umbral de entrada por
nivel, ordenado de forma estrictamente creciente, produce el mismo resultado
sin solapes ni casos especiales en el último programa. El umbral vive en el
programa y se lee vigente en cada evaluación posterior.

Ejemplo con tres programas:

| Posición | Código | Umbral de entrada | Intervalo efectivo |
| --- | --- | --- | --- |
| 1 | basic | 0 | `[0, 100)` |
| 2 | advanced | 100 | `[100, 500)` |
| 3 | pro | 500 | `[500, ∞)` |

Con 99 puntos el placement es `basic`; con 100, `advanced`; con 500 o más,
`pro`.

## Estados del plan

| Disponibilidad | Archivo | Condición efectiva |
| --- | --- | --- |
| inactivo | no | Editable; puede no tener módulos; no admite suscripción futura en esta fase. |
| activo | no | Editable si conserva al menos una vinculación; requiere módulos operativos para permanecer coherente. |
| inactivo | sí | Archivado: no se consulta en el catálogo vigente, conserva código y vinculaciones, no se restaura. |

Un plan nunca está activo y archivado a la vez. Activar y desactivar no publican una versión.

## Estados de la suscripción y del placement

```mermaid
stateDiagram-v2
    [*] --> pending: plan requiere aprobación
    [*] --> active: plan no requiere aprobación
    pending --> active: administrador aprueba
    pending --> rejected: administrador rechaza
    active --> ended: cancelación
    active --> ended: cambio de plan
    rejected --> [*]
    ended --> [*]
```

`pending` y `active` son estados abiertos y mutuamente excluyentes por usuario.
`rejected` y `ended` son terminales, inmutables y permanentes. Un cambio de plan
crea la nueva suscripción activa en la misma decisión que termina la anterior;
nunca reactiva ni reutiliza una suscripción histórica.

```mermaid
stateDiagram-v2
    [*] --> unfixed: placement inicial
    unfixed --> fixed: administrador fija programa
    fixed --> fixed: administrador cambia programa fijado
    fixed --> unfixed: administrador libera fijación
    unfixed --> unfixed: cambio de programa
```

Solo una suscripción `active` tiene placement. En `unfixed`, Progression puede
cambiarlo desde un run. En `fixed`,
no se ejecutan runs de Progression y la actividad de ese intervalo queda fuera
del progreso, aunque Rewards puede consumirla conforme al programa fijado.

## Ejemplos de producto

Los siguientes nombres ilustran configuraciones posibles y no fijan el catálogo definitivo:

- `Fx Prop Firm`: orientado a usuarios con buen desempeño sobre productos Prop Firm.
- `Hedge Fund`: orientado a actividad relacionada con capital gestionado y depósitos.
- `Broker`: orientado a volumen y actividad financiera de cuentas Broker.
- `Mix`: combina ponderaciones de varios módulos para usuarios versátiles.

## Eventos de negocio

- Plan creado.
- Plan activado o desactivado administrativamente.
- Plan desactivado automáticamente al quedar sin módulos operativos.
- Módulo habilitado o retirado de un plan.
- Plan archivado.
- Plan publicado.
- Requisito de aprobación del plan modificado.
- Programa creado o editado dentro de un plan.
- Programas de un plan reordenados.
- Módulos seleccionados o retirados de un programa.
- Umbral de entrada de un programa definido o modificado.
- Ladder de programas de un plan reconfigurado.
- Módulo incorporado al catálogo.
- Capacidades de un módulo modificadas.
- Módulo activado o desactivado.
- Procesamiento de un módulo pausado o reanudado.
- Usuario solicitó adhesión a un plan.
- Solicitud de suscripción activada automáticamente.
- Solicitud de suscripción aprobada.
- Solicitud de suscripción rechazada con motivo.
- Suscripción terminada por cancelación.
- Suscripción sustituida por cambio de plan.
- Placement inicial asignado.
- Usuario movido a otro programa por progresión.
- Placement modificado administrativamente.
- Placement fijado administrativamente.
- Programa fijado modificado administrativamente.
- Fijación administrativa de placement retirada.

## Decisiones pendientes

- Política al retirar un módulo de un plan con suscripciones activas.
- Reglas para publicar una nueva versión del plan y aplicarla a suscripciones existentes.
- Tratamiento y reanudación de actividad acumulada mientras un cálculo permanece pausado.
- Forma concreta de evidencia auditable de eventos rechazados por inactividad del módulo (BR-MODULE-015).
- Restauración de un plan archivado.
