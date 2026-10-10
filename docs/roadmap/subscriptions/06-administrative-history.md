# Consulta administrativa de historial de suscripciones

Estado: **completada localmente; aceptación integrada LAB5 pendiente**. Revisión: 2026-10-10.

## Entrega

Dos listados administrativos paginados de movimientos e intervalos reutilizan
la persistencia existente. No hay migraciones, nueva historia, exposición
customer ni cambios de cálculo o elegibilidad fixed. El detalle administrativo
conserva su contrato. Las rutas, parámetros, permisos, payloads, errores y ejemplos
están en el [contrato HTTP](../../rules/subscription-history-api.md).

Los repositorios PostgreSQL consultan directamente las tablas históricas con
COUNT y LIMIT/OFFSET; no hidratan todo el agregado. InMemory satisface el mismo
contrato de orden y filtros. Progression publica un puerto V1 por lote para
verificar resultados de la misma suscripción mediante operation_id; los intervalos
no reciben asociaciones inferidas.

## Aceptación y evidencia

- `SubscriptionHistoryEndpointTest`: recorrido manual A → fijación en A → B →
  liberación en B; terminales; permisos y customer denegado; filtros, paginación,
  extremos fraccionarios y conservación del detalle.
- `SubscriptionRepositoryContract` en InMemory/PostgreSQL: timestamps iguales,
  desempate por ID, filtros combinados, aislamiento por suscripción, intervalos
  abiertos, adyacentes y vacíos conservados sin solapamiento.
- `ProgressionResultReferencesPortTest`: consulta por lote en ambos repositorios,
  IDs ausentes, duplicados y resultados de otra suscripción.
- Subida/bajada/unchanged y referencias observables por HTTP; recuperación
  terminal sin duplicados y recuperación tras fallo en `ProgressionRecoveryTest`.
- Postman incorpora solo las dos requests en Administration/Subscriptions,
  con ADMIN_USERINFO y ejemplos sintéticos sin secretos.

Validación local (2026-10-10):

- `php artisan test --compact tests/Feature/Subscriptions tests/Unit/Subscriptions
  tests/Feature/SubscriptionHistoryEndpointTest.php
  tests/Feature/ProgressionResultReferencesPortTest.php
  tests/Feature/ProgressionRecoveryTest.php
  tests/Feature/ProgressionSnapshotRepositoryContractTest.php
  tests/Feature/Progression tests/Unit/ProgressionWindowClosingUseCaseTest.php
  tests/Feature/Lab5ContractTest.php tests/Architecture`: **173 pruebas aprobadas,
  1.661 aserciones**, sobre PostgreSQL aislado de testing.
- `php vendor/bin/pint --dirty --format agent`: completado.
- Postman: JSON y estructura de colección v2.1 válidos; 127 requests cubren las
  116 rutas de `php artisan route:list --except-vendor --json`, incluidas `/` y
  `/up`, contrastadas con bootstrap. Las dos requests nuevas usan ADMIN_USERINFO
  e incluyen parámetros y respuestas sanitizadas 200/401/403/404/422.
- `graphify update .`: actualizado tras los cambios de código y pruebas.
- `git diff --check`: sin errores.

No se aplicaron migraciones a la base operativa ni se cambiaron dependencias.

## Pendientes preservados

Esta entrega habilita observación para LAB5. No declara LAB5 completo ni acredita
aceptación integrada de ib-labs; tampoco cierra el pendiente de elegibilidad fixed.
No hay cambios en ib-labs ni commit/push en esta entrega.
