# Configuraciones globales IB — BDS

- **Versión:** 1.0
- **Estado:** vigente
- **Última revisión:** 2026-10-07

## Contexto y propósito

IB permite personalizar sus parámetros globales de negocio y conexión a proveedores.
El catálogo controla qué opciones existen; administración controla sus valores.
Estas opciones no sustituyen planes, programas, reglas ni versiones publicadas.

## Glosario y relaciones

- **Configuración:** opción identificada por una clave estable y agrupada por dominio.
- **Definición:** nombre, descripción, tipo, restricciones y sensibilidad de una opción.
- **Valor de respaldo:** valor establecido por la operación del servicio.
- **Valor guardado:** valor que prevalece sobre el respaldo, incluso si es nulo cuando está permitido.
- **Restablecimiento:** intención de volver a utilizar el respaldo vigente.
- **Certificación de conexión:** comprobación puntual de disponibilidad y aceptación de credenciales de un proveedor; no garantiza disponibilidad futura.

Una definición admite un único estado global de valor. Modules agrupa conexiones por
proveedor. Una configuración no pertenece a una suscripción ni a un programa.

## Reglas

| ID | Regla |
| --- | --- |
| BR-SETTINGS-001 | Solo el catálogo determina las claves disponibles y sus definiciones; administración no crea claves ni altera definiciones. |
| BR-SETTINGS-002 | Las opciones se agrupan por dominio; las conexiones de módulos se identifican además por proveedor. |
| BR-SETTINGS-003 | Un valor guardado prevalece sobre el respaldo. Ausencia de personalización o restablecimiento utiliza el respaldo vigente. |
| BR-SETTINGS-004 | Una opción incorporada copia inicialmente el respaldo vigente como valor guardado. |
| BR-SETTINGS-005 | Reconciliar el catálogo conserva el valor guardado o el estado de restablecimiento; actualiza definiciones y retira opciones ausentes. |
| BR-SETTINGS-006 | Una definición incompatible con un valor vigente impide aplicar la reconciliación completa. |
| BR-SETTINGS-007 | Los valores deben satisfacer el tipo y restricciones de su definición sin conversiones silenciosas. |
| BR-SETTINGS-008 | Los secretos solo pueden reemplazarse o restablecerse con autorización específica; nunca se revelan en consultas ni historial. |
| BR-SETTINGS-009 | Un cambio se aplica a operaciones nuevas y no altera decisiones históricas ni entradas ya congeladas. |
| BR-SETTINGS-010 | Certificar exige disponibilidad del proveedor y credenciales aceptadas. No modifica la disponibilidad ni el estado operativo del módulo. |
| BR-SETTINGS-011 | Certificar utiliza valores vigentes; no admite configuraciones candidatas ni persiste una garantía permanente. |
| BR-SETTINGS-012 | Cambios administrativos conservan actor, motivo, instante y evidencia anterior/posterior sin revelar secretos. |

## Estados y transiciones

Una opción comienza con valor guardado. Personalizar reemplaza ese valor.
Restablecer pasa al uso del respaldo; personalizar nuevamente vuelve a valor guardado.
Reconciliar conserva ese estado salvo que retire la opción del catálogo.

No hay cálculos económicos nuevos ni eventos de integración publicados en esta entrega.
El historial registra incorporación, modificación, restablecimiento y retiro.

## Decisiones pendientes

La retención del historial debe cerrarse antes de producción. La aceptación integrada
de certificación depende de la disponibilidad del contrato de salud en cada proveedor.
