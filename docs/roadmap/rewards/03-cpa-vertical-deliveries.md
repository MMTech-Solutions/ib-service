# RWD2: entregas verticales CPA

Estado: **RV1, RV2 y RV3 implementados; validación contractual pendiente**
Última revisión: 2026-10-02

## RV1 — captura y progreso

Consumir `auth.account.registered` V1, resolver el contexto vigente y persistir
el snapshot CPA de manera idempotente. Crear o mantener el único progreso en
`pending`; no consultar proveedores ni crear Rewards en esta entrega.

**Aceptación:** redelivery no duplica contexto; el snapshot no cambia con
ediciones posteriores; módulos inactivos o sin asociación CPA se resuelven con
resultado explícito y auditable.

## RV2 — evidencia Broker y Reward `pending`

Evaluar la regla `cpa_fixed_amount` con evidencia Broker. Modules/Broker obtiene hechos
de volumen de Broker Service y depósitos certificados de Finance; Rewards suma,
evalúa y actualiza progreso. La evaluación calificada crea una sola Reward
`pending` y escribe su id en el contexto.

**Aceptación:** paginación y rangos estables; moneda exacta; precisión decimal;
error reintentable no produce Reward; dos ejecuciones concurrentes no generan
dos obligaciones.

## RV3 — lectura de progreso

Publicar consulta cliente de los contextos pertenecientes al IB autenticado y
consulta administrativa paginada por IB, referido, programa, módulo y estado.
Las respuestas muestran cantidades agregadas, banderas, corte y estado, pero no
tokens, URLs internas, depósitos individuales ni payloads de proveedor.

**Aceptación:** `data` contiene directamente el recurso o colección; filtros y
paginación viven en `meta`; pruebas demuestran aislamiento de cliente y permiso
administrativo.

**Implementación:** cliente queda limitado por propiedad al IB del `UserContext`
y no recibe evidencia ni campos administrativos. Administración exige
`ib.rewards.manage`, puede filtrar por IB, referido, programa, módulo y estado,
y recibe `reward_id` y el error sanitizado. La paginación expone contadores, no
URLs.

## Fuera de RWD2

Solicitud de pago, settlement, reversa, compensación, notificaciones y cualquier
modalidad distinta de CPA con evidencia Broker y regla `cpa_fixed_amount`.

RWD-A1 sustituye la selección conjunta de proveedor/regla. RV1–RV3 conservan
su evidencia; las factories y la extracción del cálculo corresponden a
[RWD-A2](10-rwd-a2-cpa-volume-refactor.md), todavía no implementada.
