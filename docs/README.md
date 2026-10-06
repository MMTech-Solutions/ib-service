# Documentación de IB Service

Este directorio contiene las fuentes de verdad funcionales y técnicas del servicio. Se encuentra en `broker/ib-service/api/docs` y su propósito es permitir que producto, negocio e ingeniería compartan el mismo vocabulario, que las decisiones sean verificables y que el código pueda contrastarse con reglas explícitas.

## Secciones

### [`bds/`](bds/README.md)

Business Domain Specifications. Describen conceptos, invariantes, cálculos, estados y eventos del negocio sin depender de Laravel, bases de datos, endpoints o mensajería.

- [`plans-and-subscriptions.bds.md`](bds/plans-and-subscriptions.bds.md): planes, programas, módulos, suscripciones y placement.
- [`progression.bds.md`](bds/progression.bds.md): contribuciones multi-módulo, ponderación por puntos y runs de progresión.
- [`rewards.bds.md`](bds/rewards.bds.md): reglas reutilizables, asignaciones, CPA, volumen tradeado, PnL y recompensas.

### [`rules/`](rules/README.md)

Reglas de construcción y operación del software. Traducen las necesidades del dominio a límites técnicos sin sustituir los BDS.

- [`architecture.md`](rules/architecture.md): estructura, fronteras y dependencias.
- [`architecture-validation.md`](rules/architecture-validation.md): guardrails PHPUnit y ratchet de deuda arquitectónica.
- [`api-conventions.md`](rules/api-conventions.md): forma del envelope HTTP; `data` es el recurso o la colección.
- [`integrations.md`](rules/integrations.md): Kafka, eventos, IAM, SDKs, clientes HTTP y contratos por audiencia.
- [`negative-pnl-contract.md`](rules/negative-pnl-contract.md): contrato PnL por lotes y agregado por nivel/moneda.
- [`strategies.md`](rules/strategies.md): reglas configurables y patrón Strategy.
- [`code-style.md`](rules/code-style.md): convenciones de PHP y Laravel.
- [`technology-stack.md`](rules/technology-stack.md): stack confirmado y decisiones pendientes.
- [`programming-best-practices.md`](rules/programming-best-practices.md): calidad, pruebas, idempotencia y observabilidad.
- [`security.md`](rules/security.md): controles de seguridad y protección de datos.
- [`bds.md`](rules/bds.md): propósito, formato y mantenimiento de los BDS.
- [`domain-clock.md`](rules/domain-clock.md): reloj de aplicación y controles exclusivos de Lab.

### [`roadmap/`](roadmap/README.md)

Planificación trazable por feature. Organiza inventarios de casos de uso,
modelado de dominio, entregas verticales, diseño de datos e implementación sin
sustituir los BDS ni las reglas técnicas.

- [`modules/`](roadmap/modules/README.md): fase inicial del catálogo de módulos,
  capacidades y control operativo.
- [`plans/`](roadmap/plans/README.md): primer consumidor inter-feature de
  Modules; P1 completado; P0.3 (período de progresión) publicado.
  P3 completada localmente: vinculación administrativa de versiones de plantillas.
- [`programs/`](roadmap/programs/README.md): PR1 y P2 completados (catálogo
  administrativo y ladder vivo de umbrales, incluido el primer nivel en `0`).
- [`rules/`](roadmap/rules/README.md): R1 y R2 completados (catálogo,
  versiones y asignaciones históricas); P0.1 publicado (puerto de contexto
  `points_per_quantity_unit` + `BR-RULE-016`).
- [`subscriptions/`](roadmap/subscriptions/README.md): S1 completada
  (suscripciones, placement, fijación y salvaguarda de archivo); P0.2 publicado.
- [`progression/`](roadmap/progression/README.md): PG1 y PG2 completadas;
  runs y placement operativos; [contrato LAB5](roadmap/progression/15-lab5-contract.md) implementado localmente con recuperación por umbrales vigentes y auditoría por intento, pendiente de adaptación y aceptación integrada de ib-labs.
- [`rewards/`](roadmap/rewards/README.md): RWD2, RWD3 y RWD4.1 implementados;
  RWD4.2a local con S2S pendiente; RWD-A1 documental completada y RWD-A2 completada (A2.1–A2.3).
  RWD4.2.1 incorpora configuración histórica y cálculo PnL; RWD4.2.2 completa localmente
  runner y recuperación. El cierre local E2E completa settlement PnL, coordinación
  financiera, snapshots de volumen y consultas HTTP; generación y settlement están
  habilitados por defecto.
  Las pruebas integradas se realizarán con ib-labs, todavía inconcluso; siguen pendientes
  para el cierre E2E, sin bloquear desarrollo ni habilitación del código.
  [Plan E2E y evidencia](roadmap/rewards/11-e2e-historical-pnl.md): código completado localmente.

El [refactor CPA por puntos](roadmap/rewards/12-cpa-points-refactor.md) sustituye
el modelo anterior: calificación por dos umbrales independientes, aportes
multi-módulo de volumen y depósitos comunes certificados.

## Jerarquía de autoridad

1. BDS vigente para semántica e invariantes de negocio.
2. Reglas técnicas de `docs/rules/` para arquitectura e implementación.
3. Roadmap para secuencia, estado y decisiones pendientes de ejecución.
4. Contratos de integración versionados, cuando sean creados.
5. Código, migraciones y pruebas como evidencia de implementación.

Si el código contradice un BDS vigente, la contradicción debe reportarse; no debe reinterpretarse silenciosamente el dominio a partir del código.

## Mantenimiento

- Toda decisión nueva de negocio debe actualizar el BDS afectado antes o junto con su implementación.
- Toda regla técnica transversal debe registrarse en `docs/rules/`.
- El estado y la secuencia de una feature deben mantenerse en
  `docs/roadmap/`, sin elevar hipótesis a reglas confirmadas.
- Al crear, renombrar o retirar documentación, deben actualizarse este índice y el índice de la sección correspondiente.
- Las decisiones pendientes deben permanecer explícitamente marcadas; no deben presentarse como invariantes confirmadas.

N-PnL por nivel y moneda (2026-10-06): [entrega y evidencia](roadmap/rewards/13-negative-pnl-level-currency.md).

## Refactor PnL realizado — 2026-10-06

El contrato vigente utiliza exclusivamente profit de posiciones cerradas por intervalo, con position_ids completos y snapshots por cuenta en IB. Sustituye el cálculo anterior por balances, cashflows y baselines monetarias descrito en entregas históricas de este documento. Se conserva compensación por nivel/moneda y settlement. Implementación completada localmente: IB 115 pruebas / 1246 assertions; Broker 18 pruebas / 146 assertions. Pint aprobado y Graphify actualizado en ambos servicios. Postman v2.1 válido; IB cubre las 100 rutas propias, incluido /up; la ruta interna Broker conserva método y headers S2S. Instalación limpia validada por las suites sobre bases aisladas de testing; no se reinició la base operativa. S2S y datos reales pendientes. Reconciliación de cierres incorporados o corregidos después del run pendiente de decisión. Véase [contrato vigente](rules/negative-pnl-contract.md).
