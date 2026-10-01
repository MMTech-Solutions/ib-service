# M5: actividad Broker S2S y configuración instrumental

Estado: **En curso**

Evidencia: el feed interno acepta referido y pares canonicos de grupo/simbolo
para la evidencia CPA, sin cambiar el contrato M3 de Progression.

M5 conserva M4 como cierre del catálogo Broker S2S. Reemplaza el fixture de
actividad de Progression por volumen cerrado paginado de Broker y publica la
administración atómica de símbolos de Program validada contra el catálogo vivo.

Los depósitos certificados de Finance no ingresan a Progression en esta etapa.
`broker-service` entrega únicamente hechos de volumen cerrado; no compone
depósitos ni métricas CPA. Cuando Rewards requiera evidencia CPA, el subfeature
Broker de Modules en IB consultará Finance directamente y conservará separadas
ambas fuentes. Ningún proveedor decide elegibilidad ni pagos.
