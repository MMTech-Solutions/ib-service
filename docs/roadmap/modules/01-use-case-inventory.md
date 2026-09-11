# Modules: inventario de casos de uso

Estado: **Completado**  
Última revisión: 2026-09-11

## Propósito

Este inventario nombra intenciones de negocio y resultados observables. Los
nombres son descriptivos y provisionales: no obligan todavía a crear una clase,
un endpoint o una transacción con el mismo nombre.

## Actores

- **Administrador de IB:** consulta el catálogo, modifica datos permitidos y
  activa o desactiva módulos.
- **Operaciones:** pausa o reanuda el procesamiento de un módulo ante riesgo
  operativo.
- **Despliegue:** reconcilia mediante un comando idempotente las definiciones
  respaldadas por código con el catálogo persistido.
- **Feature consumidor:** consulta capacidades mediante puertos de entrada sin
  conocer la implementación interna de `Modules`.
- **Proceso interno:** resuelve una fuente y obtiene actividad normalizada para
  progresión o recompensas.

## Inventario inicial

| Área | Intención | Resultado observable | Entrega candidata | Estado |
| --- | --- | --- | --- | --- |
| Catálogo | Sincronizar módulos desde código | Las definiciones implementadas se reconcilian idempotentemente con el catálogo persistido. | M1 | Aceptado |
| Catálogo | Consultar módulos | Se listan módulos con disponibilidad, procesamiento y capacidades. | M1 | Aceptado |
| Catálogo | Consultar un módulo | Se obtiene su identidad, semántica, capacidades y condición actual. | M1 | Aceptado |
| Catálogo | Cambiar datos administrativos permitidos | El dato vigente cambia sin alterar la identidad técnica ni snapshots publicados. | M1 | Aceptado |
| Catálogo | Activar un módulo | Un módulo vuelve a ser seleccionable y puede operar según su `processing_status`. | M1 | Aceptado |
| Catálogo | Desactivar un módulo | Se detienen selección, ingesta, consultas externas, cálculos y pagos sin eliminar historial. | M1 | Aceptado |
| Capacidades | Sincronizar capacidades implementadas | El catálogo refleja el vocabulario respaldado por las implementaciones del módulo. | M1 | Aceptado |
| Operación | Pausar el procesamiento de un módulo | Se detienen cálculos y pagos mientras continúan ingesta y consultas de actividad. | M1 | Aceptado |
| Operación | Reanudar el procesamiento | Los cálculos y pagos futuros vuelven a admitirse de forma auditable. | M1 | Aceptado |
| Operación | Consultar historial de control | Se conoce quién cambió qué, cuándo y por qué. | M1 | Aceptado |
| Operación | Rechazar actividad de un módulo inactivo | No se ingiere actividad de dominio y queda evidencia técnica del rechazo. | M3 | Diferido: BR-MODULE-015 vigente; verificación ejecutable con el primer consumidor de actividad |
| Consumo | Obtener módulos seleccionables para un plan | `Plans` recibe solo los objetos contractuales exigidos por su primer caso de uso. | Plans P1 | Pospuesto al consumidor |
| Consumo | Verificar la condición de un módulo | El consumidor real obtiene la condición necesaria antes de actuar. | Entrega del consumidor | Pospuesto al consumidor |
| Fuentes | Consultar símbolos admitidos por una fuente | Se obtienen instrumentos normalizados sin revelar el proveedor. | M2 | Pendiente de consumidor |
| Fuentes | Consultar actividad normalizada | Se obtienen contribuciones o hechos utilizables por otros features. | M3 | Pendiente de consumidor |
| Fuentes | Cambiar el proveedor de una capacidad | El consumidor conserva su puerto aunque cambie Broker por Trading Account Service. | M4 | Pendiente de adapter |

`M1` a `M4` son entregas propias de Modules definidas en
[`03-vertical-deliveries.md`](03-vertical-deliveries.md).
`Plans P1` pertenece al roadmap de [`Plans`](../plans/README.md).

## Decisiones cerradas

1. La disponibilidad se representa con `is_active`; no se necesita un enum de
   ciclo de vida.
2. El código es una identidad funcional estable, no el código PHP. Las clases
   pueden cambiar detrás de mapas controlados.
3. Las capacidades son vocabulario respaldado por implementaciones y no se
   conceden desde administración.
4. El botón de pánico opera sobre el módulo completo mediante
   `processing_status`, inicialmente `running` o `paused`.
5. La pausa mantiene ingesta y consultas de actividad, pero detiene cálculos y
   pagos. La inactividad detiene todas esas operaciones.
6. La auditoría persiste el identificador de IAM del actor y el motivo. Nombre
   y correo se consultan para reportes o se copian solo en eventos que los
   requieran.
7. Los módulos referenciados nunca se eliminan física ni lógicamente; se
   desactivan.
8. M1 expone lista, detalle, modificación administrativa permitida,
   activación/desactivación, pausa/reanudación e historial. Las capacidades se
   incluyen en lista y detalle, sin endpoint exclusivo.

El efecto sobre trabajos que ya estaban ejecutándose se define en la etapa 2 y
no cambia el alcance del inventario.

## Criterios de salida

- Cada intención de M1 fue aceptada o movida explícitamente.
- Se acordaron actores y resultados observables de M1.
- Las preguntas del inventario fueron cerradas y las decisiones técnicas
  derivadas se registraron en `docs/rules/`.
- El alcance aprobado permite continuar con agregados y límites
  transaccionales.
