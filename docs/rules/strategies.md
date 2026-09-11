# Estrategias configurables

## Objetivo

Permitir que CPA, volumen, PnL, puntos de progresión y futuras modalidades reutilicen una misma orquestación sin codificar un pipeline independiente por módulo.

## Ubicación y separación obligatoria

- Los contratos viven en `Contracts/Strategies` y las implementaciones en `Services/Strategies`.
- Un **repository** obtiene hechos de una fuente persistente, remota o en memoria.
- Un **adapter** en `Services/Adapters` traduce los tipos de un SDK o API hacia DTOs propios.
- Una **strategy** interpreta inputs normalizados y una configuración validada.
- Una **rule version** conserva el tipo de strategy y su configuración inmutable.
- Una **assignment** determina plan, programa, módulo, vigencia y scope.
- El **pipeline** resuelve contexto, elegibilidad, idempotencia, auditoría y salida.
- La selección del repository y la selección de la strategy son decisiones independientes y utilizan factories separadas.

## Registry

Los tipos de strategy se resuelven mediante un registry explícito y cerrado. El JSON nunca puede contener nombres de clase, código ejecutable, consultas, credenciales ni endpoints arbitrarios.

Cada tipo registrado debe declarar:

- Identificador estable.
- Versión de esquema.
- JSON Schema de configuración.
- Capacidades requeridas.
- Tipo y unidad de cada input.
- Resultado posible.
- Reglas de idempotencia.

El registry o factory recibe contexto tipado. No inspecciona silenciosamente el entorno ni acepta nombres de clases provenientes de configuración de usuario.

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
- Los reintentos pertenecen al pipeline y no deben duplicar resultados.

## Pruebas

- Cada strategy se prueba contra una matriz de entradas, bordes, precisión y configuraciones inválidas.
- Los repositories en memoria implementan los mismos contratos que los persistentes o remotos.
- Los tests de una strategy usan DTOs normalizados y no dependen de respuestas de SDK.
- Los contract tests verifican que cada implementación de repository respeta la misma semántica observable.
