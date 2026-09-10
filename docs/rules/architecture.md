# Arquitectura

## Estado

Base arquitectónica inicial. Los detalles de despliegue y contratos se definirán en fases posteriores.

## Principios obligatorios

- El servicio constituye el bounded context autoritativo de planes, programas, suscripciones, progresión, reglas y recompensas IB.
- Plan es la raíz funcional; los módulos externos suministran hechos o métricas y no controlan las reglas IB.
- Broker, Copy Trading, Prop Firm y futuros módulos se integran independientemente mediante puertos explícitos.
- La indisponibilidad de un módulo no debe bloquear el procesamiento de módulos no relacionados.
- Identity conserva autoridad sobre identidad y red de referidos.
- Finance conserva autoridad sobre dinero asentado.
- Los identificadores de otros servicios son referencias opacas; no se simulan foreign keys distribuidas.

## Organización interna prevista

Se utilizará una estructura feature-first. Cada capacidad tendrá su propio límite de aplicación, dominio, persistencia e integración. La lista inicial orientativa es:

```text
app/
└── Features/
    ├── Plans/
    ├── Programs/
    ├── Subscriptions/
    ├── Progression/
    ├── Rules/
    ├── Rewards/
    └── Integrations/
```

Esta estructura no autoriza todavía la creación de dichos features. Antes de implementarlos debe cerrarse su modelo y documentarse la convención de capas.

## Dependencias

- Dominio no depende de HTTP, Eloquent, Kafka ni clientes externos.
- Casos de uso orquestan el dominio mediante contratos explícitos.
- Adaptadores externos traducen payloads hacia conceptos internos normalizados.
- Persistencia implementa contratos definidos por la capacidad propietaria.
- No se importan modelos o repositorios pertenecientes a otro servicio.

## Integración

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

- Capas y namespaces definitivos de cada feature.
- Bus de eventos y formato del envelope.
- Estrategia de consistencia entre runs, actividad tardía y settlement.
- Contratos S2S, observabilidad y topología de despliegue.
