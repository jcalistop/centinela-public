COMGES **no** sigue el mismo **flujo de etapas** que Metas. Combina una **parametrización global** del instrumento con la **configuración y operación por Servicio de Salud**.

### Antes de la plataforma

El ciclo inicia con la publicación, por parte del MINSAL, de las **orientaciones técnicas** (OOTT) del año. Esas orientaciones guían la parametrización en Centinela, pero no se gestionan dentro del sistema.

### Parametrización global (una vez para todos los SS)

El **parametrizador** define el catálogo del año para todos los Servicios de Salud:

- Compromisos e indicadores.
- **Cortes** del año de medición (cantidad según OOTT; p. ej. Corte 1, Corte 2, …, Corte *n*).
- Acciones (secciones a evaluar) dentro de cada corte, con su **ponderación** para el cálculo global.
- Tablas de sensibilidad de cada indicador.

### Configuración por Servicio de Salud

El **control de gestión** del SS prepara la operación antes de abrir la carga a referentes:

1. Selecciona las tablas de sensibilidad que aplican a su SS (cuando hay más de una opción).
2. Asigna **referentes** por indicador.
3. Abre el instrumento en etapa **Autoevaluación**.
4. Configura cada indicador: cortes habilitados (pestañas editables) y fechas de apertura y cierre.

### Etapas operativas (por SS y año)

| Etapa | Quién opera | Qué ocurre |
| ----- | ----------- | ---------- |
| **Autoevaluación** | Referentes (y CG según ventanas) | Carga de evaluaciones y **medios de verificación** por acción y **corte**, dentro de las ventanas configuradas. |
| **Evaluación preliminar** | Control de gestión | Registro de los resultados de la evaluación externa (MINSAL, SEREMI, DIPRES u otro organismo según el marco del año). |
| **Apelación** | Referentes asignados | Presentación de apelaciones como medios de verificación documentales. |
| **Evaluación final** | Control de gestión | Registro del resultado final, incorporando resoluciones de apelaciones cuando corresponda. |
| **Cerrado** | Todos (solo lectura) | El año indicador queda concluido para ese SS; no hay edición operativa. |

Los **cortes** estructuran la carga principalmente en **Autoevaluación**; en **Apelación** pueden aplicarse según la configuración del indicador.

**Escala de cumplimiento:**

- Años indicador **anteriores a 2025**: porcentaje.
- Año indicador **2025 en adelante**: puntaje de **0 a 4**.

<!-- diagram-source: docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.workflow.json (export SVG Light/classic: npm run diagrams:export-ciclo-comges) -->
![Ciclo COMGES por Servicio de Salud](/docs/diagrams/ciclo-comges.svg)

> **Nota:** De acuerdo a la OOTT del año, pueden configurarse varios cortes (Corte 1, Corte 2, …, Corte *n*), que pueden ser evaluaciones locales (monitoreos) o cortes anuales.
