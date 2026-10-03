# Roadmap del feature Progression

Estado: **PG1 y PG2 completadas**
Dependencias satisfechas: `Programs P2`, `Rules R2`, `Subscriptions S1`,
`Modules M3` (contrato + adapter deposits), Progression sesiones 1–5, Rules P0.1,
Subscriptions P0.2, Plans P0.3
Dependencia operativa PG2 satisfecha: resolución vigente de upline mediante
IAM; la distribución se congela localmente por actividad
Última revisión: 2026-10-03

## Objetivo

`Progression` convierte actividad heterogénea de módulos en puntos sin
dimensión, conserva evaluaciones auditables y, en una segunda entrega, ejecuta
runs por plan y ventana para mantener, subir o bajar el placement.

## Posición en la secuencia

Rules aporta asignaciones históricas y la estrategia `points_per_quantity_unit`.
Subscriptions aporta el contexto vigente e histórico de suscripción y
placement. Modules M3 aportará la consulta de actividad normalizada. Progression
es el primer consumidor real de esa actividad y de la evaluación de reglas para
puntos.

```mermaid
flowchart LR
    Programs[Programs P2] --> Progression
    Rules[Rules R2] --> Progression
    Subs[Subscriptions S1] --> Progression
    ModulesM3[Modules M3] --> PG1[Progression PG1]
    PG1 --> PG2[Progression PG2]
    PG2 --> Placement[Placement automático]
```

## Primera entrega: PG1

Consulta pull-only de actividad normalizada, evaluación durable (aceptada o
excluida) y contribuciones auditables. No ejecuta runs ni mueve placement.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Inventario de casos de uso | [`01-use-case-inventory.md`](01-use-case-inventory.md) | Completado para PG1 |
| 2. Agregados, estados y transacciones | [`02-domain-model.md`](02-domain-model.md) | Completado para PG1 |
| 3. Entregas verticales | [`03-vertical-deliveries.md`](03-vertical-deliveries.md) | Completado para PG1 |
| 4. Modelo de datos | [`04-pg1-data-model.md`](04-pg1-data-model.md) | Completado para PG1 |
| 5. Implementación y contract tests | [`05-pg1-implementation.md`](05-pg1-implementation.md) | PG1 Completada (sesiones 1–5 y P0) |

## Segunda entrega: PG2

Runs por plan y ventana, resultados independientes por suscripción y cambios
de placement conforme al ladder vivo. PG2 incorpora como necesidad de producto
la progresión por red interna: la actividad de la downline del IB genera puntos
según el nivel de distribución y la plantilla de progresión asociada al símbolo.

La planificación, el modelo de dominio y las entregas de PG2 están en
[`06-pg2-network-progression-planning.md`](06-pg2-network-progression-planning.md).
PG2 usa el upline vigente de IAM una sola vez por actividad y conserva la
distribución resultante; no depende de una consulta histórica de red.

| Etapa | Documento | Estado |
| --- | --- | --- |
| 1. Planificación | [`06-pg2-network-progression-planning.md`](06-pg2-network-progression-planning.md) | Completado |
| 2. Modelo de dominio | [`07-pg2-network-domain-model.md`](07-pg2-network-domain-model.md) | Completado |
| 3. Entregas verticales | [`08-pg2-network-vertical-deliveries.md`](08-pg2-network-vertical-deliveries.md) | Completado |
| 4. Modelo de datos | [`09-pg2-network-data-model.md`](09-pg2-network-data-model.md) | Completado |
| 5. Foundations | [`10-pg2-network-foundations.md`](10-pg2-network-foundations.md) | Implementada (PG2.1) |
| 6. Contribuciones | [`11-pg2-network-contributions.md`](11-pg2-network-contributions.md) | Implementada (PG2.2) |
| 7. Runs y placement | [`12-pg2-network-runs-placement.md`](12-pg2-network-runs-placement.md) | Implementada (PG2.4 y PG2.5) |
| 8. Cierre | [`13-pg2-network-closure.md`](13-pg2-network-closure.md) | Completado |
| 9. Operación y release | [`14-operations-release.md`](14-operations-release.md) | Implementado; pendiente de evidencia de despliegue |

## Decisiones confirmadas

