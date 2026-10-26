Los instrumentos de Metas (18.834, 19.664 y 20.707) combinan **parametrización global del catálogo** con un **ciclo de vida por Servicio de Salud y año**, con el mismo punto de partida normativo que COMGES.

### Antes de la plataforma

El ciclo inicia con la publicación, por parte del MINSAL, de las **orientaciones técnicas** (OOTT) del año para la ley correspondiente. Esas orientaciones guían la parametrización en Centinela, pero no se gestionan dentro del sistema.

### Parametrización global (una vez para todos los SS)

El **parametrizador** define el catálogo del instrumento en base a las OOTT: indicadores, fórmulas, campos de datos, umbrales de semáforo por indicador y tipos de entrada/agregación por defecto.

### Etapas operativas (por SS y año)

| Etapa | Quién opera | Qué ocurre |
| ----- | ----------- | ---------- |
| **No iniciado** | Control de gestión (solo avance de etapa) | Estado inicial; referentes y visualizadores **no ven** el instrumento. Sin configuración de establecimientos. |
| **Formulación** | CG + responsable de establecimiento | Instrumento visible para participantes. CG configura **aplicabilidad** y **meta efectiva** por indicador-establecimiento; designa responsables. Responsable selecciona indicadores en **convenio** y **ponderaciones**. Carga de datos permitida. |
| **Autoevaluación** | Referentes, responsables y CG | Convenio congelado. Carga periódica y evaluación del cumplimiento **período a período** (mensual, trimestral, semestral o anual según configuración — **sin cortes** como COMGES). |
| **Evaluación preliminar** | Control de gestión | Registro de la evaluación externa (MINSAL, SEREMI, DIPRES u otro organismo). |
| **Apelación** | Responsable de establecimiento designado | Presentación de apelaciones (justificación y adjuntos) por indicador con incumplimiento. |
| **Evaluación final** | Control de gestión | Registro del resultado final, con resolución de apelaciones cuando corresponda. |
| **Cerrado** | Todos (solo lectura) | Periodo concluido; sin edición operativa. |

En **Metas 20.707**, la carga puede desagregarse por **unidad interna** según la configuración del instrumento.

<!-- diagram-source: docs/architecture/modules/metas/ciclo-metas/centinela-ciclo-metas.workflow.json (export SVG Light/classic: npm run diagrams:export-ciclo-metas) -->
![Ciclo Metas por Servicio de Salud](/docs/diagrams/ciclo-metas.svg)

> **Nota:** No usa cortes COMGES; la carga y evaluación son **período a período** según el tipo de entrada configurado (mensual, trimestral, semestral o anual).
