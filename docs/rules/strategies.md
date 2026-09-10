# Estrategias configurables

## Objetivo

Permitir que CPA, volumen, PnL, puntos de progresión y futuras modalidades reutilicen una misma orquestación sin codificar un pipeline independiente por módulo.

## Separación obligatoria

- Un **connector** obtiene o normaliza hechos de un módulo.
- Una **strategy** interpreta inputs normalizados y una configuración validada.
- Una **rule version** conserva el tipo de strategy y su configuración inmutable.
- Una **assignment** determina plan, programa, módulo, vigencia y scope.
- El **pipeline** resuelve contexto, elegibilidad, idempotencia, auditoría y salida.

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
