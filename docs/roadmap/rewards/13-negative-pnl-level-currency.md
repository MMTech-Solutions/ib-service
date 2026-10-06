# N-PnL por nivel y moneda

Estado: completada localmente; S2S y datos reales pendientes. Última revisión: 2026-10-06.

## Entrega

Broker recibe `subjects` y corte común y devuelve cuentas planas más usuarios
completados. IB congela red y evidencia, agrega ganancias y pérdidas por nivel y
moneda y genera una única Reward pending por total negativo. La selección de
regla/plantilla es común por módulo. Evidencia y outcomes permiten reconstruir
cada importe. Mínimo y redondeo se aplican una vez al agregado.

Decisión consciente: precisión única por moneda; USD/3 junto a USD/2 no está
contemplado. Contradicciones impiden cálculo/avance de baseline. No se modifica
la forma de obtener PnL por cuenta.

## Contratos y actualización

[Contrato y procedimiento operativo](../../rules/negative-pnl-contract.md).
Cambio incompatible sin cálculo dual. Se preservan obligaciones financieras
históricas. Migración protegida con consolidación de selecciones, trabajos y
baselines, conservando cursores para evitar pagos repetidos.

## Evidencia

- IB: 121 pruebas / 1233 assertions aprobadas de configuración, migración,
  runner, compensación por nivel/moneda, precisión contradictoria, lotes,
  recuperación, concurrencia, auditoría, settlement y arquitectura.
- Broker: 16 pruebas / 103 assertions aprobadas del endpoint y arquitectura.
- Pint completado en ambos repositorios. Graphify actualizado: IB 6702 nodos /
  16488 relaciones; Broker 14113 nodos / 36739 relaciones.
- Postman v2.1 válido en ambos servicios y request PnL de Broker contrastada
  con route:list. La colección IB cubre rutas propias y /up.
- Caso de aceptación: -1000 + 600 - 200 genera una única Reward pending
  de 60 USD con tres contribuciones y sus evidencias.

S2S Broker/IAM/Finance y datos reales pendientes; esta entrega no acredita
esos escenarios. No se ejecutaron migraciones ni despliegue en producción.
