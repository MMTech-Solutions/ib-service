# Propósito y mantenimiento de los BDS

## Definición

Un Business Domain Specification describe cómo funciona el negocio independientemente de la tecnología. Debe seguir siendo comprensible si el servicio cambia de framework, transporte o persistencia.

## Contenido obligatorio

- Contexto y propósito.
- Glosario de términos.
- Conceptos y relaciones relevantes.
- Reglas identificadas de forma estable.
- Fórmulas o cálculos del negocio.
- Estados y transiciones cuando existan.
- Eventos de negocio.
- Decisiones pendientes claramente separadas.

## Contenido prohibido

- Clases, namespaces o paths de código.
- Tablas, columnas o migraciones como diseño de dominio.
- Rutas HTTP, topics, jobs o comandos.
- Frameworks, SDKs o herramientas.
- Estado de implementación o tareas de migración.

## Convenciones

- Ubicación: `docs/bds/`.
- Nombre: `<domain-area>.bds.md`.
- Identificadores: prefijo del área y número estable, por ejemplo `BR-POINTS-001`.
- Idioma principal: español; términos técnicos consolidados pueden conservarse en inglés.
- Estado del documento y versión deben aparecer al inicio.

## Flujo de cambio

1. Identificar el BDS propietario de la regla.
2. Determinar si la decisión está confirmada o pendiente.
3. Actualizar glosario, regla, cálculo, evento y diagrama afectados.
4. Contrastar reglas técnicas y contratos derivados.
5. Implementar y probar el comportamiento.
6. Actualizar los índices si se crea, renombra o divide un documento.

Una contradicción entre código y BDS no se resuelve modificando silenciosamente el BDS para que describa el código. Primero debe decidirse si existe un defecto de implementación o un cambio legítimo de negocio.
