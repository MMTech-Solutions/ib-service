# PG2: modelo de dominio de progresión por red interna

Estado: **Completada**
Dependencias de implementación: `Modules M2` y contrato histórico de red de
referidos de `auth-service`
Última revisión: 2026-09-28

## Objetivo

Definir el modelo de dominio para convertir una actividad de un referido en
contribuciones de puntos auditables para los IB beneficiarios de su red, sin
alterar los límites de propiedad de Modules, Plans, Programs, Rules,
Subscriptions ni Auth.

## Agregados y responsabilidades

| Concepto | Propietario | Responsabilidad |
| --- | --- | --- |
| Red y nivel de distribución | auth-service | Resuelve históricamente los IB beneficiarios de un referido en `occurred_at`. |
| Símbolo e instrumento | Modules | Publica una identidad normalizada; no calcula puntos ni decide beneficiarios. |
| Plantilla de progresión | Progression | Conserva niveles, `weight` y versiones inmutables; no contiene datos de pago. |
| Configuración de símbolo para progresión | Progression | Determina qué símbolos son elegibles y qué versión de plantilla aplica. |
| Evaluación y contribución | Progression | Decide elegibilidad por beneficiario y guarda el snapshot de cálculo. |
| Plan, programa y placement | Plans, Programs y Subscriptions | Aportan el contexto del IB beneficiario vigente en `occurred_at`. |

## Invariantes

- El referido fuente nunca es beneficiario de su propia actividad.
- Un IB puede recibir una contribución por cada nivel de red alcanzable, nunca
  dos para la misma combinación idempotente.
- Un nivel sin `weight` configurado se excluye sin puntos.
- La plantilla y su versión se determinan por el símbolo elegible vigente en
  `occurred_at`; cambios posteriores no alteran contribuciones existentes.
- La consulta de red usa historia de Auth y no reconstruye el pasado desde la
  topología actual.
- La actividad previa a la activación de esta capacidad no se reprocesa.

## Flujo de decisión

```text
Actividad normalizada de referido
  → símbolo elegible y plantilla vigentes
  → red histórica: IB beneficiario + nivel
  → suscripción/placement/programa del beneficiario
  → regla aplicable y weight del nivel
  → evaluación y contribución snapshot
  → run de la ventana del beneficiario
```

## Límites transaccionales

- Auth y Modules se consultan fuera de la transacción local.
- La evaluación y todas las contribuciones derivadas que se persistan para una
  actividad se coordinan localmente y resuelven colisiones mediante la
  restricción idempotente autoritativa.
- Un fallo para un IB beneficiario no invalida las contribuciones ya finales de
  los demás; queda reintentable con evidencia propia.

## Criterio de salida

- El BDS v0.7 representa todas las invariantes anteriores.
- Propiedad de cada concepto y frontera interservicio no son ambiguas.
- La siguiente fase puede definir entregas verticales sin elegir estructuras de
  persistencia o APIs definitivas.

## Evidencia

- `docs/bds/progression.bds.md` v0.7.
- `06-pg2-network-progression-planning.md` completada el 2026-09-28.

## Próximo paso

Crear `08-pg2-network-vertical-deliveries.md` cuando `Modules M2` y el contrato
histórico de Auth tengan una fecha de disponibilidad o una evidencia equivalente.
