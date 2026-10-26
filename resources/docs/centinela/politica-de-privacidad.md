# Política de privacidad

**Documento:** Política de privacidad y tratamiento de datos personales — plataforma Centinela  
**Versión:** 1.1 (borrador)  
**Vigencia desde:** [FECHA] _(pendiente validación institucional)_  
**Co-responsables del tratamiento:** Servicio de Salud del Reloncaví y el Servicio de Salud adoptante de pertenencia del usuario

> Este texto es un **borrador orientativo** para revisión del área jurídica y del referente de datos personales del Servicio de Salud del Reloncaví y de los Servicios de Salud adoptantes. Debe alinearse con el inventario real de tratamientos y con la **Ley N° 19.628** sobre protección de los datos personales y su reforma por la **Ley N° 21.719**, y demás normativa sectorial.

## 1. Co-responsables e información de contacto

Centinela es una plataforma **multi-Servicio de Salud**. El tratamiento de datos personales puede involucrar a más de un responsable según la naturaleza de la información:

| Co-responsable | Ámbito del tratamiento |
| --- | --- |
| **Servicio de Salud del Reloncaví** | Operación de la plataforma compartida, soporte de producto, seguridad técnica transversal, solicitudes de acceso canalizadas al operador y tratamientos que afecten la instancia como conjunto |
| **Servicio de Salud adoptante del usuario** | Datos operativos de **su territorio**: usuarios locales, cargas de instrumentos, medios de verificación, tickets y gestión cotidiana de evaluación |

### Contacto — SSR Reloncaví (plataforma)

| Campo | Detalle |
| --- | --- |
| Co-responsable | Servicio de Salud del Reloncaví |
| Domicilio | [DOMICILIO_LEGAL] _(pendiente validación institucional)_ |
| RUT (si corresponde publicarlo) | [RUT_INSTITUCIONAL] _(pendiente validación institucional)_ |
| Canal para ejercicio de derechos (asuntos de plataforma) | [CORREO_ARCO_O_FORMULARIO] _(pendiente validación institucional)_ |

### Contacto — SS adoptante del usuario

Para solicitudes relacionadas con datos operativos de **su territorio** (acceso, rectificación, cancelación, oposición u otros derechos reconocidos por la ley), el titular debe dirigirse preferentemente al canal que disponga **su Servicio de Salud de pertenencia**:

| Campo | Detalle |
| --- | --- |
| Servicio de Salud | El asignado al usuario en la plataforma |
| Canal de contacto | [CONTACTO_DATOS_SS_ADOPTANTE] _(pendiente validación por cada SS adoptante)_ |

Las solicitudes se formularán con la **identificación razonable** del solicitante. La Institución correspondiente dará respuesta conforme a los plazos y procedimientos legales vigentes.

## 2. Finalidades del tratamiento

Los datos personales tratados a través de Centinela se utilizan para las siguientes finalidades, según el módulo y rol del usuario:

- **Autenticación y gestión de acceso:** identificación, credenciales, perfiles, asignación a Servicio de Salud y rol.
- **Operación de instrumentos de evaluación:** parametrización, carga, cálculo, revisión y cierre de **COMGES**, **Metas** (leyes 18.834, 19.664, 20.707) y, cuando esté habilitado en la instancia, **ADP**.
- **Gestión territorial:** filtrado y segregación de información por SS; asignación de referentes, responsables de establecimiento y establecimientos.
- **Soporte interno:** registro, seguimiento y cierre de **tickets** de consultas, incidencias o solicitudes de configuración.
- **Solicitud de acceso público:** tramitación de formularios enviados desde la pantalla de login por personas sin usuario habilitado (vía **correo electrónico** al receptor correspondiente).
- **Contacto institucional:** respuesta a consultas enviadas desde «¿Qué es Centinela?» u otros formularios habilitados.
- **Seguridad y trazabilidad:** registros de sesión, auditoría de cambios relevantes en datos, logs técnicos y métricas de uso agregadas.
- **Notificaciones operativas:** envío de correos asociados a tickets, accesos u otros eventos configurados.
- **Asistencia contextual (condicional):** si el módulo **Chat IA** está habilitado y liberado en la instancia, orientación sobre instrumentos y procedimientos dentro de los límites definidos por la plataforma.

