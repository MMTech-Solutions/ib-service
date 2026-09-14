# Programs P2: entregas verticales

Estado: **Listo** (pendiente de implementación)
Dependencia: etapas de inventario y dominio P2

## P2 — Publicación, snapshots y umbrales

Entrega vertical de HTTP a persistencia (cuando se implemente):

- umbral de entrada entero por programa y validación del ladder del plan;
- publicación de una versión inmutable con selecciones, umbral congelado y
  snapshots de módulo;
- republicación que crea una nueva versión sin mutar el historial;
- consulta de configuración vigente e historial de versiones;
- puerto mínimo Programs → Modules para leer semántica/capacidades al
  publicar;
- rechazo de publicar sin módulos habilitados o con ladder inválido.

Criterio de aceptación: un operador con `ib.programs.manage` define umbrales
estrictamente crecientes en los programas de un plan no archivado, selecciona
al menos un módulo habilitado y publica. Obtiene la versión vigente con
snapshots. Tras cambiar umbral o módulos, republica y ve una nueva versión;
la anterior permanece consultable e inmutable. Publicar sin módulos o con
umbrales no crecientes se rechaza. Un plan archivado rechaza publicar.

### Superficie HTTP (provisional)

Nombres de ruta no definitivos hasta la implementación:

- `PATCH .../programs/{program}` admite `entry_threshold` en el borrador
  (además de los campos de PR1).
- `POST .../programs/{program}/publications` publica una nueva versión.
- `GET .../programs/{program}/publications/current` configuración vigente.
- `GET .../programs/{program}/publications` historial paginado.
- `GET .../programs/{program}/publications/{version}` detalle de versión.

Permiso: `ib.programs.manage` en la surface `admin_panel` (mismo que PR1,
salvo decisión distinta en implementación).

### Contratos entre features

- Reutiliza `ResolvePlanContextPort` / `PlanContextData` V1.
- Origina un puerto mínimo de lectura de módulo para snapshot (semántica +
  capacidades). Disponibilidad y procesamiento no viajan en el snapshot.
- No publica puerto hacia Rules ni Subscriptions en P2.

## Fuera de P2

- Configuración de actividad o scope (`BR-MODULE-004`).
- Suscripciones, placement ejecutado y anclaje.
- Rules, Progression, Rewards.
- Eventos Kafka de integración.
- Activación, desactivación o archivo del programa.

## Criterios de salida (documentales)

Alcance, dependencias y criterios de aceptación de P2 están definidos para
comenzar implementación. La evidencia de extremo a extremo se registrará en
un `11-p2-implementation.md` (o equivalente) cuando exista código.
