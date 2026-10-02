# Reglas y recompensas IB — BDS

- **Versión:** 1.3
- **Estado:** RWD4.1 implementado para Rewards por volumen; RWD4.2 (PnL negativo) y la validación contractual S2S externa permanecen pendientes

**Propósito:** definir reglas reutilizables, su asignación contextual y la trazabilidad de las recompensas.

## Contexto

CPA, volumen, PnL, puntos de progresión y futuras modalidades utilizan un pipeline común de evaluación y auditoría. Las diferencias se expresan mediante estrategias y configuraciones versionadas. Una regla puede compartirse entre varios programas del mismo plan sin duplicar su definición.

El catálogo de reglas —identidad, versiones publicadas e inmutables y asignaciones históricas— es único para Progression y Rewards. Progression consume estrategias de contribución; Rewards consume estrategias económicas. Ambos conservan la versión aplicada en sus resultados.

## Glosario

| Concepto | Definición |
| --- | --- |
| Regla | Política reutilizable que identifica una estrategia. Tiene un nombre único dentro del plan y un slug estable derivado de su nombre inicial. Puede destinarse a progresión o a recompensa según su tipo de estrategia. |
| Versión de regla | Configuración publicada e inmutable de una regla. |
| Asignación | Relación histórica que establece en qué programa, módulo, vigencia y scope aplica una versión de regla. |
| Asignación activa | Asignación cuyo intervalo de vigencia está abierto (`ends_at` nulo). |
| Scope de instrumentos | Alcance de instrumentos en el que aplica la asignación. En esta fase el único valor admitido es `all` (todos los instrumentos del módulo). |
| Estrategia | Tipo de cálculo que interpreta una configuración válida y produce una evaluación. |
| Recompensa | Obligación calculada a favor de un beneficiario, registrada en el ledger propio de IB con su causa, snapshot y estado. |
| Ledger de recompensas | Registro autoritativo de IB para las obligaciones de recompensa. Conserva su causa y snapshot; su estado refleja el ciclo informado por Finance. |
| Settlement | Confirmación de que la operación financiera solicitada fue asentada. |
| Contexto CPA | Snapshot que fija referido, plan, programa, asignación, versión de regla, scope y condiciones aplicables a una adquisición. |
| Progreso de verificación CPA | Estado observable y único de los requisitos de un contexto CPA. No es un historial de intentos ni una recompensa. |
| Evidencia CPA | Hechos verificables de actividad o depósito que una estrategia usa para evaluar un contexto CPA; no incluye una decisión de elegibilidad. |
| Run de Reward | Ejecución durable que congela el corte, configuración y red aplicables antes de calcular Rewards. No es un historial de intentos financieros. |
| Reward de volumen | Obligación originada por una posición cerrada elegible y distribuida a la red configurada. |
| PnL neto del período | Variación de balance de una cuenta, menos depósitos certificados netos de retiros del mismo período. |

## Relaciones

```mermaid
erDiagram
    PLAN ||--o{ RULE : owns
    RULE ||--|{ RULE_VERSION : versions
    PROGRAM ||--o{ RULE_ASSIGNMENT : receives
    MODULE_BINDING ||--o{ RULE_ASSIGNMENT : scopes
    PROGRAM_MODULE_SELECTION ||--o{ RULE_ASSIGNMENT : constrains
    RULE_VERSION ||--o{ RULE_ASSIGNMENT : reused_by
    RULE_ASSIGNMENT ||--o{ REWARD : produces
    RULE_ASSIGNMENT ||--o{ CONTRIBUTION : converts
    CPA_CONTEXT ||--o| REWARD : pays_once
    CPA_CONTEXT ||--|| CPA_VERIFICATION_PROGRESS : tracks
```

## Reglas de dominio

