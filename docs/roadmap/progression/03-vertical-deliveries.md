# Progression PG1: entregas verticales

Estado: **Listo para el modelo de datos, condicionado a Modules M3**
Dependencias: inventario y modelo de dominio PG1 completados; `Programs P2`,
`Rules R2` y `Subscriptions S1` completados; `Modules M3` pendiente como
fuente de actividad normalizada pull-only
Última revisión: 2026-09-17

## Principio de corte

PG1 es una única capacidad de evaluación durable: desde una actividad
normalizada obtenida por consulta hasta una evaluación administrativa trazable,
aceptada con contribución o excluida con un motivo cerrado. Puede construirse en
bloques internos, pero no se habilita la evaluación productiva mientras no
existan simultáneamente la resolución de contexto, la protección de
idempotencia y la consulta administrativa del resultado.

La actividad sigue siendo propiedad de Modules. Progression no la replica como
catálogo ni publica contratos inter-feature antes de que esta entrega concrete
el consumidor. PG1 tampoco ejecuta runs, cierra ventanas ni modifica placement.

## PG1 — Evaluación durable de actividad y contribuciones

### Bloque 1 — Fronteras de contexto y actividad

- Modules M3 aporta una consulta pull-only de actividad normalizada. La
  actividad debe identificar su origen estable, beneficiario, módulo, métrica,
  unidad, cantidad, `occurred_at` e información instrumental disponible; el
  scope efectivo de PG1 es `all`.
- Subscriptions resuelve la suscripción abierta, su placement y la condición de
  fijación vigentes en `occurred_at`. La ausencia de suscripción activa produce
  una evaluación excluida, nunca una contribución sin beneficiario.
- Plans resuelve si el plan estaba activo y su período de progresión obligatorio
  para derivar la ventana UTC de la actividad.
- Rules resuelve la asignación y versión publicadas de
  `points_per_quantity_unit` vigentes en `occurred_at`; Programs aporta el
  programa del placement y confirma que el módulo estaba seleccionado entonces.
- Los datos que crucen estas fronteras son contractuales y versionados. Ningún
  adaptador accede a modelos o repositories internos de otro feature. La forma
  exacta de los puertos se define con esta entrega, sin alterar contratos V1
  incompatiblemente.

### Bloque 2 — Decisión de evaluación

- Para cada actividad y beneficiario, Progression resuelve el contexto al
  instante de ocurrencia, deriva su ventana y emite exactamente una evaluación
  inmutable: `accepted` o `excluded`.
- Una evaluación `accepted` crea en la misma decisión una contribución que
  conserva actividad fuente, suscripción, plan, programa, módulo, métrica,
  unidad, cantidad nativa, ventana, regla y versión aplicadas, ponderación y
  puntos resultantes. Los puntos son decimales exactos de hasta ocho decimales
  y no son dinero.
- Una evaluación `excluded` no crea contribución y conserva exactamente uno de
  los motivos cerrados: `placement_fixed`, `plan_inactive`,
  `module_not_selected`, `module_inactive`, `unit_mismatch`,
  `scale_exceeded`, `window_closed_after_pause`, `late_activity`,
  `no_active_subscription` o `no_applicable_rule`.

- Para consulta humana, una evaluación excluida expone el código estable
  `exclusion_reason` y un campo de solo lectura `exclusion_explanation`,
  generado por el sistema a partir de ese código y del contexto conservado. No
  es texto libre ni un mensaje aportado por un operador; así mantiene la
  clasificación cerrada, permite traducción posterior y evita motivos ad hoc.

  | `exclusion_reason` | `exclusion_explanation` para administración |
  | --- | --- |
  | `placement_fixed` | La actividad no aporta puntos porque el placement de la suscripción estaba fijado cuando ocurrió. |
  | `plan_inactive` | La actividad no aporta puntos porque el plan estaba inactivo en la verificación final. Progression no la difiere ni la recupera al reactivar el plan. |
  | `module_not_selected` | La actividad no aporta puntos porque el módulo no estaba seleccionado en el programa vigente de la suscripción. |
  | `module_inactive` | La actividad no aporta puntos porque el módulo estaba inactivo. |
  | `unit_mismatch` | La actividad no aporta puntos porque su unidad no coincide con la unidad de la regla aplicable. |
  | `scale_exceeded` | La actividad no aporta puntos porque la cantidad o la ponderación tiene más de ocho decimales admitidos. |
  | `window_closed_after_pause` | La actividad no aporta puntos porque se procesó al reanudar el módulo y su ventana original ya había cerrado. |
  | `late_activity` | La actividad no aporta puntos porque pertenece a una ventana que un run ya cerró. |
  | `no_active_subscription` | La actividad no aporta puntos porque el beneficiario no tenía una suscripción activa cuando ocurrió. |
  | `no_applicable_rule` | La actividad no aporta puntos porque no había una regla vigente aplicable para el programa, módulo, métrica y unidad. |
- Una unidad incompatible no se convierte mediante FX. Una cantidad o
  ponderación con más de ocho decimales se excluye: no hay redondeo silencioso.
- El retiro de un módulo y los cambios de selección tienen efecto desde su
  instante efectivo. Las contribuciones anteriores no se recalculan.

### Bloque 3 — Persistencia atómica e idempotencia

- La evaluación y su contribución opcional se persisten de forma atómica: no
  puede quedar una evaluación aceptada sin contribución ni una contribución sin
  evaluación.
