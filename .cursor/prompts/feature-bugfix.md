# Plantilla de prompt — Feature/Bugfix (Centinela)

Copia/pega y completa lo mínimo. La idea es forzar el análisis de **año/instrumento/dependencia** antes de escribir código.

## Contexto

- **Spec ID**: SDD-??? (ruta activa: `.github/docs/sdd/specs/<dominio>/SDD-???-...md`; si está Released, en `archive/<año>/<dominio>/`)
- **Dominio**: metas | adp | comges | chat-ia | supervision | auth | ux | infra | docs
- **Tipo**: feature | bugfix | refactor | performance | db-change (si aplica)
- **Módulo**: COMGES | SNSS | Admin(Voyager) | Otro

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

1. ¿Qué rutas/vistas/componentes se afectan para `ano < 2025`?\n
2. ¿Qué rutas/vistas/componentes se afectan para `ano >= 2025` (comges-2025)?\n
3. ¿Depende de `indicatorType`? ¿Cuál(es)?\n
4. ¿Dónde se debe aplicar/validar el filtro `cen_hie_dependency_id`?\n
5. ¿Qué evidencia mínima confirma que no hay regresión?

## Entregable esperado

- Plan de cambios por archivos (lista de archivos y por qué)\n
- Cambios incrementales (PR pequeño)\n
- **Test plan**: `php artisan test` + verificación manual específica\n
- **Formato**: `vendor/bin/pint --dirty`
