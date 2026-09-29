# PG2: cierre documental y gate de implementación

Estado: **PG2 completado**
Última revisión: 2026-09-29

## Decisiones cerradas

- La cadena ascendente del referido es única e inmutable.
- La distribución se resuelve una vez y se congela localmente por actividad.
- Respuesta vacía satisfactoria es final; error técnico es reintentable.
- Reintentos y runs nunca consultan IAM cuando existe distribución final.
- `occurred_at` continúa gobernando configuración, suscripción, placement y
  reglas; no gobierna la resolución de upline.

## Matriz de evidencia para la implementación

| Escenario | Resultado esperado |
| --- | --- |
| Varios uplines | Un destinatario congelado por IB y nivel; contribuciones independientes. |
| Upline vacío | Distribución final vacía sin contribuciones. |
| Error IAM | Sin distribución final y actividad reintentable. |
| Reintento posterior al snapshot | Reutiliza destinatarios sin llamada IAM. |
| Reintento concurrente | Una distribución canónica, sin duplicados. |
| Run | Suma contribuciones locales y no llama IAM. |

## Evidencia PG2.4

- El comando `progression:close-windows` es la entrada operativa y tiene un
  schedule fallback local cada cinco minutos.
- PostgreSQL impone idempotencia por plan/ventana y por run/suscripción.
- Los resultados finales son inmutables; los fallidos se reintentan sin
  revertir resultados de otras suscripciones ni mover placement.

## Evidencia PG2.5

- PG2.5 aplica una sola vez cada resultado `completed` final con programa
  objetivo mediante un ledger PostgreSQL transaccional; conserva la auditoría
  de Subscriptions y respeta toda fijación vigente.

## Gate

PG2 está cerrado. No se requieren cambios de SDK ni un endpoint histórico de
IAM. Adoptar una futura capacidad histórica requerirá revisar el BDS y decidir
explícitamente si afecta actividades nuevas, correcciones o migraciones.
