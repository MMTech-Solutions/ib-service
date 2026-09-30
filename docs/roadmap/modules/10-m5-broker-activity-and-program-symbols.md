# M5: actividad Broker S2S y configuraciÃ³n instrumental

Estado: **En curso**

M5 conserva M4 como cierre del catÃ¡logo Broker S2S. Reemplaza el fixture de
actividad de Progression por volumen cerrado paginado de Broker y publica la
administraciÃ³n atÃ³mica de sÃ­mbolos de Program validada contra el catÃ¡logo vivo.

Los depÃ³sitos certificados de Finance no ingresan a Progression en esta etapa.
Broker los consulta como fachada para las mÃ©tricas futuras de CPA, junto con el
volumen desde la captura del contexto y el snapshot de sÃ­mbolos CPA. Broker no
decide elegibilidad ni pagos.
