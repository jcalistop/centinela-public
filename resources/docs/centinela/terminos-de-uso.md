# Términos de uso

**Documento:** Términos de uso de la plataforma Centinela  
**Versión:** 1.1 (borrador)  
**Vigencia desde:** [FECHA] _(pendiente validación institucional)_  
**Operador de la plataforma:** Servicio de Salud del Reloncaví

> Este texto es un **borrador orientativo** para revisión del área jurídica del Servicio de Salud del Reloncaví y de los Servicios de Salud adoptantes antes de su publicación oficial.

## 1. Objeto y ámbito

Los presentes Términos de uso regulan el acceso y la utilización de la plataforma web **Centinela**, orientada al **seguimiento, consolidación y rendición de cuentas** de instrumentos de evaluación del ámbito del Ministerio de Salud (MINSAL) en Servicios de Salud (SS) y sus establecimientos.

Centinela permite, entre otras funciones, la parametrización, carga, cálculo y revisión de resultados de instrumentos como **COMGES** (Compromisos de Gestión) y **Metas** sanitarias (leyes 18.834, 19.664 y 20.707), según la habilitación de cada SS. El acceso autenticado se realiza principalmente a través del panel administrativo (`/admin`).

El usuario opera siempre en el contexto de **su Servicio de Salud** y **su rol** asignado. El uso de la plataforma implica la lectura y aceptación de estos términos en la medida en que resulte aplicable según el perfil de usuario y la normativa vigente.

## 2. Plataforma compartida y co-responsabilidad

Centinela es una **plataforma compartida**: el **Servicio de Salud del Reloncaví** (en adelante, «SSR Reloncaví») la desarrolla, publica y opera como instancia técnica, y otros Servicios de Salud pueden **adoptarla** para sus procesos de evaluación. Cada SS mantiene **datos separados**; la confidencialidad entre territorios es obligatoria.

| Actor | Rol en la plataforma |
| --- | --- |
| **SSR Reloncaví** | Desarrollo, evolución, operación de la instancia y soporte de producto |
| **SS adoptante** | Administración de usuarios, parametrización territorial y operación de instrumentos habilitados en su territorio |
| **MINSAL** | Marco normativo y evaluación de instrumentos; **no** es operador centralizado de Centinela |
| **Usuario** | Accede con credenciales institucionales en contexto de su SS y rol |

En materia de datos personales, el tratamiento se rige por la [Política de privacidad](/legal/politica-de-privacidad), donde se describe el modelo de **co-responsabilidad** entre SSR Reloncaví (plataforma) y el SS adoptante del usuario (datos operativos de su territorio).

### Identificación del operador de la plataforma

- **Razón social:** Servicio de Salud del Reloncaví
- **Domicilio:** [DOMICILIO_LEGAL] _(pendiente validación institucional)_
- **Correo de contacto institucional:** [CORREO_INSTITUCIONAL] _(pendiente validación institucional)_

Para asuntos propios de un SS adoptante distinto del Reloncaví, el usuario debe canalizar sus consultas operativas o de acceso ante la autoridad o unidad de gestión de **su Servicio de Salud de pertenencia**.

## 3. Módulos e instrumentos

La disponibilidad de módulos depende de la **versión desplegada** y de la **habilitación configurada para cada SS**. Actualizar el software no implica automáticamente que todos los instrumentos estén activos en un territorio.

| Categoría | Módulo / instrumento | Estado al corte jul-2026 |
| --- | --- | --- |
| Instrumentos | COMGES | **Liberado** |
| Instrumentos | Metas (18.834, 19.664, 20.707) | **Liberado** (si el SS está habilitado) |
| Instrumentos | ADP (Alta Dirección Pública) | **En desarrollo** |
| Soporte | Tickets internos | **Liberado** |
| Soporte | Manual contextual | **En desarrollo** |
| Soporte | Chat IA | **En desarrollo** |
| Catálogo | Establecimientos (SNSS) | **Liberado** (según rol) |

Los módulos marcados como **en desarrollo** pueden existir parcialmente en el servidor, pero **no** deben considerarse herramientas operativas liberadas para usuarios finales hasta comunicación expresa del operador de la plataforma.

## 4. Perfiles de acceso

La plataforma contempla **cinco roles de negocio** con alcance definido por la Institución y la configuración técnica vigente:

| Rol | Alcance típico |
| --- | --- |
| **Administrador** | Configuración global, usuarios, habilitación de módulos y catálogos transversales |
| **Parametrizador** | Catálogos, maquetas de indicadores y documentación normativa; sin acceso al módulo de tickets |
| **Control de gestión (CG)** | Seguimiento y cierre por SS; gestión de referentes y configuración Metas en su territorio |
| **Referente** | Carga y revisión de indicadores según **asignación**, **etapa** y **corte** vigentes |
| **Visualizador** | Solo lectura; sin carga ni descarga de medios de verificación COMGES |

