# Reglas de dominio — Centinela

> Reglas de negocio transversales que el agente debe verificar en cambios documentados y PRs.
> **No** duplicar en perfiles del kit — fuente única para la instancia.

**Visión de plataforma (hoy en pausa):** [instrumentos-plataforma.md](instrumentos-plataforma.md).

Última formalización: **2026-10-25** (ocultar Filament; pausa de evolución de plataforma / producto cerrado / admin Voyager). Antes: **2026-10-15** (paneles Filament multi-audiencia); **2026-10-07**.

**Ritual periódico:** ver [README.md](README.md) § Ritual de alineamiento.

---

## Contexto

Centinela es el sistema de evaluación y seguimiento de instrumentos MINSAL para Servicios de Salud (SS): COMGES, Metas (18.834, 19.664, 20.707), ADP y módulos de soporte. Los usuarios operan en el contexto de su SS y rol; la confidencialidad entre SS es obligatoria.

Documentación relacionada: [README.md](README.md) · [instrumentos-plataforma.md](instrumentos-plataforma.md) · [metas-ciclo-vida.md](metas-ciclo-vida.md) · [metas-calculo.md](metas-calculo.md) · [comges-ciclo-vida.md](comges-ciclo-vida.md) · [comges-calculo.md](comges-calculo.md) · [adp-operativo.md](adp-operativo.md) · [modulos-roles.md](modulos-roles.md).

---

## Roles y autorización (5 invariantes)

Los cinco roles de negocio son **invariantes** durante la transición Voyager → Filament. La implementación técnica de permisos puede migrar de tablas Voyager a policies Laravel + Filament Shield, pero el significado operativo de cada rol no cambia sin alcance documentado.

| Rol | Alcance típico | Notas |
| --- | -------------- | ----- |
| **admin** | Acceso global; exención de filtro tenancy cuando el código lo permite | Configuración, usuarios, todos los módulos |
| **parametrizador** | Catálogos globales, maquetas de indicadores, usuarios Voyager/Filament | Ve compromisos COMGES sin indicadores configurados; tickets de soporte (gestión) |
| **control-gestion** (CG) | Seguimiento y cierre por SS; catálogo ADP por SS; documentos normativos | Descarga COMGES sin restricción de usuario asignado; gestión de referentes Metas |
| **referente** | Carga y revisión dentro de su SS | Edición COMGES según etapa/corte y asignación; carga Metas/ADP en su ámbito |
| **visualizador** | Solo lectura en instrumentos | Sin upload/import ni descarga de medios de verificación; solo documentos normativos (`documentos`/`documents`) |

**Reglas:**

- Toda consulta o mutación de datos operativos debe respetar el **Servicio de Salud** del usuario autenticado (`Auth::user()->cen_hie_dependency_id`). Sin consultas globales sin filtro explícito y justificado por escrito. En modelos adoptantes, el trait `BelongsToHealthService` aplica GlobalScope de tenancy.
- El rol **visualizador** tiene restricciones adicionales vía `ReadOnlyFilePolicy` (denegar upload, import, media manager Voyager; descarga limitada a documentación normativa).
- Cambios en permisos, roles o policies requieren verificación en panel admin (`/admin`) y en flujos Livewire afectados.
- Matriz detallada módulo × rol: [modulos-roles.md](modulos-roles.md).
- **Acceso a instrumentos (COMGES / Metas / ADP)** vía `ModuleAccess`:
 1. **Capa 1 — `indicator_types.estado`:** si Off → denegar a **todos** los roles (incluido admin). Menú y Module Access no operan el instrumento.
 2. **Capa 2 — toggle SS / `habilitado` anual / SS:** si Off → denegar a todos **excepto admin y parametrizador**. `control-gestion` no bypasea esta capa.

---

## Entidades y períodos activos

