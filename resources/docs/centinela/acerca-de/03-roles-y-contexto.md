## Roles de la plataforma

Centinela define **cinco roles** de negocio, invariantes para todos los módulos:

| Rol | Alcance resumido |
| --- | ---------------- |
| **admin** | Acceso global: configuración, usuarios y todos los módulos habilitados. |
| **parametrizador** | Catálogos globales, maquetas de indicadores y usuarios; sin gestión operativa de tickets de soporte. |
| **control-gestion** (CG) | Seguimiento y cierre por Servicio de Salud; gestión de referentes Metas; descarga COMGES sin restricción de asignación por indicador. |
| **referente** | Carga y revisión dentro de su Servicio de Salud, según etapa del instrumento y asignación. |
| **visualizador** | Solo lectura en instrumentos; sin carga ni descarga de medios de verificación (salvo documentación normativa publicada). |

## Responsable de establecimiento (Metas)

Además de los roles anteriores, en **Metas** existe la designación de **responsable de establecimiento**: no es un rol como tal sino una asignación por centro y año que habilita, entre otras tareas, la gestión del **convenio y ponderaciones** en la etapa de Formulación y el registro de **apelaciones** en la etapa correspondiente, para su establecimiento.

## Contexto de Servicio de Salud

Cada usuario opera en el contexto de **su Servicio de Salud**. La plataforma segrega la información entre servicios: un usuario no debe acceder a datos operativos de otro Servicio de Salud salvo perfiles con alcance global documentado (admin).

## Año indicador

En el panel de administración, el **año indicador** activo en sesión condiciona la navegación y los datos mostrados en los módulos de negocio (COMGES, Metas, etc.). Al cambiar de año, las vistas y resultados se alinean al periodo seleccionado.
