---
id: centinela-dashboard
title: Panel principal de Centinela
version: 1.5.0
status: published
updated_at: 2026-06-17
owner: Equipo Centinela
tags: [ayuda, centinela, dashboard, inicio, año, deploy]
toc: true
applies_to:
    routes:
        - voyager.dashboard
---

## Objetivo

Esta es la **primera ayuda** que verás al entrar al **inicio del panel de Centinela** (`/admin`). El panel principal te da contexto de la aplicación, confirma en **qué año** estás trabajando los indicadores y sirve de **punto de partida** para abrir el menú lateral y el resto de módulos.

::::info
En cualquier pantalla puedes abrir **Ayuda** para ver documentación contextual según la ruta en la que te encuentres.
::::

## Información general del sistema

**Centinela** es la **aplicación**: plataforma de visualización y gestión de **indicadores** para los **Servicios de Salud**. Desde el panel accedes a instrumentos, metas, datos y reportes según tu **rol** y la configuración vigente.

**Voyager** es el **framework / panel de administración** (paquete sobre Laravel) que Centinela usa para montar la interfaz de `/admin` (menús, listados tipo BREAD, etc.). En este manual hablamos de **Centinela** y del **panel**; solo mencionamos Voyager cuando ayuda a reconocer textos o iconos que vienen de esa base.

- **Acceso:** tras iniciar sesión llegas al **inicio del panel de Centinela** (`/admin`).
- **Menú lateral:** agrupa accesos a indicadores y módulos habilitados para tu perfil (inicio, tipos de indicador y otros ítems definidos en la aplicación).
- **Barra superior:** enlaces habituales al inicio del panel, perfil y cierre de sesión (según lo configurado).
- **Perfil de usuario:** en el lateral verás tu nombre y correo; desde **Perfil** puedes revisar o ajustar datos de tu cuenta cuando la opción esté disponible.

Si el administrador activa **modo mantenimiento**, verás un aviso en el panel y se te pedirá **Cerrar sesión** hasta que finalicen las tareas.

## Uso del año de indicadores

En Centinela, el **año de trabajo** no es solo informativo: la sesión guarda un **año de indicadores** y **la mayoría de listados, formularios y cálculos** filtran datos de ese año.

### Qué verás en el panel

- Un texto del tipo: **«Usted se encuentra en los indicadores del año …»** con el año actual de la sesión.
- El botón **Cambiar año** (lista desplegable con los años disponibles en el sistema).
- Si tu perfil **no es administrador**, debajo verás el **dashboard de cumplimiento** con cuatro bloques: **Indicadores por ámbito**, **Indicadores por instrumento**, **Top 5 establecimientos** y **Alertas** (cuando apliquen a tu Servicio de Salud). Cada bloque muestra promedios o listados compactos; el icono de flecha abre una **vista detalle** con enlaces **Abrir** al módulo correspondiente (COMGES, Metas, ADP, etc.). Los datos se actualizan con una caché breve; si acabas de cargar resultados y no ves cambios, espera unos instantes o vuelve a entrar al inicio.

En la cabecera del menú lateral, el título del administrador suele mostrar también el **año activo**, para que siempre tengas visible el contexto al navegar.

### Cambiar el año de trabajo

1. En el panel principal, localiza el bloque con el año actual y el control **Cambiar año**.
2. Abre el menú y elige el año que necesitas.

**Resultado esperado:** la sesión queda asociada al año elegido y vuelves al panel principal; al abrir otros módulos, los datos corresponderán a ese año.

### Buenas prácticas con el año

- **Cambia el año desde el panel** cuando necesites revisar otro periodo; no asumas que dos pestañas del navegador comparten siempre el mismo estado de sesión.
- Tras cambiar de año, si algo no coincide con lo esperado, **vuelve a entrar al módulo** o actualiza la vista para asegurarte de que se aplicó el contexto nuevo.

## Bloques de administración (Uso y Deploy)

Los usuarios con rol **admin** pueden ver, debajo del contenido principal del inicio (`/admin`):

- **Uso de la plataforma (estadística):** pestañas con gráficos, tablas y salud del sistema (si el seguimiento de uso está activo).
- **Deploy:** historial y controles de despliegue en intranet.

Ambos bloques comparten el mismo estilo visual **Compass** de Centinela (misma línea que módulos como Establecimientos): un marco por bloque, títulos y etiquetas con tamaño uniforme, y enlaces a GitHub sin mezclar subrayado con badges de estado. Si notas paneles duplicados o texto difícil de leer, reporta al equipo.

