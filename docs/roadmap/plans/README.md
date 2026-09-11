# Roadmap del feature Plans

Estado: **No iniciado**  
Dependencia: completar `Modules M1`  
Última revisión: 2026-09-11

## Posición en la secuencia

`Plans` será el primer consumidor inter-feature de `Modules`. No se diseñará su
frontera antes de inventariar el caso de uso concreto que configura los módulos
de un plan.

```text
Caso de uso de Plans
    ↓ necesidad contractual concreta
Puerto de entrada propiedad de Modules
    ↓ implementado por
Caso de uso de Modules
```

## Primera entrega candidata: P1

La entrega deberá cubrir verticalmente:

- identidad y datos mínimos de un Plan IB;
- selección de módulos activos reconocidos por `Modules`;
- persistencia de la vinculación entre plan y módulo;
- rechazo de módulos inexistentes o inactivos;
- consulta del plan con sus módulos configurados;
- primer puerto público de `Modules`, limitado a la necesidad demostrada por el
  caso de uso de `Plans`.

Los nombres del caso de uso y del puerto permanecen abiertos hasta realizar el
inventario de Plans. No se asume todavía `ConfigurePlanModulesUseCase`,
`ResolveSelectableModulesPort` ni otra firma concreta.

## Relación con Programs

`Programs` solo puede comenzar su frontera después de P1. Todo programa
pertenece a un plan y únicamente podrá configurar módulos previamente
habilitados por ese plan. Esta dependencia impide que `Programs` consulte el
catálogo global como si el Plan IB no fuera la raíz funcional.

## Próximo paso

Después de completar `Modules M1`, crear la iteración completa de Plans:

1. inventario de casos de uso;
2. agregados, estados y transacciones;
3. entregas verticales;
4. modelo de datos de P1;
5. implementación con repositorios en memoria y PostgreSQL mediante contract
   tests.