| ID | Regla |
| --- | --- |
| BR-RULE-001 | La identidad de una regla pertenece al plan y es independiente de un programa o módulo concreto. |
| BR-RULE-002 | Una versión de regla publicada es inmutable. Toda modificación crea una nueva versión. |
| BR-RULE-003 | Una misma versión puede asignarse a múltiples combinaciones de programa y módulo del mismo plan. |
| BR-RULE-004 | La asignación determina el programa, módulo, vigencia y scope de instrumentos donde aplica la versión. |
| BR-RULE-005 | Una regla no puede utilizar un módulo que el plan no tenga habilitado. |
| BR-RULE-006 | Un cambio de versión no altera recompensas, contribuciones ni contextos calculados con versiones anteriores. |
| BR-RULE-007 | La configuración de una versión debe ser válida para el tipo de estrategia declarado antes de publicarse. |
| BR-RULE-008 | Toda regla tiene un nombre obligatorio y único dentro de su plan, una descripción opcional y un slug único derivado del nombre inicial. El slug permanece estable aunque cambie el nombre; una colisión de nombre o slug impide crear o renombrar la regla. |
| BR-RULE-009 | Publicar una versión no cambia automáticamente las asignaciones existentes. Cada asignación selecciona deliberadamente una versión publicada, y sustituirla por otra versión es una decisión explícita. |
| BR-RULE-010 | Una asignación solo puede referenciar un módulo habilitado por el plan y seleccionado por el programa receptor. |
| BR-RULE-011 | Una asignación selecciona una versión publicada de la misma regla. El identificador de versión de una asignación histórica no se modifica. |
| BR-RULE-012 | La vigencia de una asignación es el intervalo semiabierto `[starts_at, ends_at)`. Una asignación activa tiene `ends_at` nulo. Crear una asignación la activa de inmediato. Un reemplazo o retiro en el mismo instante puede dejar `ends_at = starts_at` (intervalo vacío). |
| BR-RULE-013 | En un instante dado existe como máximo una asignación activa por combinación de regla, programa y módulo. Distintas reglas pueden coexistir en el mismo programa y módulo cuando no violan BR-RULE-016. |
| BR-RULE-014 | Reemplazar la versión de una asignación cierra la vigente (`ends_at = ahora`) y crea otra activa con la nueva versión en la misma operación. Retirar solo cierra la vigente. |
| BR-RULE-015 | En esta fase el scope de una asignación es siempre `all`. Un scope instrumental explícito requiere el catálogo de instrumentos del módulo. |
| BR-RULE-016 | Para la estrategia `points_per_quantity_unit`, en un instante dado existe como máximo una asignación activa por combinación de programa, módulo y métrica o unidad. El mismo tipo puede repetirse en ese programa y módulo solo con métricas o unidades distintas. |
| BR-REWARD-001 | CPA, volumen y PnL comparten la orquestación de contexto, elegibilidad, idempotencia, auditoría y solicitud de pago. |
| BR-REWARD-002 | Cada estrategia declara los hechos o métricas que necesita; compartir pipeline no obliga a compartir el mismo input. |
| BR-REWARD-003 | Una recompensa conserva plan, programa, módulo, asignación, versión de regla, inputs y resultado utilizados. |
| BR-REWARD-004 | La creación de una recompensa es idempotente respecto a su fuente, beneficiario, regla y dimensión de distribución. |
| BR-REWARD-005 | IB es fuente de verdad de por qué existe una recompensa; el dominio financiero es fuente de verdad de si el dinero fue asentado. |
| BR-REWARD-006 | El procesamiento pausado de un módulo detiene sus nuevos cálculos y pagos sin modificar reglas ni snapshots publicados. |
| BR-REWARD-007 | Pausar o desactivar un módulo no revierte automáticamente recompensas ya calculadas ni settlements confirmados. |
| BR-REWARD-008 | Una recompensa CPA nace en estado `pending` y solo transiciona a `settled` cuando Finance confirma síncronamente una comisión `posted`, creada o recuperada por idempotencia. La causa y el snapshot no se sustituyen durante esa transición. |
| BR-REWARD-009 | Un fallo de integración o contrato de Finance deja la Reward en `failed`. `pending` y `failed` son reintentables; cada reintento usa la misma clave idempotente de settlement. |
| BR-REWARD-010 | IB conserva únicamente el resumen operativo del settlement: proveedor, referencia financiera, clave idempotente, contador, último intento, error sanitizado y fecha de settlement. Finance conserva el asiento financiero definitivo. |
| BR-REWARD-011 | RWD3.1 asienta solo Rewards CPA mediante `commission_type = cpa`, a favor del beneficiario de la Reward y en la wallet Finance derivada como `lowercase(currency_code) + '-main'`. |
| BR-REWARD-012 | Una Reward `pending` o `failed` solo puede cancelarse después de consultar Finance por su clave de settlement; si existe una comisión `posted` compatible, IB conserva o repara el estado `settled` y rechaza la cancelación. |
| BR-REWARD-013 | Una Reward `settled` puede revertirse una sola vez mediante un evento Finance `reversal` idempotente que referencia su comisión original. La reversa no borra ni reescribe el asiento inicial. |
| BR-REWARD-014 | Una compensación crea una nueva Reward independiente, enlazada a la reversada, con importe explícito positivo y la misma moneda y precisión congeladas. No reutiliza el contexto CPA ni modifica la Reward origen. |
| BR-REWARD-015 | La reconciliación recurrente consulta solo operaciones financieras inciertas o Rewards con hold; no reconsulta el historial confirmado por antigüedad. Una contradicción verificable activa un hold y solo una consulta posterior consistente puede retirarlo. |
| BR-REWARD-016 | Las Rewards de volumen y PnL resuelven la red multinivel al inicio de su run y conservan los beneficiarios y niveles resueltos. Un cambio posterior de red no reescribe una distribución iniciada. |
| BR-REWARD-017 | La strategy solicita a Identity como máximo la profundidad mayor que pueda remunerar la configuración congelada. Si el proveedor no admite límite, IB descarta localmente los niveles no remunerables sin alterar el snapshot. |
| BR-REWARD-018 | La Reward de volumen es idempotente por posición fuente, beneficiario, nivel de distribución, asignación y versión de regla. Los modos `event`, `periodic` y `both` comparten esa misma identidad. |
| BR-REWARD-019 | La configuración de volumen pertenece al programa y tiene vigencia. Sus modos admisibles son `event`, `periodic` y `both`; cambiarla no modifica Rewards ya creadas. |
| BR-REWARD-020 | La moneda y precisión de una Reward de volumen proceden del `server_group` de la posición. No se infieren de ICU, Finance ni de una plantilla de pago. |
| BR-REWARD-021 | La base distribuible de volumen se obtiene de la posición: modalidad fija = `closed_volume × participation_rate`; modalidad porcentual = `broker_granted_commission × participation_rate`. La base se distribuye por nivel conforme a la plantilla congelada. Cada resultado aplica además `personal_rate` del beneficiario y, solo si es Master IB, su `master_rate`; los tres valores se congelan en la Reward. |
| BR-REWARD-021A | El modo predeterminado de una Reward de volumen es `periodic`. Los modos `event` y `both` usan el evento de Trading únicamente como disparador para consultar la posición autoritativa en Broker. Un `409 CLOSED_POSITION_NOT_READY`, timeout o 5xx no crea Reward y conserva una recepción reintentable con backoff. |
| BR-REWARD-022 | Una configuración PnL pertenece al programa, es histórica y declara una cadencia `daily`, `weekly`, `monthly` o `yearly`. Los períodos son UTC, semiabiertos y solo se evalúan después de cerrados. |
| BR-REWARD-023 | Para una cuenta y período, `pnl_neto = balance_final - balance_inicial - (depósitos_certificados - retiros_certificados)`. Solo `pnl_neto < 0` puede originar Rewards PnL; su base es el valor absoluto de ese resultado. |
| BR-REWARD-024 | Una Reward PnL es idempotente por cuenta, período cerrado, beneficiario, nivel, asignación y versión de regla. |
| BR-REWARD-025 | Las evidencias conservan referencias de Broker y Finance que justifican una Reward, pero no sustituyen el ledger ni delegan en proveedores la elegibilidad, la red, el importe o settlement. |
| BR-INSTRUMENT-001 | Un instrumento se referencia mediante un binding perteneciente al módulo que origina la actividad. |
| BR-INSTRUMENT-002 | El mismo instrumento comercial puede habilitarse para unos módulos y excluirse de otros. |
| BR-INSTRUMENT-003 | Los identificadores locales de IB no tienen que coincidir con los identificadores del módulo proveedor. |
| BR-CPA-001 | Al recibir una adquisición CPA se fija el usuario referido, el IB y el contexto vigente que determina por qué programa se pagará, incluida la asignación y la versión de regla aplicable. El contexto se captura al recibir el hecho; no se presupone un instante de ocurrencia aportado por el productor. |
| BR-CPA-002 | La progresión posterior del IB y la publicación de nuevas versiones no cambian el programa, asignación, versión de regla, scope o condiciones congeladas en el contexto CPA. |
| BR-CPA-003 | Una misma adquisición no puede pagarse nuevamente por el solo hecho de que el IB cambie de programa. |
| BR-CPA-004 | La captura CPA es idempotente por el par usuario referido e IB. Las redeliveries del mismo hecho no crean otro contexto ni otra recompensa. |
| BR-CPA-005 | El contexto CPA conserva el conjunto de símbolos marcados para CPA del programa en el instante de captura. Cambios posteriores de programa, regla o símbolos no modifican ese snapshot. |
| BR-CPA-006 | Un programa puede tener como máximo una asociación CPA activa a una versión publicada de regla `cpa_fixed_amount`. |
| BR-CPA-007 | Los requisitos CPA configurados se cumplen de forma acumulativa: todos deben satisfacerse para que la estrategia pueda evaluar una adquisición. |
| BR-CPA-008 | Cuando un requisito CPA exige depósito certificado, este se mide desde la captura CPA y en la misma moneda configurada, sin conversión de moneda. |
| BR-CPA-009 | Cuando un requisito CPA exige volumen cerrado, se mide desde la captura CPA y solo para el snapshot de símbolos y grupos configurado al capturarla. |
| BR-CPA-010 | La verificación CPA es independiente de Progression: no mueve placements, no consume puntos y no aplica ponderaciones ni profundidad de red. |
| BR-CPA-011 | Un contexto CPA tiene un único progreso de verificación observable. Sus estados son `pending`, `qualified` y `error`; un error técnico no altera el contexto ni crea una recompensa. |
| BR-CPA-012 | La evidencia CPA se observa desde la captura hasta un corte explícito. Cada evaluación conserva el último corte que pudo consultar, sin convertir los intentos en un historial de dominio. |
| BR-CPA-013 | Cuando todos los requisitos acumulativos se satisfacen, el contexto puede originar una única recompensa `pending`; la relación a esa recompensa pertenece al contexto CPA, no al progreso de verificación. |
| BR-CPA-014 | El proveedor de actividad entrega evidencia normalizada y Finance certifica depósitos; ninguno decide elegibilidad CPA, importe, beneficiario, recompensa ni settlement. |
| BR-CPA-015 | Una versión CPA que exija depósito declara explícitamente la precisión de su moneda. IB usa esa precisión congelada para convertir y comparar minor units; no la infiere de ICU, Finance ni otra fuente externa. |
| BR-CPA-016 | Al calificar un contexto CPA, IB conserva en la Reward la evidencia normalizada que la justifica. Cada hecho conserva proveedor, tipo e identificador fuente; la evidencia no sustituye el ledger ni el progreso CPA. |
| BR-CPA-017 | El modo incremental solo es válido si cada proveedor garantiza que no publicará, corregirá ni retirará hechos anteriores a un corte confirmado. Hasta verificar ese contrato, IB debe reevaluar el intervalo completo desde la captura. |
| BR-CPA-018 | El ledger `rewards` es genérico. La relación específica de CPA se conserva de forma inversa, única e inmutable mediante `cpa_context.reward_id`; una Reward no exige ni contiene un contexto CPA. |
| BR-CPA-019 | La lectura cliente del progreso CPA queda limitada al IB propietario del contexto. La lectura administrativa requiere la capacidad de gestión de Rewards y puede exponer únicamente los identificadores y errores sanitizados necesarios para auditoría. |

