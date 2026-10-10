# Historial administrativo de suscripciones V1

Estado: **contrato obligatorio, implementado localmente**. Revisión: 2026-10-10.

Estas lecturas exponen la historia existente de Subscriptions conforme a
BR-SUBSCRIPTION-010, 011, 014 y 021 y BR-POINTS-043. No crean otra historia,
reconstruyen movimientos desde el placement actual ni modifican elegibilidad,
Progression, Rewards o settlement. La aceptación integrada LAB5 permanece pendiente.

## Acceso y rutas

| Método | Ruta | Permiso y surface |
| --- | --- | --- |
| GET | `/api/ib/v1/admin/subscriptions/{subscription}/changes` | `ib.subscriptions.manage`, `admin_panel` |
| GET | `/api/ib/v1/admin/subscriptions/{subscription}/placements` | `ib.subscriptions.manage`, `admin_panel` |

Gateway exige `X-Internal-Gateway` y `X-Userinfo`; Postman utiliza
`{{GATEWAY_INTERNAL_SECRET}}` y `{{ADMIN_USERINFO}}`. Los UUID y secretos de
ejemplo son variables o identidades sintéticas, nunca credenciales reales.
No existe una ruta customer equivalente. La respuesta de suscripción abierta
del cliente no incorpora logs. El detalle administrativo conserva su colección
`changes` y su contrato anterior, sin añadirle referencias de Progression.

Administración consulta `pending`, `active`, `rejected` y `ended`. La autorización
vigente es administrativa global, sin inventar un filtro nuevo de propietario o
tenant. Cada consulta está restringida a la suscripción del path; un filtro no
puede incorporar registros de otra suscripción.

## Parámetros

`subscription` es UUID obligatorio. `page` es entero >= 1, por defecto 1.
`per_page` es entero entre 1 y 100, por defecto 100. Una página sin resultados
devuelve `data: []`, incluso cuando excede el número de páginas.

| Consulta | Filtro | Validación y efecto |
| --- | --- | --- |
| Changes | `action` | Enum vigente de acciones de suscripción |
| Changes | `actor_kind` | `iam` o `system` |
| Changes | `occurred_at_from` | Fecha UTC Z, límite inclusivo |
| Changes | `occurred_at_to` | Fecha UTC Z, límite exclusivo; posterior a from cuando vienen ambos |
| Placements | `program_id` | UUID; sin coincidencias devuelve colección vacía |
| Placements | `is_fixed` | Booleano Laravel: true/false en JSON, 0/1 en query string |
| Placements | `overlap_from` | Fecha UTC Z; exige overlap_until |
| Placements | `overlap_until` | Fecha UTC Z posterior a overlap_from; exige ambos extremos |

Las fechas deben existir realmente y terminar en `Z`; se admiten fracciones de
segundo sin truncar los extremos del filtro. Los filtros se combinan con AND.
Acciones vigentes: `request`, `approve`, `reject`, `cancel`, `change_plan_out`,
`change_plan_in`, `change_program`, `fix_placement`, `release_placement`,
`progression_placement` y `update_reward_rates`.

Orden fijo ascendente: changes por `occurred_at, id`; placements por
`effective_from, id`. No se incorpora un parámetro de orden configurable.

## Payload y vínculo verificable

`data` es el array directo. `meta.pagination` conserva la forma del normalizador
y `meta.filters` incluye únicamente filtros aplicados, con `is_fixed` booleano.

Cada cambio devuelve `id`, `operation_id`, `action`, `actor_kind`,
`actor_external_user_id`, `reason`, `occurred_at`, `previous_status`, `next_status`,
`previous_program_id`, `next_program_id`, `previous_is_fixed`, `next_is_fixed` y
los snapshots existentes `previous/next_personal_rate`, `previous/next_is_master`
y `previous/next_master_rate`. Los valores no disponibles permanecen null.
Se conserva el ID IAM del actor; no se consultan ni exponen nombres/correos.
`actor_kind` y `action` son la evidencia de actor/origen disponible; no se inventa
otro atributo de origen.

El listado añade `run_result_id` y `run_id`, ambos null salvo que la acción sea
`progression_placement` y `operation_id` resuelva un resultado persistido de la
misma suscripción. Progression publica un puerto Input V1 por lote con IDs de
operación y suscripción; sus repositorios verifican pertenencia. No se infiere
asociación por timestamp, programa o parecido de IDs. Subscriptions no consulta
directamente las tablas o modelos de otro feature.

Cada placement devuelve `id`, `subscription_id`, `program_id`, `is_fixed`,
`effective_from` y `effective_until`. No incluye referencias de Progression,
porque el intervalo no tiene ese vínculo directo persistido.

## Semántica temporal

Los intervalos son semiabiertos `[effective_from, effective_until)`; null en el
extremo final representa uno abierto. La ventana solicitada también es
semiabierta y no vacía. Solapamiento requiere:

```text
effective_from < overlap_until
AND (effective_until IS NULL
     OR (effective_until > effective_from AND effective_until > overlap_from))
```

Los intervalos de duración cero permanecen en el historial sin filtro temporal;
nunca cuentan como actividad ni solapan una ventana. La adyacencia tampoco
constituye solapamiento. Fix/release son visibles aunque no cambie el programa.
Progression con outcome unchanged no crea un movimiento ni un intervalo nuevo.

## Ejemplos sanitizados

Consulta:

```http
GET /api/ib/v1/admin/subscriptions/10000000-0000-4000-8000-000000000001/placements?is_fixed=1&overlap_from=2026-10-01T00:00:00Z&overlap_until=2026-10-02T00:00:00Z
Accept: application/json
X-Internal-Gateway: {{GATEWAY_INTERNAL_SECRET}}
X-Userinfo: {{ADMIN_USERINFO}}
```

Éxito (campos de paginación esenciales):

```json
{
  "success": true,
  "data": [{
    "id": "60000000-0000-4000-8000-000000000001",
    "subscription_id": "10000000-0000-4000-8000-000000000001",
    "program_id": "20000000-0000-4000-8000-000000000001",
    "is_fixed": true,
    "effective_from": "2026-10-01T01:00:00.000000Z",
    "effective_until": null
  }],
  "meta": {
    "message": "Subscription placements retrieved successfully.",
    "pagination": {"current_page": 1, "per_page": 100, "total": 1, "last_page": 1},
    "filters": {"is_fixed": true, "overlap_from": "2026-10-01T00:00:00Z", "overlap_until": "2026-10-02T00:00:00Z"}
  }
}
```

Errores vigentes: 401 sin identidad gateway; 403 sin permiso administrativo,
incluida identidad customer; 404 por suscripción inexistente; 422 por UUID,
fecha, filtro o paginación inválidos. Los errores del framework mantienen su
envelope propio, sin convertirlos a excepciones de negocio.

404:

```json
{
  "success": false,
  "message": "Subscription [10000000-0000-4000-8000-000000000001] was not found.",
  "error": {"code": "SUBSCRIPTION_NOT_FOUND", "details": []}
}
```

403: `{"message":"This action is unauthorized."}`.

422, ejemplo para `per_page=101`:

```json
{
  "message": "The per page field must be between 1 and 100.",
  "errors": {"per_page": ["The per page field must be between 1 and 100."]}
}
```

La colección Postman contiene ejemplos completos de changes y placements y sus
errores. [Entrega y evidencia](../roadmap/subscriptions/06-administrative-history.md).
