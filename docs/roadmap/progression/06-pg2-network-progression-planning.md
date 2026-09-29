# PG2: planificación de progresión por red interna

Estado: **Completada**
Dependencia operativa satisfecha: resolución vigente de upline en IAM
Última revisión: 2026-09-29

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
- La red y el nivel se resuelven una sola vez al procesar la actividad y se
  conservan como distribución inmutable; símbolo, plantilla, versión y
  `weight` conservan su semántica de `occurred_at`.
- No existe backfill: el comportamiento nuevo solo aplica a actividad ocurrida
  desde su activación.
- Las plantillas de Progression comparten convenciones administrativas con las
  de pago, pero no configuración ni fórmulas económicas.

## Dependencias y bloqueos

| Dependencia | Propietario | Condición de desbloqueo |
| --- | --- | --- |
| Catálogo de instrumentos y símbolos | Modules M2 | **Satisfecha:** frontera V1 publicada con referencias opacas por módulo. |
| Upline vigente | IAM | **Satisfecha:** resolver una vez los IB beneficiarios y niveles del referido; la respuesta se congela localmente antes de contribuir. |
| Semántica de negocio | BDS Progression | **Satisfecha:** BDS v0.8 incorpora glosario, reglas, auditoría y distribución congelada de PG2. |

No se asociará una plantilla a `metric_code` ni se usará la red actual como
fallback: ambas alternativas romperían el requisito de configuración por
símbolo e historicidad.

## Secuencia de sesiones

| Fase | Documento a crear al iniciarla | Alcance | Estado |
| --- | --- | --- | --- |
| 1 | Este documento | Decisiones, BDS y dependencias | Completada |
| 2 | `07-pg2-network-domain-model.md` | Agregados, snapshots, invariantes e idempotencia | Completada |
| 3 | `08-pg2-network-vertical-deliveries.md` | Entregas verticales y criterios observables | Completada |
| 4 | `09-pg2-network-data-model.md` | Persistencia, restricciones, índices y concurrencia | Completada |
| 5 | `10-pg2-network-foundations.md` | Puerto IAM y catálogo de plantillas | Diseño completado |
| 6 | `11-pg2-network-contributions.md` | Distribución, evaluaciones y contribuciones auditables | Diseño completado |
| 7 | `12-pg2-network-runs-placement.md` | Runs, resultados y placement | Diseño completado |
| 8 | `13-pg2-network-closure.md` | Regresión, evidencia integral y gate para Rewards | Diseño completado |

Cada sesión crea su documento al iniciarse y lo actualiza al cerrarse con
estado, evidencia verificable, pruebas ejecutadas, bloqueos y próximo paso.
No se crearán documentos vacíos para fases todavía no iniciadas.

## Criterio de salida de esta fase

- El BDS de Progression refleja las decisiones confirmadas sin introducir
  detalles de infraestructura. **Completado el 2026-09-29.**
- `Modules M2` y la consulta vigente de upline tienen condiciones de salida
  comprobables.
- El README de Progression registra el diseño PG2 cerrado y su siguiente paso.
- No se ha modificado código, persistencia, contratos ejecutables ni el
  comportamiento de PG1.

## Evidencia

- `docs/bds/progression.bds.md` v0.8: red interna, nivel `0`, plantilla propia,
  distribución congelada, idempotencia por beneficiario y sin backfill.
- `07-pg2-network-domain-model.md`: modelo de dominio completado.
- `docs/roadmap/modules/07-m2-instrument-catalog-planning.md` y la tarea de
  `auth-service`: dependencias documentadas con condiciones de salida.

## Próximo paso

Crear las foundations de PG2. La futura consulta histórica de Auth no altera
esta decisión sin una nueva regla de dominio.