- Los puntos no son dinero (`BR-POINTS-002`).
- Se reutiliza el catálogo `Rules` (`Rule`, `RuleVersion`, `RuleAssignment`);
  no hay catálogo paralelo de contribución.
- Como máximo una asignación activa `points_per_quantity_unit` por programa,
  módulo y métrica/unidad (`BR-POINTS-016`, `BR-RULE-016`).
- El total de la ventana suma contribuciones de métricas y módulos distintos;
  no ponderaciones duplicadas de la misma métrica (`BR-POINTS-017`).
- Solo aportan puntos los módulos habilitados por el plan y seleccionados por
  el programa vigente al ocurrir (`BR-POINTS-003`).
- El período de progresión es obligatorio en el plan (`daily` / `weekly` /
  `monthly`, UTC); cambios rigen desde la siguiente ventana
  (`BR-PLAN-018`, `BR-PLAN-019`).
- Ventanas fijas con reinicio de puntos; margen técnico global de una hora
  (`BR-POINTS-019`, `BR-POINTS-020`).
- Contexto de suscripción, placement y regla por instante de ocurrencia;
  distribución de red congelada por actividad y umbrales vigentes al run
  (`BR-POINTS-015`, `BR-POINTS-036`–`039`).
- Actividad tardía: evaluación durable sin puntos (`BR-POINTS-021`).
- Catálogo inicial de motivos de exclusión cerrado (BDS v0.6).
- Precisión decimal máxima de ocho decimales; sin redondeo silencioso
  (`BR-POINTS-007`).
- Sin conversión FX en PG1/PG2 (`BR-POINTS-030`); reversas fuera de alcance.
- Pausas: al reanudar se procesa solo si la ventana sigue abierta
  (`BR-POINTS-013`).
- Plan inactivo: Progression no consulta ni evalúa actividad, no crea
  contribuciones ni ejecuta runs; reactivar no recupera actividad previa
  (`BR-POINTS-028`).
- Retiro de módulo: efecto inmediato (`BR-POINTS-029`, `BR-PLAN-020`).
- Run por plan y ventana con resultado por suscripción; fallos parciales
  reintentables; run completado no se reabre (`BR-POINTS-023`,
  `BR-POINTS-027`).
- Placement objetivo directo, con salto de varios niveles; cero puntos
  resuelven el primer programa (`BR-POINTS-024`, `BR-POINTS-025`,
  `BR-PROGRAM-015`). Programs valida y persiste el umbral `0` del primer
  nivel; evidencia en
  [`Programs P2`](../programs/11-p2-implementation.md).
- Suscripción a mitad de ventana participa en el cierre actual
  (`BR-POINTS-026`).
- Administración consulta evaluaciones, contribuciones, runs y resultados;
  el cliente no (`BR-POINTS-031`).

## Fuera de PG1

- Ejecución de runs y mutación automática de placement (PG2).
- Reversas y corrección de contribuciones ya registradas.
- Conversión FX entre monedas.
- Scope instrumental distinto de `all` (Modules M2).
- Ventana móvil.
- Contratos públicos especulativos de Subscriptions, Rules o Modules sin el
  consumidor real de la vertical correspondiente.
- Kafka como vía principal de actividad: PG1 usa consulta pull-only vía M3.

## Referencias canónicas

- [`progression.bds.md`](../../bds/progression.bds.md)
- [`plans-and-subscriptions.bds.md`](../../bds/plans-and-subscriptions.bds.md)
- [`rewards.bds.md`](../../bds/rewards.bds.md)
- [`architecture.md`](../../rules/architecture.md)
- [`strategies.md`](../../rules/strategies.md)
- [`integrations.md`](../../rules/integrations.md)

## Evidencia del cierre local E2E

La regresión de actividad → contribución → cierre de ventana → placement verifica
configuración y suscripción locales, conservación del programa histórico y
idempotencia de evaluación, run y aplicación. No requiere refactor de PG1/PG2.
La evidencia conjunta está en el [plan E2E](../rewards/11-e2e-historical-pnl.md#entrega-de-cierre-local-e2e).
Broker/IAM reales y scheduler compartido se validarán después con ib-labs.

## Próximo paso

Validar en el entorno de despliegue el scheduler compartido, alertas sobre logs
agregables y la recuperación operativa documentada en MMTECH-240.
