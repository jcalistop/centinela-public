# Módulos y roles — matriz de acceso

Matriz de referencia **módulo × rol** para cambios, PRs y validación funcional. Deriva de policies, middleware y flujos Livewire vigentes.

**Último refresh:** **2026-10-07** (paridad Metas; ADP en pausa).

**Reglas transversales:** [domain-rules.md](domain-rules.md) · [instrumentos-plataforma.md](instrumentos-plataforma.md) · ciclo Metas [metas-ciclo-vida.md](metas-ciclo-vida.md).

**Leyenda:**

| Símbolo | Significado |
| ------- | ----------- |
| **T** | Acceso total (CRUD / gestión en su ámbito o global si admin) |
| **L** | Solo lectura |
| **O** | Operación en su SS según etapa, asignación o policy |
| **—** | Sin acceso o sin operación típica |
| **E** | Exento de filtro SS (solo admin) |

**Nota Metas:** «Responsable de establecimiento» es **designación** (no rol Voyager). En la matriz, las celdas de `referente` con «solo su centro» exigen esa designación cuando la acción lo indica.

---

## 1. Instrumentos de evaluación

**Gate de acceso (`ModuleAccess`):** (1) `indicator_types.estado` Off ⇒ sin acceso para ningún rol (menú y rutas); (2) con catálogo On, toggle SS / `habilitado` anual Off ⇒ sin acceso salvo **admin** y **parametrizador** (`control-gestion` no bypasea). UI Módulos por SS: toggles del instrumento Off en catálogo quedan disabled.

### COMGES

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Browse compromisos | T | T (incl. sin indicadores) | O | O | L |
| Carga evaluaciones / medios | O | — | O | O (corte/etapa) | — |
| Descarga medios verificación | T | O | T | O (si asignado) | — |
| Resultados globales | T | L | T | L | L |
| Parametrización indicadores BREAD | T | T | O | — | — |

Detalle de ciclo y etapas: [comges-ciclo-vida.md](comges-ciclo-vida.md). Cálculo y cortes: [comges-calculo.md](comges-calculo.md).

### Metas (18.834 / 19.664 / 20.707)

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Acceso al instrumento en etapa **No iniciado** | T | T (catálogo global) | T (sin config. estructural) | — | — |
| Cambio de etapa vigente (retroceso con motivo) | T | — | T | — | — |
| Selección establecimientos / hub | T | T (maquetas) | T | O (≥ Formulación) | L (≥ Formulación) |
| Configuración indicadores por SS | T | — | T | — | — |
| Configuración estructural por establecimiento (`aplica` / meta / centros; ≠ No iniciado / Cerrado) | T | — | T | — | — |
| Convenio y ponderaciones (responsable; solo **Formulación**) | T | — | T | O (solo su centro) | — |
| Guardar borrador / Enviar a SS (firma; Formulación + BORRADOR) | T | — | T | O (solo su centro) | — |
| Reabrir convenio enviado (motivo; solo Formulación) | T | — | T | — | — |
| Ver bitácora del establecimiento (modal; L) | T | — | T | O (solo su centro) | — |
| Designación responsables de establecimiento | T | — | T | — | — |
| Carga por períodos (mensual/trim/…) en **Formulación** y **Autoevaluación** | T | — | T | O (indicador + responsable centro) | — |
| Apelaciones (responsable; etapa **Apelación**) | T | — | T | O (su centro) | — |
| Registro evaluación preliminar / final | T | — | T | — | — |
| Configuración campos por SS | T | T | T | — | — |
| Borrado datos cargados | T | — | T | — | — |
| Cálculo y resultados | T | L | T | O / L según etapa | L (≥ Formulación) |
| Catálogo indicadores (gestión referentes) | T | — | T | — | — |
| Operación en etapa **Cerrado** | L | L | L | L | L |

Detalle de ciclo y cálculo: [metas-ciclo-vida.md](metas-ciclo-vida.md) · [metas-calculo.md](metas-calculo.md). Políticas por etapa: §4 de ciclo de vida.

### ADP (borrador — suite **en pausa**; no invariante)

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Catálogo global cargos / maqueta | T | T | — | — | L |
| Catálogo indicadores por SS | T | — | T | — | L |
| Convenio y parametrización | T | O | T | — | L |
| Carga mensual / autoevaluación | T | — | T | O | — |
| Sensibilidad (ver/editar) | T | — | T | — | L |
| Preliminar / apelaciones / final | T | — | T | O | L |

Detalle: [adp-operativo.md](adp-operativo.md). Validar con CG al reactivar el módulo.

---

## 2. Parametrización y administración (`/admin`)

| Módulo | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Usuarios y roles | T (E) | T | — | — | — |
| `indicator_types` / habilitación SS | T | T | O | — | — |
| Settings plataforma | T | T | — | — | — |
| Documentos normativos | T | — | T | L | L (solo `documentos`) |
| Actividades / timeline indicador | T | — | T | L | L |
| BREAD Voyager legacy | T | T | O | O | L |
| Panel Filament (objetivo v5.0.0) | T | T | O | O | L |

