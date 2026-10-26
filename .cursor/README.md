# Cursor en este repo

Este directorio contiene contexto **específico de Cursor** (no de GitHub):

- `rules/`: reglas persistentes para el agente en este proyecto
- `prompts/`: plantillas de prompts de equipo (feature/bugfix, db-change, etc.)

## Regla principal

- `rules/centinela.md` es la regla base del repo y referencia las fuentes de verdad:
  `AGENTS.md`, `.github/docs/sdd/*` (incluye `templates/pr-template.md` para PRs) y `.github/copilot-instructions.md`.
