# PG2: foundations de red y plantillas

Estado: **Implementada**  
Última revisión: 2026-09-29

## Frontera de red

Progression define un puerto de salida para resolver todos los uplines vigentes
de un referido. PG2.2 filtrará el resultado conforme a la plantilla y
configuración aplicables. Su resultado propio contiene
solo el identificador estable del IB beneficiario y su nivel de distribución.
El adapter traduce la respuesta de IAM y no expone tipos, URLs, tokens ni
errores técnicos del SDK fuera de la integración.

El puerto no recibe `occurred_at`: la red se obtiene antes de congelar la
distribución y no se presenta como una consulta histórica.

## Resultados

| Resultado | Efecto |
| --- | --- |
| Upline con beneficiarios | Finaliza la distribución con sus destinatarios. |
| Upline vacío | Finaliza una distribución vacía. |
| Error recuperable o contrato inválido | No finaliza distribución; la actividad queda reintentable. |

## Plantillas y símbolos

Las plantillas versionadas y la configuración de símbolo siguen siendo propias
de Progression. Su resolución usa `occurred_at` y se conserva en cada
contribución, independiente del instante de resolución de red.

## Próximo paso

PG2.1 publica el puerto IAM, los repositorios en memoria y PostgreSQL, y la
migración de distribución. La siguiente etapa es PG2.2: consumir el snapshot
para crear evaluaciones y contribuciones por beneficiario.

## Evidencia

- `ReferralUplineAdapterTest`: normalización de niveles, fallo IAM y wiring.
- `ActivityDistributionRepositoryTest`: idempotencia del snapshot en memoria.
- `php artisan migrate --pretend --no-interaction`: esquema PostgreSQL válido.
