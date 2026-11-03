# Centinela — API/MCP agentes (P3a, solo lectura)

Guía operativa corta. Contrato de máquina: OpenAPI y glosario en este mismo directorio. No sustituye el manual de usuario Voyager.

## Flag

- Variable: `CENTINELA_AGENT_API_ENABLED` (vía `config/centinela.php` → `agent_api.enabled`).
- **Default: apagado (`false`).** Con flag off, `api/v1` y `/mcp` responden denegado.
- Activar en un entorno con datos reales requiere aceptación institucional del riesgo de egreso a proveedores LLM (`domain-rules` § PII/egreso).

## Token Sanctum (rol `agente`)

```bash
php artisan centinela:agent:issue-token agente@ejemplo.local --create --ss=<cen_hie_dependency_id>
php artisan centinela:agent:revoke-token agente@ejemplo.local
```

- Ability: **`read` únicamente** (sin `write` / `write:*`).
- El usuario técnico tiene **SS fijo**; no hay bypass de tenancy por rol admin.
- El plain text del token se muestra **una sola vez** al emitir; no lo versionar ni pegar en docs.

## Allowlist IP

Si `centinela.agent_api.ip_allowlist` tiene entradas, las peticiones fuera de la lista se deniegan. Lista vacía = no aplica filtro de red (solo útil en lab).

## Ritual de orientación (MCP)

Orden sugerido antes de pedir datos de indicadores:

1. `get-health-tool` — superficie viva
2. `list-years-tool` / `get-context-tool` — año y SS del token
3. `list-instrumentos-tool` — `comges` \| `metas`
4. `get-glossary-tool` — vocabulario
5. `get-capabilities-tool` / `get-open-api-tool` — contrato y límites (`GetOpenApiTool` → slug `open-api`, no `openapi`)
6. `search-indicadores-tool` — descubrimiento COMGES+Metas **sin** exigir `instrumento` (REST: `GET /api/v1/search?q=`)
7. Tools de datos (`list-compromisos-tool`, `list-indicadores-tool`, etc.)

No existe `set_context`. Pasar `year` por argumento (o header REST `X-Centinela-Year` / `?year=`) cuando haga falta.

## Onboarding cliente LLM

Plantillas agnósticas al proveedor (Cursor, bots, orquestadores, etc.):

- System prompt: [agent-system-prompt.md](./agent-system-prompt.md)
- Ejemplos Q→tools: [agent-examples.md](./agent-examples.md)

## REST equivalente

Con flag on + Bearer `read`:

- `GET /api/v1/glossary`
- `GET /api/v1/openapi.json`
- Resto de lectura bajo `/api/v1/...` (ver OpenAPI)

MCP: `POST /mcp` (mismo auth y middleware).

## PII y egreso

La allowlist e intranet protegen el **canal** Centinela; **no** impiden que un agente (Cursor, bots, etc.) reenvíe datos al proveedor del modelo. Tratar respuestas como potencialmente sensibles; no asumir egreso «seguro por estar en red interna».
