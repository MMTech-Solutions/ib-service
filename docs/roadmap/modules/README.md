# Roadmap del feature Modules

## Objetivo

`Modules` representa en IB Service las capacidades que otros dominios aportan a
planes y programas. Su primera fase debe permitir representar esas capacidades,
consultarlas y controlar su operatividad sin acoplar el dominio a Broker,
PropFirm, Copy Trading o al futuro Trading Account Service.

## Estado

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para M1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | M1 completado |
| 4. Tablas de la primera entrega | [`04-first-delivery-data-model.md`](04-first-delivery-data-model.md) | Completado para M1 |
| 5. Implementación y contract tests | [`05-first-delivery-implementation.md`](05-first-delivery-implementation.md) | M1 completado |

Última revisión: 2026-09-11.

## Decisiones confirmadas

- El catálogo de módulos se persiste en IB Service.
- La identidad y capacidades disponibles proceden de un registro cerrado en
  código y se reconcilian con persistencia mediante `modules:sync`.
- Administración no crea módulos ni concede capacidades.
- `is_active` controla la disponibilidad total; no existe eliminación física ni
  lógica para módulos que hayan sido referenciados.
- `processing_status` distingue inicialmente `running` y `paused`; la pausa
  detiene cálculos y pagos, pero conserva ingesta y consultas de actividad.
- El módulo activo sigue siendo seleccionable aunque su procesamiento esté
  pausado.
- M1 no ofrece creación administrativa de módulos ni un endpoint exclusivo para
  capacidades; lista y detalle incluyen las capacidades sincronizadas.
- El catálogo no se versiona completamente.
- La configuración publicada de un programa conserva un snapshot de la
  semántica y capacidades del módulo utilizadas al publicarse.
- La relación directa con el registro actual del módulo controla su
  operatividad en tiempo real y permite un botón de pánico.
- El puerto de entrada pertenece al feature que expone la capacidad y su caso
  de uso actúa como implementador.
- El puerto de salida pertenece al feature que necesita una capacidad externa;
  su adaptador se implementa dentro de ese feature.
- Solo objetos contractuales y objetos del `SharedKernel` cruzan fronteras de
  features.
- Los objetos tipados del feature distinguen visibilidad por carpeta: internos
  en `DTOs/`; públicos en `Contracts/Data/V1/` (u otra versión mayor). El
  sufijo `Data` no implica por sí solo un contrato publicado.
- Solo se versionan por directorio `Contracts/Data` y `Contracts/Events`. Los
  DTOs internos no se versionan; se refactorizan dentro de `DTOs/`.
- M1 no publica objetos en `Contracts/Data` sin un consumidor inter-feature
  real. La primera publicación ocurrirá con `Plans P1`.
- Los bindings se concentrarán inicialmente en `ModulesServiceProvider`.
- Broker puede sustituir temporalmente al futuro Trading Account Service tras
  adaptadores estrechos; ese reemplazo no debe cambiar a los consumidores.
- M1 no diseña una frontera pública para consumidores hipotéticos. La primera
  surgirá en `Plans P1`, cuando un caso de uso real necesite consultar módulos.
- M1a entrega la sincronización y el listado administrativo; M1b incorpora
  detalle, edición, control operativo e historial.
- Los módulos nuevos nacen activos y en `running`.
- La concurrencia usa `lock_version` técnico sin versionar funcionalmente el
  catálogo. Listado, detalle y mutaciones lo exponen como token de concurrencia.
- El historial operativo es un hijo inmutable del agregado y solo registra
  activación, desactivación, pausa y reanudación. La edición administrativa y
  el sync no escriben historial.
- Las cuatro operaciones operativas exigen motivo y son idempotentes.
- BR-MODULE-015 permanece vigente; su verificación ejecutable se realiza en M3.

## Alcance inicial

El primer incremento confirmado abarca catálogo persistente, sincronización de
módulos y capacidades desde código, consultas administrativas, edición de datos
permitidos, activación/desactivación, pausa/reanudación del procesamiento y
control operativo auditable. La consulta de símbolos y actividad externa se
posterga hasta que esos contratos y el modelo base estén estables.

## Fuera del primer incremento

- Configuración de planes y programas.
- Puertos inter-feature para Plans, Programs u otros consumidores.
- Snapshot de configuración de programas.
- Ingesta o consulta de posiciones, cuentas y métricas.
- Normalización de depósitos, lotaje, challenges, PnL o CPA.
- Cálculo de progresión y recompensas.
- Sustitución definitiva de Broker por Trading Account Service.

## Referencias canónicas

- [`plans-and-subscriptions.bds.md`](../../bds/plans-and-subscriptions.bds.md)
- [`progression.bds.md`](../../bds/progression.bds.md)
- [`architecture.md`](../../rules/architecture.md)
- [`integrations.md`](../../rules/integrations.md)

## Próximo paso

[`Plans P1`](../plans/README.md) ya consume el puerto público mínimo. M2 sigue
condicionado a un consumidor real de `EvaluateActivity`.
