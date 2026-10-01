# RWD2: modelo de datos CPA

Estado: **Listo**
Última revisión: 2026-09-30

## Contexto CPA

Extender `cpa_contexts` con el snapshot inmutable de requisitos y símbolos o
grupos CPA, y con `reward_id` opcional y único. `reward_id` es nulo hasta que
la evaluación cree su obligación; no se almacena en el progreso.

La unicidad de referido + IB se mantiene como protección persistente de la
captura idempotente.

## Progreso de verificación

Crear `cpa_verification_progress` con una clave única a `cpa_context_id` y los
siguientes datos de lectura:

| Dato | Semántica |
| --- | --- |
| Referido e IB | Identidades opacas del contexto. |
| Estado | `pending`, `qualified` o `error`. |
| Volumen observado/requerido y unidad | Decimales exactos y unidad configurada. |
| Depósito observado/requerido y moneda | Minor units exactos, moneda sin conversión y precisión congelada por la configuración CPA. |
| Banderas de cumplimiento | Resultado independiente de cada requisito. |
| Desde/hasta observado | Captura y último corte consultado. |
| Última evaluación y error | Operabilidad sin historia de intentos; error sanitizado y nullable. |
| Auditoría | Instantes de creación y actualización. |

No existe una tabla por intento, una copia de `reward_id` ni un ledger de
depósitos o trades. Las referencias necesarias para justificar una decisión se
conservan en el snapshot o en la Reward conforme al contrato de evidencia.

## Reward

El ledger `rewards` es genérico: no contiene `cpa_context_id`. Para CPA, la
relación única y protegida se conserva en sentido inverso mediante
`cpa_contexts.reward_id`; por eso el mismo ledger podrá registrar volumen y
PnL sin requerir un contexto CPA. El contexto CPA es causa comercial congelada,
no un hecho de `reward_evidence`.

RWD2 crea el ledger `rewards` con una restricción persistente que impide más de
una Reward por contexto CPA. La Reward conserva causa, beneficiario, importe y
moneda congelados, referencias de regla y módulo, snapshot de evidencia y
estado inicial `pending`. No incluye columnas de settlement en esta entrega.

La restricción de una sola Reward por contexto CPA se implementa con la
unicidad de `cpa_contexts.reward_id`, no con una columna de CPA en `rewards`.

`reward_evidence` conserva los hechos normalizados que justificaron una Reward:
`evidence_provider`, `evidence_type` e identificador fuente son obligatorios.
Para volumen cerrado también conserva cantidad, unidad, instante y referencia
instrumental; para depósitos certificados conserva importe minor, moneda e
instante acreditado. No existe `source_system` en Reward: su módulo ya expresa
el origen funcional de la obligación.

## Concurrencia y retención

La evaluación bloquea o protege el contexto y su relación a Reward de modo que
dos runners no dupliquen la obligación. Las llamadas remotas ocurren antes de
la transacción de actualización final. Los índices de lectura cubren contexto,
IB, referido, estado, módulo/programa y último corte sin indexar payloads o
secretos.