Centinela está diseñada para **indicadores agregados de gestión**. No es un sistema de ficha clínica. Los usuarios **no deben** cargar datos clínicos ni identificadores de pacientes salvo que una norma o procedimiento expreso lo exija y esté autorizado.

## 3. Categorías de datos personales

| Categoría | Datos tratados (referencia) | Origen / módulo |
| --- | --- | --- |
| **Identificación de usuario** | Nombre, correo electrónico, unidad, anexo, Servicio de Salud, rol | Cuenta de usuario (`/admin`) |
| **Datos operativos de instrumentos** | Asignaciones, valores agregados, resultados, semáforos, etapas, observaciones de gestión | COMGES, Metas, ADP (si habilitado) |
| **Archivos y evidencias** | Medios de verificación, planillas importadas, adjuntos de tickets | Storage de la plataforma |
| **Soporte** | Asunto, descripción, mensajes, área, estado, usuarios intervinientes | Módulo tickets |
| **Solicitud de acceso** | Nombre, correo, teléfono (opcional), cargo (opcional), SS, mensaje | Formulario público `/solicitar-acceso` (envío por correo; **sin persistencia en base de datos** de la solicitud) |
| **Datos técnicos** | Identificador de sesión, inicio y fin de sesión de plataforma, dirección IP en logs del servidor, registros de auditoría | Sesión Laravel, `platform_auth_sessions`, auditoría |
| **Asistencia IA (condicional)** | Texto de consultas del usuario en sesión; contexto de instrumento, año y SS necesario para la respuesta | Chat IA (solo si módulo habilitado y liberado) |

No se tratarán categorías que no sean estrictamente necesarias para las finalidades indicadas (principio de **proporcionalidad**). Si un usuario incluye datos sensibles o clínicos en medios de verificación, tickets o mensajes, será **responsabilidad del SS y del usuario** haberlo hecho conforme a la normativa aplicable y con la debida minimización.

## 4. Base jurídica del tratamiento

El tratamiento se fundamenta, según el caso, en:

- El ejercicio de **competencias públicas** de los Servicios de Salud en materia de gestión, evaluación y seguimiento de instrumentos MINSAL.
- El cumplimiento de **obligaciones legales** aplicables al sector salud y a la función pública.
- El **interés legítimo institucional** en seguridad, trazabilidad, continuidad operativa y mejora del servicio, con medidas proporcionales.
- El **consentimiento** del titular, cuando corresponda (por ejemplo, al enviar una solicitud de acceso o, si se habilita, al utilizar el Chat IA), sin perjuicio de otras bases previstas en la ley.

## 5. Conservación de los datos

Los datos se conservarán durante el tiempo **necesario** para cumplir las finalidades descritas y los plazos legales o reglamentarios de conservación aplicables a documentos o registros del sector salud y de la función pública.

A modo orientativo:

- **Cuentas de usuario:** mientras la relación operativa con el SS lo requiera y según políticas internas de baja.
- **Datos de instrumentos y archivos:** según ciclos de evaluación, obligaciones de conservación documental del SS y respaldos institucionales. _[Políticas de retención detalladas: pendiente validación institucional.]_
- **Sesiones de plataforma:** hasta cierre de sesión o expiración programada.
- **Tickets de soporte:** mientras estén abiertos y por el plazo posterior necesario para trazabilidad; cierre automático de tickets resueltos antiguos según configuración.
- **Auditoría:** según política del SS y necesidades de trazabilidad del operador.
- **Logs técnicos:** plazo acotado y proporcional a seguridad y diagnóstico. _[Detalle: pendiente validación institucional.]_

## 6. Comunicación y encargados del tratamiento

Los datos personales **no se ceden** con fines comerciales. Pueden comunicarse o tratarse por **encargados** estrictamente necesarios para operar la plataforma, entre otros:

