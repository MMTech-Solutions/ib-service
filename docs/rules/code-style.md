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
- Utilizar Form Requests para validar entrada HTTP y `spatie/laravel-data` para Commands, DTOs y resultados tipados.
- Ubicar cada Command HTTP en `Http/V1/Commands` y construirlo mediante `Command::fromRequest()` a partir de `$request->validated()`.
- No construir Commands con `$request->all()` salvo que una convención documentada reemplace por completo la validación del FormRequest con validación de `laravel-data`.
- Normalizar respuestas HTTP desde el controller con el trait `ApiResponse` aportado por `mmt/laravel-feature-scaffold` (dependencia transitiva de `mmt/api-response-normalizer`).
- La forma de `data` sigue [`api-conventions.md`](api-conventions.md): `data` es el array del listado o el objeto del recurso, sin wrapper por nombre.
- Extender `App\Support\Exceptions\ApiException` (`MmtException`) para errores HTTP esperados.
- Utilizar API Resources solo cuando exista una transformación específica de presentación que un Result Data no deba asumir.
- Mantener controllers delgados y sin reglas de negocio.
- Inyectar `UserContext` para identidad y capacidades. No obtener el usuario
  actual directamente mediante helpers, facades o Requests de Laravel.
- No acceder al entorno mediante `env()` fuera de archivos de configuración.
- Los API Resources son transformadores puros: no resuelven ni reciben repositories, factories, Services o UseCases.
- No ejecutar consultas, cargar relaciones ni ocultar accesos a datos dentro de accessors o Resources.
- No propagar tipos pertenecientes a SDKs fuera de su adapter o repository.

## Nombres arquitectónicos

- Usar los sufijos `UseCase`, `Action`, `Repository`, `RepositoryInterface`, `RepositoryFactory`, `Adapter`, `Strategy` y `Data` de acuerdo con la responsabilidad definida en `architecture.md`.
- Los objetos tipados con `spatie/laravel-data` usan el sufijo `Data`. La ubicación distingue el rol: internos en `DTOs/`; públicos en `Contracts/Data/V1/` (u otra versión mayor). No renombrar mecánicamente precedentes externos que utilicen `DTO`.
- No versionar por directorio los DTOs internos. Solo versionar `Contracts/Data` y `Contracts/Events`.
- Los objetos de valor se nombran por el concepto que representan y no necesitan el sufijo `ValueObject`.
- Evitar nombres genéricos como `Manager`, `Helper`, `Processor` o `CommonService` cuando exista un nombre de negocio más preciso.

## Formato

Después de modificar PHP:

```bash
vendor/bin/pint --dirty --format agent
```

El formato no sustituye revisiones de arquitectura, dominio o seguridad.
