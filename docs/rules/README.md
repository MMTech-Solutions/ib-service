# Reglas del proyecto

Esta sección documenta decisiones técnicas transversales. Sus archivos orientan la implementación, pero no redefinen las invariantes de negocio establecidas en `docs/bds/`.

| Documento | Propósito |
| --- | --- |
| [`architecture.md`](architecture.md) | Fronteras, dependencias y estructura del servicio. |
| [`architecture-validation.md`](architecture-validation.md) | Guardrails PHPUnit, ratchet y retiro de deuda arquitectónica. |
| [`api-conventions.md`](api-conventions.md) | Forma del envelope HTTP: `data` es el recurso o la colección. |
| [`integrations.md`](integrations.md) | Kafka, eventos, IAM, SDKs, clientes HTTP y contratos por audiencia. |
| [`negative-pnl-contract.md`](negative-pnl-contract.md) | Contrato por lotes, agregación por nivel/moneda y actualización sin compatibilidad. |
| [`strategies.md`](strategies.md) | Orquestación propia por modalidad, factories separadas de evidencia/cálculo y configuración versionada. |
| [`code-style.md`](code-style.md) | Convenciones de PHP y Laravel. |
| [`technology-stack.md`](technology-stack.md) | Tecnologías confirmadas y pendientes. |
| [`programming-best-practices.md`](programming-best-practices.md) | Calidad, pruebas y confiabilidad. |
| [`security.md`](security.md) | Seguridad de entradas, integraciones y datos. |
| [`bds.md`](bds.md) | Creación y mantenimiento de especificaciones de dominio. |
| [`domain-clock.md`](domain-clock.md) | Instante de dominio por operación y controles privados de Lab. |

Toda regla nueva debe indicar si es obligatoria, recomendada o una decisión todavía pendiente.
