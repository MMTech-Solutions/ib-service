# Reloj de dominio y perfil Lab

Estado: decisión técnica obligatoria. Revisión: 2026-10-05.

## Captura y alcance

SharedFeatures/Clock captura un instante UTC por operación. El sistema es el
default; no se altera Carbon globalmente. HTTP captura al entrar al middleware,
Progression CLI antes del mutex, modules:sync y comandos Rewards al empezar,
jobs al recibir y handlers Kafka propios al iniciar cada mensaje. Se libera al
terminar, también ante error, para no heredar contextos en workers persistentes.

Las decisiones históricas de Plans, Programs (configuración y plantillas), Rules,
Subscriptions, Modules (control operativo) y Progression consumen ese instante.
Los repositories existentes reciben los timestamps de dominio explícitos; sus
records históricos desactivan timestamps ORM automáticos. Los hechos externos
conservan occurred_at. Logs, timeouts, locks, consumo de fallos y auditoría técnica
usan tiempo real. La captura del reloj no cambia la semántica temporal de Finance
ni convierte su tiempo real en tiempo Lab.

## Configuración y protocolo privado

- IB_DOMAIN_CLOCK=system por defecto; controlled habilita la fuente de archivo.
- IB_LAB_PROFILE=true, APP_ENV=local e IB_LAB_CLOCK_FILE son obligatorios para
  controlled. IB_LAB_FAILURES_ENABLED=false por defecto.
- El volumen debe ser exclusivo de una instancia Lab; todos sus procesos usan
  el mismo path y la misma base IB. No montar este volumen en producción.
- Provisionar fuera de public con ACL exclusiva del usuario del servicio.
  En Unix los archivos se escriben con modo 0600; en Windows aplicar ACL al
  directorio. El path nunca procede de un request HTTP.
- Archivo: {"version":1,"context_id":"UUID","sequence":0,"now_utc":"2026-09-10T10:00:00Z"}.
  Se aceptan fechas reales UTC con segundos y hasta seis decimales, terminadas en Z.
- Inicializar y avanzar mediante ib-lab:clock. El comando reemplaza el archivo
  atómicamente bajo el lock estable <archivo>.lock, compartido por todas las
  operaciones. Si está ocupado, rechaza el avance; el runner espera a finalizar
  la operación antes de reintentarlo.
- <archivo>.accepted conserva contexto, secuencia y tiempo aceptados después de
  reiniciar. Secuencia creciente no admite tiempo menor; secuencia repetida
  exige el mismo instante. No reutilizar una secuencia con otro tiempo.
- Ausencia, corrupción, retroceso, contexto distinto o modo fuera de Lab abortan
  sin fallback. Un contexto nuevo requiere reset offline explícito de la
  instancia aislada: detener workers y scheduler, restablecer su base de datos
  de Lab y retirar archivo/estado/lock antes de inicializar un nuevo UUID.
- No reemplazar ni borrar el lock mientras existan procesos activos. Escribir
  directamente el reloj mientras una operación está activa queda fuera del
  contrato; utilizar exclusivamente el comando de avance.

## Fallos controlados

Progression ofrece un puerto de fallo cerrado, sin efecto con la configuración
ordinaria. El adapter Lab exige el contexto controlado y registra fallos por ID,
contexto, operación, etapa y selector UUID. El consumo se confirma de manera
durable antes de lanzar el fallo y fuera de la transacción de placement.
No se admite consumir dentro de una transacción externa que pudiera revertirlo.

Solo se permiten before_result_finalize y before_placement, operaciones close
y recover, y selectores subscription o result. Repetir el armado del mismo ID
con los mismos valores conserva su consumo; cambiar esos valores se rechaza.
Un reinicio no vuelve a armarlo. Este mecanismo prueba las fronteras internas;
los errores Broker/IAM de ingesta se evidencian por separado.

