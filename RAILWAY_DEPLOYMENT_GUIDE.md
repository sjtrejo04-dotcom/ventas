# Guía de Despliegue en Producción: Railway + Supabase (PostgreSQL)

Esta guía detalla el procedimiento paso a paso para desplegar en la nube la aplicación **Ventas Declaración** (desarrollada con **Laravel 12**, **FilamentPHP v4**, **Livewire 3** y **Tailwind CSS**) conectada a la base de datos PostgreSQL alojada en **Supabase**.

---

## 1. Arquitectura y Requisitos Previos

- **Código Fuente**: Repositorio en GitHub [`Ventas-Declaraci-n-`](https://github.com/Agarcia313777/Ventas-Declaraci-n-), rama `main`.
- **Base de Datos**: Instancia PostgreSQL en Supabase (`aws-0-us-west-2.pooler.supabase.com:6543`) con SSL obligatorio (`DB_SSLMODE=require`).
- **Archivos de Despliegue ya incluidos en el repositorio**:
  - `nixpacks.toml`: Orquesta la instalación de PHP 8.4 con sus extensiones (`intl`, `zip`, `pdo_pgsql`, `pgsql`, `bcmath`, `mbstring`), Composer, Node.js 20 y la compilación de assets con Vite (`npm run build`).
  - `composer.json`: Incluye los requisitos de PHP `^8.4` y las extensiones nativas `ext-intl`, `ext-zip`, `ext-pdo_pgsql`, `ext-pgsql`.
  - `.env.example`: Plantilla con todas las variables de producción requeridas.

---

## 2. Paso a Paso para Desplegar en Railway

### Paso 1: Iniciar Sesión en Railway
1. Dirígete a [railway.com](https://railway.com/).
2. Haz clic en **Login** y selecciona **Continue with GitHub**.
3. Autoriza el acceso a tu cuenta de GitHub (`Agarcia313777`).

---

### Paso 2: Crear un Nuevo Proyecto desde GitHub
1. En el Dashboard de Railway, haz clic en el botón **"+ New Project"**.
2. Selecciona la opción **"Deploy from GitHub repo"**.
3. Busca y selecciona el repositorio: `Ventas-Declaraci-n-`.
4. Selecciona la rama: `main`.
5. Haz clic en **"Deploy Now"**.
   > *Nota:* El primer intento de despliegue puede quedar pausado o fallar mientras no se configuren las variables de entorno. Esto es completamente normal.

---

### Paso 3: Configurar las Variables de Entorno

1. Haz clic sobre la tarjeta del servicio web recién creado.
2. Ve a la pestaña **"Variables"**.
3. En la esquina superior derecha de la sección de variables, haz clic en el botón **"Raw Editor"**.
4. Pega el siguiente bloque exacto de variables:

```dotenv
APP_NAME="Ventas Declaracion"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:zh5ZKl/SGCD+ZAQEvJiMSn8AdYXAx7QXMkL5MZCHwK4=
APP_TIMEZONE=America/Caracas
APP_LOCALE=es
APP_FALLBACK_LOCALE=en

# Conexión cifrada a Supabase (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=aws-0-us-west-2.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.yzlopcstdglbjgmxdbjl
DB_PASSWORD=uXjDgL3EiLfIM1ME
DB_SSLMODE=require

# Sesiones y Caché en Base de Datos
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# Directorio Raíz del Servidor Web
NIXPACKS_PHP_ROOT_DIR=/app/public
```

5. Haz clic en **"Update Variables"**.

---

### Paso 4: Generar Dominio Público HTTPS y Comando Pre-Deploy

En la misma tarjeta del servicio, dirígete a la pestaña **"Settings"**:

#### A. Generar Dominio Público
1. Desplázate hacia abajo hasta la sección **Networking** (o **Public Networking**).
2. Haz clic en el botón **"Generate Domain"**.
3. Railway te asignará una URL pública segura con certificado SSL (ejemplo: `ventas-declaracion-production.up.railway.app`).
4. Copia esa URL completa y regresa a la pestaña **Variables**:
   - Agrega la variable: `APP_URL=https://tu-dominio-generado.up.railway.app`

#### B. Comando Pre-Deploy (Migraciones Automáticas)
1. En **Settings**, busca la sección **Deploy**.
2. En el campo **Pre-deploy Command** (o **Release Command**), ingresa:
   ```bash
   php artisan migrate --force && php artisan optimize:clear
   ```
   *Esto garantiza que en cada actualización de código, Railway ejecute las migraciones pendientes en Supabase antes de activar la nueva versión, sin interrumpir el servicio.*

#### C. Comando de Inicio Personalizado (Custom Start Command)
1. En **Settings** -> **Deploy**, verifica el campo **Custom Start Command**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=$PORT
   ```
   *(El repositorio ya incluye `Procfile` y `nixpacks.toml` que configuran este comando automáticamente).*

---

### Paso 5: Ejecutar el Despliegue (Redeploy)

1. Ve a la pestaña **Deployments**.
2. En el menú de los tres puntos `...` del último despliegue, haz clic en **"Redeploy"**.
3. Puedes hacer clic sobre el despliegue para ver los **Build Logs** en tiempo real.
   - Observarás que `nixpacks.toml` instala PHP 8.4, Composer y Node.js.
   - Se ejecuta `npm run build` para compilar los estilos de Tailwind CSS y Filament v4.
   - El contenedor inicia el servidor web apuntando a `/app/public`.
4. Una vez completado, el estado cambiará a **Active** con un indicador verde.

---

### Paso 6: Verificación y Acceso al Sistema

1. Abre tu navegador y accede a:
   ```
   https://tu-dominio-generado.up.railway.app/admin
   ```
2. Inicia sesión con tus credenciales de administrador:
   - **Correo Electrónico**: `test@example.com`
   - **Contraseña**: `password`
3. Verifica los módulos clave:
   - **Punto de Venta (POS)** (`/admin/pos`): Apertura de turno, ingreso de productos al carrito, cálculo del 3% de IGTF en pagos mixtos con divisas y emisión del comprobante térmico SENIAT (Modelo PlanSuárez).
   - **Compras a Proveedores** (`/admin/expenses`): Recepción de mercancía con cálculo dinámico de PVP y actualización automática de stock en Supabase.
   - **Tasas de Cambio** (`/admin/exchange-rates`): Visualización de la tasa oficial activa del BCV.

---

## 3. Automatización de la Tasa BCV (Cron Job en Railway)

En el código fuente ya está programado el comando `bcv:sync` para ejecutarse diariamente a las **09:00** y **17:00**.

Para mantener activo el programador de tareas en Railway:
1. En tu proyecto de Railway, haz clic en **"+ New"** -> **"GitHub Repo"** y selecciona el mismo repositorio `Ventas-Declaraci-n-`.
2. Renombra este segundo servicio como `worker-cron`.
3. Comparte las mismas variables de entorno del servicio principal.
4. En la pestaña **Settings** del servicio `worker-cron`, en **Custom Start Command**, coloca:
   ```bash
   php artisan schedule:work
   ```
5. Desactiva el Networking público en este servicio (no necesita dominio, solo corre en segundo plano).

---

## 4. Solución de Problemas Comunes (Troubleshooting)

| Síntoma | Causa Probable | Solución |
| :--- | :--- | :--- |
| **Error 500 / "Vite manifest not found"** | No se ejecutó `npm run build` durante el build. | Asegúrate de que el archivo `nixpacks.toml` esté presente en la raíz de tu repositorio. |
| **Error de conexión a PostgreSQL** | Credenciales o puerto incorrecto de Supabase. | Revisa que `DB_PORT=6543`, `DB_HOST=aws-0-us-west-2.pooler.supabase.com` y `DB_SSLMODE=require` estén configurados. |
| **Página en blanco o error 404** | El servidor web está sirviendo la raíz en vez de `/public`. | Confirma que la variable `NIXPACKS_PHP_ROOT_DIR=/app/public` esté definida. |
| **Error "Cannot modify header information"** | FrankenPHP finaliza la conexión antes del shutdown de Laravel. | Se soluciona con el comando `[start]` (`php artisan serve`) definido en `nixpacks.toml`. |
| **Error "No application encryption key has been specified"** | Falta la variable `APP_KEY`. | Copia exactamente el valor de `APP_KEY` provisto en el Paso 3. |
