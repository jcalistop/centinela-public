# Plantilla de prompt — Feature/Bugfix (Centinela)

Copia/pega y completa lo mínimo. La idea es forzar el análisis de **año/instrumento/dependencia** antes de escribir código.

## Contexto

- **Alcance documentado**: (qué cambia y qué no)
- **Módulo**: COMGES | SNSS | Admin(Voyager) | Otro
- **Tipo**: feature | bugfix | refactor | performance | db-change (si aplica)

## Reglas críticas a respetar

- Impacto por **año**: `ano < 2025` vs `ano >= 2025`
- Impacto por **instrumento**: dependencia de `indicatorType` (si aplica)
- Segmentación por **Servicio de Salud**: `Auth::user()->cen_hie_dependency_id`
- Frontend: **Bootstrap 5 + SASS** (no Tailwind), Livewire 3
- Validación: inline (Voyager) o `#[Validate]` (Livewire) — no Form Requests salvo pedido

## Solicitud

Quiero implementar:

- (descripción corta)

## Preguntas que debes responder antes de cambiar código

1. ¿Qué rutas/vistas/componentes se afectan para `ano < 2025`?
2. ¿Qué rutas/vistas/componentes se afectan para `ano >= 2025` (comges-2025)?
3. ¿Depende de `indicatorType`? ¿Cuál(es)?
4. ¿Dónde se debe aplicar/validar el filtro `cen_hie_dependency_id`?
5. ¿Qué evidencia mínima confirma que no hay regresión?

## Entregable esperado

- Plan de cambios por archivos (lista de archivos y por qué)
- Cambios incrementales (PR pequeño)
- **Test plan**: `php artisan test` + verificación manual específica
- **Formato**: `vendor/bin/pint --dirty`
