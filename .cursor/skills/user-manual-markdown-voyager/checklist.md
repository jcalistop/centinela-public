# Checklist de calidad (manual contextual)

## Contexto y mapeo

- [ ] Tengo **route name** (o familia) de la pantalla y está reflejado en `applies_to.routes`.
- [ ] Existe una regla en `resources/user-manual/index.yaml` que mapea a este doc (`route_exact`/`route_prefix`/`route_regex`).
- [ ] La regla está en el **orden correcto** (más específico primero; `route_exact` antes de prefijos; prefijos antes de regex).
- [ ] `defaults.fallback_id` existe y apunta a `general`.

## Front‑matter y naming

- [ ] `id` coincide con el nombre del archivo (`pages/<id>.md`).
- [ ] `title` es claro y coincide con el módulo/pantalla.
- [ ] `status` es uno de: `draft | review | published | deprecated`.
- [ ] `updated_at` está en `YYYY-MM-DD`.
- [ ] `version` es SemVer (`1.0.0`).
- [ ] `tags` no son demasiados (3–8) y ayudan a clasificar.

## UX del contenido (SDD-035)

- [ ] Arranca con **Objetivo** (qué resuelve) y **Cuándo usarlo**.
- [ ] Tiene procedimientos escaneables: listas cortas y subtítulos.
- [ ] Cada procedimiento termina con **Resultado esperado**.
- [ ] Incluye “Errores comunes” (callout o details) si hay fallos típicos.
- [ ] “Preguntas frecuentes” con respuestas de 2–4 líneas.

## Artefactos y seguridad

- [ ] Callouts usan `::::info|tip|warning|danger` y no se encadenan demasiados.
- [ ] Mermaid (si aplica): IDs sin espacios, sin HTML en labels.
- [ ] Examples: sin datos sensibles (RUT, nombres reales, correos reales, etc.).
- [ ] Links: preferir rutas internas/IDs estables.
