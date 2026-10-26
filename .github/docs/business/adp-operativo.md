# ADP — reglas operativas

> **Estado: borrador formal — no vigente como invariante.** Suite ADP está **en pausa** (retarget v4.9.0+). No usar como regla definitiva hasta reactivar el módulo. Higiene de enlaces: 2026-10-07.

Documento de negocio para **Alta Dirección Pública (ADP)**. Última formalización de contenido: **2026-07-09** · banner/enlaces: **2026-10-07**.

**Reglas transversales:** [domain-rules.md](domain-rules.md) · [instrumentos-plataforma.md](instrumentos-plataforma.md)

---

## 1. Alcance del instrumento

ADP evalúa el desempeño de **cargos en establecimientos** mediante **convenios multianuales** con periodos anuales móviles (no necesariamente año calendario).

**Incluye:**

- Catálogo global de cargos y asignaciones vigentes (persona en cargo en establecimiento).
- Convenio de desempeño por asignación, duración X años.
- Periodos anuales móviles (p. ej. marzo a marzo) definidos por fechas.
- Evaluación parcial a los **6 meses** dentro de la autoevaluación.
- Indicadores con ficha, constructor de fórmula (patrón Metas) y tablas de sensibilidad (patrón COMGES).
- Flujo: autoevaluación → preliminar → apelaciones por indicador → final (con overrides trazables).
- Documentación normativa por instrumento (tabla `documents`, patrón Metas/COMGES).

**Excluye (por ahora):**

- Reutilizar tablas `met_*` o `com_*`.
- Integraciones externas (RRHH, Active Directory) para sincronizar personas/cargos.
- Workflow avanzado (firmas, plazos legales multi-actor) más allá de estados mínimos.

---

## 2. Granularidad y segmentación

| Dimensión | Regla |
| --------- | ----- |
| **Unidad de evaluación** | Cargo-establecimiento por convenio (`adp_agreements` sobre asignación) |
| **Servicio de Salud** | Toda consulta y mutación filtrada por `cen_hie_dependency_id` del usuario |
| **Ruta** | `/admin/adp` — instrumento con controller dedicado (`indicator_types.route = adp`) |

---

## 3. Ciclo de evaluación

```mermaid
flowchart LR
 A[Parametrizacion convenio] --> B[Carga mensual]
 B --> C[Autoevaluacion parcial 6m]
 C --> D[Calculo periodo anual]
 D --> E[Preliminar snapshot]
 E --> F[Apelaciones por indicador]
 F --> G[Final con overrides]
```

| Etapa | Descripción |
| ----- | ----------- |
| **Autoevaluación** | Carga mensual de datos + evaluación parcial a 6 meses |
| **Preliminar** | Snapshot de resultados calculados del periodo anual; baseline para apelaciones |
| **Apelaciones** | Por **indicador** (no por objetivo completo); ligadas al periodo anual |
| **Final** | Resultado post-apelaciones; puede incluir **overrides** manuales con trazabilidad (quién, cuándo, por qué, valor anterior/nuevo) |

---

## 4. Parametrización y ponderaciones

- Objetivos e indicadores por convenio (`adp_objectives`, `adp_indicators`).
- Meta y ponderación por indicador y periodo anual.
- **Ponderación mínima por indicador:** 10 %.
- **Suma de ponderaciones** del set del periodo anual: 100 %.
- Cálculo bloqueado si ponderaciones inválidas.

**Roles en parametrización:**

| Acción | Roles |
| ------ | ----- |
| Catálogo global de cargos / maqueta indicadores | admin, parametrizador |
| Catálogo de indicadores por SS | admin, control-gestion |
| Ver tablas de sensibilidad | admin, control-gestion |

---

## 5. Motor de cálculo

Reutiliza componentes del chasis (convergencia iniciada):

| Capacidad | Origen |
| --------- | ------ |
| Evaluador de fórmulas | `MetFormulaEvaluatorService` (Metas) |
| Normalización sensibilidad | `ComSensitivityTable::normalizeValorRule` (COMGES) |
| Servicio ADP | `AdpCalculationService` |

**Tres métodos de cálculo por indicador:**

| Método | Regla |
| ------ | ----- |
| **Porcentual** | Fórmula tokenizada → resultado numérico → cumplimiento vs meta |
| **Sensibilidad** | Valor evaluado mapeado a puntaje/cumplimiento por rangos sin solapamiento ni huecos |
| **Dicotómico** | Cumple / no cumple → puntaje/cumplimiento (por defecto 0/100) |

- Recalcular es **idempotente** (sobreescribe misma clave lógica).
- Resultados persistidos: `adp_indicator_results`, global `adp_agreement_period_results`.
- Fórmula inválida o variables faltantes: resultado `null` con error; no rompe el cálculo global (`n_failed`).

**No aplica** `CurrentCutoffPolicy` de COMGES.

---

## 6. Preliminar, apelaciones y final

- **Preliminar:** refleja cálculo completo del periodo; congela baseline para apelar.
- **Apelación:** solo si existe preliminar del periodo; una entidad por (periodo, indicador).
- **Final:** requiere preliminar (o decisión explícita de final manual documentada); overrides auditados.

---

## 7. Catálogo e indicadores replicados

- Catálogo global + habilitación por SS  bajo control de **control-gestion**.
- Indicadores ADP en tablas `adp_*` propias; campos similares a `com_indicators` / `met_indicators` sin FK cruzada (deuda P2: medición compartida).

---

## 8. Roles en ADP (resumen)

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Catálogo global / maqueta | Sí | Sí | No | No | Lectura |
| Catálogo por SS | Sí | No | Sí | No | Lectura |
| Carga mensual / autoevaluación | Sí | No | Sí | En su SS | No |
| Apelaciones / final | Según spec | No | Sí | Según etapa | No |

Detalle: [modulos-roles.md](modulos-roles.md). Validar con CG cuando el módulo entre en producción.

---

## 9. Checklist rápido para specs ADP

- [ ] ¿Segmentación por `cen_hie_dependency_id`?
- [ ] ¿Ponderaciones 10 % mínimo y suma 100 %?
- [ ] ¿Método de cálculo (porcentual / sensibilidad / dicotómico) documentado?
- [ ] ¿Trazabilidad de overrides en etapa final?
- [ ] Marcar borrador si la regla aún no está Released.
