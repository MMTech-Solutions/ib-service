# Modules: agregados, estados y transacciones

Estado: **En descubrimiento**  
Dependencia satisfecha: `01-use-case-inventory.md` completado

## Decisiones de dominio ya confirmadas

- La semántica seleccionada por un programa se preserva mediante snapshot.
- La operatividad se consulta desde la relación viva con el registro del
  módulo; una pausa actual prevalece sobre el snapshot.
- El catálogo y el control operativo tienen responsabilidades diferentes.
- `is_active` representa disponibilidad total y `processing_status` representa
  `running` o `paused`.
- La pausa conserva ingesta y consultas de actividad; la inactividad detiene
  toda participación del módulo.
- La identidad y capacidades proceden de un registro cerrado respaldado por
  implementaciones, no de creación administrativa.
- No existe eliminación física ni lógica para módulos referenciados.
- Otros features no reciben modelos Eloquent ni entidades internas de
  `Modules`.
- La etapa modela únicamente el agregado interno requerido por M1. No define
  todavía puertos ni Data públicos para otros features.

## Hipótesis de agregados

Estas opciones deben evaluarse; aún no autorizan clases ni tablas.

| Candidato | Responsabilidad posible | Decisión pendiente |
| --- | --- | --- |
| `Module` | Identidad estable, disponibilidad, procesamiento, datos permitidos y capacidades vigentes. | Confirmar si las capacidades y el control pertenecen a su límite transaccional. |
| `ModuleCapability` | Vocabulario implementado que un programa puede copiar en su snapshot. | Confirmar identidad y tratamiento al desaparecer del registro técnico. |
| `OperationalControlChange` | Evidencia inmutable de activación, desactivación, pausa y reanudación. | Definir si es entidad de auditoría, evento de dominio o ambos. |

## Estados confirmados

| `is_active` | `processing_status` | Condición efectiva |
| --- | --- | --- |
| `false` | cualquiera | Inactivo: no selecciona, ingiere, consulta, calcula ni paga. |
| `true` | `running` | Activo y procesando normalmente. |
| `true` | `paused` | Activo y seleccionable; captura actividad, pero no calcula ni paga. |

La activación y desactivación no modifican `processing_status`. Reactivar un
módulo previamente pausado lo devuelve a la condición activa y pausada hasta
que exista una reanudación explícita.

## Decisiones pendientes de esta etapa

- Confirmar si `Module` es el único agregado de M1 o si el historial operativo
  requiere una frontera propia.
- Definir el comportamiento exacto de trabajos en curso ante desactivación o
  pausa y los puntos obligatorios de comprobación.
- Elegir la estrategia de concurrencia para cambios administrativos y sync.
- Definir si un módulo nuevo queda activo o inactivo tras el primer sync.
- Delimitar qué datos descriptivos son gobernados por código y cuáles por
  administración.
- Decidir si la auditoría y los eventos de cambio requieren outbox en M1.

## Límites transaccionales por validar

- Sincronizar la identidad del módulo y sus capacidades implementadas.
- Cambiar el catálogo sin modificar configuraciones publicadas.
- Aplicar un cambio operativo y registrar su auditoría de forma atómica.
- Evitar que dos cambios operativos concurrentes pierdan información.
- Publicar eventos solo después de confirmar la transacción local, si M1 los
  requiere.

El snapshot de programa pertenece al feature que publica la configuración del
programa y no a una transacción del catálogo de `Modules`.

La frontera pública de `Modules` no es una transacción de M1. Se diseñará con el
primer caso de uso de `Plans P1`, que aportará la necesidad consumidora.

## Invariantes que deberá expresar el modelo

- Un módulo mantiene una identidad estable entre proveedores y adaptadores.
- Una capacidad no se trata como disponible si no pertenece al módulo.
- Un elemento no seleccionable puede conservar referencias históricas.
- El control operativo vigente se evalúa en tiempo de ejecución.
- Cada cambio operativo es atribuible y auditable.
- La semántica histórica no cambia al editar el catálogo vivo.

## Resultado esperado de esta etapa

Un documento revisado con agregados definitivos, comandos aceptados, estados y
transiciones, invariantes por agregado, conflictos de concurrencia y fronteras
transaccionales para M1.
