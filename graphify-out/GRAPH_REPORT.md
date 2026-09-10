# Graph Report - ib-service  (2026-09-10)

## Corpus Check
- 49 files · ~16,820 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 317 nodes · 328 edges · 39 communities (34 shown, 5 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d255bf69`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- composer.json
- scripts
- Laravel Boost Guidelines
- package.json
- Laravel Boost Guidelines
- User
- Planes, programas y suscripciones IB — BDS
- 0001_01_01_000000_create_users_table.php
- Progresión multi-módulo por puntos — BDS
- Reglas y recompensas IB — BDS
- docs/README.md
- config
- AppServiceProvider
- bootstrap/app.php
- TestCase
- logging.php
- ExampleTest
- console.php
- Controller.php
- Illuminate\Support\Facades\Route
- Arquitectura
- Seguridad
- Estrategias configurables
- Do Things the Laravel Way
- Documentación de IB Service
- Propósito y mantenimiento de los BDS
- Mejores prácticas de programación
- Instrucciones del repositorio
- Laravel Boost
- Estilo de código

## God Nodes (most connected - your core abstractions)
1. `User` - 9 edges
2. `require-dev` - 9 edges
3. `scripts` - 9 edges
4. `Laravel Boost Guidelines` - 8 edges
5. `Laravel Boost Guidelines` - 8 edges
6. `Planes, programas y suscripciones IB — BDS` - 8 edges
7. `Progresión multi-módulo por puntos — BDS` - 8 edges
8. `Reglas y recompensas IB — BDS` - 8 edges
9. `Arquitectura` - 8 edges
10. `setup` - 7 edges

## Surprising Connections (you probably didn't know these)
- `ExampleTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/ExampleTest.php → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (39 total, 5 thin omitted)

### Community 0 - "composer.json"
Cohesion: 0.06
Nodes (34): autoload, autoload-dev, psr-4, psr-4, description, extra, laravel, keywords (+26 more)

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

### Community 5 - "User"
Cohesion: 0.10
Nodes (15): User, UserFactory, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Attributes\Hidden, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Database\Eloquent\Factories\HasFactory (+7 more)

### Community 6 - "Planes, programas y suscripciones IB — BDS"
Cohesion: 0.25
Nodes (8): Contexto, Decisiones pendientes, Ejemplos de producto, Eventos de negocio, Glosario, Planes, programas y suscripciones IB — BDS, Reglas de dominio, Relaciones

### Community 7 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.23
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 8 - "Progresión multi-módulo por puntos — BDS"
Cohesion: 0.25
Nodes (8): Auditoría de una contribución, Contexto, Decisiones pendientes, Ejemplos de conversión, Flujo de dominio, Glosario, Progresión multi-módulo por puntos — BDS, Reglas de dominio

### Community 9 - "Reglas y recompensas IB — BDS"
Cohesion: 0.25
Nodes (8): Contexto, Decisiones pendientes, Ejemplo de reutilización CPA, Eventos de negocio, Glosario, Reglas de dominio, Reglas y recompensas IB — BDS, Relaciones

### Community 10 - "docs/README.md"
Cohesion: 0.10
Nodes (17): Deployment, graphify, Laravel Pint Code Formatter, PHP, PHPUnit, Running Tests, Business Domain Specifications, Convenciones (+9 more)

### Community 11 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 13 - "bootstrap/app.php"
Cohesion: 0.40
Nodes (4): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request

### Community 14 - "TestCase"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

### Community 15 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 29 - "Arquitectura"
Cohesion: 0.25
Nodes (8): Arquitectura, Decisiones pendientes, Dependencias, Estado, Integración, Organización interna prevista, Persistencia y auditoría, Principios obligatorios

### Community 30 - "Seguridad"
Cohesion: 0.29
Nodes (7): APIs e integraciones, Configuración dinámica, Datos personales y auditoría, Desarrollo, Dinero y recompensas, Principios obligatorios, Seguridad

### Community 31 - "Estrategias configurables"
Cohesion: 0.29
Nodes (7): Configuración, Estrategias configurables, Fallos, Objetivo, Registry, Separación obligatoria, Versionado

### Community 32 - "Do Things the Laravel Way"
Cohesion: 0.33
Nodes (6): APIs & Eloquent Resources, Do Things the Laravel Way, Model Creation, Testing, URL Generation, Vite Error

### Community 33 - "Documentación de IB Service"
Cohesion: 0.33
Nodes (6): [`bds/`](bds/README.md), Documentación de IB Service, Jerarquía de autoridad, Mantenimiento, [`rules/`](rules/README.md), Secciones

### Community 34 - "Propósito y mantenimiento de los BDS"
Cohesion: 0.33
Nodes (6): Contenido obligatorio, Contenido prohibido, Convenciones, Definición, Flujo de cambio, Propósito y mantenimiento de los BDS

### Community 35 - "Mejores prácticas de programación"
Cohesion: 0.33
Nodes (6): Confiabilidad, Datos y cálculos, Diseño, Mejores prácticas de programación, Observabilidad, Pruebas

### Community 36 - "Instrucciones del repositorio"
Cohesion: 0.40
Nodes (5): Fuentes de verdad, Instrucciones del repositorio, Mantenimiento documental, Routing documental obligatorio, Uso obligatorio de Graphify

### Community 37 - "Laravel Boost"
Cohesion: 0.50
Nodes (4): Artisan, Laravel Boost, Project Rules, Tinker

### Community 38 - "Estilo de código"
Cohesion: 0.50
Nodes (4): Estilo de código, Formato, Laravel, PHP

## Knowledge Gaps
- **166 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+161 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **5 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `scripts` connect `scripts` to `composer.json`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Why does `Laravel Boost Guidelines` connect `Laravel Boost Guidelines` to `docs/README.md`?**
  _High betweenness centrality (0.017) - this node is a cross-community bridge._
- **Why does `Planes, programas y suscripciones IB — BDS` connect `Planes, programas y suscripciones IB — BDS` to `docs/README.md`?**
  _High betweenness centrality (0.017) - this node is a cross-community bridge._
- **What connects `Controller`, `$schema`, `name` to the rest of the system?**
  _166 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.05714285714285714 - nodes in this community are weakly interconnected._
- **Should `scripts` be split into smaller, more focused modules?**
  _Cohesion score 0.08 - nodes in this community are weakly interconnected._
- **Should `package.json` be split into smaller, more focused modules?**
  _Cohesion score 0.09523809523809523 - nodes in this community are weakly interconnected._