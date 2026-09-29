# PG2: runs y placement sobre contribuciones congeladas

Estado: **PG2.4 implementado; PG2.5 pendiente**
Última revisión: 2026-09-29

## Responsabilidad

Un run cierra una ventana de un plan después del margen técnico, suma las
contribuciones válidas ya persistidas para cada suscripción y determina el
programa objetivo con el ladder vigente al ejecutar el run.

## Reglas operativas

- El run no consulta IAM, no resuelve uplines y no crea destinatarios.
- Una suscripción recibe un resultado independiente: completado, omitido o
  fallido reintentable.
- El resultado usa el programa de mayor posición cuyo umbral se cumple; cero
  puntos resuelven el primer programa.
- Un run completado no se reabre; solo sus resultados fallidos se reintentan.

## Evidencia futura

Las pruebas deben demostrar que cambiar la respuesta de IAM después de congelar
una distribución no modifica contribuciones ni resultados de run.

## Evidencia PG2.4

- `progression:close-windows` cierra ventanas vencidas tras el margen global de
  una hora y se ejecuta como fallback cada cinco minutos.
- Los runs y resultados tienen claves únicas PostgreSQL y solo reintentan
  resultados fallidos; no consultan IAM ni reconstruyen distribuciones.
- El resultado conserva puntos y programa objetivo; no modifica placement.

## Próximo paso

Implementar PG2.5 para aplicar el cambio de placement a partir de resultados
finales, sin reabrir runs.
