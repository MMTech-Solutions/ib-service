# Reglas del proyecto

Esta sección documenta decisiones técnicas transversales. Sus archivos orientan la implementación, pero no redefinen las invariantes de negocio establecidas en `docs/bds/`.

| Documento | Propósito |
| --- | --- |
| [`architecture.md`](architecture.md) | Fronteras, dependencias y estructura del servicio. |
| [`integrations.md`](integrations.md) | Kafka, eventos, IAM, SDKs, clientes HTTP y contratos por audiencia. |
| [`strategies.md`](strategies.md) | Contrato para estrategias y configuración JSON versionada. |
| [`code-style.md`](code-style.md) | Convenciones de PHP y Laravel. |
| [`technology-stack.md`](technology-stack.md) | Tecnologías confirmadas y pendientes. |
| [`programming-best-practices.md`](programming-best-practices.md) | Calidad, pruebas y confiabilidad. |
| [`security.md`](security.md) | Seguridad de entradas, integraciones y datos. |
| [`bds.md`](bds.md) | Creación y mantenimiento de especificaciones de dominio. |

Toda regla nueva debe indicar si es obligatoria, recomendada o una decisión todavía pendiente.
