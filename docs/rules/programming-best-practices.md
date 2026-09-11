# Mejores prácticas de programación

## Diseño

- Modelar primero el lenguaje y las invariantes del BDS.
- Mantener funciones y clases enfocadas en una responsabilidad identificable.
- Favorecer composición y contratos explícitos sobre herencia profunda.
- Evitar abstracciones genéricas antes de contar con dos usos reales y compatibles.
- No convertir detalles de un módulo en conceptos obligatorios del núcleo IB.
- No usar implementaciones existentes de `broker-service` como precedente cuando contradigan las reglas de este repositorio.
- Mantener separadas la obtención de actividad, su normalización y su interpretación económica.
- No permitir que un UseCase invoque otro UseCase ni que una Action cruce su feature o subfeature.
- Acceder a repositories exclusivamente mediante factories.
- Mantener modelos Eloquent dentro de su repository propietario.
- Mantener Commands junto a su adaptador de entrada y no reutilizar Commands HTTP para eventos Kafka, Jobs o CLI.
- Separar eventos internos de dominio, eventos de integración y mensajes dirigidos a una audiencia externa.

## Datos y cálculos

- Utilizar decimales exactos para dinero, tasas, lotes y puntos.
- Definir moneda, precisión y unidad junto a toda cantidad que lo requiera.
- Trabajar con intervalos temporales explícitos y preferiblemente semiabiertos `[from, to)`.
- Congelar versiones y valores utilizados en resultados auditables.
- Imponer idempotencia mediante constraints persistentes además de verificaciones de aplicación.

## Pruebas

- Priorizar pruebas de feature para casos de uso y reglas de dominio observables.
- Añadir pruebas unitarias a estrategias y objetos de valor con matrices de borde.
- Cubrir duplicados, redelivery, límites temporales, precisión y reversas.
- Reproducir un defecto con una prueba antes de corregirlo cuando sea viable.
- Ejecutar la suite más estrecha que demuestre el cambio y ampliar según el riesgo.
- Proveer repositories en memoria o test doubles mediante factories, sin introducir ramas por entorno en la lógica productiva.
- Ejecutar contract tests compartidos contra las implementaciones en memoria, persistentes y remotas de un mismo repository.
- Incorporar tests de arquitectura cuando se elija la herramienta, especialmente para impedir imports entre features y dependencias desde Resources hacia acceso a datos.

## Confiabilidad

- Diseñar consumidores para entrega al menos una vez.
- Separar reintentos recuperables de errores contractuales permanentes.
- No mantener transacciones de base de datos abiertas durante llamadas de red.
- Publicar hechos después de persistir mediante un mecanismo transaccional confiable.
- Registrar correlation ID, causation ID y claves de idempotencia sin exponer datos sensibles.

## Observabilidad

- Emitir métricas por strategy, módulo, resultado y causa de descarte.
- Los logs deben explicar decisiones técnicas, no sustituir el ledger auditable.
- Toda operación distribuida debe poder seguirse de extremo a extremo.
