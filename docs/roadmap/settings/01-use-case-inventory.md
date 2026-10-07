# Settings: inventario de casos de uso

Estado: completado. Revisión: 2026-10-07.

Administración lista por dominio/sección/proveedor, consulta valores, reemplaza
personalizaciones con motivo y versión, restablece respaldo y certifica una conexión.
No crea claves ni modifica definiciones. Secretos requieren permiso adicional.

Operación sincroniza el catálogo desde CLI y puede anticipar diferencias sin escribir.
Consumidores consultan un snapshot efectivo por operación, conservando historial previo.

Criterios: no escrituras desde lecturas, ninguna clave arbitraria, valores secretos
ocultos, certificación común para los accesos Settings/Modules.
