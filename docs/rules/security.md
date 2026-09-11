# Seguridad

## Principios obligatorios

- Denegar por defecto y conceder el mínimo privilegio necesario.
- Autenticar y autorizar toda API, evento y operación administrativa.
- Tratar payloads de otros servicios como entrada no confiable.
- Validar estructura, tipos, rangos, unidades, moneda y versión de contrato.
- No almacenar secretos dentro de reglas JSON, eventos, logs o snapshots de auditoría.

## APIs e integraciones

- Los endpoints internos también requieren identidad S2S y autorización por capacidad.
- Aplicar límites de tamaño, paginación, timeouts y rate limiting.
- Rechazar eventos de productores o versiones no reconocidas.
- Verificar que referencias externas pertenezcan al tenant o contexto autorizado.
- Proteger replay y endpoints de reconciliación contra ventanas ilimitadas.
- Mapear respuestas de SDK o APIs externas a DTOs propios antes de entregarlas a UseCases o strategies.
- No propagar tokens, errores técnicos, URLs internas ni objetos de respuesta de un SDK a contratos del dominio o respuestas HTTP.
- Preferir `$request->validated()` para construir Commands. No trasladar masivamente `$request->all()` a objetos de aplicación.
- El acceso operativo a IAM usa `mmt/laravel-iam-service-sdk`; `mmtech/iam-rbac` no sustituye ese SDK.

## Configuración dinámica

- `strategy_type` se resuelve mediante allowlist.
- Toda configuración se valida contra un schema versionado.
- Prohibir nombres de clases, expresiones ejecutables, SQL, URLs y credenciales arbitrarias.
- Limitar profundidad, cantidad de reglas e instrumentos y tamaño total del JSON.
- Registrar quién publica una versión y cuándo entra en vigor.

## Dinero y recompensas

- Finance es la autoridad del settlement; IB no modifica balances directamente.
- Toda solicitud financiera incluye clave idempotente y contexto mínimo verificable.
- Aplicar autorización reforzada a publicación de reglas, cambios de rates, reversas y operaciones manuales.
- Evitar exposición de información financiera o de red en respuestas no autorizadas.

## Datos personales y auditoría

- Persistir únicamente los identificadores y snapshots necesarios para justificar decisiones.
- La auditoría transaccional de cambios operativos persiste el identificador
  estable del actor de IAM, la acción, el motivo, el instante y los estados
  anterior y posterior.
- Nombre y correo del actor se consultan mediante
  `mmt/laravel-iam-service-sdk` al construir reportes o se incluyen como
  snapshot en un evento solo cuando el contrato de su audiencia lo requiera;
  no forman parte del agregado operativo.
- Separar datos operativos de información sensible de perfil.
- Redactar tokens, secretos, contraseñas y datos personales de logs y errores.
- Definir retención y acceso del historial antes de producción.

## Desarrollo

- No registrar `.env` ni credenciales en Git.
- Mantener dependencias actualizadas y revisar advisories.
- No interpolar entrada en SQL, comandos del sistema o nombres de clases.
- Añadir pruebas de autorización y aislamiento entre tenants cuando se implemente esa dimensión.