- Reprocesar la misma actividad para el mismo beneficiario conserva el resultado
  ya persistido y no duplica la evaluación. La protección debe cubrir reintentos
  y concurrencia; una comprobación previa aislada no basta.
- La contribución aceptada también preserva la versión de regla aplicada, por lo
  que cambios posteriores de ponderación, selección, placement o umbral no
  modifican la evidencia histórica.
- Si el módulo estuvo pausado, la actividad permanece en su origen hasta
  reanudar. Se evalúa solo si su ventana original sigue abierta; si no, queda
  excluida con `window_closed_after_pause`. `late_activity` queda preparada
  para el cierre efectivo que incorporará PG2.
- Un plan inactivo detiene las consultas, evaluaciones, contribuciones y runs
  de Progression para sus suscripciones. Al reactivar, no se hace backfill: solo
  puede evaluarse actividad ocurrida desde esa reactivación. Si una evaluación
  en curso alcanza su verificación final con el plan inactivo, se excluye con
  `plan_inactive` y no queda diferida.

### Bloque 4 — Consulta administrativa y evidencia operativa

- Administración puede listar y consultar el detalle de evaluaciones y sus
  contribuciones, incluidas las excluidas y su motivo. La respuesta permite
  reconstruir el contexto y la decisión tomada, sin recalcularla. Para una
  exclusión presenta `exclusion_reason` y su `exclusion_explanation` generado.
- El usuario IB no dispone de lectura de evaluaciones ni contribuciones de
  Progression en PG1.
- La superficie HTTP, permisos, filtros, paginación y Resources se definen en
  la implementación de esta entrega conforme a las convenciones API; no se
  adelantan rutas ni envelopes en este documento.
- La evidencia operativa cubre tanto la evaluación aceptada como cada exclusión
  relevante, la ausencia de duplicados bajo reintento concurrente y la
  imposibilidad de observar una mutación parcial.

## Contratos entre features

| Proveedor | Necesidad concreta de PG1 | Límite |
| --- | --- | --- |
| Modules M3 | Actividad normalizada por consulta pull-only. | No Kafka como vía principal ni catálogo duplicado en Progression. |
| Subscriptions | Suscripción, placement y fijación vigentes en `occurred_at`. | No acceso a persistencia interna ni mutación de placement. |
| Plans | Estado del plan y período de progresión al ocurrir. | No se decide aquí el modelo de versiones del plan. |
| Rules | Asignación y versión `points_per_quantity_unit` vigentes. | No catálogo paralelo ni segunda ponderación de la misma métrica. |
| Programs | Programa del placement y selección efectiva del módulo. | Los umbrales vivos se consumen recién en PG2 al ejecutar un run. |

## Trazabilidad

| Bloque | Reglas principales |
| --- | --- |
| 1. Contexto y actividad | `BR-POINTS-001`, `003`–`006`, `015`–`020`, `BR-PLAN-018`–`020`, `BR-SUBSCRIPTION-011` |
| 2. Evaluación | `BR-POINTS-002`, `007`–`009`, `013`, `016`, `017`, `021`, `022`, `028`–`031` |
| 3. Atomicidad e idempotencia | `BR-POINTS-008`, `009`, `012`, `013`, `021`, `028`, `029` |
| 4. Consulta | `BR-POINTS-022`, `031` |

## Criterio de aceptación

PG1 queda aceptada cuando, de extremo a extremo:

- una actividad normalizada consultada a Modules M3 se evalúa usando el
  contexto de suscripción, placement, plan, programa y regla vigente en su
  `occurred_at`;
- una actividad elegible crea exactamente una evaluación `accepted` y una sola
  contribución atómica, con sus snapshots auditables y puntos decimales exactos;
- toda actividad no elegible crea una evaluación `excluded`, sin contribución y
  con un único motivo del catálogo cerrado;
- reintentos concurrentes de una misma actividad y beneficiario no generan
  evaluaciones ni contribuciones duplicadas;
- cambios posteriores de configuración no alteran evaluaciones ni
  contribuciones ya persistidas;
- la pausa conserva actividad solo bajo su regla de ventana abierta; la
  inactividad del plan detiene Progression y no admite recuperación retroactiva;
  el retiro de módulo y la unidad o escala no válida respetan las reglas de
  elegibilidad y preservan la evidencia resultante;
- administración puede consultar tanto aceptaciones como exclusiones y el
  usuario IB no puede consultar estos artefactos;
- no existe ejecución de run, cierre de ventana ni cambio automático de
  placement.

## Fuera de PG1

- Runs por plan y ventana, resultados por suscripción y reintentos parciales.
- Cierre efectivo de ventanas y mutación automática de placement.
- Lectura de umbrales vivos del ladder para decidir el programa objetivo.
- Reversas o compensaciones de contribuciones ya registradas.
- Conversión FX, fuente de tasa y scopes instrumentales distintos de `all`.
- Tablas, restricciones, índices, mecanismo concreto de concurrencia, rutas y
  contratos de transporte; se cierran en las etapas 4 y 5.

## Criterios de salida de la etapa

- PG1 tiene un único corte funcional, bloques internos con valor observable y
  trazabilidad al BDS.
- Las fronteras necesarias responden a un consumidor real y no exponen detalles
  internos ni contratos especulativos.
- Las decisiones de aceptación permiten definir el modelo de datos, incluidas
  atomicidad, snapshots e idempotencia bajo concurrencia.
- La dependencia concreta de Modules M3 y las extensiones pendientes en Plans,
  Programs y Rules permanecen visibles para la planificación de implementación.
