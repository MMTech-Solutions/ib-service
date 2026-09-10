# Stack tecnológico

## Confirmado

| Tecnología | Versión inicial | Uso |
| --- | --- | --- |
| PHP | 8.4 | Runtime del servicio. |
| Laravel Framework | 13.31.0 | Framework de aplicación. |
| PostgreSQL | Configurado; versión de servidor pendiente | Persistencia relacional prevista. |
| PHPUnit | 12.5.35 | Pruebas automatizadas. |
| Laravel Pint | 1.32.1 | Formato de PHP. |
| Laravel Boost | 2.8.0 | Guías para desarrollo asistido. |
| Graphify | CLI local | Grafo de conocimiento y análisis de impacto. |
| Composer | 2.10.0 | Gestión de dependencias PHP. |

Las versiones exactas instaladas en `composer.lock` prevalecen sobre este resumen. Este documento debe actualizarse cuando cambie una dependencia estructural.

## No decidido todavía

- Tecnología y contratos del bus de eventos.
- Redis u otro backend para locks y cache distribuida.
- Plataforma de observabilidad y trazas.
- Estrategia de almacenamiento de archivos.
- Contenedores, orquestación y destino de despliegue.
- Librería para JSON Schema y contratos de eventos.

No añadir una tecnología pendiente por conveniencia local sin una decisión documentada y aprobación explícita.
