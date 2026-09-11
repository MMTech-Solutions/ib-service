# Arquitectura

## Estado

Arquitectura base aprobada. Este documento define la estructura y las fronteras que deben respetar las primeras implementaciones. Las decisiones que todavía no están cerradas se enumeran al final y no deben resolverse silenciosamente desde el código.

`broker-service` es una referencia de nomenclatura y experiencia previa, no una fuente de verdad arquitectónica para este repositorio. Sus implementaciones existentes pueden incumplir sus propias reglas y deben evaluarse antes de reutilizarse.

## Principios obligatorios

- El servicio constituye el bounded context autoritativo de planes, programas, suscripciones, progresión, reglas y recompensas IB.
- Plan es la raíz funcional; los módulos externos suministran hechos o métricas y no controlan las reglas IB.
- La organización interna es **Feature First con puertos y adaptadores**.
- El servicio desplegable no se utiliza como única frontera interna: cada feature protege sus datos e implementación.
- Broker, Copy Trading, Prop Firm y futuros módulos se integran independientemente mediante contratos explícitos.
- La indisponibilidad de un módulo no debe bloquear el procesamiento de módulos no relacionados.
- Identity conserva autoridad sobre identidad y red de referidos.
- Finance conserva autoridad sobre dinero asentado.
- Los identificadores de otros servicios son referencias opacas; no se simulan foreign keys distribuidas.
- Todo código propio está tipado. Los límites usan DTOs, objetos de valor o contratos explícitos, nunca arrays sin contrato cuando la estructura sea conocida.

## Estructura de un feature

La estructura conserva la terminología utilizada por el equipo en `broker-service`: `UseCases` representa la orquestación de aplicación y `Http` permanece en la raíz del feature. No se imponen directorios globales `Application`, `Domain`, `Infrastructure` o `Delivery`.

```text
app/
├── Features/
│   ├── SharedKernel/
│   └── <Feature>/
│       ├── Console/
│       ├── Http/
│       │   └── V1/
│       │       ├── Controllers/
│       │       ├── Commands/
│       │       ├── Requests/
│       │       ├── Resources/
│       │       └── Middleware/
│       ├── UseCases/
│       ├── Actions/
│       ├── Contracts/
│       │   ├── Ports/
│       │   │   ├── Input/
│       │   │   └── Output/
│       │   ├── Repositories/
│       │   ├── Strategies/
│       │   ├── Data/
│       │   └── Events/
│       ├── DTOs/
│       ├── ValueObjects/
│       ├── Models/
│       ├── Repositories/
│       ├── Factories/
│       ├── Services/
│       │   ├── Adapters/
│       │   └── Strategies/
│       ├── Exceptions/
│       ├── Events/
│       ├── Listeners/
│       ├── Jobs/
│       └── Support/
├── SharedFeatures/
│   └── User/
│       ├── Context/
│       │   └── Contracts/
│       └── UserServiceProvider.php
└── Support/
```

No se crean todos esos directorios por anticipado. Cada feature incorpora únicamente los artefactos que necesita y conserva esos nombres cuando aparezca la responsabilidad correspondiente.

La infraestructura de Laravel que ejecuta responsabilidades de negocio pertenece
al feature o subfeature propietario. Sus comandos Artisan viven directamente en
`Console`, sus trabajos en `Jobs`, sus listeners en `Listeners` y sus
eventos internos en `Events`, todos dentro de ese límite. No se ubican comandos,
jobs, listeners ni eventos propios de un feature en directorios globales bajo
`app/`.

`Console` no introduce una subdivisión `Commands`: todas sus clases son comandos
Artisan y se nombran con el sufijo `Command`.

Cada service provider de feature registra explícitamente sus comandos de consola
y cualquier listener que no sea descubierto mediante la convención vigente de
Laravel. Los archivos globales de `routes/console.php` y la configuración de
bootstrap se reservan para composición transversal o tareas que no pertenecen a
un feature de negocio; no son una ubicación alternativa para sus clases.