| Concepto | Regla |
| -------- | ----- |
| **Año indicador (COMGES / Metas)** | Año activo vía servicio `YearContext` (clave de sesión `indicator_year` sin renombrar; v4.13.0). Trait Eloquent `FiltersByActiveYear` (`scopeCurrentYear`). Validar ramas **`ano < 2025`** y **`ano >= 2025`** antes de tocar COMGES o vistas por año. **Excepción:** el dashboard público (`GET /`, `/publico/ss/{ss}`, `/publico/paneles/{slug}`, `/publico/paneles/{slug}/preview`, `/publico/centinela`) usa año por query string en detalle SS y **no** escribe `session('indicator_year')` / `YearContext`. `/publico/centinela` es solo copy institucional (sin datos de instrumentos). Solo aplica si `module.public_dashboard_enabled` está On (Off → redirect login). `GET /` suma un total de visitas sin usuario, SS ni sesión. En `GET /`, CTA «¿Quieres publicar un panel?» abre modal de requisitos y `mailto:` a `notifications.contact_mail_to`; no automatiza publicación. En detalle de panel **publicado**, CTA «Reportar inconsistencia» usa el mismo buzón `mailto:` contextual (slug/URL/versión); no crea ticket ni modifica el panel. |
| **Instrumento** | Bifurcar por `indicatorType` antes de modificar lógica, rutas o UI por instrumento. |
| **Servicio de Salud** | Filtrar por `cen_hie_dependency_id` del usuario; no exponer datos de otras SS. Chasis tenancy vigente: trait `BelongsToHealthService` (GlobalScope; sin bypass `admin` en el trait; activación por modelo — v4.14.0). **Excepción:** lectura del catálogo CEN (`cen_centers`) en `/admin/establecimientos` (y `/catalogo/establecimientos` si el flag T2b está On). El maestro de establecimientos es información territorial pública. Dropdown de SS (default = SS del usuario); explorar otros SS es solo lectura (browse/mapa/ficha/export). Mutaciones del catálogo e instrumentos siguen el SS del usuario. No escribe `YearContext` ni muta `cen_hie_dependency_id` del usuario. No es bypass de `BelongsToHealthService`. **Excepción:** agregados COMGES/Metas por SS habilitado visibles **sin autenticación** solo si `module.public_dashboard_enabled` y `module.public_ss_dashboard_enabled` están On (solo resumen; sin carga cruda, PII ni medios). Paneles nacionales curados requieren dash On y `publicado` en el hub (`GET /`): modo `html_bundle` (HTML/CSV/PNG; iframe `sandbox="allow-scripts"` sin `allow-same-origin`) o `embed_externo` (URL https allowlist + PNG; iframe a origen externo). **Preview:** panel con assets listos y `publicado = false` puede verse sin login solo vía URL firmada temporal (TTL config `centinela.public_info_panel_preview_ttl_days`, default 7). El shell usa gate de sesión; los assets del iframe `html_bundle` (sin `allow-same-origin`) se autorizan además con firma HMAC en la URL (`<base>` / path), sin cookies; **no** aparece en la landing; no suma al contador de visitas. Metadatos de autoría opcionales; título y descripción obligatorios. Upload HTML con scan; curadores + **contrato para agentes** `.md` (descargable en guest con dash On); ZIP solo precarga el Form Filament (Create/Edit), **sin** auto-publicar. Invitación a publicar: mailto al admin; sin cola ni upload desde el guest. Reportar inconsistencia en panel publicado: mailto contextual al mismo buzón; no aplica en preview. Curador admin puede generar el enlace de revisión desde Filament Configuración (copiar URL / mailto). |
| **Cortes COMGES** | Respetar `CurrentCutoffPolicy` y gates `current-add-edit` / `current-download` al tocar medios de verificación o acciones por corte. |

---

## Invariantes transversales (visión de plataforma, en pausa)

Aplican a la campaña **v5.0.0** y convergencia de instrumentos. Detalle en [instrumentos-plataforma.md](instrumentos-plataforma.md).

| Invariante | Regla |
| ---------- | ----- |
| Segmentación SS | No negociable; `BelongsToHealthService` y/o filtrado explícito en toda ruta de acceso. **Excepción:** lectura del catálogo territorial CEN en el módulo establecimientos (ver fila Servicio de Salud). |
| Año activo | `YearContext` + clave `indicator_year`; sin ramas ad hoc dispersas. |
| Bifurcación COMGES | `< 2025` = porcentaje; `≥ 2025` = puntaje 0–4 (`ComgesYearMode`). |
| Inmutabilidad histórica | Resultados publicados no se alteran; convergencia P1 exige paridad bit a bit antes del switch. |
| Chasis de instrumentos | Nuevos instrumentos solo vía chasis común; sin cuarto motor ni cuarto catálogo aislado. |
| Interoperabilidad | **P3** (diferido): API sobre capa de medición. **P3a** (anticipo readonly): fachada neutral + MCP in-app (fachada de lectura para agentes); no esquemas `com_/met_/adp_` como contrato público. |
| Ventanas operativas | Cambios de plataforma fuera de cortes COMGES y cierres Metas. |

---

## Reglas por instrumento (resumen)

