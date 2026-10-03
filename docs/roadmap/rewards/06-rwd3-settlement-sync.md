# RWD3.1: settlement síncrono de Rewards CPA

Estado: **Implementado; validación contractual pendiente**
Última revisión: 2026-10-03

## Extensión vigente

El [cierre local E2E](11-e2e-historical-pnl.md#entrega-de-cierre-local-e2e)
completa settlement CPA/volumen/PnL, solicitudes congeladas, traducción de niveles
y holds. Generación y settlement PnL están habilitados por defecto con controles
independientes. El alcance CPA original de este documento se conserva como evidencia.

## Propósito

Asentar una Reward CPA `pending` o `failed` mediante Finance sin eventos entre
servicios. IB conserva la causa de la obligación y Finance conserva el asiento
financiero autoritativo.

## Contrato y flujo

Rewards reclama una Reward con lease técnico corto, fuera de toda transacción
remota. El adapter propio de Rewards invoca `POST
/api/finance/v1/ib/commission-events` con `X-Internal-Token` y
`X-Internal-Source: mmt-ib-service`.

La solicitud usa una clave estable `ib-service:reward:<id>:settlement`,
`commission_type = cpa`, el importe congelado en minor units y la wallet
`lowercase(currency_code) + '-main'`. Una respuesta Finance `created` o
`duplicate` cuyo evento sea `posted` y coincida con causa, importe, moneda y
precisión asienta la Reward.

```text
pending / failed
        ↓ claim técnico
Finance commission-events
        ├─ created | duplicate + posted → settled
        └─ timeout | rechazo | contrato inválido → failed
```

La pérdida de la respuesta no duplica el pago: el siguiente intento conserva la
misma clave y Finance devuelve el resultado idempotente.

## Datos y operación

`rewards` conserva proveedor, referencia Finance, clave idempotente, contador,
último intento, error sanitizado, fecha de settlement y lease. No existe tabla
de intentos ni se persiste la respuesta completa de Finance.

El comando `rewards:settle-pending` se ejecuta cada minuto, en una sola
instancia y sin solapamiento. Selecciona `pending` y `failed`; los fallidos
respetan un delay configurable de cinco minutos. No hay endpoint HTTP en esta
entrega.

## Límites y cierre

- Solo se asientan Rewards vinculadas a un `cpa_context` y a una regla
  `cpa_fixed_amount`.
- `cancelled`, reversas, compensaciones y reconciliación por consulta son
  RWD3.2.
- El cierre exige contract tests y smoke S2S contra Finance real; hasta entonces
  las pruebas HTTP focalizadas demuestran únicamente el contrato local.