La lista inicial de features de primer nivel es orientativa y puede cambiar al cerrar el modelo de cada capacidad:

```text
app/Features/
├── Modules/
├── Plans/
├── Programs/
├── Subscriptions/
├── Progression/
├── Rules/
└── Rewards/
```

Crear una nueva raíz directamente bajo `app/` o introducir un tipo de artefacto base que no esté autorizado aquí requiere aprobación. Crear un feature o una subdivisión necesaria dentro de esta estructura no requiere una aprobación adicional, pero sí debe respetar el BDS y las reglas aplicables.

## Feature compuesto Modules

`Modules` es un feature de primer nivel y compuesto. Representa los módulos que pueden aportar actividad a planes y programas, sus capacidades y la adaptación de sus fuentes externas.

```text
Features/Modules/
├── Contracts/
│   ├── Ports/
│   │   ├── Input/
│   │   └── Output/
│   └── Data/
├── Shared/
├── Catalog/
├── Sources/
│   ├── TradingAccounts/
│   ├── Broker/
│   ├── PropFirm/
│   └── CopyTrading/
├── Broker/
├── CopyTrading/
├── PropFirm/
└── HedgeFund/
```

- `Catalog` persiste la identidad, código estable, `is_active`,
  `processing_status` y las capacidades implementadas por cada módulo.
- `Sources` centraliza el acceso a cada sistema externo y evita duplicar SDKs, clientes HTTP, repositories o mapeos entre módulos.
- Los planes y programas referencian módulos registrados; no copian ni redefinen su identidad.
- Cada subfeature de módulo implementa únicamente las capacidades que realmente
  puede proporcionar. Una capacidad es vocabulario respaldado por una
  implementación y no puede concederse administrativamente.
- Se prefieren puertos tipados por capacidad, como actividad de trading, depósitos o challenges, frente a una interfaz universal con campos o métodos opcionales.
- Se prefieren Data especializados por actividad frente a un `ModuleActivityData` genérico lleno de propiedades opcionales.
- `Modules` obtiene, valida y normaliza actividad. `Programs`, `Rules`, `Progression` y `Rewards` deciden cómo configurarla, interpretarla o pagarla.
- El catálogo no almacena credenciales, tokens, endpoints arbitrarios ni nombres de clases. La configuración técnica vive en archivos de configuración o gestores de secretos y las factories resuelven implementaciones mediante allowlists.
- Las referencias de instrumentos específicas de Broker, Copy Trading u otro módulo se resuelven dentro del subfeature propietario y se exponen como contratos tipados.
- Broker puede implementar temporalmente los puertos destinados al futuro Trading Account Service, exponiendo únicamente las necesidades de IB. La sustitución posterior cambia el adapter o repository de salida, no los consumidores ni los contratos de `Modules`.
- Un módulo puede combinar varias fuentes: Broker puede obtener depósitos desde Broker Service y volumen, PnL, cuentas o símbolos desde Trading Account Service; PropFirm puede obtener challenges desde PropFirm Service y cuentas o métricas desde Trading Account Service.

El catálogo de módulos no se versiona como un agregado completo. La configuración publicada del programa conserva un snapshot inmutable de la semántica y capacidades utilizadas, mientras mantiene una relación directa con el registro del módulo para consultar su control operativo actual.

La disponibilidad y el estado de procesamiento no forman parte del snapshot ni
crean una nueva versión. Se modelan por separado:

- `is_active` indica si el módulo participa en el sistema. Un módulo inactivo no
  puede seleccionarse, ingerir eventos, consultar actividad externa, calcular
  ni pagar.
- `processing_status` es un estado explícito, inicialmente `running` o
  `paused`. En `paused` se detienen cálculos y pagos, pero continúan la ingesta y
  las consultas de actividad necesarias para preservar el período configurado.