Durante la transición de plataforma pueden coexistir filas Voyager y Filament; el alcance documentado debe indicar cuál capa toca.

---

## 3. Módulos accesorios

### Soporte (tickets)

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Acceso al módulo | T | **—** (excluido) | T (su SS) | T (propios/asignados) | T (solo creados por él) |
| Crear ticket | T | — | T | T | T |
| Gestionar estado / asignar | T | — | T (su SS) | — | — |
| Asignar a referente | T | — | T (su SS) | — | — |
| Receptores / GitHub issues | T | — | — | — | — |

Fuente: `SupportTicketPolicy`, `BrowseSoporteVoyagerMenu`.

### Chat IA

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Uso del chat | T | T | T | T | T |

Integración en layout admin; sin restricción por rol distinta a acceso `/admin` (validar en el alcance de Chat IA si cambia).

### Manual contextual

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Ver ayuda drawer | T | T | T | T | T |
| Editar páginas manual | T | T | — | — | — |

### Visualizador SNSS (establecimientos)

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Browse establecimientos | T | T | L | L | L |
| Sync nacional / notas / merge | T | T | — | — | — |
| Export / equivalencias glosa | T | T | — | — | — |

Operaciones de sincronización y mantenimiento: típicamente **admin** y **parametrizador**. Lectura filtrada por SS vía `hieFilter` en consultas. Browse L = SS propio por defecto; se puede explorar el catálogo público de otro SS (solo lectura: listado, mapa, ficha, export). Mutaciones (sync SS, alta, edición, borrar, notas, merge) siguen en el SS del usuario.

### Supervisión / deploy dashboard

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Dashboard deploy / monitor | T | — | — | — | — |

### Mensajería manual

| Acción | admin | parametrizador | control-gestion | referente | visualizador |
| ------ | ----- | -------------- | --------------- | --------- | ------------ |
| Botón en browse Usuarios | T | — | T | — | — |
| Compose / encolar (`/admin/mensajeria`) | T (E) | — | T (su SS) | — | — |
| Ver campañas | T (todas) | — | T (propias) | — | — |

Fuente: `MessagingAudienceResolver`, `MessagingComposeScope`. Entrada por botón (sin menú Voyager). Filament dual-run hasta ocultar.

---

## 4. Restricciones transversales por rol

### visualizador

- Middleware `ReadOnlyFilePolicy`: sin upload, import, media manager Voyager.
- Descarga: solo documentación normativa (`documentos` / `documents`); medios de verificación COMGES denegados.
- Soporte: solo tickets creados por el propio usuario.
- Metas: sin acceso en etapa **No iniciado**; solo lectura desde Formulación en adelante.

### parametrizador

- Excluido del módulo Soporte (`SupportTicketPolicy`).
- Ve compromisos COMGES sin indicadores configurados (`ComgesBrowseContext`).
- Acceso a maquetas Metas y catálogos globales; no cambia etapa Metas ni opera convenio por centro.

### control-gestion

- Seguimiento y cierre por SS; no exento de filtro tenancy salvo políticas explícitas (`BelongsToHealthService` / filtro SS — [domain-rules.md](domain-rules.md)).
- Metas: cambia etapa (motivo en retroceso); config. estructural; designa responsables; registra preliminar/final.
- Gestión catálogo ADP por SS (cuando el módulo esté activo); asignación de referentes en soporte.

### referente

- Operación acotada a su SS y asignaciones (COMGES: `ComIndicatorSetting`; Metas: indicadores +, si aplica, designación como **responsable de establecimiento**).
- COMGES: edita solo en etapas `AUTOEVALUACION` y `APELACION`; CG registra `PRELIMINAR` y `FINAL` ([comges-ciclo-vida.md](comges-ciclo-vida.md) §4).
- Metas: convenio/apelaciones solo si es responsable designado del centro; carga en Formulación y Autoevaluación bajo DR-205-08.

### admin

- Único rol con acceso global rutinario (exención tenancy donde el código lo implementa).
- Gestión de usuarios, receptores soporte, sync SNSS, settings.
- Metas en **Cerrado**: solo lectura, igual que el resto de roles.

---

## 5. Uso en cambios documentados

Al documentar impacto en roles:

- [ ] ¿Cambia la matriz de este documento? Si sí, actualizar `modulos-roles.md` en el mismo PR o alcance.
- [ ] ¿Afecta solo Voyager, solo Filament o ambos durante transición?
- [ ] ¿Requiere validación con CG/Referentes para el rol afectado?

---

## Pendiente

- Refinar filas **Chat IA** y **Filament** cuando T1 cierre permisos en producción.
- Validar matriz **ADP** al reactivar la suite (hoy en pausa; [adp-operativo.md](adp-operativo.md)).
