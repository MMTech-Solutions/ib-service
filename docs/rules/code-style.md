# Estilo de código

## PHP

- Seguir PSR-12 y el formato aplicado por Laravel Pint.
- Usar `declare(strict_types=1)` en archivos PHP propios cuando se establezca la plantilla del proyecto.
- Declarar tipos de parámetros, propiedades y retornos.
- Utilizar promoción de propiedades en constructores cuando aporte claridad.
- Usar llaves en todas las estructuras de control.
- Nombrar clases, métodos y variables por intención de negocio.
- Preferir objetos de valor y DTOs tipados sobre arrays sin contrato.
- Usar PHPDoc para shapes o genéricos que PHP no pueda expresar.
- Los casos de enums usan `TitleCase`.

## Laravel

- Crear artefactos del framework mediante comandos `php artisan make:* --no-interaction`.
- Versionar las APIs desde el comienzo.
- Utilizar Form Requests para validar entrada HTTP y API Resources para salida.
- Mantener controllers delgados y sin reglas de negocio.
- No acceder al entorno mediante `env()` fuera de archivos de configuración.
- No ocultar consultas costosas dentro de accessors o resources.

## Formato

Después de modificar PHP:

```bash
vendor/bin/pint --dirty --format agent
```

El formato no sustituye revisiones de arquitectura, dominio o seguridad.
