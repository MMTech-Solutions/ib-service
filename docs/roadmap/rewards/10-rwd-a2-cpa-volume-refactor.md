# RWD-A2 — refactor de CPA y volumen

Estado: **En curso; RWD-A2.1 y RWD-A2.2 completadas**
Última revisión: 2026-10-02.
Dependencia satisfecha: [RWD-A1](09-rwd-a1-documentation-alignment.md).
Bloquea: runner económico y cierre RWD4.2.

## Objetivo y estado observado

Alinear CPA/volumen con [Strategies](../../rules/strategies.md) sin cambiar negocio.
`VerifyCpaContextsUseCase` delega acumulación y calificación a
`CpaFixedAmountCalculationStrategy`, resuelta mediante factory específica.
`ProcessVolumeRewardsUseCase` delega a `TradedVolumeCommissionCalculationStrategy`
mediante su factory específica. Las factories de proveedores quedan pendientes.

La evidencia local acredita las factories económicas CPA y volumen, pero no
contratos S2S ni validación con datos reales.

## RWD-A2.1 completada: extracción CPA

Input y resultado internos e inmutables usan Laravel Data. La Strategy suma
volumen con BCMath a escala 8 y depósitos en minor units; exige ambos umbrales.
La factory admite únicamente `cpa_fixed_amount` y rechaza códigos desconocidos
con una excepción técnica propia. Rules conserva definición y schema publicados.

El UseCase conserva consulta de evidencia, corte, configuración congelada,
progreso, errores y persistencia. El incremental calificado se confirma con
evidencia completa desde captura y cantidades iniciales cero. No hay cambios
de migraciones, HTTP, Postman, scheduler, settlement ni proveedores.

Evidencia: `CpaRewardCalculationTest`, `VerifyCpaContextsUseCaseTest` y
`CpaRewardCalculationPersistenceTest` cubren bordes, factory, modos, fallos y
unicidad/contexto original tras cambios de programa y plan. Resultado local:
40 tests, 220 assertions aprobadas, incluidas regresiones CPA y arquitectura;
Pint aprobado y `graphify update .` completado (5479 nodos, 13021 relaciones).
Esta evidencia usa fixtures y dobles, no datos reales ni S2S.

## RWD-A2.2 completada: cálculo de volumen

La Strategy económica conserva la aritmética de la Action anterior, retirada:
base fija/porcentual, tasas, comparación con mínimo antes de redondear y redondeo
único a precisión de moneda. El DTO interno usa Laravel Data y propiedades
readonly; recibe el mínimo explícito leído por el UseCase con default `0.01`.
El resultado sigue siendo `PositiveMoney` o `null`, sin I/O en cálculo.

La factory admite únicamente `traded_volume_commission` y rechaza otros códigos
mediante una excepción técnica controlada por la suite de arquitectura. El
UseCase conserva contexto histórico, receipts, reintentos y clave económica
común entre evento/barrido. No cambia puertos ni schemas publicados.

Evidencia local del 2026-10-02: 61 tests y 321 assertions aprobadas de Rewards,
volumen, CPA y arquitectura; además 3 tests y 52 assertions de rates/contexto
histórico de suscripciones. Las pruebas cubren bordes, sustitución vía factory,
transmisión del mínimo y persistencia del importe retornado. Pint aprobado y
`graphify update .` completado (5494 nodos, 13062 relaciones).

Pendientes para completar A2: resolución de proveedores por factories
específicas. RWD4.2 permanece bloqueada; esta entrega no demuestra S2S.

## Trabajo de implementación posterior

- Completado en A2.1: extraer evaluación CPA a Strategy económica con input de contexto/requisitos
  congelados y evidencia normalizada; devolver cantidades y calificación tipadas.
  Resolver por factory propia. Progreso y creación transaccional permanecen en
  el UseCase; conservar evidencia incremental y reevaluación completa vigentes.
- Completado en A2.2: resolver cálculo de volumen por factory tipada, reutilizando
  la aritmética de la Action retirada. Conservar comisión fija/porcentual, participación, plantilla,
  rates, mínimo y redondeo existentes; retornar dinero o ausencia de Reward.
- Seleccionar proveedores mediante factories específicas de capacidad en el
  feature dueño: evidencia CPA/posiciones en Modules; conservar puerto y adapter
  PnL de Rewards sin implementar cálculo. Usar composición Laravel y mapas cerrados,
  sin selección por entorno ni nombres de clase aportados por JSON.
- Mantener puertos y Data públicos; no publicar una Strategy interna por
  conveniencia. Ninguna firma universal une CPA, posición y corte PnL.
  Factories de repositories permanecen independientes.
- Conservar captura CPA, barrido por módulo/rango y receipts de volumen por evento.
  Ambos canales convergen en procesamiento económico de la misma posición.
- Mantener identificadores de reglas, schemas publicados, claves idempotentes,
  snapshots, tablas, comandos, rutas, permisos y respuestas HTTP. No requiere
  migraciones, nuevas dependencias ni cambios del contrato Postman.
- No crear engine genérico, cadena de pasos ni cálculo PnL ficticio. Cada UseCase
  conserva contexto, elegibilidad, red, transacciones, idempotencia y reintentos.

## Garantías de equivalencia

- CPA conserva contexto ante cambio de programa/plan, requisitos acumulativos,
  filtros de moneda/símbolos y una sola Reward por adquisición.
- Volumen conserva contexto por instante de cierre, procesamiento tardío e
  identidad común de `event`, `periodic` y `both`.
- Factories retornan implementaciones tipadas, rechazan códigos desconocidos
  antes de efectos y permiten test doubles sin ramas por entorno.
- Strategies económicas no hacen I/O ni persisten; proveedores no deciden
  calificación, recompensas ni settlement.

## Fuera de alcance y pendientes E2E

No corregir silenciosamente comportamiento durante el refactor. Quedan para
entrega funcional independiente: filtros operativos/holds del claim de settlement,
coordinación con operaciones administrativas, nivel original en reversas y
congelación durable de red/configuración de volumen antes de efectos parciales.
Su aceptación requiere pruebas focalizadas; reorganizar clases no demuestra
esas garantías. El cierre E2E sigue subordinado a esta secuencia y a esos pendientes.

Corte histórico Broker–IB, runner PnL y validación financiera permanecen en
[RWD4](08-rwd4-volume-and-negative-pnl.md). Progression no se refactoriza aquí.

## Criterios de salida y evidencia requerida

Ejecutar tests existentes de captura/verificación CPA, procesamiento/cálculo de
volumen y contratos de repositories; añadir pruebas focalizadas de equivalencia,
factories, errores y bordes monetarios. Aprobar suite de arquitectura y comprobar
ausencia de I/O/persistencia en cálculo. Ejecutar Pint tras modificar PHP y
`graphify update .` tras modificar código.

Registrar resultados, fecha y cambios finales en este documento e índices; solo
entonces marcar RWD-A2 completada. Eso retira un bloqueo de RWD4.2, no el gate
independiente de evidencia S2S de Broker histórico e IAM.
