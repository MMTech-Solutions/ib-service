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

RWD-A2.1 registra explícitamente `UnsupportedCpaRewardCalculationStrategyException`
como excepción técnica: rechaza un código interno desconocido de la factory CPA,
sin representar un error HTTP ni ampliar exclusiones por directorio.

RWD-A2.2 registra `UnsupportedVolumeRewardCalculationStrategyException` con la
misma responsabilidad técnica para la factory económica de volumen. Su ruta
exacta se controla en la suite; no se amplían exclusiones por directorio.

- UseCases, Actions, Controllers y Resources no importan ni usan `ConnectionInterface`, `DB`, Query Builder, Eloquent o `->table(...)` fuera del baseline.
- Todo `RepositoryFactory` expone únicamente `make()` además de su constructor.
- Controllers y Resources no dependen de repositories, factories ni persistencia.
- Controllers no convierten reglas de negocio en `ValidationException` o `InvalidArgumentException`, ni emiten errores HTTP ad hoc. Las reglas de negocio HTTP se propagan como `ApiException` y el handler global usa `ApiResponse::error`.

## Retiro de deuda

RWD4.2.1 registra la excepción técnica
`app/Features/Rewards/Exceptions/UnsupportedNegativePnlRewardCalculationStrategyException.php`.
La factory económica PnL rechaza códigos internos desconocidos; no representa
un error HTTP. Su ruta exacta se controla en el guardrail sin excluir directorios.

RWD-A2.3 registra las excepciones técnicas
`Modules/Catalog/Exceptions/UnsupportedRewardEvidenceProviderException` y
`Rewards/Exceptions/UnsupportedNegativePnlPeriodsProviderException`: rechazan
códigos internos desconocidos en factories sin representar errores HTTP.
La suite controla ambas rutas exactas, sin exclusiones por directorio.

Al refactorizar una entrada histórica, se elimina primero del baseline y se mantiene la prueba verde sin sustituirla por una exclusión más amplia. Cambiar un contrato público de puerto requiere su propia entrega y actualización de consumidores; QG1 no autoriza hacerlo implícitamente.

## Ejecución

```powershell
php artisan test --compact tests/Architecture
```

Después de cambios de código se ejecutan también Pint y `graphify update .` según `AGENTS.md`.

## Refactor CPA por puntos (2026-10-06)

ResolveCpaRuleContextUseCase deja de acceder directamente a persistencia; se retira
su entrada del baseline. CpaEvidenceContractException es una excepción técnica
de contrato externo inmutable usada por el procesamiento de Rewards, sin transporte
HTTP; su ruta exacta se añade al baseline sin ampliar exclusiones por directorio.
Los nuevos puertos de depósitos certificados y capacidad CPA exponen execute().
