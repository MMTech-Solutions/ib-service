# PG2: distribución y contribuciones por red

Estado: **Implementada (PG2.2)**  
Última revisión: 2026-09-29

## Flujo

1. Identificar una actividad elegible y su referido fuente.
2. Reutilizar su distribución final, o resolver el upline vigente y finalizar
   la distribución local.
3. Para cada destinatario congelado, resolver el contexto histórico restante
   en `occurred_at` y evaluar la contribución.
4. Persistir cada evaluación y contribución con su destinatario, nivel y
   configuración aplicada.

## Idempotencia y fallos

- El mismo destinatario y nivel no se duplican para una actividad.
- Una contribución final no se recalcula por reintentos ni cambios posteriores
  de red, plantilla o regla.
- El fallo de un destinatario se registra como reintentable sin invalidar las
  contribuciones finales de los demás.
- Los runs consumen contribuciones, nunca actividades pendientes de resolver.

## Evidencia de implementación

- `EvaluateProgressionActivitiesUseCase` reutiliza o finaliza primero la distribución local y nunca consulta IAM tras existir snapshot.
- Cada beneficiario y nivel se evalúa de forma independiente con el contexto histórico de suscripción, plan, programa, regla y configuración de símbolo.
- Las contribuciones conservan el peso de distribución, plantilla/versión y referencia de configuración; los fallos IAM o por beneficiario se devuelven reintentables sin deshacer resultados finales.
- Evidencia: `EvaluateProgressionActivitiesUseCaseTest`, contract tests de evaluaciones y migración `2026_09_29_154331`.

## Precedente para Rewards

Rewards debe congelar causa y beneficiario antes de solicitar settlement. Este
documento no introduce Rewards ni cambia su BDS.

## Próximo paso

Implementar el cierre de ventana y resultados descritos en
`12-pg2-network-runs-placement.md`.