- Un módulo activo con procesamiento pausado continúa siendo seleccionable.
- Desactivar el módulo no reescribe `processing_status`; al reactivarlo conserva
  la condición operativa previa y no se reanuda accidentalmente.

La condición que se presenta al consumidor se deriva de ambos campos: inactivo,
activo procesando o activo con procesamiento pausado. No se persiste un tercer
estado redundante.

La disponibilidad y el control de procesamiento tienen semánticas diferentes:

- `is_active` es el apagado total y prevalece también sobre programas
  publicados.
- `processing_status = paused` es el botón de pánico para cálculos y pagos del
  módulo completo, sin perder la captura de actividad.
- El control no se configura por capacidad o etapa en esta fase.
- Los eventos recibidos para un módulo inactivo se confirman en el transporte,
  no generan actividad de dominio ni replay automático y dejan evidencia
  técnica y métricas de rechazo.

### Registro técnico y sincronización del catálogo

Los módulos y capacidades disponibles se definen mediante un registro cerrado
controlado por código. El registro utiliza claves estables y puede apoyarse en
Enums u objetos tipados; nunca persiste ni permite seleccionar nombres de
clases PHP. `ModulesServiceProvider` relaciona esas claves con implementaciones
concretas mediante mapas cerrados.

El comando idempotente `modules:sync` reconcilia ese registro con el catálogo
persistido:

- crea identidades nuevas y actualiza únicamente datos gobernados por código;
- sincroniza las capacidades respaldadas por implementaciones existentes;
- preserva `is_active`, `processing_status` y demás valores gobernados por
  administración;
- desactiva y reporta un módulo persistido cuya implementación dejó de existir;
- nunca reactiva automáticamente un módulo que vuelva a aparecer en el
  registro.

Los módulos nuevos nacen activos y con procesamiento `running`. El nombre y la
descripción iniciales del módulo proceden del registro, pero pasan a ser datos
administrativos y no se sobrescriben en sincronizaciones posteriores. Las
capacidades retiradas se conservan inactivas; sus datos descriptivos siguen
gobernados por código y se reactivan cuando reaparece su implementación.

La concurrencia del catálogo es optimista mediante `lock_version`, un detalle
de persistencia que no representa una versión funcional. El listado no lo
expone; el detalle y las respuestas de mutación lo entregan únicamente como
token de concurrencia. No existen revisiones históricas del catálogo ni una
tabla de versiones de módulos.

La opción explícita `--prune` puede eliminar solo registros que nunca hayan
sido referenciados. Debe rechazar y reportar cualquier eliminación que rompa
relaciones o auditoría; esos registros permanecen inactivos. El sync ordinario
es conservador y no elimina registros.

## Features compuestos y subfeatures

Un feature puede contener subfeatures cuando representa una capacidad compuesta, por ejemplo `Plans/Catalog` y `Plans/Templates`. Cada subfeature conserva sus propios `Http`, `UseCases`, `Actions`, repositories, factories y modelos cuando los necesite.

Un feature compuesto puede declarar un hermano `Shared`:

```text
Features/Plans/
├── Shared/
│   ├── Contracts/
│   ├── DTOs/
│   ├── ValueObjects/
│   └── Services/
├── Catalog/
└── Templates/
```

`Shared` se limita a conceptos y lógica que pertenecen al feature padre y son realmente compartidos por sus subfeatures:

- Solo puede ser consumido por subfeatures del mismo feature compuesto.
- Puede contener contratos, DTOs, objetos de valor y servicios internos compartidos.
- No contiene controllers, UseCases, Actions, repositories ni modelos compartidos.
- No se utiliza como ubicación provisional ni como sustituto de decidir quién es dueño de un concepto.

## SharedKernel de la aplicación

`app/Features/SharedKernel` contiene conceptos de valor transversales a toda la aplicación. Se utiliza ese nombre, en lugar de `Shared`, para no confundirlo con el `Shared` interno de un feature compuesto.

