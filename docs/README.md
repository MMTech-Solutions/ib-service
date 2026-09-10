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
- [`strategies.md`](rules/strategies.md): reglas configurables y patrón Strategy.
- [`code-style.md`](rules/code-style.md): convenciones de PHP y Laravel.
- [`technology-stack.md`](rules/technology-stack.md): stack confirmado y decisiones pendientes.
- [`programming-best-practices.md`](rules/programming-best-practices.md): calidad, pruebas, idempotencia y observabilidad.
- [`security.md`](rules/security.md): controles de seguridad y protección de datos.
- [`bds.md`](rules/bds.md): propósito, formato y mantenimiento de los BDS.

## Jerarquía de autoridad

1. BDS vigente para semántica e invariantes de negocio.
2. Reglas técnicas de `docs/rules/` para arquitectura e implementación.
3. Contratos de integración versionados, cuando sean creados.
4. Código, migraciones y pruebas como evidencia de implementación.

Si el código contradice un BDS vigente, la contradicción debe reportarse; no debe reinterpretarse silenciosamente el dominio a partir del código.

## Mantenimiento

- Toda decisión nueva de negocio debe actualizar el BDS afectado antes o junto con su implementación.
- Toda regla técnica transversal debe registrarse en `docs/rules/`.
- Al crear, renombrar o retirar documentación, deben actualizarse este índice y el índice de la sección correspondiente.
- Las decisiones pendientes deben permanecer explícitamente marcadas; no deben presentarse como invariantes confirmadas.
