# Graph Report - ib-service  (2026-09-11)

## Corpus Check
- 114 files · ~34,104 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 679 nodes · 968 edges · 58 communities (54 shown, 4 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 1 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `de6f8165`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- composer.json
- scripts
- Laravel Boost Guidelines
- package.json
- Laravel Boost Guidelines
- ModuleCapabilityRecord
- Planes, programas y suscripciones IB — BDS
- Illuminate\Database\Migrations\Migration
- Progresión multi-módulo por puntos — BDS
- Reglas y recompensas IB — BDS
- AGENTS.md
- Modules: inventario de casos de uso
- Module
- bootstrap/app.php
- TestCase
- logging.php
- UserContext
- console.php
- Controller.php
- Arquitectura
- Seguridad
- Estrategias configurables
- ModuleRepositoryContract
- Documentación de IB Service
- Propósito y mantenimiento de los BDS
- Integraciones
- Instrucciones del repositorio
- docs/README.md
- 01-create-testing-database.sh
- Modules: roadmap por entregas verticales
- Modules: agregados, estados y transacciones
- Roadmap del feature Modules
- Modules: modelo de datos de la primera entrega
- Roadmap de IB Service
- SyncModulesUseCase
- Modules: implementación de la primera entrega
- Spatie\LaravelData\Data
- Do Things the Laravel Way
- Estilo de código
- Roadmap del feature Plans
- Stack tecnológico
- ModulesServiceProvider.php
- Mejores prácticas de programación
- IB Service
- bds/README.md

## God Nodes (most connected - your core abstractions)
1. `Module` - 30 edges
2. `ModuleRecord` - 20 edges
3. `Arquitectura` - 19 edges
4. `TestCase` - 17 edges
5. `PostgreSqlModuleRepository` - 16 edges
6. `InMemoryModuleRepository` - 15 edges
7. `ModuleListQueryData` - 14 edges
8. `ModuleRepositoryContract` - 13 edges
9. `ModuleRepositoryFactory` - 12 edges
10. `ModulesPageData` - 11 edges

## Surprising Connections (you probably didn't know these)
- `InMemoryModuleRepositoryContractTest` --references--> `InMemoryModuleRepository`  [EXTRACTED]
  tests/Feature/Modules/Catalog/InMemoryModuleRepositoryContractTest.php → app/Features/Modules/Catalog/Repositories/InMemory/InMemoryModuleRepository.php
- `findByCode()` --references--> `Module`  [EXTRACTED]
  app/Features/Modules/Catalog/Contracts/Repositories/ModuleRepositoryInterface.php → app/Features/Modules/Catalog/Models/Module.php
- `create()` --references--> `Module`  [EXTRACTED]
  app/Features/Modules/Catalog/Contracts/Repositories/ModuleRepositoryInterface.php → app/Features/Modules/Catalog/Models/Module.php
- `update()` --references--> `Module`  [EXTRACTED]
  app/Features/Modules/Catalog/Contracts/Repositories/ModuleRepositoryInterface.php → app/Features/Modules/Catalog/Models/Module.php
- `deleteIfUnreferenced()` --references--> `Module`  [EXTRACTED]
  app/Features/Modules/Catalog/Contracts/Repositories/ModuleRepositoryInterface.php → app/Features/Modules/Catalog/Models/Module.php

## Import Cycles
- None detected.

## Communities (58 total, 4 thin omitted)

### Community 0 - "composer.json"
Cohesion: 0.04
Nodes (45): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+37 more)

### Community 1 - "scripts"
Cohesion: 0.08
Nodes (26): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+18 more)

### Community 2 - "Laravel Boost Guidelines"
Cohesion: 0.25
Nodes (8): Application Structure & Architecture, Conventions, Documentation Files, Foundational Context, Frontend Bundling, Laravel Boost Guidelines, Replies, Verification Scripts

### Community 3 - "package.json"
Cohesion: 0.10
Nodes (20): concurrently, @laravel/multiplex, laravel-vite-plugin, devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite (+12 more)

### Community 4 - "Laravel Boost Guidelines"
Cohesion: 0.08
Nodes (23): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+15 more)

### Community 5 - "ModuleCapabilityRecord"
Cohesion: 0.07
Nodes (22): ModuleCapabilityRecord, User, ModuleCapabilityRecordFactory, ModuleRecordFactory, UserFactory, DatabaseSeeder, LocalRbacSnapshotSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents (+14 more)

### Community 6 - "Planes, programas y suscripciones IB — BDS"
Cohesion: 0.25
Nodes (8): Contexto, Decisiones pendientes, Ejemplos de producto, Eventos de negocio, Glosario, Planes, programas y suscripciones IB — BDS, Reglas de dominio, Relaciones