Puede contener objetos de valor estables como `Money`, `PositiveMoney`, `Number`, `Currency`, cantidades, identificadores base y sus contratos estrictamente necesarios. No contiene UseCases, Actions, controllers, repositories, modelos Eloquent, clientes HTTP, SDKs ni reglas pertenecientes a un feature concreto.

Incorporar un concepto al Shared Kernel requiere que tenga la misma semántica e invariantes para todos sus consumidores. La mera repetición de una clase no basta para promoverla.

## Shared Features de la aplicación

`app/SharedFeatures` contiene fronteras de aplicación transversales que necesitan
adaptar infraestructura o servicios externos y, por ello, no pertenecen al
`SharedKernel`. No es una ubicación genérica para reutilización: cada shared
feature debe tener una responsabilidad transversal estable, al menos un
consumidor real y estar diseñada para servir a más de un feature de negocio.

`SharedFeatures/User` es la frontera única para la identidad autenticada y sus
capacidades dentro de la aplicación:

- `UserContext` es la API consumida por Requests, UseCases, Actions y Services.
- `UserConnectorInterface` desacopla esa API del mecanismo de autenticación.
- `GatewayUserConnector` encapsula `Mmtech\Rbac\Auth\GatewayUser` y
  `PermissionCheckerInterface`; esos tipos no se propagan a features de negocio.
- `UserServiceProvider` es el único punto que obtiene el usuario desde el guard
  de Laravel y construye el connector con alcance de request.
- Toda comprobación de capacidad recibe una `UserSurface` explícita; no infiere
  silenciosamente la surface desde el path fuera del middleware de transporte.

El código de aplicación no utiliza `auth()`, la facade `Auth`,
`request()->user()` ni `$request->user()` para obtener la identidad actual.
Requests y demás consumidores inyectan `UserContext`. El acceso al guard queda
confinado a `UserServiceProvider` y al middleware de autenticación; el connector
recibe ya construido el `GatewayUser` que debe adaptar.

## Contratos y comunicación entre features

### Puertos de entrada

Si Feature A necesita una capacidad expuesta por Feature B:

1. B, como propietario de la capacidad, publica un puerto pequeño y cohesivo en sus `Contracts`.
2. Un UseCase de B implementa el puerto de entrada.
3. A depende del puerto, nunca del UseCase concreto.
4. A envía objetos contractuales tipados y objetos de valor del `SharedKernel`.
5. B no conoce a A ni crea contratos nombrados por el consumidor.

Los puertos se diseñan por necesidad o capacidad cohesiva, no por consumidor. No se crea una interfaz universal que acumule operaciones no relacionadas. Un adaptador puede colaborar con varios puertos cuando todos pertenecen a la misma integración técnica, sin convertirlos en un contrato único.

Un puerto público no se crea anticipando consumidores hipotéticos. Se diseña
cuando aparece el primer caso de uso real que cruza la frontera, como parte de
la entrega vertical de ese consumidor. El feature proveedor conserva la
propiedad del puerto y de su implementación, mientras la necesidad observada
determina el contrato mínimo. Los adapters HTTP o Console internos no justifican
por sí solos publicar un contrato inter-feature.

Para la jerarquía inicial, `Plans` es el primer consumidor de `Modules` al
seleccionar módulos para un plan. `Programs` se diseña después y parte de los
módulos habilitados por su plan; no consulta el catálogo global ignorando al
Plan IB como raíz funcional.

Un UseCase no importa ni ejecuta directamente otro UseCase. Sí puede consumir el puerto de entrada de otro feature aunque la implementación resuelta por Laravel sea un UseCase del feature proveedor.

Ejemplo canónico:

```text
Programs UseCase
    ↓ depende de ListModuleSymbolsPort
Modules/ListModuleSymbolsUseCase
    ↓ depende de TradingSymbolsSourcePort
BrokerTradingSymbolsAdapter               # sustituto temporal
    ↓
broker-service

TradingAccountServiceTradingSymbolsAdapter # implementación futura
    ↓
Trading Account Service
```

