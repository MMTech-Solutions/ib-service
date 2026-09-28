# M2: planificación del catálogo de instrumentos para Progression

Estado: **Pendiente de implementación**
Consumidor real: `Progression PG2`
Última revisión: 2026-09-28

## Propósito

M2 habilita que Progression identifique símbolos e instrumentos normalizados por
módulo y asocie una plantilla de progresión a los símbolos elegibles, sin
exponer identificadores ni modelos internos del proveedor externo.

## Necesidad del consumidor

PG2 requiere, en `occurred_at`, una referencia de símbolo estable para:

- decidir si la actividad está habilitada para progresión;
- resolver la plantilla y versión de `weight` aplicable;
- conservar el símbolo en el snapshot de contribución.

No es válido sustituir esta capacidad por `metric_code`, una unidad o un nombre
comercial no normalizado.

## Contrato mínimo esperado

Modules publicará una capacidad versionada que permita listar o resolver
símbolos por módulo mediante identidades opacas y estables. La respuesta debe
incluir la referencia normalizada necesaria para que un consumidor conserve su
propia configuración, sin trasladar reglas de Progression a Modules.

## Criterios de salida

- Un adapter temporal hacia Broker obtiene símbolos por módulo y los traduce a
  identidades contractuales propias de Modules.
- La sustitución futura por Trading Account Service no cambia el contrato
  consumido por Progression.
- Progression puede asociar y consultar su configuración por símbolo sin
  importar tipos, SDKs o identificadores internos del proveedor.
- Hay pruebas contractuales del puerto y evidencia de una consulta real.

## Fuera de M2

- Plantillas, pesos, reglas, puntos, beneficiarios y placement.
- Resolver red de referidos o usuarios IB.

## Próximo paso

Inventariar el caso de uso de Modules y definir sus entregas verticales antes de
crear contratos o adapters.