### Community 7 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.13
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 8 - "Progresión multi-módulo por puntos — BDS"
Cohesion: 0.25
Nodes (8): Auditoría de una contribución, Contexto, Decisiones pendientes, Ejemplos de conversión, Flujo de dominio, Glosario, Progresión multi-módulo por puntos — BDS, Reglas de dominio

### Community 9 - "Reglas y recompensas IB — BDS"
Cohesion: 0.25
Nodes (8): Contexto, Decisiones pendientes, Ejemplo de reutilización CPA, Eventos de negocio, Glosario, Reglas de dominio, Reglas y recompensas IB — BDS, Relaciones

### Community 10 - "AGENTS.md"
Cohesion: 0.18
Nodes (10): Artisan, Deployment, graphify, Laravel Boost, Laravel Pint Code Formatter, PHP, PHPUnit, Project Rules (+2 more)

### Community 11 - "Modules: inventario de casos de uso"
Cohesion: 0.33
Nodes (6): Actores, Criterios de salida, Decisiones cerradas, Inventario inicial, Modules: inventario de casos de uso, Propósito

### Community 12 - "Module"
Cohesion: 0.07
Nodes (26): ModuleListQueryData, ModulesPageData, create(), deleteIfUnreferenced(), findByCode(), paginate(), transaction(), update() (+18 more)

### Community 13 - "bootstrap/app.php"
Cohesion: 0.40
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request

### Community 14 - "TestCase"
Cohesion: 0.08
Nodes (12): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\File, Illuminate\Testing\TestResponse, PHPUnit\Framework\Attributes\DataProvider, UserContextArchitectureTest, ExampleTest (+4 more)

### Community 15 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 16 - "UserContext"
Cohesion: 0.07
Nodes (17): ListModulesCommand, self, ListModulesController, ListModulesRequest, can(), UserSurface, UserContext, BackedEnum (+9 more)

### Community 29 - "Arquitectura"
Cohesion: 0.08
Nodes (26): Actions, Actividad externa, Arquitectura, Composición con Laravel, Contratos y comunicación entre features, Decisiones pendientes, Estado, Estructura de un feature (+18 more)

### Community 30 - "Seguridad"
Cohesion: 0.29
Nodes (7): APIs e integraciones, Configuración dinámica, Datos personales y auditoría, Desarrollo, Dinero y recompensas, Principios obligatorios, Seguridad

### Community 31 - "Estrategias configurables"
Cohesion: 0.25
Nodes (8): Configuración, Estrategias configurables, Fallos, Objetivo, Pruebas, Registry, Ubicación y separación obligatoria, Versionado

### Community 32 - "ModuleRepositoryContract"
Cohesion: 0.19
Nodes (4): NoModuleReferences, ModuleRepositoryContract, InMemoryModuleRepositoryContractTest, PostgreSqlModuleRepositoryContractTest

### Community 33 - "Documentación de IB Service"
Cohesion: 0.29
Nodes (7): [`bds/`](bds/README.md), Documentación de IB Service, Jerarquía de autoridad, Mantenimiento, [`roadmap/`](roadmap/README.md), [`rules/`](rules/README.md), Secciones

### Community 34 - "Propósito y mantenimiento de los BDS"
Cohesion: 0.29
Nodes (6): Contenido obligatorio, Contenido prohibido, Convenciones, Definición, Flujo de cambio, Propósito y mantenimiento de los BDS

### Community 35 - "Integraciones"
Cohesion: 0.18
Nodes (11): Consumo de eventos, Decisiones pendientes, Envelope mínimo, Estado, Eventos internos, eventos de integración y notificaciones, IAM y autorización, Integraciones, Kafka (+3 more)

### Community 36 - "Instrucciones del repositorio"
Cohesion: 0.29
Nodes (7): Colección Postman obligatoria, Fuentes de verdad, Instrucciones del repositorio, Mantenimiento documental, Precedencia e interpretación de Laravel Boost, Routing documental obligatorio, Uso obligatorio de Graphify

### Community 39 - "Modules: roadmap por entregas verticales"
Cohesion: 0.18
Nodes (11): Dependencias, M1a — Sincronización y listado del catálogo, M1b — Catálogo y control operativo, M2 — Catálogo externo de instrumentos, M3 — Fuentes de actividad, M4 — Sustitución y endurecimiento operativo, Modules: roadmap por entregas verticales, Primera entrega consumidora: Plans P1 (+3 more)

