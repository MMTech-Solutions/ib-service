# Documentación de IB Service

Este directorio contiene las fuentes de verdad funcionales y técnicas del servicio. Su propósito es permitir que producto, negocio e ingeniería compartan el mismo vocabulario, que las decisiones sean verificables y que el código pueda contrastarse con reglas explícitas.

## Secciones

### [`bds/`](bds/README.md)

Business Domain Specifications. Describen conceptos, invariantes, cálculos, estados y eventos del negocio sin depender de Laravel, bases de datos, endpoints o mensajería.

- [`plans-and-subscriptions.bds.md`](bds/plans-and-subscriptions.bds.md): planes, programas, módulos, suscripciones y placement.
- [`progression.bds.md`](bds/progression.bds.md): contribuciones multi-módulo, ponderación por puntos y runs de progresión.
- [`rewards.bds.md`](bds/rewards.bds.md): reglas reutilizables, asignaciones, recompensas y contexto CPA.

### [`rules/`](rules/README.md)

Reglas de construcción y operación del software. Traducen las necesidades del dominio a límites técnicos sin sustituir los BDS.

- [`architecture.md`](rules/architecture.md): estructura, fronteras y dependencias.
- [`api-conventions.md`](rules/api-conventions.md): forma del envelope HTTP; `data` es el recurso o la colección.
- [`integrations.md`](rules/integrations.md): Kafka, eventos, IAM, SDKs, clientes HTTP y contratos por audiencia.
- [`strategies.md`](rules/strategies.md): reglas configurables y patrón Strategy.
- [`code-style.md`](rules/code-style.md): convenciones de PHP y Laravel.
- [`technology-stack.md`](rules/technology-stack.md): stack confirmado y decisiones pendientes.
- [`programming-best-practices.md`](rules/programming-best-practices.md): calidad, pruebas, idempotencia y observabilidad.
- [`security.md`](rules/security.md): controles de seguridad y protección de datos.
- [`bds.md`](rules/bds.md): propósito, formato y mantenimiento de los BDS.

### [`roadmap/`](roadmap/README.md)

Planificación trazable por feature. Organiza inventarios de casos de uso,
modelado de dominio, entregas verticales, diseño de datos e implementación sin
sustituir los BDS ni las reglas técnicas.

- [`modules/`](roadmap/modules/README.md): fase inicial del catálogo de módulos,
  capacidades y control operativo.
- [`plans/`](roadmap/plans/README.md): primer consumidor inter-feature de
  Modules; P1 completado.
- [`programs/`](roadmap/programs/README.md): PR1 completado (catálogo
  administrativo); P2 Listo (publicación, snapshots y umbrales documentados).
  Planificación: [`06-second-delivery-planning.md`](roadmap/programs/06-second-delivery-planning.md).

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