| Instrumento | Documento de negocio | Estado |
| ----------- | -------------------- | ------ |
| COMGES | [comges-ciclo-vida.md](comges-ciclo-vida.md) · [comges-calculo.md](comges-calculo.md) | Formalizado |
| Metas (18.834 / 19.664 / 20.707) | [metas-ciclo-vida.md](metas-ciclo-vida.md) · [metas-calculo.md](metas-calculo.md) | Formalizado (ciclo alineado; ver formalización 2026-09-01 en ciclo de vida) |
| ADP | [adp-operativo.md](adp-operativo.md) | Borrador formal — suite ADP **en pausa** (no publicado); no tratar como invariante definitivo |

---

## Reglas transversales para cambios y PRs

### Año e instrumento

- [ ] Si afecta COMGES: documentar impacto en flujo pre-2025 y 2025+.
- [ ] Si afecta un `indicatorType` concreto, nombrarlo en el alcance.
- [ ] Si lee o escribe el año activo: preferir `YearContext` / `FiltersByActiveYear`; no añadir lecturas ad hoc de `session('indicator_year')` salvo justificación.

### Filtrado de datos

- [ ] Consultas y acciones filtradas por SS del usuario salvo rol con alcance explícito documentado.
- [ ] Si el modelo tiene (o debería tener) tenancy SS: considerar `BelongsToHealthService`; no inventar bypass `admin` en el trait.

### Rutas y caché

- [ ] Si toca rutas dinámicas (`routes/web.php` aprox. líneas 43–51 o registro por `IndicatorType`): documentar y evaluar invalidación de caché `indicator_type_routes`.

### UI administrativa (transición Voyager → Filament)

> **Pausa operativa 2026-10-24 (producto cerrado / admin Voyager):** no se abren cambios de plataforma Filament (T2d+, T3, C1b, P1/P2/P3). El **producto cerrado** cuya administración se traspasa opera con **Voyager `/admin` como único shell administrativo visible**. Paneles públicos (curaduría) y mensajería viven en Voyager. Filament permanece en el árbol y está **oculto** (maestro hard-off, rutas 404, toggles Settings ocultos, keys fuera de `.env.example`); no se desmantela (`config`/gates/BD quedan). Guest Livewire y MCP/P3a no cambian. El chasis de cuatro paneles abajo es **histórico** (código en árbol / tests); no autorizan reabrir la evolución de plataforma.