## Ejemplo de reutilización CPA

```text
Plan Fx - Advanced
└── Regla CPA Standard v1
    ├── Programa Basic    + Broker
    ├── Programa Advanced + Broker
    └── Programa Pro      + Broker
```

Las tres asignaciones comparten configuración económica y scope `all`. Si cambian monto o umbrales, se publica otra versión y las asignaciones se reemplazan deliberadamente, conservando el historial.

## Eventos de negocio

- Versión de regla publicada.
- Regla asignada o retirada de un programa y módulo.
- Versión de una asignación reemplazada.
- Contexto CPA capturado.
- Progreso CPA actualizado.
- Contexto CPA calificado.
- Actividad aceptada para evaluación.
- Recompensa calculada.
- Pago solicitado.
- Reward asentada.
- Reward cancelada, revertida o compensada.
- Discrepancia financiera detectada o resuelta.
- Run de Reward iniciado o cerrado.
- Reward de volumen calculada.
- Período PnL evaluado.

## Decisiones pendientes

- Prioridad cuando múltiples reglas de recompensa coinciden con la misma actividad.
- Si una actividad puede producir varias recompensas válidas dentro del mismo plan.
- Confirmación futura de Trading Account Service como emisor posterior a la persistencia para eliminar la carrera conocida del modo `event`; `event` y `both` ya están autorizados mediante receipts recuperables.
- Contratos S2S definitivos de cuentas/balances Broker y depósitos/retiros certificados Finance para PnL.
- Forma del scope instrumental explícito cuando exista el catálogo de instrumentos.
