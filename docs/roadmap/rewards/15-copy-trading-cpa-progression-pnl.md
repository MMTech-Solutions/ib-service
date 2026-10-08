# CPA, Progression y PnL para Copy Trading

Última revisión: 2026-10-07.
Estado: implementación local completada y verificada. Proveedor externo y aceptación integrada pendientes.

## Decisiones

Solo cuentas creadas desde Copy Trading, exclusión de externas asociadas garantizada por proveedor. Actividad atribuida al dueño. CPA/Progression/PnL por HTTP periódico; Kafka exclusivamente volumen. Finance certifica depósitos CPA comunes. Contribuciones y compensación independientes por módulo. Mismas fórmulas, precisión, idempotencia, pausas, snapshots y settlement existentes.

## Entrega

Adapters de CPA y Progression en Modules/Sources/CopyTrading. Factory Progression por módulo/capacidad y soporte de símbolos. PnL por módulo con transporte independiente, validación compartida y procedencia congelada. Configuración administrativa explícita; no se activan reglas automáticamente. Sin tablas ni dependencias nuevas; snapshots antiguos conservados.

[Contrato requerido](../../rules/copy-trading-provider-contract.md). La colección raíz añade Provider Contracts / Copy Trading, ejemplos de catálogo/feed/PnL y contrato Kafka en descripción, separados de rutas propias de IB.

## Verificación y pendientes

Evidencia local: 128 pruebas aprobadas / 2061 assertions. Incluye HTTP Copy Trading → calificación CPA con Finance, configuración instrumental → puntos → cierre → placement, PnL → Reward pending → settlement y regresiones Broker/volumen/arquitectura. Pint aprobado. Postman v2.1 válido: rutas propias contrastadas con route:list --except-vendor y /up del bootstrap; siete requests del proveedor verificados por separado. Graphify actualizado al cierre.

Pendientes externos: endpoints Copy Trading, credenciales, topic real, garantía de cortes CPA y aceptación S2S con IAM/Finance hasta settlement. Pruebas simuladas no acreditan esos pendientes.