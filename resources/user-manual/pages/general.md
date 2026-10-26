---
id: general
title: Manual de usuario (General — respaldo)
version: 1.1.0
status: published
updated_at: 2026-04-27
owner: Equipo Centinela
tags: [ayuda, general, centinela, fallback]
toc: true
applies_to:
    routes: []
---

## Objetivo

Este documento es el **manual de respaldo** de Centinela: aparece cuando abres **Ayuda** y **no hay una página de manual más específica** para la ruta o pantalla actual. Resume qué es la aplicación, cómo se relaciona con el panel técnico (Voyager) y qué puedes esperar del sistema y de la ayuda contextual.

## Centinela y el panel de administración

- **Centinela** es la **aplicación** de negocio: seguimiento y gestión de **indicadores** e instrumentos para los **Servicios de Salud**, con roles, datos y flujos propios.
- **Voyager** es el **framework / paquete de panel** sobre Laravel que Centinela utiliza para la interfaz de administración en **`/admin`** (menús laterales, listados, formularios tipo BREAD, etc.). Ver referencias a “Voyager” en la UI no significa que uses otra producto distinto: es la base del panel de Centinela.

## Ayuda contextual

1. En el panel o en módulos de Centinela, abre **Ayuda** (según el lugar donde esté el control en tu pantalla).
2. El sistema intenta cargar el fragmento de manual asociado a la **ruta o familia de rutas** en la que estás.

**Resultado esperado:** ves explicaciones y pasos alineados con la pantalla actual. Si no hay coincidencia, se usa este manual **general** como respaldo.

::::info
La disponibilidad del manual puede depender de la configuración del entorno (por ejemplo si la función está habilitada en tu despliegue).
::::

## Cuándo verás este manual (fallback)

- Estás en una pantalla **aún sin página dedicada** en `resources/user-manual/pages/`.
- La regla del índice de ayuda no encontró un documento más concreto y aplica el **`fallback`** configurado para Centinela.

## Navegación habitual

- El **inicio del panel** (`/admin`) concentra el mensaje de bienvenida, el **año de indicadores** y el control **Cambiar año**. La ayuda detallada de esa pantalla está en **Panel principal de Centinela** (`centinela-dashboard`); ábrela con **Ayuda** estando en el dashboard.
- El **menú lateral** depende de tu rol y de los menús habilitados; agrupa accesos a instrumentos y módulos.
- **Perfil** y **Cerrar sesión** suelen estar en la barra superior o en el menú de usuario, según el tema.

### Ir al inicio del panel desde otra sección

1. Usa el enlace de **inicio** o **panel** (por ejemplo el icono de inicio en la barra superior).
2. Confirma en el bloque central el **año** activo si vas a cargar o editar datos.

**Resultado esperado:** vuelves al dashboard de Centinela y puedes cambiar de año o elegir otro módulo desde el menú.

## Sesión, roles y permisos

- Trabajas con una **sesión** en el navegador: si expira, puede pedirse de nuevo inicio de sesión o fijar de nuevo el **año de indicadores** según el flujo.
- Tu **rol** (administrador, control de gestión, visualizador, etc.) define qué menús y acciones ves; no todos los usuarios ven las mismas opciones.

## Errores comunes

- **“No veo la ayuda que esperaba”:** puede ser fallback; revisa si estás en una pantalla muy nueva o poco documentada, o pide que se añada una regla y página en el manual.
- **“No tengo un botón que tiene otro usuario”:** revisa **rol** y **Servicio** asociado; no es un fallo intermitente del menú en la mayoría de los casos.
- **Datos que no cuadran con el año:** el contexto de año se fija en el panel principal; vuelve al inicio, revisa el año mostrado y usa **Cambiar año** si hace falta.

## Preguntas frecuentes

**¿Dónde está la ayuda del dashboard y del año?**  
En el manual **Panel principal de Centinela** (`centinela-dashboard`). Debes abrir **Ayuda** estando en la ruta de inicio del panel (`voyager.dashboard` / `/admin`).

**¿Este archivo sustituye al resto del manual?**  
No: es el **último recurso** cuando no hay documento específico. Con el tiempo, las pantallas deberían tener su propia página enlazada por reglas en `index.yaml`.

## Soporte

Si necesitas soporte, contacta al equipo correspondiente de tu Servicio o de la plataforma.

## Referencias

- **Inicio del panel y año de indicadores:** `centinela-dashboard` (ayuda al estar en el dashboard de Centinela).
