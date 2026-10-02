# Roadmap del feature Rewards

Estado: **RWD1 inventariado; RWD2/RV1-RV3 implementados; validación contractual pendiente**
Dependencias: `Rules R2`, `Subscriptions S1`, Modules M5, `auth-service` V1 y
Finance interno para depósitos certificados
Última revisión: 2026-09-30

## Objetivo

`Rewards` registra las obligaciones económicas de IB y conserva su causa,
snapshot y estado. Finance es autoridad del settlement. RWD2 implementará CPA
hasta la creación idempotente de una obligación `pending`; settlement, reversas
y compensaciones no pertenecen a esta entrega.

## Posición en la secuencia

Auth origina el contexto CPA. Rules, Programs y Subscriptions fijan su
configuración comercial. Modules/Broker obtiene evidencia normalizada desde
Broker Service y Finance; Rewards la interpreta y conserva el progreso y el
ledger.

```mermaid
flowchart LR
    Auth[auth-service] --> Contexto[Contexto CPA]
    Rules[Rules y Programs] --> Contexto
    Subs[Subscriptions] --> Contexto
    Broker[Broker Service: volumen cerrado] --> Modules[Modules/Broker]
    Finance[Finance: depósitos certificados] --> Modules
    Contexto --> Rewards[Rewards CPA]
    Modules --> Rewards
    Rewards --> Progress[Progreso CPA]
    Rewards --> Ledger[Reward pending]
```

## RWD1 — inventario

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado y corregido |

## RWD2 — verificación CPA, progreso y ledger `pending`

| Etapa | Documento | Estado |
| --- | --- | --- |
| 2. Modelo de dominio | [`02-cpa-domain-model.md`](02-cpa-domain-model.md) | Listo |
| 3. Entregas verticales | [`03-cpa-vertical-deliveries.md`](03-cpa-vertical-deliveries.md) | Listo |
| 4. Modelo de datos | [`04-cpa-data-model.md`](04-cpa-data-model.md) | Listo |
| 5. Implementación y contract tests | [`05-cpa-implementation-plan.md`](05-cpa-implementation-plan.md) | RV1-RV3 implementados; contract tests pendientes |

## Decisiones confirmadas

- La captura CPA permanece idempotente por referido e IB y congela requisitos,
  versión de regla y símbolos/grupos CPA.
- CPA no participa en Progression, pesos ni red multinivel.
- El módulo Broker obtiene volumen cerrado de Broker Service y depósitos
  certificados directamente de Finance; Broker Service no compone depósitos.
- Los requisitos de volumen y depósito se satisfacen acumulativamente y sin FX.
- Cada contexto tiene un solo progreso visible. `reward_id` se conserva en
  `cpa_context`, nunca en el progreso.
- Una evaluación calificada crea una única Reward `pending`; Finance solo decide
  su settlement posterior.

## Próximo paso

Validar el envelope real de `auth.account.registered` V1 y los contratos de
Broker Service y Finance antes de cerrar RWD2. Settlement permanece fuera de
esta vertical.