`Programs` nunca importa `ListModuleSymbolsUseCase`, el adapter concreto ni tipos de Broker o Trading Account Service. El cambio de fuente es transparente para el consumidor.

### Puertos de salida y adapters

Un puerto de salida pertenece al feature que necesita acceder a una capacidad externa. Ese mismo feature contiene el adapter o repository que implementa el puerto y traduce el SDK, HTTP, Kafka o persistencia hacia sus contratos propios.

Los puertos de salida no exponen tipos del proveedor externo. Cuando varias partes de `Modules` utilizan el mismo servicio externo, la implementación se centraliza bajo `Modules/Sources/<Source>` y los módulos componen esas capacidades sin duplicar integraciones.

### Objetos que cruzan la frontera

Los puertos aceptan objetos contractuales de `Contracts/Data`, Queries tipadas y objetos de valor del `SharedKernel`. No reciben entidades, modelos o DTOs internos del consumidor. Sus resultados siguen la misma regla y no exponen la implementación interna del proveedor.

Solo los artefactos deliberadamente publicados en `Contracts` pueden cruzar una frontera entre features. No pueden cruzarla:

- Modelos Eloquent.
- Entidades u objetos de valor internos.
- Repositories ni factories.
- UseCases ni Actions.
- Services internos.
- Builders, queries Eloquent o tipos pertenecientes a un SDK.

Los DTOs de contrato son inmutables, completamente tipados y estables para sus consumidores. Un DTO interno vive en `DTOs`; uno que forme parte de un puerto público puede vivir en `Contracts/Data`. De igual forma, un evento interno vive en `Events` y un evento publicado y versionado vive en `Contracts/Events`.

## UseCases, Actions y Services

### UseCases

- Son los puntos de entrada de aplicación para una intención completa.
- Orquestan Actions, Services internos, factories, repositories obtenidos mediante factories y puertos de otros features.
- No contienen lógica HTTP.
- Un UseCase nunca invoca otro UseCase.
- Puede invocar un puerto de entrada de otro feature sin conocer el UseCase concreto que lo implementa.
- El UseCase es propietario de la transacción de aplicación cuando un flujo requiere atomicidad entre varias operaciones locales.
- Un UseCase exclusivamente HTTP puede recibir el Command inmutable de `Http/V1/Commands` como excepción pragmática a la dirección física de dependencias.
- Si el mismo UseCase se invoca desde HTTP, Kafka, CLI o Jobs, recibe un input neutral en `DTOs`; cada adaptador de entrada construye ese input y no reutiliza Commands pertenecientes a otro transporte.

### Actions

- Representan una operación de aplicación concreta, acotada y nombrable.
- Solo pueden utilizarse dentro del feature o subfeature donde se declaran.
- No se comparten entre subfeatures, aunque pertenezcan al mismo feature compuesto.
- No reciben Requests HTTP ni actúan como frontera pública.
- La atomicidad de una Action describe una intención indivisible; no implica abrir por sí sola una transacción independiente de la coordinada por el UseCase.

### Services

- Son implementación interna del feature, no su frontera pública.
- Encapsulan funcionalidad extendida, algoritmos, integración o colaboración interna que no corresponde a un UseCase, Action o Repository.
- `Services/Strategies` contiene familias de algoritmos intercambiables.
- `Services/Adapters` contiene implementaciones que traducen contratos externos o puertos.
- Un Service puede moverse o elevarse a `Shared/Services` cuando la lógica pertenece al feature padre y debe ser compartida por varios subfeatures.

## Composición con Laravel

Inicialmente todos los bindings de `Modules`, sus puertos, adapters, repositories, factories y fuentes externas se registran explícitamente en `app/Providers/ModulesServiceProvider.php`.

