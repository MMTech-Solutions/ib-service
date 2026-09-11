# Stack tecnológico

## Confirmado

| Tecnología | Versión inicial | Uso |
| --- | --- | --- |
| PHP | 8.4 | Runtime del servicio. |
| Laravel Framework | 13.31.0 | Framework de aplicación. |
| PostgreSQL | Configurado; versión de servidor pendiente | Persistencia relacional prevista. |
| PHPUnit | 12.5.35 | Pruebas automatizadas. |
| Laravel Pint | 1.32.1 | Formato de PHP. |
| Laravel Boost | 2.8.0 | Guías para desarrollo asistido. |
| Graphify | CLI local | Grafo de conocimiento y análisis de impacto. |
| Composer | 2.10.0 | Gestión de dependencias PHP. |
| `spatie/laravel-data` | 4.23.0 | Commands, DTOs, resultados y payloads tipados. |
| `mmt/api-response-normalizer` | 1.1.2 | Envelope y respuestas HTTP normalizadas desde controllers. |
| `mmt/laravel-feature-scaffold` | 1.2.0 | Excepción base `MmtException` para errores HTTP normalizados. |
| `mmt/laravel-iam-service-sdk` | 1.2.0 | Interacción operativa con `auth-service` mediante su SDK. |
| `mmtech/iam-rbac` | 1.13 | Autorización distribuida y transporte Kafka disponible para publicación y consumo. |

Las versiones exactas instaladas en `composer.lock` prevalecen sobre este resumen. Este documento debe actualizarse cuando cambie una dependencia estructural.

## No decidido todavía

- Envelope definitivo de Kafka, Schema Registry y políticas operativas; el transporte se apoya en `mmtech/iam-rbac`.
- Redis u otro backend para locks y cache distribuida.
- Plataforma de observabilidad y trazas.
- Estrategia de almacenamiento de archivos.
- Contenedores, orquestación y destino de despliegue.
- Librería para JSON Schema y contratos de eventos.

No añadir una tecnología pendiente por conveniencia local sin una decisión documentada y aprobación explícita.
