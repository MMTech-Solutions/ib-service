# Instrucciones del repositorio

Este repositorio contiene el microservicio IB. Antes de explorar, planificar o modificar, confirmar la raíz con `git rev-parse --show-toplevel` y mantener todo el alcance dentro de este repositorio salvo petición explícita.

## Fuentes de verdad

La documentación canónica comienza en [`docs/README.md`](docs/README.md):

- [`docs/bds/`](docs/bds/README.md) define lenguaje, invariantes, cálculos, estados y eventos del negocio.
- [`docs/rules/`](docs/rules/README.md) define arquitectura, estrategias, stack, estilo, buenas prácticas, seguridad y mantenimiento de BDS.
- [`docs/roadmap/`](docs/roadmap/README.md) registra secuencia, estado y decisiones pendientes de implementación por feature; no sustituye BDS ni reglas.
- El código y las pruebas demuestran implementación; no redefinen silenciosamente el negocio.

Cuando una petición pueda cambiar semántica del dominio, leer primero el BDS del área y [`docs/rules/bds.md`](docs/rules/bds.md). Si la decisión no está cerrada, conservarla como pendiente y solicitar definición antes de implementarla como invariante.

## Routing documental obligatorio

Antes de actuar, leer los documentos que correspondan al alcance:

| Alcance | Documentación obligatoria |
| --- | --- |
| Planes, programas, módulos, suscripciones o placement | `docs/bds/plans-and-subscriptions.bds.md` |
| Puntos, ponderaciones, contribuciones o runs | `docs/bds/progression.bds.md` |
| Reglas de pago, versiones, asignaciones, rewards o CPA | `docs/bds/rewards.bds.md` |
| Estructura, features, capas o integraciones | `docs/rules/architecture.md` |
| Kafka, eventos, IAM, SDKs, clientes HTTP o notificaciones | `docs/rules/integrations.md` y `docs/rules/security.md` |
| Strategy, connectors o configuración JSON | `docs/rules/strategies.md` |
| PHP o Laravel | `docs/rules/code-style.md` y `docs/rules/programming-best-practices.md` |
| Dependencias o infraestructura | `docs/rules/technology-stack.md` |
| Entradas, autorización, datos sensibles, reglas dinámicas o pagos | `docs/rules/security.md` |
| Creación o modificación de BDS | `docs/rules/bds.md` |
| Planificación o implementación de una feature incluida en el roadmap | `docs/roadmap/README.md`, el `README.md` de la feature y el documento de su etapa actual |

Si existen reglas generadas en `.ai/rules/`, leer primero `.ai/rules/index.md`, todos los archivos cuyos globs cubran el cambio y realizar la búsqueda por palabras clave exigida por Laravel Boost.

## Uso obligatorio de Graphify

Para explorar código, dependencias, arquitectura o impacto:

1. Ejecutar primero `graphify query "<pregunta>"` desde la raíz.
2. Usar `graphify explain "<concepto>"` para inspección focalizada.
3. Usar `graphify path "<símbolo A>" "<símbolo B>"` para relaciones.
4. Consultar `graphify-out/wiki/index.md` para navegación amplia si existe.
5. Leer `graphify-out/GRAPH_REPORT.md` solo para revisiones amplias o cuando las consultas no basten.
6. Usar `rg`, `git grep` o lecturas directas después de que el grafo haya orientado la búsqueda, o cuando el grafo no contenga la información.

Después de modificar código, ejecutar `graphify update .`. Los cambios en `graphify-out/` son esperados y no deben descartarse por sí solos. Los cambios exclusivamente documentales no requieren reconstruir el grafo, salvo que se desee incorporarlos mediante una extracción semántica posterior.

## Mantenimiento documental

- Toda feature de negocio nueva crea o actualiza su BDS junto con el código.
- Toda decisión transversal nueva actualiza el archivo correspondiente en `docs/rules/`.
- Actualizar `docs/README.md` y el índice de sección al crear, renombrar o retirar documentos.
- Actualizar el estado, la fecha de revisión y la evidencia del roadmap al avanzar una etapa o entrega.
- No presentar una decisión pendiente como regla confirmada.
- No usar el roadmap para contradecir o reemplazar un BDS o una regla técnica vigente.
- No incluir detalles técnicos en BDS ni reglas de negocio en documentos técnicos como sustituto del BDS.

## Precedencia e interpretación de Laravel Boost

Laravel Boost proporciona convenciones predeterminadas del framework. No define por sí solo la arquitectura ni las reglas de dominio de IB Service.

Ante conflicto o ambigüedad, aplicar este orden dentro del repositorio:

1. Instrucción explícita del usuario para la tarea actual.
2. BDS vigente del área afectada.
3. Reglas específicas en `docs/rules/`.
4. Reglas aplicables en `.ai/rules/`, cuando existan.
5. Convenciones consolidadas en código propio comparable.
6. Laravel Boost y convenciones generales de Laravel.

La instrucción de seguir convenciones existentes exige buscar un precedente dentro del mismo feature y responsabilidad. El scaffold inicial, el código de ejemplo y `broker-service` no constituyen por sí solos una decisión arquitectónica de este proyecto. Si no existe precedente, seguir `docs/rules/architecture.md` y `docs/rules/code-style.md`; solicitar definición cuando la decisión afecte un punto que esos documentos mantienen pendiente.

En la regla de Boost sobre nuevos directorios, **base folder** significa una nueva raíz arquitectónica directamente bajo la raíz del repositorio o bajo `app/`. Los directorios autorizados por `docs/rules/architecture.md` pueden crearse cuando una implementación real los necesite, sin aprobación individual para cada subdivisión. Introducir otra raíz o categoría base sí requiere aprobación.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
