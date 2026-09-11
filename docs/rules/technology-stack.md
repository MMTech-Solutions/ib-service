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
| `mmt/laravel-iam-service-sdk` | 1.2.0 | Interacción operativa con `auth-service` mediante su SDK. |
| `mmtech/iam-rbac` | 1.13 | Autorización distribuida y transporte Kafka disponible para publicación y consumo. |

## Dependencias aprobadas pendientes de instalación

| Tecnología | Restricción inicial | Uso |
| --- | --- | --- |
| `spatie/laravel-data` | Versión compatible con Laravel 13 por confirmar al instalar | Commands, DTOs, resultados y payloads tipados. |
| `mmt/api-response-normalizer` | Versión compatible por confirmar al instalar | Envelope y respuestas HTTP normalizadas desde controllers. |

Estas dependencias son decisiones arquitectónicas aprobadas, pero no se consideran instaladas hasta que aparezcan en `composer.json` y `composer.lock`. Antes de incorporarlas se debe confirmar la versión disponible y ejecutar las pruebas correspondientes.

Las versiones exactas instaladas en `composer.lock` prevalecen sobre este resumen. Este documento debe actualizarse cuando cambie una dependencia estructural.

## No decidido todavía

- Envelope definitivo de Kafka, Schema Registry y políticas operativas; el transporte se apoya en `mmtech/iam-rbac`.
- Redis u otro backend para locks y cache distribuida.
- Plataforma de observabilidad y trazas.
- Estrategia de almacenamiento de archivos.
- Contenedores, orquestación y destino de despliegue.
- Librería para JSON Schema y contratos de eventos.

No añadir una tecnología pendiente por conveniencia local sin una decisión documentada y aprobación explícita.
