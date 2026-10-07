# Roadmap de IB Service

Este directorio convierte las decisiones de dominio y arquitectura en entregas
verificables. El roadmap registra qué se pretende construir, qué decisiones
faltan y qué evidencia permite considerar terminada cada etapa.

No es una fuente de verdad alternativa: los BDS gobiernan la semántica del
negocio y `docs/rules/` gobierna las restricciones técnicas. Si un documento de
roadmap contradice esas fuentes, debe corregirse el roadmap.

## Índice por feature

| Feature | Estado | Etapa actual | Última revisión |
| --- | --- | --- | --- |
| [`Modules`](modules/README.md) | M1-M5 implementados; validación S2S pendiente | M5: actividad Broker S2S y configuración instrumental de Programs | 2026-10-02 |
| [`Plans`](plans/README.md) | P1 + P0.3 + P3 completados localmente | Validación integrada de vinculaciones con ib-labs | 2026-10-04 |
| [`Programs`](programs/README.md) | PR1 y P2 completados; BR-PROGRAM-015 cerrado | Sin extensión pendiente de Progression | 2026-09-29 |
| [`Rules`](rules/README.md) | R1 y R2 completados; P0.1 completada | Extensión Progression: puerto de contexto publicado | 2026-09-17 |
| [`Subscriptions`](subscriptions/README.md) | S1 + P0.2 completadas | Extensión Progression publicada | 2026-09-17 |
| [`Progression`](progression/README.md) | PG1/PG2 y contrato LAB5 implementados localmente | Aceptación integrada LAB5 con ib-labs, despliegue y alertas | 2026-10-05 |
| [`Rewards`](rewards/README.md) | Cierre de código E2E completado localmente; A1/A2 completadas | [E2E](rewards/11-e2e-historical-pnl.md): Generación y settlement PnL habilitados; consultas y regresiones locales aprobadas; validación posterior con ib-labs | 2026-10-03 |

## Secuencia entre features

Actualización de volumen, 2026-10-06: [eventos de proveedores](rewards/14-volume-provider-events.md), implementación local en IB/Broker; productor Copy Trading y aceptación integrada pendientes.

La jerarquía del dominio determina el orden inicial. Tras PR1, P2 cierra el
ladder vivo de umbrales. Rules y Subscriptions pueden avanzar después;
Progression y Rewards dependen de ambos.

```text
Modules M1
    ↓
Plans P1
    ↓
Programs PR1
    ↓
Programs P2  (ladder vivo de umbrales)
    ├──→ Rules R1/R2 ─────────────┐
    └──→ Subscriptions S1 ────────┤──→ Progression PG1 → PG2
                                  └──→ Rewards
Modules M3 (actividad) ───────────→ Progression PG1
Modules M2 (instrumentos) ─────────→ Progression PG2
```

```mermaid
flowchart LR
    PR1["Programs PR1 completado"] --> P2["Programs P2: ladder vivo"]
    P2 --> Rules["Rules R1/R2"]
    P2 --> Subs["Subscriptions S1"]
    Rules --> Progression["Progression PG1"]
    Subs --> Progression
    ModulesM3["Modules M3"] --> Progression
    ModulesM2["Modules M2"] --> PG2
    Progression --> PG2["Progression PG2"]
    Rules --> Rewards["Rewards"]
    Subs --> Rewards
```

- `Modules M1` no publica contratos inter-feature especulativos. Sus objetos
  tipados viven como DTOs internos hasta que un consumidor real exija un
  `Contracts/Data/V1`.
- `Plans P1` es el primer consumidor real de `Modules`; su necesidad concreta
  origina el primer puerto de entrada público de `Modules` y, con él, el
  primer `Contracts/Data` versionado.
- `Programs` no consume libremente el catálogo de módulos: primero debe obtener
  el contexto y los módulos habilitados por el plan propietario.
- `Programs P2` implementa el ladder vivo de umbrales, incluido el umbral `0`
  del primer programa. No publica ni versiona programas. Rules y
  Subscriptions abren inventario tras P2.
- `Subscriptions` conserva una única suscripción abierta global por usuario,
  historia terminal y placement libre o fijado. S1 está implementada de extremo
  a extremo (contextos, persistencia, solicitud/moderación, ciclo de vida,
  fijación y salvaguarda de archivo), con permisos, Postman y suite PostgreSQL
  verificados. Progression y Rewards pueden consumir ese contexto sin reabrir
  S1 para contratos especulativos.