- No se utilizará un patrón `Registrar` para los módulos.
- Las clases concretas que Laravel puede construir automáticamente no requieren un binding manual.
- El provider registra únicamente contratos que necesitan seleccionar o sustituir implementación y la composición de sus factories.
- El crecimiento de `ModulesServiceProvider` se acepta en esta fase. Su división o descubrimiento automático se decidirá posteriormente con evidencia real de complejidad.
- Configuración o registros persistidos nunca proporcionan nombres de clases arbitrarios al contenedor; toda selección utiliza mapas cerrados controlados por código.

## Repositories y factories

El repository abstrae una colección o fuente de datos relevante para el dominio, aunque la implementación consulte PostgreSQL, memoria, otro microservicio o un SDK.

- Cada repository tiene una interfaz en `Contracts/Repositories` y al menos una implementación en `Repositories`.
- Cada repository se obtiene mediante su factory en `Factories`; UseCases, Actions, Jobs y Services nunca instancian un repository directamente.
- La factory expone únicamente `make(...)` y retorna la interfaz correspondiente.
- La factory es el punto de composición y puede seleccionar implementaciones persistentes, remotas o en memoria usando contexto tipado.
- La factory no contiene reglas de negocio ni detecta silenciosamente `APP_ENV` para cambiar el comportamiento.
- Los tests pueden inyectar una factory controlada que retorne un repository en memoria o un test double.
- Una estrategia de repository selecciona la fuente o forma de acceso; una estrategia de negocio interpreta los datos obtenidos. Ambas decisiones permanecen separadas.

Un repository remoto es apropiado cuando la integración se presenta al dominio como una colección consultable de hechos, por ejemplo actividad de Prop Firm. No debe utilizarse como nombre alternativo para cualquier gateway con comandos o efectos laterales arbitrarios.

## Modelos, entidades, DTOs y objetos de valor

- Los modelos Eloquent son detalles de persistencia y no salen del repository propietario.
- Ningún modelo se lee ni se modifica desde un UseCase, Action, Resource u otro feature.
- El repository devuelve entidades, objetos de valor, DTOs o read models tipados según el caso.
- Una respuesta de SDK se mapea en el adapter o repository; los tipos del SDK no se propagan al resto del feature.
- Solo se crea una entidad cuando IB gobierna su identidad y ciclo de vida.
- Solo se crea un modelo para datos persistidos localmente; una actividad externa no requiere un modelo salvo que exista un snapshot, caché o proyección local explícita.

`spatie/laravel-data` es la tecnología obligatoria para Commands, DTOs, resultados, filtros y payloads serializables. Los objetos de valor dedicados se conservan para conceptos con invariantes, igualdad por valor u operaciones propias, como dinero, puntos, períodos, cantidades ponderadas o referencias de símbolo. Un `Data` puede contener objetos de valor; una misma clase no debe asumir ambiguamente ambos roles.

## Actividad externa

La obtención, normalización e interpretación de actividad son responsabilidades separadas:

```text
SDK o API del módulo
    ↓
Repository / Adapter del módulo
    ↓
Activity Data normalizado
    ↓
Contribution o Reward Strategy
    ↓
Contribución, puntos o recompensa auditable
```

- El repository decide de dónde obtener la actividad.
- El adapter traduce el contrato técnico externo.
- El DTO representa la observación normalizada.
- La strategy decide cómo convertirla en contribución, puntos o recompensa.
- Las factories de repository y strategy resuelven decisiones diferentes y no deben combinarse.

## HTTP y Resources

El flujo HTTP canónico es:

```text
FormRequest → Http/V1/Command → UseCase → Result Data → Controller → API normalizer
```

