# Settings y certificación de conexiones

Estado: **obligatoria**. Última revisión: 2026-10-07.

## Fronteras y catálogo

Settings es un feature global en app/Features/Settings. Su catálogo explícito cubre
rewards, modules y finance excepto repository. No descubre claves dinámicamente ni
incorpora la infraestructura de Laravel, RBAC/Kafka base, Lab o configuraciones
particulares/versionadas de otros features.

Las definiciones declaran clave, dominio (Rewards/Modules/Finance), sección,
proveedor opcional, posición, nombre, descripción, tipo, nulabilidad, sensibilidad,
schema_version y límites. Sus cambios pertenecen al código. Los archivos config
conservan el respaldo y son el único lugar que consulta env.

Settings publica ResolveSettingsPort::execute(list<string>): ResolvedSettingsData.
La captura devuelve valores efectivos para una operación. Los consumidores no
acceden a repositories de Settings ni reemplazan el repositorio global de config.
Los procesadores de volumen/PnL y las operaciones Finance capturan sus valores
una vez al iniciar la operación. No hay caché persistente.

## Persistencia y sincronización

settings guarda definición JSONB, valor, modo stored/fallback, lock_version e instantes.
Un null almacenado permitido es distinto de ausencia. El modo fallback no conserva
valor anterior. Los valores secretos se cifran con Encrypter; APP_KEY y claves
anteriores son infraestructura externa a Settings. Rotar esas claves debe conservar
capacidad de descifrar los registros vigentes.

settings:sync copia respaldos para altas, conserva valores/modos en claves existentes,
actualiza definiciones y elimina claves retiradas. --dry-run valida y muestra conteos
sin escribir. El catálogo completo y sus valores se validan antes de aplicar cambios.
Un conflicto aborta toda la operación. Sync, edición y reset comparten una transacción
con advisory lock PostgreSQL 71931007; lock_version protege clientes administrativos.

Los errores de BD/cifrado se propagan; nunca activan un respaldo silencioso.
Migraciones, ayuda, arranque y config:cache no resuelven valores persistidos.
Ejecutar sync únicamente desde la versión desplegada, después de migraciones.
No ejecutarlo desde versiones antiguas durante un despliegue mixto.

## HTTP y secretos

API administrativa: GET settings, GET/PATCH settings/{key}, POST settings/{key}/reset.
El listado parte del catálogo y muestra opciones no sincronizadas sin escribir.
Su colección es plana con metadatos de dominio/sección/proveedor para agrupación;
paginación y filtros pertenecen a meta. Editar/resetear una clave no sincronizada
devuelve 409; clave desconocida, 404.

Permisos AdminPanel: ib.settings.read, ib.settings.manage e ib.settings.secrets.manage.
Gestionar secretos exige ambos permisos de gestión. Valores sensibles no aparecen
en responses, historial, salida CLI ni errores de certificación. Las URLs configurables
son HTTP(S), sin userinfo/query/fragment; tokens no aceptan caracteres de control.
No se permite código ejecutable ni selección de clases desde valores guardados.

Auditoría conserva actor IAM o identidad CLI, acción, motivo, definición, modo,
versión e instantes. Los secretos solo dejan indicadores de presencia/cambio, sin
valor ni hash. La retención sigue pendiente para producción.

## Certificación y contrato de proveedor

Modules posee CertifyProviderConnectionPort y el servicio interno compartido.
Settings lo consume por puerto; el catálogo de Modules utiliza el mismo servicio.
Rutas POST settings/modules/{provider}/certify-connection y
modules/{module}/certify-connection requieren respectivamente ib.settings.manage e
ib.modules.manage. Comparten límite de 10 intentos/minuto/actor tras autenticación gateway.

Registro cerrado: broker espera broker-service; copy_trading espera copy-trading-service.
La prueba obtiene una captura efectiva de base_url, internal_prefix, internal_token,
source_service y timeout_seconds. Ejecuta GET {base_url}{internal_prefix}/health,
con X-Internal-Token y X-Internal-Source, sin redirects ni retries y fuera de transacciones.
URL/token/source ausentes producen not_configured sin llamadas externas.

El proveedor debe autenticar con el mismo middleware interno de sus rutas de actividad:
401 para token inválido/ausente, 403 para origen no autorizado y 503 para indisponibilidad.
Solo después de autenticar y comprobar disponibilidad devuelve:

```json
{"success":true,"data":{"status":"ready","service":"broker-service","schema_version":1},"meta":{"message":"Ready"}}
```

Un endpoint público /up no acredita el contrato. IB exige 200, success=true, status=ready,
identidad esperada y schema_version entero 1. Devuelve proveedor, module_id opcional,
certified, code, checked_at y duration_ms; nunca reenvía el cuerpo/error del proveedor.
Códigos: certified, not_configured, authentication_rejected, authorization_rejected,
timeout, unreachable, endpoint_missing, unhealthy e invalid_response.
Resultados negativos completados usan 200 HTTP con certified=false.
No se guardan certificaciones ni se alteran estados operativos.

## Kafka y procesos persistentes

TopicHandlerRegistry se compone mediante proxy diferido nativo PHP 8.4: el registro
dinámico se prepara una sola vez antes de construir el registro real, cuando se usa.
No se invalida un registro construido ni se consulta Settings al descubrir comandos.
Los topics de actividad y auth account registered se resuelven desde Settings.
Cambiar topics requiere reiniciar el consumidor; config:cache contiene solo bindings estáticos.

## Verificación y límites

Los tests aislados de lógica pueden sustituir SettingRepositoryInterface por InMemory
sin ramas productivas por entorno. Las pruebas de persistencia y endpoint Settings
deben usar PostgreSQL. La certificación local usa Http::fake; no acredita los endpoints
reales. UI, proveedores y aceptación S2S permanecen fuera de este repositorio.
