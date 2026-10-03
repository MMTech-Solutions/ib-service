# RWD-A1 — alineación documental de Rewards

Estado: **Completada (entrega exclusivamente documental)**
Última revisión: 2026-10-03.
Dependencias: decisiones de arquitectura confirmadas; BDS y reglas vigentes.

## Decisión y alcance

Se retira la obligación de pipeline común. Cada modalidad tiene orquestación
propia y conserva elegibilidad, identidad económica, auditoría y configuración.
Proveedores y cálculos se seleccionan mediante factories separadas; el UseCase
conserva decisiones y efectos locales. Settlement permanece independiente.

No se modifica PHP, migraciones, rutas, Postman ni grafo. No se altera el
comportamiento económico de CPA/volumen ni se declara implementada una factory.
La alineación de código pertenece a [RWD-A2](10-rwd-a2-cpa-volume-refactor.md).

## Evidencia documental

- [BDS Rewards](../../bds/rewards.bds.md): contexto y BR-REWARD-001/002/017 expresan
  garantías de negocio, sin exigir pipeline ni I/O desde una Strategy.
- [Strategies](../../rules/strategies.md) y [Architecture](../../rules/architecture.md):
  propiedad, factories, contratos específicos y orquestación independiente.
- Documentos RWD1/RWD2/RWD4: prescripciones anteriores sustituidas, evidencia
  histórica conservada y diferencias con código actual explícitas.
- [RWD4](08-rwd4-volume-and-negative-pnl.md): diseño PnL y dependencia de corte
  histórico Broker–IB, sin afirmar que ese corte esté implementado.
- Índices de Rewards, roadmap general y documentación sincronizados.

## Secuencia y criterios de avance

La siguiente secuencia registra el estado al cerrar A1. Posteriormente,
[RWD-A2](10-rwd-a2-cpa-volume-refactor.md) se completó en A2.1–A2.3; RWD4.2
conserva los gates pendientes de corte histórico e IAM.

Actualización RWD4.2.1 (2026-10-03): por decisión del usuario, los gates S2S
que siguen en la secuencia histórica se aplican a activación/cierre E2E,
no al desarrollo de código. Configuración/cálculo PnL están implementados localmente.

1. RWD-A1 completada: fuentes coherentes y enlaces verificables.
2. RWD-A2 lista: refactor sin cambios funcionales, con equivalencia y arquitectura
   aprobadas antes de declararlo completado.
3. Corte histórico Broker–IB pendiente: demostrar balance y cashflow local para
   el mismo corte UTC solicitado, independiente del antiguo IB de Broker.
4. RWD4.2 bloqueada: requiere RWD-A2 y evidencia S2S de cortes históricos e IAM.

RWD4.2a conserva su estado local y evidencia externa pendiente. Validaciones
CPA/RWD3 y correcciones funcionales del cierre E2E siguen separadas.

## Verificación documental

Buscar pipeline/registry/Strategy/factory en BDS, reglas y roadmap; conservar el
registry de definiciones Rules y retirar exigencias de ejecución común. Revisar
enlaces, fechas, estados e índices. No corresponde Pint, pruebas PHP ni actualización
Graphify para esta entrega exclusivamente documental.