| Encargado / comunicación | Finalidad | Notas |
| --- | --- | --- |
| **Proveedor de hosting / infraestructura** | Alojamiento de la aplicación y bases de datos | _[PROVEEDORES_HOSTING_CORREO: pendiente validación institucional]_ |
| **Proveedor de correo electrónico** | Notificaciones, solicitudes de acceso, contacto | Mismo placeholder anterior |
| **Repositorio de desarrollo (opcional, flujo interno)** | Si un administrador promueve un ticket a issue externo como parte del **trabajo interno** del equipo de producto | Flujo **opcional**, solo vía administrador; no es una acción del usuario final |

Si no existen otros encargados en la instancia desplegada, el operador lo declarará expresamente al completar la validación institucional.

### Chat IA (condicional)

Si el módulo **Chat IA** está habilitado y liberado, las consultas del usuario pueden procesarse mediante un **proveedor de inferencia externo** (p. ej. GitHub Models). En ese caso:

- Se enviará al proveedor **solo el contexto mínimo** necesario (instrumento, año, SS y texto de la consulta).
- Puede implicar **transferencia internacional** de datos hacia países donde opere el proveedor.
- El operador aplicará medidas de **minimización** y no registrará contenido completo de conversaciones en logs si la política interna lo restringe.

## 7. Transferencias internacionales

**No se planifican transferencias internacionales** de datos personales en el funcionamiento habitual de la plataforma, salvo:

- El uso del módulo **Chat IA**, cuando esté habilitado y liberado (ver sección 6).
- Comunicaciones **opcionales** derivadas de flujos **internos de administración** (p. ej. promoción de un ticket por un administrador), en la medida en que el servicio externo correspondiente procese datos fuera de Chile.

En esos casos, el tratamiento se realizará con las garantías y bases que exija la normativa vigente y la evaluación institucional correspondiente.

## 8. Derechos del titular

Conforme a la legislación chilena en la materia, incluida la reforma introducida por la **Ley N° 21.719**, el titular podrá ejercer los derechos que la ley le reconozca, entre ellos (según corresponda al momento de la solicitud): **acceso**, **rectificación**, **supresión/cancelación**, **oposición**, **portabilidad**, **bloqueo** y otros previstos en la normativa vigente y su reglamentación.

| Tipo de solicitud | Canal preferente |
| --- | --- |
| Datos operativos de su SS (cargas, tickets, asignaciones) | Servicio de Salud de **pertenencia** del usuario |
| Asuntos transversales de la plataforma, acceso o instancia técnica | **SSR Reloncaví** (contacto sección 1) |

## 9. Cookies y tecnologías similares

Centinela utiliza **cookies técnicas estrictamente necesarias** para el funcionamiento de la aplicación web, en particular:

- Cookie de **sesión** (`laravel_session` o equivalente), para mantener la autenticación del usuario.
- Token **CSRF**, para proteger formularios contra solicitudes falsificadas.

Estas cookies **no** tienen finalidad publicitaria ni de perfilamiento. Al cierre de sesión o expiración, dejan de ser operativas según la configuración del navegador y del servidor.

**No se utilizan cookies analíticas de terceros** en la versión actual de la plataforma. Si en el futuro se incorporaran tecnologías adicionales, esta política será actualizada.

## 10. Seguridad

Los co-responsables y el operador adoptan medidas **técnicas y organizativas** razonables para proteger los datos personales frente a accesos no autorizados, pérdida o alteración, incluyendo, según corresponda:

- Autenticación con credenciales institucionales y gestión de roles.
- **Segmentación por Servicio de Salud** en consultas y operaciones.
- Políticas de archivos que restringen carga, descarga o importación según rol y ruta (`ReadOnlyFilePolicy` y equivalentes).
- Registro de **auditoría** de cambios relevantes en modelos configurados.
- Limitación de solicitudes (**rate limiting**) en formularios públicos sensibles.
- Respaldos y controles de acceso al entorno de despliegue institucional.

## 11. Actualizaciones de esta política

Esta política podrá ser **actualizada**. La versión vigente se publicará en la plataforma con indicación de fecha y versión. Se recomienda revisarla periódicamente.

## 12. Enlaces relacionados

- [Términos de uso](/legal/terminos-de-uso)
