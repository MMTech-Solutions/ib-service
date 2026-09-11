# IB Service

Microservicio responsable de definir cómo un Introducing Broker se suscribe a un plan, progresa entre sus programas y recibe recompensas generadas por distintos módulos de negocio.

El servicio se diseña con el **Plan IB como raíz funcional**. Broker, Copy Trading, Prop Firm, Hedge Fund y futuros módulos actúan como proveedores independientes de actividad; ninguno debe ser requisito técnico para que otro genere progresión o recompensas.

## Estado

El repositorio se encuentra en fase de fundación. En esta etapa se establecen el lenguaje de dominio, las reglas arquitectónicas y las normas de desarrollo. Los contratos de integración, el modelo de datos y los endpoints se definirán posteriormente.

## Stack inicial

| Tecnología | Uso inicial |
| --- | --- |
| PHP 8.4 | Runtime del servicio. |
| Laravel 13 | Framework de aplicación. |
| PostgreSQL | Persistencia relacional. |
| `spatie/laravel-data` | Commands, objetos Data y contratos tipados. |
| `mmt/api-response-normalizer` | Normalización de respuestas HTTP. |
| `mmt/laravel-iam-service-sdk` | Interacción operativa con `auth-service`. |
| `mmtech/iam-rbac` | Autorización distribuida y transporte Kafka. |
| PHPUnit | Pruebas automatizadas. |
| Laravel Pint | Formato de PHP. |
| Laravel Boost | Guías para desarrollo asistido. |
| Graphify | Navegación del código y análisis de impacto. |

Las versiones exactas y las decisiones pendientes se mantienen en [`docs/rules/technology-stack.md`](docs/rules/technology-stack.md).

## Documentación

El índice canónico está en [`docs/README.md`](docs/README.md):

- [`docs/bds/`](docs/bds/README.md): especificaciones y reglas del dominio.
- [`docs/rules/`](docs/rules/README.md): decisiones técnicas y normas de implementación.
- [`docs/roadmap/`](docs/roadmap/README.md): estado y entregas planificadas por feature.
- [`AGENTS.md`](AGENTS.md): instrucciones obligatorias para agentes de IA.

Antes de implementar comportamiento de negocio, debe consultarse el BDS correspondiente. Antes de modificar código, deben consultarse las reglas técnicas aplicables y utilizarse Graphify según `AGENTS.md`.
