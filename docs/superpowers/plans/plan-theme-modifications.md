# Plan: Implementación de Modo Claro y Oscuro

Este plan identifica los pasos necesarios para soportar correctamente temas claro y oscuro en las vistas personalizadas de Filament, evitando colores fijos (hardcoded) y aprovechando el sistema de diseño nativo.

## 1. Análisis del Problema Actual
- **Colores estáticos:** Las vistas recientes (como `custom-dashboard.blade.php`) tienen colores fijos, por ejemplo, `bg-[#1A1A1A]` (oscuro) y texto `text-white`. Estos se ven bien en modo oscuro pero son ilegibles o incorrectos en modo claro.
- **CSS no responsivo al tema:** En `resources/css/filament/admin/theme.css` se definieron variables para `.dark`, pero las vistas Blade no están utilizando las utilidades de Tailwind (`dark:`) ni las variables CSS correctamente para alternar.

## 2. Objetivos
- Permitir que el usuario cambie entre modo claro y oscuro desde el panel de Filament.
- Asegurar que todas las vistas (Dashboard, POS, Centro de Transcripción) respondan correctamente al tema activo.
- Mantener los colores de la marca (`#FF5F1F`, `#00FF94`) intactos.

## 3. Pasos de Implementación

### Tarea 1: Refactorizar las Vistas Blade (Dashboard, POS, etc.)
Reemplazar los colores hardcoded por clases de Tailwind que soporten `dark:`.
- Cambiar `bg-[#1A1A1A]` por clases dinámicas como `bg-white dark:bg-gray-900`.
- Cambiar `border-[#2A2A2A]` por `border-gray-200 dark:border-gray-800`.
- Cambiar `text-white` por `text-gray-900 dark:text-white`.
- Reemplazar textos grises como `text-gray-400` por `text-gray-500 dark:text-gray-400`.

### Tarea 2: Actualizar la Configuración de Tailwind y CSS
- Revisar `resources/css/filament/admin/theme.css` para asegurar que las variables de la paleta clara y oscura estén correctamente balanceadas si se requiere sobrescribir los valores por defecto de Tailwind.
- Opcionalmente, agregar los colores personalizados al `tailwind.config.js` para usarlos como `bg-brand-primary` en lugar de `bg-[#FF5F1F]`.

### Tarea 3: Verificación de Filament Admin Panel
- Asegurar que `AdminPanelProvider.php` no esté forzando el modo oscuro, y que el botón de toggle de tema de Filament esté habilitado (normalmente lo está por defecto).

### Tarea 4: Recompilar y Pruebas
- Ejecutar `npm run build` para generar el CSS final.
- Probar la interfaz visualmente alternando entre tema claro y oscuro en el panel de administración.

---

**Estado:** ESPERANDO APROBACIÓN
**Siguiente Paso:** Cuando estés listo, indícame ejecutar el plan o envíame tu siguiente comando.