En el instrumento **Metas**, el **responsable de establecimiento** es una **designación por centro** (no un rol de login adicional): el CG designa a uno o más usuarios referentes para operar en ese establecimiento — configurar convenio y ponderaciones en Formulación, cargar datos en su centro cuando además son referentes del indicador, y registrar apelaciones en la etapa correspondiente.

Se aplicará el principio de **mínimo privilegio**: a cada persona le corresponde el rol estrictamente necesario para sus funciones.

## 5. Uso permitido

El usuario se compromete a:

- Utilizar la plataforma **exclusivamente** para fines relacionados con los instrumentos y procedimientos **autorizados** para su SS y su rol.
- Respetar el **calendario de etapas y cortes** de cada instrumento (autoevaluación, preliminar, apelación, cierre, según corresponda).
- Mantener la **confidencialidad** de sus credenciales de acceso y notificar de forma oportuna cualquier uso no autorizado.
- Respetar la **integridad** de la información registrada y las instrucciones operativas de su SS o del operador de la plataforma.
- **No intentar acceder** a datos de otros Servicios de Salud ni eludir los filtros territoriales del sistema.
- Registrar consultas, incidencias o solicitudes de configuración preferentemente a través del **módulo de tickets** de soporte, cuando esté habilitado.
- Cargar únicamente **indicadores agregados de gestión** y evidencias acordes al instrumento; **no** incorporar datos clínicos ni identificadores de pacientes cuando no sean estrictamente necesarios y autorizados.

## 6. Conductas prohibidas

Queda prohibido, entre otros:

- Acceder o intentar acceder a recursos, indicadores, archivos o datos de **otro SS** o para los que el usuario **no tenga autorización** según su rol y asignación.
- Introducir **código malicioso**, realizar ataques o acciones que comprometan la **seguridad** o disponibilidad del servicio.
- **Compartir credenciales** o permitir que terceros no autorizados operen con la cuenta del usuario.
- Subir **datos clínicos**, identificadores de pacientes u otros **datos personales de terceros** innecesarios para las finalidades del instrumento.
- Utilizar la plataforma de forma que vulnere la **normativa sectorial** (incluida la relativa a datos personales y reserva médica) o derechos de terceros.
- Alterar, suprimir o falsear registros con fines distintos a los procedimientos oficiales del instrumento.

## 7. Propiedad intelectual y reutilización de contenidos

Los elementos de software, diseño y contenidos propios de la plataforma se rigen por la normativa aplicable al sector público y a la propiedad del operador y de los SS que aporten contenidos, salvo indicación en contrario. La reutilización de información pública, en su caso, se ajustará a la **Ley N° 20.248** y normas complementarias.

Los **resultados agregados** y la información operativa de un SS pueden estar sujetos a **restricciones de confidencialidad territorial**; no podrá divulgarse información de un territorio a usuarios de otro.

## 8. Limitación de responsabilidad

La plataforma se ofrece según la **disponibilidad técnica y operativa** del operador. Pueden producirse **interrupciones** programadas o urgentes por mantenimiento o seguridad. No se garantiza un funcionamiento ininterrumpido ni libre de errores; se procurará su corrección en plazos razonables.

Centinela **no sustituye** el criterio normativo del MINSAL, las resoluciones oficiales de los instrumentos ni las decisiones de gestión que correspondan a cada SS. Las visualizaciones y cálculos de la plataforma apoyan el seguimiento interno; la evaluación formal de cada instrumento se rige por su marco legal y metodológico vigente.

## 9. Modificaciones

El operador de la plataforma podrá **actualizar** estos Términos de uso. Se indicará la versión y fecha de vigencia en el encabezado del documento. El uso continuado de la plataforma tras la publicación de cambios relevantes podrá considerarse aceptación en los términos que defina la normativa aplicable y la política interna de la Institución.

## 10. Ley aplicable y reclamos

Estos términos se interpretan conforme a las **leyes de la República de Chile**. Las controversias o reclamos se canalizarán por los medios que el SS de pertenencia del usuario o el SSR Reloncaví dispongan, según corresponda, sin perjuicio de las vías que correspondan según la naturaleza jurídica de la relación (administrativa, laboral, etc.).

## 11. Política de privacidad

El tratamiento de datos personales asociado al uso de la plataforma se describe en la [Política de privacidad](/legal/politica-de-privacidad).