- [ ] Identificar capa afectada: **Voyager BREAD** (legacy), **Filament Resource** (objetivo) o **Livewire custom** en panel Filament.
- [ ] **¿En qué panel Filament vive?** (`servicio-salud` / `catalogo` / `pais` / `configuracion`) — ver [Paneles Filament](#paneles-filament-chasis-multi-audiencia).
- [ ] Si retira funcionalidad Voyager: exigir paridad validada con CG/Referentes antes del retiro.
- [ ] Cambios de plataforma Filament: citar evolución de plataforma / admin Voyager en impacto técnico.

#### Paneles Filament (chasis multi-audiencia)

La UI Filament se organiza en **cuatro paneles** (paths URL), cada uno con audiencia y gate de rol propios. No sustituyen Voyager `/admin` hasta un alcance de retiro documentado (T3). Decisión de chasis: evolución de plataforma / admin Voyager §2.2. El path `/filament` se **retira** (no alias permanente; sin uso en producción con flags on).

| Panel | Path | Roles de entrada | Contenido típico |
| ----- | ---- | ---------------- | ---------------- |
| Operación SS | `/servicio-salud` | visualizador, referente, control-gestion (+ admin soporte) | Trabajo diario por SS + año (p. ej. browse COMGES C1a, contexto año/SS) |
| Catálogo | `/catalogo` | parametrizador, admin | Parametrización global infrecuente (alto blast radius: afecta a todos los SS) |
| País | `/pais` | parametrizador, admin | Trabajo diario autenticado nivel país; **no** es el dashboard guest |
| Configuración | `/configuracion` | admin | Plataforma: settings, flags, deploy UI. Curaduría paneles públicos **canónica: Voyager `/admin/paneles-publicos`**. Path Filament oculto. |

**Reglas:**

1. Un panel = una audiencia / riesgo principal (no mezclar operación SS con catálogo global en el mismo shell; catálogo y país se separan aunque compartan roles: blast radius vs frecuencia de uso).
2. Tenancy y año siguen vigentes en paneles operativos (`YearContext`, `cen_hie_dependency_id`); el contexto año/SS en UI solo en `servicio-salud`.
3. **Tres capas de acceso** (ninguna sustituye a otra). Producto cerrado:
 - **Maestro:** hard-off en runtime (`FilamentChasisGate::runtimeEnabled()` siempre `false`). Env `CENTINELA_FILAMENT_*` y BD `module.filament_enabled` se ignoran. URLs de paneles, login `/servicio-salud/login` y legado `/filament/*` → **404**. Toggles `module.filament_*` ocultos en Settings; keys fuera de `.env.example`. `config`/gates/filas BD quedan. Reabrir exige un alcance documentado. En PHPUnit el OR inclusivo legado sigue (solo tests).
 - **Panel / superficie (histórico, tests):** matriz de rol (`canAccessPanel`); flags por Resource/Page. Encender el maestro en test no enciende todas las superficies.
 - **Ids/paths:** `Panel::id()` = `path` = kebab-case (`servicio-salud`, `catalogo`, `pais`, `configuracion`).
4. Guest/público (`module.public_dashboard_*`) **no** es el panel Filament `pais`.
5. Cambios de path, id de panel o matriz rol→panel requieren alcance documentado (y enmienda de la decisión de chasis §2.2 si cambia el chasis).
6. Si el usuario tiene acceso a **más de un** panel, la UI ofrece **«Cambiar panel»** (menú de usuario): solo destinos permitidos por su rol; no deep-link a la página actual en otro shell.
7. **Login canónico:** Voyager `/admin/login` (`GET /login` redirige ahí). `/servicio-salud/login` → **404**. Home post-login Filament por rol es histórico (solo tests con override).
8. **parametrizador** no entra a `/servicio-salud`. Trabajo diario: `/pais`; parametrización global: `/catalogo`. Operación SS queda en visualizador, referente, control-gestion (+ admin soporte). Decisión de producto 2026-10-20 (sin alcance documentado formal).
9. **Mensajería manual (superficie canónica: Voyager `/admin/mensajeria`):** emiten solo `admin` (alcance global) y `control-gestion` (su `cen_hie_dependency_id`; no bypasea SS ajena). Entrada: botón a la derecha del browse Usuarios (no ítem de menú). Paths Filament `/configuracion/mensajeria` y `/servicio-salud/mensajeria` ocultos. Voyager **no** usa `FilamentMessagingGate`.

### Cortes y políticas de edición (COMGES)

- [ ] Si afecta altas/ediciones o descargas «al corte»: verificar `CurrentCutoffPolicy` y reglas en `ComgesCutoffUiRules` / soporte equivalente.

### Cálculo Metas

- [ ] Si afecta agregación, semáforos o cumplimiento: cruzar con [metas-calculo.md](metas-calculo.md).

### Cálculo COMGES

- [ ] Si afecta evaluaciones, agregación global o escalas: cruzar con [comges-calculo.md](comges-calculo.md).

### Convergencia de instrumentos (P1–P3)

- [ ] Si afecta motor de fórmulas o sensibilidad compartidos (P1): documentar paridad histórica requerida.
- [ ] Si afecta capa de medición o catálogo unificado (P2): cruzar con [instrumentos-plataforma.md](instrumentos-plataforma.md).
- [ ] Si expone API de datos (**P3** o anticipo **P3a**): segmentación SS en tokens; contrato neutral (instrumento como atributo); no exponer esquemas internos por instrumento. Citar fachada de lectura para agentes en P3a.

### PII y egreso a proveedores LLM (P3a / agentes)

> Formalizado 2026-10-07 (AG-12). Detalle de decisión: fachada de lectura para agentes.

- La allowlist IP / «intranet only» protege el **canal** Centinela; **no** impide que un agente (Cursor, bots, etc.) envíe datos extraídos al **proveedor del modelo** fuera de la red institucional.
- Datos potencialmente sensibles en lecturas de instrumentos: nombres/correos de referentes y responsables de establecimiento; texto libre en observaciones; metadatos de documentos adjuntos.
- Tokens de agente: ability **`read` únicamente**; atados a un SS; sin mutaciones de dominio vía `api/v1` / `mcp` en el programa P3a.
- Activación operativa del flag `CENTINELA_AGENT_API_ENABLED` en un entorno con datos reales requiere **aceptación institucional** del riesgo de egreso (jurídica / Encargado de Protección de Datos según corresponda). Esta regla no determina por sí sola la aplicabilidad de Ley 19.628 / 21.719 / 21.663.
- Cambios P3a: documentar en impacto técnico si el endpoint/tool puede devolver PII y cómo se audita el acceso (log de requests de agente).

- [ ] Si expone o amplía canal agentes (`api/v1`, `mcp`): citar esta sección; no asumir escritura ni egreso «seguro por intranet».

---

## Glosario

| Término | Definición |
| ------- | ---------- |
| **SS** | Servicio de Salud — unidad organizacional de filtrado de datos |
| **COMGES** | Instrumento de evaluación con reglas distintas según año activo |
| **Metas** | Instrumento de metas con cálculo periódico y semáforos (leyes 18.834, 19.664, 20.707) |
| **ADP** | Acuerdo de desempeño por cargos e indicadores; convenios multianuales |
| **CG** | Control de gestión — rol de seguimiento institucional por SS |
| **Corte** | Período de reporte COMGES con políticas de edición/descarga |
| **Chasis** | Capacidades compartidas entre instrumentos (catálogo, motor, medición, presentación) |
| **Medición** | Definición de qué se mide y cómo se carga; entidad futura compartida (P2) |
| **Instrumento** | Tipo de evaluación habilitado (`indicator_types`: COMGES, Metas-*, ADP) |
| **Etapa** | Fase del ciclo de evaluación. **COMGES** (por SS): Autoevaluación → Evaluación preliminar → Apelación → Evaluación final → Cerrado — precedida de parametrización global y configuración por SS ([comges-ciclo-vida.md](comges-ciclo-vida.md)). **Metas** (por SS): No iniciado → Formulación → Autoevaluación → Preliminar → Apelación → Final → Cerrado — precedida de parametrización global del catálogo ([metas-ciclo-vida.md](metas-ciclo-vida.md)). **ADP:** ver [adp-operativo.md](adp-operativo.md) (borrador; suite en pausa). |
| **Ley (Metas)** | Marco normativo que define un `indicatorType` Metas (18.834, 19.664, 20.707) |
| **Convenio (ADP)** | Acuerdo de desempeño multianual por asignación cargo-establecimiento |
| **Convenio del establecimiento (Metas)** | Selección en convenio y ponderaciones del centro; estados `Borrador` / `Enviado` con firma de envío |
| **Responsable de establecimiento (Metas)** | Designación por centro/año/instrumento (no rol Voyager). CG designa uno o más usuarios referentes por establecimiento; un usuario solo puede ser responsable de un centro. Capacidades: convenio y ponderaciones (etapa Formulación), carga en su centro (si además es referente del indicador), registro de apelaciones (etapa Apelación). Ver matriz en [modulos-roles.md](modulos-roles.md) § Metas. |
| **Bitácora del establecimiento (Metas)** | Historial de solo lectura del ciclo del centro (envíos/reaperturas del convenio y apelaciones); modal on-demand desde Indicadores del centro |
| **YearContext** | Servicio de lectura/escritura del año activo en sesión (`indicator_year`) |
| **BelongsToHealthService** | Trait Eloquent con GlobalScope de tenancy por SS |
| **Escala puntaje** | COMGES `ano ≥ 2025`: cumplimiento como puntaje 0–4, no porcentaje |
| **Escala porcentaje** | COMGES `ano < 2025` y Metas: cumplimiento en % |
| **Panel Filament** | Instancia Panel Builder con path URL y auth propios (`servicio-salud`, `catalogo`, `pais`, `configuracion`). Matriz rol→panel en [Paneles Filament](#paneles-filament-chasis-multi-audiencia). |

---

## Impacto en cambios documentados (checklist rápido)

Al documentar el impacto técnico de un cambio, responder (o «No aplica — razón»):

| Pregunta | Referencia |
| -------- | ---------- |
| ¿Afecta flujo pre-2025 (`ano < 2025`)? | Entidades y períodos activos |
| ¿Afecta flujo 2025+ (`ano >= 2025`)? | Entidades y períodos activos |
| ¿Afecta un `indicatorType` concreto? | Reglas por instrumento |
| ¿Afecta filtrado por `cen_hie_dependency_id` / `BelongsToHealthService`? | Filtrado de datos |
| ¿Lee o escribe el año activo (`YearContext` / `FiltersByActiveYear`)? | Entidades y períodos activos |
| ¿Toca rutas dinámicas? | Rutas y caché |
| ¿Requiere invalidar caché `indicator_type_routes`? | Rutas y caché |
| ¿Es visible en admin (Voyager / Filament / Livewire)? | UI administrativa |
| ¿En qué panel Filament vive (`servicio-salud` / `catalogo` / `pais` / `configuracion`)? | Paneles Filament |
| ¿Afecta `CurrentCutoffPolicy`? | Cortes COMGES |
| ¿Afecta convergencia P1/P2/P3? | Invariantes de visión de plataforma |
| ¿Requiere paridad histórica de resultados? | Inmutabilidad histórica |

Completar además la tabla de **impacto stack** Laravel/Voyager (hasta retiro Voyager, fase T3). Cambios de plataforma v5.0.0 deben citar la visión de plataforma (hoy en pausa).
