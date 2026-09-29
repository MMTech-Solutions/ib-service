# PG2: runs y placement sobre contribuciones congeladas

Estado: **Diseño completado; implementación pendiente**  
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

## Próximo paso

Ejecutar el cierre integral definido en `13-pg2-network-closure.md`.
