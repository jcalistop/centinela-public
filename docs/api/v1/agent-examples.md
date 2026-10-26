# Centinela — ejemplos agente (Q → tools)

Cada fila: pregunta del usuario → secuencia sugerida de tools MCP. Año: pasar `year` si el contexto no basta. Aplicable a cualquier cliente MCP/API (IDE, bot, orquestador).

| # | Pregunta | Secuencia |
| - | -------- | --------- |
| 1 | ¿Está viva la API de agentes? | `get-health-tool` |
| 2 | ¿Qué año y SS tengo? | `get-context-tool` (+ `list-years-tool` si hace falta) |
| 3 | ¿Qué instrumentos existen? | `list-instrumentos-tool` |
| 4 | ¿Qué significa «corte» / «ley»? | `get-glossary-tool` o resource glossary |
| 5 | Orientarme antes de consultar | prompt `orientate` o resource `centinela://v1/agent-playbook` |
| 6 | Busco un indicador que hable de «lista de espera» (no sé si COMGES o Metas) | `search-indicadores-tool` (`q=lista de espera`) → `get-indicador-tool` con `instrumento` del hit |
| 7 | Buscar por clave parcial «1.2» | `search-indicadores-tool` (`q=1.2`) |
| 8 | Listar indicadores COMGES del año | `list-indicadores-tool` (`instrumento=comges`) |
| 9 | Detalle de un indicador Metas ya identificado | `get-indicador-tool` (`instrumento=metas`, `id=…`) |
| 10 | Resultados por establecimiento de un indicador Metas | `list-resultados-tool` (`instrumento=metas`, `indicador_id=…`) |
| 11 | ¿Hay alertas del dashboard? | `get-dashboard-alertas-tool` |
| 12 | ¿Puedo cargar una evaluación por chat? | **No** — explicar P3a readonly; sin tools de escritura |

Ver también: [agent-system-prompt.md](./agent-system-prompt.md), [README-agentes.md](./README-agentes.md), [agent-playbook.md](./agent-playbook.md).
