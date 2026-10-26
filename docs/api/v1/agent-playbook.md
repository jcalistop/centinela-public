# Centinela — playbook agente P3a (solo lectura)

Guía corta para clientes MCP/API. No sustituye el manual Voyager ni `domain-rules` completo.

## Año y SS

- **SS:** viene del token Sanctum (usuario técnico con `cen_hie_dependency_id` fijo). No se elige por tool.
- **Año:** pasar `year` en argumentos de tool o, en REST, header `X-Centinela-Year` / `?year=`. Sin override usa el año default del catálogo.

## Instrumento

Usar atributo `instrumento`: `comges` | `metas`.

No inventar rutas ni prefijos de esquema interno (`com_`, `met_`) como contrato público.

## Ritual de orientación

Antes de pedir indicadores/resultados:

1. `get-health-tool`
2. `list-years-tool` / `get-context-tool`
3. `list-instrumentos-tool`
4. `get-glossary-tool` (o resource `centinela://v1/glossary`)
5. `get-capabilities-tool` / `get-open-api-tool` (o resources `centinela://v1/openapi`, playbook)
6. Si no conoces el instrumento: `search-indicadores-tool` (`q`) o REST `GET /api/v1/search`
7. Establecimientos del territorio (SS del token): `list-establecimientos-tool` / `get-establecimiento-tool` (filtros `tipo=SAR`, `vigente=true` → usar `meta.total`)
8. Tools de datos de indicadores (`list-indicadores-tool`, detalle, resultados, alertas)

También: prompt MCP `orientate` y resource `centinela://v1/agent-playbook`.

Plantillas de onboarding: [agent-system-prompt.md](./agent-system-prompt.md) · [agent-examples.md](./agent-examples.md).

## Límites P3a

- Solo ability **`read`**. Sin escritura. Sin `set_context`.
- Flag `CENTINELA_AGENT_API_ENABLED` apagado por defecto.
- **PII/egreso:** intranet no implica egreso seguro al proveedor LLM. Activar el flag en datos reales requiere aceptación institucional (ADR-041 / domain-rules).

## Enlaces lógicos

| Tipo | Identificador |
| ---- | ------------- |
| Tool | `get-capabilities-tool`, `get-glossary-tool`, `get-open-api-tool`, `list-establecimientos-tool`, `get-establecimiento-tool` |
| Resource | `centinela://v1/glossary`, `centinela://v1/openapi`, `centinela://v1/agent-playbook` |
| Prompt | `orientate` |
| REST | `GET /api/v1/glossary`, `GET /api/v1/openapi.json`, `GET /api/v1/establecimientos` |
