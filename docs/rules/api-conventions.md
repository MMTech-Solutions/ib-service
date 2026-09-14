# Convenciones de API HTTP

Estado: **obligatoria**.

Reglas de forma del envelope de éxito para endpoints HTTP del servicio. Complementan
[`architecture.md`](architecture.md) (flujo HTTP) y [`code-style.md`](code-style.md)
(uso del trait `ApiResponse`). No definen semántica de negocio.

## Envelope

Toda respuesta de éxito usa `MMT\ApiResponseNormalizer\ApiResponse` y conserva:

```json
{
  "success": true,
  "data": ...,
  "meta": { "message": "..." }
}
```

- `meta.message` transporta el mensaje humano de la operación.
- Con listados paginados, añadir `meta.pagination` vía el parámetro `paginator` del trait.
- Los filtros aplicados van en `meta.filters`, no dentro de `data`.
- `204 No Content` (p. ej. archive/delete sin cuerpo) no incluye `data`.

## Forma de `data` (obligatoria)

`data` **es** el payload tipado. No se envuelve bajo el nombre del recurso ni de la colección.

| Operación | Forma correcta de `data` | Forma prohibida |
| --- | --- | --- |
| Listado / colección (con o sin paginación) | Array JSON de ítems: `data: [ {...}, ... ]` | `data: { "plans": [ ... ] }`, `data: { "modules": [ ... ] }`, `data: { "collection": [ ... ] }` |
| Show / store / update / acciones que devuelven el recurso | Objeto JSON del recurso: `data: { "id": "...", ... }` | `data: { "plan": { ... } }`, `data: { "module": { ... } }` |

### Ejemplos

Listado:

```json
{
  "success": true,
  "data": [
    { "id": "...", "code": "broker" }
  ],
  "meta": {
    "message": "Modules retrieved successfully.",
    "pagination": { "current_page": 1, "per_page": 100, "total": 1, "last_page": 1 },
    "filters": {}
  }
}
```

Recurso singular:

```json
{
  "success": true,
  "data": {
    "id": "...",
    "code": "mix",
    "is_active": false
  },
  "meta": {
    "message": "Plan retrieved successfully."
  }
}
```

## Qué decide el agente o el desarrollador

Al implementar o modificar un controller HTTP:

1. Leer esta regla antes de elegir la forma de `data`.
2. Pasar al trait el array del listado o el `toArray()` del DTO/recurso **sin** clave envolvente.
3. No inventar aliases (`collection`, plural del recurso, etc.) en `data`.
4. Alinear assertions de tests y ejemplos Postman al mismo shape (`data.0.*` en listados; `data.*` en singulares).
5. Si un endpoint necesita devolver varios agregados distintos en una sola respuesta, documentar la excepción en esta regla antes de implementarla; no usar wrappers ad hoc sin registro.

## Relación con otros documentos

- Flujo HTTP y Resources: [`architecture.md`](architecture.md) § HTTP y Resources.
- Estilo de controllers y `ApiResponse`: [`code-style.md`](code-style.md).
- Directive operativa para agentes: [`AGENTS.md`](../../AGENTS.md) (routing documental y decisión de salida).
- Precedentes de `broker-service` o del README del normalizador que envuelvan por nombre de recurso **no** autorizan desviarse de esta regla.
