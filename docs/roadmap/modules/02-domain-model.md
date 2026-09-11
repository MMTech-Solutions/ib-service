# Modules: agregados, estados y transacciones

Estado: **Completado para M1**
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

## Agregados confirmados

| Concepto | Responsabilidad | Decisión |
| --- | --- | --- |
| `Module` | Identidad estable, disponibilidad, procesamiento, datos administrativos permitidos y capacidades vigentes. | Único agregado. Capacidades e historial operativo pertenecen a su límite transaccional. |
| `ModuleCapability` | Vocabulario implementado que un programa puede copiar en su snapshot. | Hijo del agregado. Una capacidad ausente del registro queda inactiva y se reactiva al reaparecer. |
| `OperationalControlChange` | Evidencia inmutable de activación, desactivación, pausa y reanudación. | Hijo inmutable del agregado. No es un evento de integración ni sustituye a un outbox. |

## Estados confirmados

| `is_active` | `processing_status` | Condición efectiva |
| --- | --- | --- |
| `false` | cualquiera | Inactivo: no selecciona, ingiere, consulta, calcula ni paga. |
| `true` | `running` | Activo y procesando normalmente. |
| `true` | `paused` | Activo y seleccionable; captura actividad, pero no calcula ni paga. |

`is_active` y `processing_status` son ortogonales. La activación y
desactivación no modifican `processing_status`. Reactivar un módulo
previamente pausado lo devuelve a la condición activa y pausada hasta que
exista una reanudación explícita. Pausar o reanudar un módulo inactivo
actualiza solo el procesamiento.

Las cuatro operaciones (`activate`, `deactivate`, `pause`, `resume`) exigen
motivo y son idempotentes: un estado ya alcanzado no genera una segunda
entrada de historial ni incrementa `lock_version`.

## Decisiones cerradas para M1

- `Module` es el único agregado; capacidades e historial operativo pertenecen
  a su límite transaccional.
- Los trabajos validan el estado al comenzar y no se cancelan si este cambia
  después.
- La concurrencia es optimista mediante `lock_version` técnico.
- Un módulo nuevo nace activo y en `running`.
- Nombre y descripción del módulo son administrativos después de crearse; los
  descriptivos de capacidades son gobernados por código.
- Una capacidad ausente queda inactiva y se reactiva al reaparecer.
- M1 no publica eventos ni incorpora outbox.
- El historial operativo registra acción, identificador IAM del actor, motivo,
  instante y estados anterior y posterior de disponibilidad y procesamiento.
- La edición administrativa de nombre y descripción no genera historial
  operativo.
- La desactivación originada por `modules:sync` no genera historial operativo.
- BR-MODULE-015 permanece vigente, pero su verificación ejecutable espera a
  M3, cuando exista un consumidor real de actividad.

## Límites transaccionales confirmados para M1

- La sincronización completa de identidades y capacidades es atómica.
- Una escritura compara `lock_version`; un valor obsoleto aborta la
  transacción.
- El sync no modifica configuraciones publicadas ni datos administrativos.
- M1 no publica eventos.
- Un cambio operativo y su entrada de historial se persisten en la misma
  transacción. Un no-op no escribe historial ni incrementa `lock_version`.

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