## Panel Deploy (solo administradores)

En la parte inferior del **inicio del panel** (`/admin`), los usuarios con rol **admin** ven el bloque **Deploy**. Sirve para observar despliegues en el servidor y, si está habilitado, solicitar un deploy controlado (no sustituye el CI en GitHub).

### Qué muestra

- **Auto ON/OFF** y si el **schedule** está bloqueado tras un fallo.
- **CI GitHub** en la rama configurada (informativo; no bloquea el botón por sí solo).
- **Rama Git local** vs rama configurada en el sistema (`services.deploy_branch`).
- **Historial** de las últimas ejecuciones (clic en una fila abre el detalle).
- Enlaces al **commit en GitHub** cuando el repositorio está configurado (`GITHUB_RELEASES_REPOSITORY`).

### Solicitar deploy (UI)

1. En **Configuración del sistema**, activa **Deploy automático** (`module.deploy_automatic_enabled`) si el equipo usa el botón del panel.
2. Confirma que el checkout del servidor está en la **misma rama** que la configuración (alerta roja si no).
3. Revisa que el **CI** en GitHub esté en verde (recomendado).
4. Pulsa **Solicitar deploy** y confirma. Si no hay cambios que aplicar, verás un mensaje de _skip_; si hay trabajo real, la solicitud va a la **cola** (requiere worker activo).

**Resultado esperado:** una nueva fila en el historial con estado `succeeded`, `skipped`, `failed` o `failed_precondition`.

### Consola (alternativa)

```text
php artisan centinela:deploy:status
php artisan centinela:deploy:manual --user_id=<id>
php artisan centinela:deploy:unlock
```

Tras un fallo del deploy automático, libera el bloqueo con **Desbloquear schedule** o `centinela:deploy:unlock` antes de confiar de nuevo en el scheduler.

### Errores comunes (Deploy)

- **Rama desalineada:** el código en disco no está en la rama de deploy configurada; haz `git checkout <rama>` antes de desplegar.
- **Schedule bloqueado:** hubo un fallo previo; corrige la causa y desbloquea.
- **CI UNKNOWN:** falta token o red hacia GitHub; el deploy manual puede seguir, pero no verás el semáforo.

## Cuándo usar este panel

- Cuando acabas de iniciar sesión y quieres **confirmar el año** antes de cargar datos.
- Cuando necesitas **cambiar de año** sin buscar la opción dentro de otro módulo.
- Cuando quieres **volver al inicio** del menú y orientarte antes de abrir un instrumento o reporte.

## Errores comunes

- **Creer que los datos “mezclan” años:** cada consulta relevante está ligada al año en sesión; si ves cifras de otro periodo, revisa qué año muestra el panel y el lateral.
- **Sesión caducada o sin año:** si la sesión expira o falta el año en sesión, el sistema puede comportarse de forma distinta (por ejemplo redirecciones o mensajes); en ese caso vuelve a **iniciar sesión** y, si aplica, vuelve a fijar el año con **Cambiar año**.
- **Mantenimiento activo:** no es un fallo de tu usuario; debes esperar la reanudación del servicio o seguir las indicaciones institucionales.

## Preguntas frecuentes

**¿El año del calendario es lo mismo que el “año de indicadores”?**  
No necesariamente. El año de indicadores es el **periodo analítico o de gestión** seleccionado en la plataforma (por ejemplo un año fiscal o de instrumento), el que aparece en el panel y en el menú.

**¿Dónde cambio el año si no estoy en el panel principal?**  
La acción explícita **Cambiar año** está en el **inicio del panel**. Puedes volver con el enlace de inicio (icono de inicio en la barra superior, según tu tema) y desde ahí cambiar el año.

**¿Por qué no veo ciertos ítems en el menú?**  
Dependen de tu **rol** y de la **parametrización** (menús e instrumentos habilitados). Si falta algo que deberías usar, coordina con el administrador de tu Servicio o del sistema.

**¿“Voyager” en algún mensaje o icono es otra aplicación?**  
No: es el nombre del **panel base** que usa Centinela en `/admin`. La aplicación que usas es **Centinela**.

## Referencias

- **Manual general (respaldo):** cuando no exista una página específica para la pantalla actual, la ayuda mostrará el documento **Manual de usuario (General)** (`general`).
