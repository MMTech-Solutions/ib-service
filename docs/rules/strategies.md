# Estrategias configurables

Estado: **regla obligatoria; alineación de implementación pendiente en RWD-A2**.

## Objetivo

Última revisión: 2026-10-02.

CPA, volumen, PnL y Progression conservan casos de uso propios. No se impone un
pipeline común, una cadena de pasos, un engine genérico por modalidad ni un DTO
universal con campos opcionales. Reutilizar garantías no exige la misma secuencia.

Esta regla sustituye la prescripción anterior. Es arquitectura objetivo:
[RWD-A2](../roadmap/rewards/10-rwd-a2-cpa-volume-refactor.md) conserva pendiente la
alineación de CPA/volumen; no incluye un refactor de Progression.

## Ubicación y separación obligatoria

- Los contratos viven en `Contracts/Strategies` y las implementaciones en `Services/Strategies`.
- Un **repository** obtiene hechos de una fuente persistente, remota o en memoria.
- Un **adapter** en `Services/Adapters` traduce los tipos de un SDK o API hacia DTOs propios.
- Una **strategy** interpreta inputs normalizados y una configuración validada.
- Una **rule version** conserva el tipo de strategy y su configuración inmutable.
- Una **assignment** determina plan, programa, módulo, vigencia y scope.
- El **UseCase** conserva contexto, elegibilidad, red cuando corresponda,
  transacciones locales, snapshots, idempotencia y reintentos. Settlement es
  independiente de la generación de recompensas.
- Rules define y valida configuraciones; Rewards ejecuta cálculos económicos.
  El feature propietario del puerto obtiene evidencia mediante proveedores y
  adapters: Modules conserva evidencia CPA/posiciones y Rewards su puerto PnL.
- Proveedores, repositories y cálculos se seleccionan mediante factories
  independientes en `Factories`, con `make(...)` y mapas cerrados. La factory
  retorna una implementación tipada; no ejecuta el proceso ni contiene negocio.
  Un código desconocido produce excepción tipada antes de efectos.
- Una interfaz de proveedor solo se reutiliza entre implementaciones de la misma
  capacidad. Evidencia CPA, posiciones y cortes PnL no se fuerzan bajo una firma.
  Compartir objetos económicos exige igualdad de semántica; cada modalidad tiene
  inputs/resultados específicos, internos salvo publicación inter-feature real.

## Registry

Rules conserva su registry explícito y cerrado de definiciones y schemas; no es
una factory de ejecución económica. El JSON nunca contiene nombres de clase,
código ejecutable, consultas, credenciales ni endpoints arbitrarios.

Los códigos de modalidad `cpa`, `volume`, `pnl` no sustituyen los tipos publicados
`cpa_fixed_amount`, `traded_volume_commission`, `negative_pnl_share` ni
`points_per_quantity_unit`. Las factories resuelven dentro de la familia tipada
del consumidor por código de proveedor/capacidad o tipo de regla correspondiente.

Cada tipo registrado debe declarar:

- Identificador estable.
- Versión de esquema.
- JSON Schema de configuración.
- Capacidades requeridas.
- Tipo y unidad de cada input.
- Resultado posible.
- Reglas de idempotencia.

El registry o factory recibe contexto tipado. No inspecciona silenciosamente el entorno ni acepta nombres de clases provenientes de configuración de usuario.

## Estrategias de ejecución de Rewards

La definición/validación de Rules es distinta del cálculo de Rewards. Las
Strategies económicas se resuelven por factories específicas y reciben inputs
normalizados y configuración congelada; no conocen HTTP, SDKs ni persistencia.
No se exige un registry universal de ejecución por `module_code + strategy_type`.
La selección de fuente permanece separada del tipo de regla.

Para CPA, la strategy evalúa requisitos acumulativos y puede producir una decisión tipada de `pending`, `qualified` o error recuperable. La creación idempotente de la Reward y la actualización del progreso pertenecen al caso de uso de Rewards, no a la strategy ni al proveedor de evidencia.

Para volumen, evento y barrido convergen en procesamiento de posición: el UseCase
resuelve red y contexto histórico; la Strategy calcula con configuración congelada.
Para PnL, programas con período vencido originan selección de beneficiarios,
referidos y cuentas; el UseCase obtiene cortes Broker, congela contexto/red y
recupera cierres históricos pendientes. La Strategy interpreta el PnL firmado.
Ninguna Strategy económica llama a IAM, Broker o Finance ni persiste Rewards.
Los proveedores seleccionan y normalizan hechos, no deciden elegibilidad ni pagos.
No se compensan datos económicos ausentes mediante defaults.

## Configuración

Toda configuración debe usar una unión discriminada:

```json
{
  "strategy_type": "points_per_quantity_unit",
  "schema_version": 1,
  "configuration": {
    "unit": "lot",
    "points_per_unit": "50.00"
  }
}
```

Las cantidades decimales se transportan como strings canónicos. Una configuración se valida al guardarse y nuevamente antes de publicarse.

## Versionado

- Una versión publicada no se modifica.
- Cambiar parámetros o schema crea otra versión.
- Las asignaciones seleccionan deliberadamente la versión que utilizan.
- Rewards y contribuciones conservan el identificador de la versión aplicada.
- No se permiten overrides libres en asignaciones; una diferencia económica requiere otra versión o regla explícita.

## Fallos

- Una strategy no realiza efectos financieros directamente.
- Una evaluación inválida produce un resultado tipado o una excepción de dominio, no un reward parcial.
- Los conectores distinguen errores recuperables, datos no elegibles y contratos inválidos.
- Los reintentos pertenecen al UseCase y no deben duplicar resultados.

## Pruebas

- Cada strategy se prueba contra una matriz de entradas, bordes, precisión y configuraciones inválidas.
- Los repositories en memoria implementan los mismos contratos que los persistentes o remotos.
- Los tests de una strategy usan DTOs normalizados y no dependen de respuestas de SDK.
- Los contract tests verifican que cada implementación de repository respeta la misma semántica observable.
- Las factories permiten sustitución controlada sin ramas por entorno; las
  implementaciones de un proveedor cumplen el mismo contrato de capacidad.
- Pruebas locales no sustituyen evidencia S2S ni prueban una arquitectura futura.
