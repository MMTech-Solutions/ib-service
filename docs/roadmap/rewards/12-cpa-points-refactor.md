# CPA por puntos — refactor del modelo vigente

Estado: implementación local completada; aceptación S2S pendiente.
Última revisión: 2026-10-06.

## Decisiones confirmadas

- Sustituir el modelo de requisitos en cantidades por dos umbrales de puntos independientes, ambos obligatorios.
- Política versionada cpa_fixed_amount: importe/moneda/precisión de pago, moneda/precisión de depósito independientes, tasa común de depósito y tasas por módulo para lotes.
- El IB beneficiario determina el programa al capturar; referido + IB sigue siendo la identidad única.
- La asociación histórica CPA pertenece al programa y se configura por HTTP sin asignación genérica a un módulo único.
- Los puntos se calculan al aceptar cada entrada; actividad real, tasa, puntos, proveedor e intervalo verificado permanecen auditables antes de calificar.
- No se requiere un historial del total por cada run. El progreso global se obtiene del ledger de contribuciones.
- Cada fuente confirma su propio corte; fuentes fallidas, inactivas o pausadas conservan aportes y no bloquean otras.
- Finance se consulta una sola vez por contexto; depósitos no se atribuyen a módulos.
- Una misma operación puede contribuir una vez por cada módulo; redeliveries de ese módulo no duplican puntos.
- Los proveedores, incluido Finance, garantizan inmutabilidad y no publican hechos anteriores a cortes confirmados. Contradicciones conocidas bloquean calificación.
- La Reward CPA carece de módulo único; settlement omite el control de módulos para CPA y conserva los del plan y Finance.

## Implementación e interfaces

Rules valida la nueva configuración, conserva asociaciones históricas, actor y retiro,
y resuelve contexto mediante repository/factory. La API administrativa es
GET/PUT/DELETE /api/ib/v1/admin/programs/{program}/cpa-configuration, con ib.rules.manage.
PUT selecciona rule_version_id publicado del mismo plan. GET devuelve la asociación
o un objeto con program_id y configured=false; DELETE devuelve 204.
Los endpoints genéricos de asignaciones rechazan CPA.

Modules separa volumen por módulo y depósitos certificados. Broker es el único
proveedor real de volumen de esta entrega; otros proveedores se prueban con dobles,
sin declarar capacidades inexistentes. Las selecciones CPA congelan símbolos/grupos.

Rewards persiste cpa_sources y cpa_contributions. Cada entrada conserva identidad,
cantidad real, unidad/moneda, tasa, puntos, ocurrencia y corte a microsegundos.
Constraints, bloqueo del contexto y transacciones impiden duplicar contribuciones
o Rewards en verificación concurrente. La asociación usa lock y unicidad activa.
Productos y sumas usan escala 16; cantidades/tasas de entrada admiten escala 8.
La respuesta de progreso expone ambos acumulados, depósito real y fuentes con
cantidades/puntos, estado y cortes independientes; cliente conserva aislamiento.

## Transición y alcance

No hay compatibilidad con el schema CPA anterior ni migración de datos antiguos.
Las migraciones se corrigen para una instalación nueva o recreación local explícita.
El refactor no ejecuta migrate:fresh sobre la base de aplicación.
El ledger genérico conserva historial financiero de nuevas instalaciones.
Progression, Rewards de volumen y PnL conservan sus cálculos y contratos.

La adaptación de ib-labs queda fuera de este repositorio: actualizar creación de
versiones CPA, nueva asociación administrativa, fixtures de suscripción del IB,
lecturas de puntos/fuentes y escenarios Finance/Broker. La aceptación S2S debe
verificar cortes definitivos y las operaciones financieras; dobles no la sustituyen.

## Evidencia local

Suite completa: 558 pruebas y 4.279 aserciones aprobadas. Verificación final posterior al formato: 13 pruebas y 126 aserciones de CPA y arquitectura aprobadas. Pint --dirty --format agent completado. Colección Postman v2.1 validada y contrastada con rutas y endpoints operativos. Grafo actualizado con graphify update .