- `Progression` PG1 está completada (sesiones 1–5 y P0). PG2 cubre runs y
  placement; Programs también cierra el umbral `0` requerido para el primer
  nivel del ladder.
- Una frontera pública se diseña junto con la entrega vertical del consumidor,
  no como una entrega aislada del proveedor.

## Iteración estándar por feature

Cada feature avanza mediante los siguientes documentos. Los nombres de clases,
tablas y endpoints siguen siendo provisionales hasta cerrar la etapa que los
define.

1. **Inventario de casos de uso:** intenciones de actores y resultados
   observables, todavía sin forzar nombres de implementación.
2. **Modelo de dominio:** agregados, estados, invariantes y límites
   transaccionales.
3. **Entregas verticales:** incrementos utilizables de entrada a persistencia,
   con criterios de aceptación.
4. **Modelo de datos de la primera entrega:** tablas, restricciones, índices y
   decisiones de concurrencia requeridas por el primer incremento.
5. **Implementación de la primera entrega:** contratos, repositorios en memoria
   y PostgreSQL, contract tests, casos de uso y adaptadores de entrada.

Una etapa puede descubrir cambios para una etapa anterior. En ese caso se
actualizan ambos documentos y se registra expresamente la decisión; no se
mantiene una secuencia artificial a costa de inconsistencias.

## Estados

| Estado | Significado |
| --- | --- |
| No iniciado | Aún no se ha trabajado la etapa. |
| En descubrimiento | Se están recopilando necesidades y alternativas. |
| Pendiente de decisión | Existe información suficiente, pero falta cerrar una decisión explícita. |
| Listo | Alcance y criterios de salida están definidos para comenzar. |
| En curso | Hay trabajo de implementación o validación activo. |
| Completado | Se cumplieron los criterios de salida y existe evidencia verificable. |
| Bloqueado | No puede avanzarse sin una dependencia o decisión externa identificada. |

## Criterios de avance

| Etapa | Criterio de salida mínimo |
| --- | --- |
| Inventario | Actores, intenciones, resultados y dudas están enumerados; el alcance de la primera entrega puede seleccionarse. |
| Dominio | Agregados, invariantes, estados y transacciones de la primera entrega están acordados. |
| Entregas | Cada incremento tiene valor observable, dependencias y criterios de aceptación. |
| Datos | El esquema de la primera entrega tiene columnas, tipos, restricciones, índices y estrategia de concurrencia definidos. |
| Implementación | El incremento funciona de extremo a extremo y la misma suite contractual valida ambos repositorios. |

Además de su evidencia funcional, toda entrega que modifique `app/Features/` debe mantener aprobada la suite `tests/Architecture/` definida en [`docs/rules/architecture-validation.md`](../rules/architecture-validation.md). Esta validación no sustituye contract tests ni validación S2S.

## Mantenimiento

- El `README.md` de cada feature conserva el estado actual, las decisiones
  confirmadas y el próximo paso.
- Cada cambio de estado actualiza la fecha de revisión y enlaza su evidencia.
- Las hipótesis se etiquetan como tales y las decisiones pendientes no se
  convierten en nombres o estructuras definitivas.
- No se agregan estimaciones ni fechas de entrega sin responsables y
  dependencias acordados.
- Al incorporar un feature, se crea su directorio siguiendo esta iteración y se
  añade al índice anterior.

CPA por puntos: [entrega vigente](rewards/12-cpa-points-refactor.md), revisión
2026-10-06. El schema de cantidades anterior se sustituye sin compatibilidad.

N-PnL por nivel y moneda (2026-10-06): [entrega y evidencia](rewards/13-negative-pnl-level-currency.md).

## Refactor PnL realizado — 2026-10-06

El contrato vigente utiliza exclusivamente profit de posiciones cerradas por intervalo, con position_ids completos y snapshots por cuenta en IB. Sustituye el cálculo anterior por balances, cashflows y baselines monetarias descrito en entregas históricas de este documento. Se conserva compensación por nivel/moneda y settlement. Implementación completada localmente: IB 115 pruebas / 1246 assertions; Broker 18 pruebas / 146 assertions. Pint aprobado y Graphify actualizado en ambos servicios. Postman v2.1 válido; IB cubre las 100 rutas propias, incluido /up; la ruta interna Broker conserva método y headers S2S. Instalación limpia validada por las suites sobre bases aisladas de testing; no se reinició la base operativa. S2S y datos reales pendientes. Reconciliación de cierres incorporados o corregidos después del run pendiente de decisión. Véase [contrato vigente](../rules/negative-pnl-contract.md).
