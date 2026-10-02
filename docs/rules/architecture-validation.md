# Validación ejecutable de arquitectura

Estado: **obligatoria**.

La suite `tests/Architecture/` convierte reglas de arquitectura en pruebas PHPUnit. Debe ejecutarse al cerrar toda entrega que modifique código de `app/Features/`; no sustituye las pruebas de comportamiento ni los contract tests S2S.

## QG1: ratchet de deuda histórica

QG1 no normaliza silenciosamente implementaciones anteriores. El test `ArchitectureGuardrailsTest` mantiene baselines exactos por ruta, regla y cantidad de coincidencias. Una ruta nueva, un método público adicional o una llamada directa adicional a persistencia falla la suite. No se permiten exclusiones por directorio, glob ni entorno.

El baseline temporal contiene únicamente:

- Implementaciones de puertos publicados antes de la convención `execute()`. Deben conservar exactamente sus métodos públicos contractuales. Un UseCase nuevo que no implemente uno de esos contratos expone solamente `execute()`.
- Cuatro UseCases con acceso directo histórico a PostgreSQL: reemplazo de configuraciones instrumentales, lectura de símbolos CPA, lectura de configuración de Progression y contexto de regla CPA. Cada extracción a repository elimina la entrada correspondiente; no se incrementan sus contadores.
- Excepciones técnicas/control interno que no son errores HTTP: los conflictos de código históricos, el descarte CPA de Kafka y la indisponibilidad reintentable de Finance. Una excepción nueva de feature extiende `ApiException` salvo que se documente y añada explícitamente como técnica antes de introducirla.

## Reglas controladas

- UseCases, Actions, Controllers y Resources no importan ni usan `ConnectionInterface`, `DB`, Query Builder, Eloquent o `->table(...)` fuera del baseline.
- Todo `RepositoryFactory` expone únicamente `make()` además de su constructor.
- Controllers y Resources no dependen de repositories, factories ni persistencia.
- Controllers no convierten reglas de negocio en `ValidationException` o `InvalidArgumentException`, ni emiten errores HTTP ad hoc. Las reglas de negocio HTTP se propagan como `ApiException` y el handler global usa `ApiResponse::error`.

## Retiro de deuda

Al refactorizar una entrada histórica, se elimina primero del baseline y se mantiene la prueba verde sin sustituirla por una exclusión más amplia. Cambiar un contrato público de puerto requiere su propia entrega y actualización de consumidores; QG1 no autoriza hacerlo implícitamente.

## Ejecución

```powershell
php artisan test --compact tests/Architecture
```

Después de cambios de código se ejecutan también Pint y `graphify update .` según `AGENTS.md`.
