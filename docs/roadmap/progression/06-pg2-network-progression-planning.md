# PG2: planificación de progresión por red interna

Estado: **Completada**
Dependencias de desbloqueo: `Modules M2` y contrato histórico de red de
referidos de `auth-service`
Última revisión: 2026-09-28

## Propósito

Planificar PG2 para que el placement de un IB pueda progresar con la actividad
de su red interna. La actividad propia no aporta: los referidos directos son el
nivel `0` y los niveles posteriores aportan conforme al `weight` configurado
para ese nivel.

Este documento es un roadmap, no una fuente alternativa de verdad. Antes de
implementar, las decisiones de negocio listadas aquí deben formalizarse en el
BDS propietario de Progression.

## Decisiones de producto que se deben formalizar

- La actividad de cualquier miembro de la downline puede generar puntos para
  el IB beneficiario; la actividad propia no genera progresión.
- Cada contribución usa el `weight` del nivel de distribución de una plantilla
  propia de Progression, asociada a un símbolo habilitado para progresión.
- Red, nivel, símbolo, plantilla, versión y `weight` se resuelven en
  `occurred_at` y se conservan como snapshot auditable.
- No existe backfill: el comportamiento nuevo solo aplica a actividad ocurrida
  desde su activación.
- Las plantillas de Progression comparten convenciones administrativas con las
  de pago, pero no configuración ni fórmulas económicas.

## Dependencias y bloqueos

| Dependencia | Propietario | Condición de desbloqueo |
| --- | --- | --- |
| Catálogo de instrumentos y símbolos | Modules M2 | Publicar la frontera que permita identificar símbolos por módulo sin exponer identificadores del proveedor. |
| Red histórica | auth-service | Publicar un contrato versionado que resuelva, para un referido y `occurred_at`, los IB beneficiarios y su nivel de distribución. |
| Semántica de negocio | BDS Progression | Incorporar glosario, relaciones, reglas, cálculo, auditoría y decisiones de migración temporal de PG2. |

No se asociará una plantilla a `metric_code` ni se usará la red actual como
fallback: ambas alternativas romperían el requisito de configuración por
símbolo e historicidad.

## Secuencia de sesiones

| Fase | Documento a crear al iniciarla | Alcance | Estado |
| --- | --- | --- | --- |
| 1 | Este documento | Decisiones, BDS y dependencias | En descubrimiento |
| 2 | `07-pg2-network-domain-model.md` | Agregados, snapshots, invariantes e idempotencia | Completada |
| 3 | `08-pg2-network-vertical-deliveries.md` | Entregas verticales y criterios observables | Bloqueada por dependencias externas |
| 4 | `09-pg2-network-data-model.md` | Persistencia, restricciones, índices y concurrencia | Bloqueada por fases 2–3 |
| 5 | `10-pg2-network-foundations.md` | Modules M2, contrato Auth y catálogo de plantillas | Bloqueada por dependencias externas |
| 6 | `11-pg2-network-contributions.md` | Fan-out, evaluaciones y contribuciones auditables | Bloqueada por fase 5 |
| 7 | `12-pg2-network-runs-placement.md` | Runs, resultados y placement | Bloqueada por fase 6 |
| 8 | `13-pg2-network-closure.md` | Regresión, evidencia integral y gate para Rewards | Bloqueada por fase 7 |

Cada sesión crea su documento al iniciarse y lo actualiza al cerrarse con
estado, evidencia verificable, pruebas ejecutadas, bloqueos y próximo paso.
No se crearán documentos vacíos para fases todavía no iniciadas.

## Criterio de salida de esta fase

- El BDS de Progression refleja las decisiones confirmadas sin introducir
  detalles de infraestructura. **Completado el 2026-09-28.**
- `Modules M2` y el contrato de `auth-service` tienen condiciones de salida
  comprobables.
- El README de Progression registra PG2 en descubrimiento y su siguiente paso.
- No se ha modificado código, persistencia, contratos ejecutables ni el
  comportamiento de PG1.

## Evidencia

- `docs/bds/progression.bds.md` v0.7: red interna, nivel `0`, plantilla propia,
  snapshots en `occurred_at`, idempotencia por beneficiario y sin backfill.
- `07-pg2-network-domain-model.md`: modelo de dominio de la siguiente fase.
- `docs/roadmap/modules/07-m2-instrument-catalog-planning.md` y la tarea de
  `auth-service`: dependencias documentadas con condiciones de salida.

## Próximo paso

Esperar la evidencia de `Modules M2` y del contrato histórico de `auth-service`
antes de crear `08-pg2-network-vertical-deliveries.md`. La implementación
continúa bloqueada hasta entonces.
