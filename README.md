# IB Service

Microservicio responsable de definir cómo un Introducing Broker se suscribe a un plan, progresa entre sus programas y recibe recompensas generadas por distintos módulos de negocio.

El servicio se diseña con el **Plan IB como raíz funcional**. Broker, Copy Trading, Prop Firm, Hedge Fund y futuros módulos actúan como proveedores independientes de actividad; ninguno debe ser requisito técnico para que otro genere progresión o recompensas.

## Estado

El repositorio se encuentra en fase de fundación. En esta etapa se establecen el lenguaje de dominio, las reglas arquitectónicas y las normas de desarrollo. Los contratos de integración, el modelo de datos y los endpoints se definirán posteriormente.

## Stack inicial

- PHP 8.4.
- Laravel 13.
- PostgreSQL como base de datos configurada.
- PHPUnit para pruebas.
- Laravel Pint para formato.
- Laravel Boost para asistencia de desarrollo.
- Graphify para navegación e impacto sobre el código.

## Documentación

El índice canónico está en [`docs/README.md`](docs/README.md):

- [`docs/bds/`](docs/bds/README.md): especificaciones y reglas del dominio.
- [`docs/rules/`](docs/rules/README.md): decisiones técnicas y normas de implementación.
- [`AGENTS.md`](AGENTS.md): instrucciones obligatorias para agentes de IA.

Antes de implementar comportamiento de negocio, debe consultarse el BDS correspondiente. Antes de modificar código, deben consultarse las reglas técnicas aplicables y utilizarse Graphify según `AGENTS.md`.