### Community 40 - "Modules: agregados, estados y transacciones"
Cohesion: 0.22
Nodes (8): Decisiones cerradas para M1a, Decisiones de dominio ya confirmadas, Estados confirmados, Hipótesis de agregados, Invariantes que deberá expresar el modelo, Límites transaccionales confirmados para M1a, Modules: agregados, estados y transacciones, Resultado esperado de esta etapa

### Community 41 - "Roadmap del feature Modules"
Cohesion: 0.25
Nodes (8): Alcance inicial, Decisiones confirmadas, Estado, Fuera del primer incremento, Objetivo, Próximo paso, Referencias canónicas, Roadmap del feature Modules

### Community 42 - "Modules: modelo de datos de la primera entrega"
Cohesion: 0.25
Nodes (7): Columnas, Criterios de salida, Modules: modelo de datos de la primera entrega, Necesidades de persistencia conocidas, Propósito, Restricciones confirmadas, Tablas aprobadas para M1a

### Community 43 - "Roadmap de IB Service"
Cohesion: 0.29
Nodes (7): Criterios de avance, Estados, Iteración estándar por feature, Mantenimiento, Roadmap de IB Service, Secuencia entre features, Índice por feature

### Community 44 - "SyncModulesUseCase"
Cohesion: 0.14
Nodes (9): ModulesSyncCommand, SyncModulesResultData, ModuleDefinitionRegistry, SyncModulesUseCase, Carbon\CarbonImmutable, Illuminate\Console\Attributes\Description, Illuminate\Console\Attributes\Signature, Illuminate\Console\Command (+1 more)

### Community 45 - "Modules: implementación de la primera entrega"
Cohesion: 0.29
Nodes (6): Definition of Done de M1, Evidencia de cierre, Evidencia de M1a, Matriz mínima de contract tests, Modules: implementación de la primera entrega, Orden de implementación propuesto

### Community 49 - "Spatie\LaravelData\Data"
Cohesion: 0.11
Nodes (6): ModuleCapabilityData, ModuleCapabilityDefinitionData, ModuleData, ModuleDefinitionData, ModuleCapability, Spatie\LaravelData\Data

### Community 50 - "Do Things the Laravel Way"
Cohesion: 0.33
Nodes (6): APIs & Eloquent Resources, Do Things the Laravel Way, Model Creation, Testing, URL Generation, Vite Error

### Community 51 - "Estilo de código"
Cohesion: 0.40
Nodes (5): Estilo de código, Formato, Laravel, Nombres arquitectónicos, PHP

### Community 52 - "Roadmap del feature Plans"
Cohesion: 0.40
Nodes (5): Posición en la secuencia, Primera entrega candidata: P1, Próximo paso, Relación con Programs, Roadmap del feature Plans

### Community 53 - "Stack tecnológico"
Cohesion: 0.67
Nodes (3): Confirmado, No decidido todavía, Stack tecnológico

### Community 54 - "ModulesServiceProvider.php"
Cohesion: 0.11
Nodes (15): App\Providers\AppServiceProvider, AppServiceProvider, ModulesServiceProvider, GatewayUserConnector, UserSurface, UserServiceProvider, Factory, Illuminate\Auth\AuthenticationException (+7 more)

### Community 55 - "Mejores prácticas de programación"
Cohesion: 0.33
Nodes (6): Confiabilidad, Datos y cálculos, Diseño, Mejores prácticas de programación, Observabilidad, Pruebas

### Community 56 - "IB Service"
Cohesion: 0.50
Nodes (4): Documentación, Estado, IB Service, Stack inicial

### Community 57 - "bds/README.md"
Cohesion: 0.29
Nodes (3): Business Domain Specifications, Convenciones, Especificaciones

## Knowledge Gaps
- **246 isolated node(s):** `Fuentes de verdad`, `Routing documental obligatorio`, `Uso obligatorio de Graphify`, `Mantenimiento documental`, `Colección Postman obligatoria` (+241 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `TestCase` to `ModuleRepositoryContract`, `Module`, `ModulesServiceProvider.php`?**
  _High betweenness centrality (0.047) - this node is a cross-community bridge._
- **Why does `ModuleRecord` connect `Module` to `ModuleCapabilityRecord`, `TestCase`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._
- **Why does `Arquitectura` connect `Arquitectura` to `docs/README.md`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **What connects `Fuentes de verdad`, `Routing documental obligatorio`, `Uso obligatorio de Graphify` to the rest of the system?**
  _246 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.043478260869565216 - nodes in this community are weakly interconnected._
- **Should `scripts` be split into smaller, more focused modules?**
  _Cohesion score 0.08 - nodes in this community are weakly interconnected._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.09523809523809523 - nodes in this community are weakly interconnected._