- Cada operación HTTP define su Command en `Http/V1/Commands`; no vive en `DTOs` ni forma parte de los contratos públicos del feature.
- El Command representa la intención de entrada HTTP, es inmutable y se implementa preferentemente con `spatie/laravel-data`.
- `Command::fromRequest()` construye el objeto desde `$request->validated()` y añade explícitamente parámetros de ruta o contexto autorizado.
- `$request->all()` no se usa como fuente general de Commands porque incluye campos no validados. Solo puede considerarse si `laravel-data` sustituye deliberadamente al FormRequest y realiza toda la validación mediante una convención futura documentada.
- Después de construirse, el Command no conserva referencias a `Request`, usuarios Eloquent ni otros objetos del framework.
- Los controllers son delgados y normalizan la respuesta mediante `mmt/api-response-normalizer`.
- El envelope HTTP pertenece al normalizador; el payload tipado pertenece a `spatie/laravel-data`.
- Las excepciones de aplicación que representan errores esperados para la API extienden `App\Support\Exceptions\ApiException`. Laravel las convierte automáticamente al envelope normalizado y no las reporta; las excepciones técnicas inesperadas conservan el manejo y reporte predeterminados del framework.
- Un API Resource es opcional y solo se utiliza cuando la representación HTTP difiere del resultado de aplicación por audiencia, permisos, enlaces o campos condicionales.
- Un Resource es un transformador puro: no inyecta ni resuelve factories, repositories, Services o UseCases.
- Un Resource no ejecuta queries, no carga relaciones y no accede a modelos de otro feature.
- Toda información requerida por un Resource debe llegar resuelta desde el UseCase mediante un DTO o read model.

Las implementaciones de Resources en `broker-service` no constituyen precedente cuando violan estas reglas.

## Support

`app/Support` contiene utilidades y primitivas técnicas transversales, sin lenguaje ni reglas de negocio. Puede alojar abstracciones acopladas al framework cuando representan una política técnica común a toda la aplicación y no constituyen por sí mismas una frontera de aplicación o integración. El `Support` de un feature sigue la misma regla dentro de su alcance.

Son candidatos válidos clocks, serialización técnica, paginación, identificadores base, utilidades de testing y excepciones base de transporte como `App\Support\Exceptions\ApiException`. Las excepciones específicas permanecen en el feature propietario y solo heredan de esa base cuando representan un error esperado de la API. No pertenecen a `Support` calculadores de rewards, reglas de elegibilidad, DTOs compartidos ni helpers nombrados por conceptos de negocio.

## Integración

- Las reglas detalladas de eventos, Kafka, IAM, SDKs y clientes HTTP están en [`integrations.md`](integrations.md).
- Preferir eventos para hechos consumidos de manera continua.
- Proveer consultas internas paginadas para reconciliación, replay y métricas de período.
- Utilizar outbox en productores e inbox/idempotencia en consumidores cuando se implemente mensajería.
- Versionar eventos y APIs desde su primera publicación.
- Los productores no conocen planes, programas ni beneficiarios IB.

## Persistencia y auditoría

- Configuraciones publicadas y cálculos históricos son inmutables.
- Cambios funcionales crean versiones explícitas.
- Rewards y contribuciones guardan las referencias y entradas que justifican su resultado.
- Dinero y cantidades utilizan representación decimal precisa.

## Decisiones pendientes

- Mapa definitivo de los features restantes y sus subfeatures; `Modules` ya está aprobado como feature compuesto.
- Organización futura de `ModulesServiceProvider` si el volumen real de bindings justifica dividirlo.
- Forma de registrar implementaciones y estrategias dentro de las factories.
- Contrato común de consulta y paginación de actividad por módulo.
- Política de DTOs públicos y versionado de contratos internos.
- Manejo tipado de errores entre features y desde integraciones externas.
- Formato definitivo del envelope y catálogo inicial de eventos.
- Límites transaccionales, Unit of Work y coordinación con outbox.
- Estrategia de consistencia entre runs, actividad tardía y settlement.
- Contratos S2S, autenticación, observabilidad y topología de despliegue.
- Herramienta y reglas automáticas para tests de arquitectura.
