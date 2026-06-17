# 🚀 Guía de despliegue en un servidor

Pasos para instalar el **Sistema de Torneos de Ping Pong** en un servidor (Linux + Apache/Nginx).
Está pensado como una receta: seguila en orden y al final tenés la app andando con HTTPS.

> Para correr en tu PC con Laragon, mirá el `README.md`. Esta guía es para un **servidor real**.

---

## 0. Requisitos del servidor

| Componente | Versión / detalle |
|------------|-------------------|
| PHP | **8.1 o superior** (probado en 8.3) en modo FPM o módulo Apache |
| Base de datos | **MySQL 8** o **MariaDB 10.3+** |
| Composer | 2.x |
| Servidor web | Apache (con `mod_rewrite`) o Nginx |
| HTTPS | Certificado SSL (obligatorio para que funcione la instalación de la PWA) |

**Extensiones de PHP necesarias** (las dos últimas las pide PhpSpreadsheet para la carga masiva de Excel):

```
pdo_mysql  mbstring  ctype  fileinfo  xml  zip  gd
```

Verificá qué tenés instalado:

```bash
php -m
```

En Ubuntu/Debian, si falta alguna:

```bash
sudo apt install php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-curl
```

---

## 1. Subir el código al servidor

Clonando desde el repositorio:

```bash
cd /var/www
git clone <URL_DEL_REPOSITORIO> pingpong
cd pingpong
```

O subiendo el proyecto por SFTP/rsync **sin** la carpeta `vendor/` (se reinstala en el paso 2).

---

## 2. Instalar dependencias (solo producción)

```bash
composer install --no-dev --optimize-autoloader
```

- `--no-dev` omite PHPUnit y herramientas de desarrollo.
- `--optimize-autoloader` genera el autoload optimizado (más rápido).

---

## 3. Configurar el entorno (`.env`)

El archivo `.env` **no viene en el repo** (tiene credenciales). Crealo en la raíz del proyecto:

```bash
nano .env
```

Pegá esto y ajustá los valores a tu servidor:

```env
# --- Aplicación ---
APP_NAME="Torneos Ping Pong"
APP_TZ=America/Bogota
# En PRODUCCIÓN siempre false (no exponer errores al usuario).
APP_DEBUG=false

# --- Base de datos ---
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pingpong
DB_CHARSET=utf8mb4
DB_USER=pingpong
DB_PASS=una_clave_fuerte_aca
```

> ⚠️ **Nunca** dejes `APP_DEBUG=true` en producción: mostraría rutas y detalles internos ante cualquier error.

---

## 4. Crear la base de datos y cargar el esquema

```bash
# Crear la base y un usuario dedicado (no uses root para la app)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS pingpong CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'pingpong'@'localhost' IDENTIFIED BY 'una_clave_fuerte_aca';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON pingpong.* TO 'pingpong'@'localhost'; FLUSH PRIVILEGES;"

# Cargar el esquema (tablas)
mysql -u pingpong -p pingpong < database/schema.sql
```

**Sobre el seed (`database/seed.sql`):** trae datos de ejemplo (jugadores, torneos y los 3 usuarios de prueba con contraseña `admin123`). En producción **no lo cargues** salvo que quieras los datos demo; vas a crear tu admin real en el paso 5.

```bash
# Solo si querés los datos de ejemplo:
# mysql -u pingpong -p pingpong < database/seed.sql
```

---

## 5. Crear el usuario administrador

Si NO cargaste el seed, generá tu admin con el script incluido:

```bash
php crear_admin.php "admin@tudominio.com" "tuClaveSegura123" "Nombre Admin"
```

O sin argumentos, para que te lo pregunte de forma interactiva:

```bash
php crear_admin.php
```

> Si el email ya existe, lo promueve a ADMIN y le resetea la contraseña. Solo corre por consola.

---

## 6. Configurar el servidor web

El punto de entrada es **`public/`**. El resto del proyecto (`.env`, `src/`, `database/`)
**no** debe ser accesible por el navegador.

### Opción A — Apache

Apuntá el `DocumentRoot` a la carpeta `public/`. El archivo `public/.htaccess` ya viene incluido
y maneja las URLs limpias; solo asegurate de tener `AllowOverride All` y `mod_rewrite` activo.

```apache
<VirtualHost *:80>
    ServerName torneos.tudominio.com
    DocumentRoot /var/www/pingpong/public

    <Directory /var/www/pingpong/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/pingpong-error.log
    CustomLog ${APACHE_LOG_DIR}/pingpong-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Opción B — Nginx

```nginx
server {
    listen 80;
    server_name torneos.tudominio.com;
    root /var/www/pingpong/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # MIME correcto para el manifest de la PWA
    location = /manifest.webmanifest {
        default_type application/manifest+json;
    }

    # Bloquear acceso a archivos sensibles
    location ~ /\.(env|git) { deny all; }
}
```

```bash
sudo nginx -t && sudo systemctl restart nginx
```

---

## 7. Permisos de archivos

El servidor web (usuario `www-data` en general) debe poder leer todo y escribir donde haga falta:

```bash
sudo chown -R www-data:www-data /var/www/pingpong
# Carpeta de subidas (si se usan fotos de jugadores)
sudo mkdir -p public/uploads && sudo chmod 775 public/uploads
```

---

## 8. HTTPS (obligatorio para la PWA)

El botón **"Instalar app"** y el modo offline (Service Worker) **solo funcionan sobre HTTPS**.
Sin SSL, la PWA no se puede instalar. La forma más simple es Let's Encrypt:

```bash
# Apache
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d torneos.tudominio.com

# Nginx
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d torneos.tudominio.com
```

Certbot configura la redirección de HTTP a HTTPS y renueva el certificado automáticamente.

---

## 9. Verificación final (checklist)

- [ ] `https://torneos.tudominio.com` abre y muestra el login.
- [ ] Podés iniciar sesión con el admin del paso 5.
- [ ] El menú, el ranking y la configuración cargan bien.
- [ ] En **Configuración → Usuarios y roles** ves los usuarios.
- [ ] En **Configuración** podés descargar la plantilla Excel y subir una carga masiva.
- [ ] En el celular (sobre HTTPS) aparece la opción de instalar la app.
- [ ] En el navegador: F12 → **Application → Manifest / Service Workers** sin errores.

---

## 10. Actualizar la app (deploys futuros)

```bash
cd /var/www/pingpong
git pull
composer install --no-dev --optimize-autoloader
# Si cambió el esquema, aplicá las migraciones correspondientes a mano.
```

> **Caché del navegador / PWA:** el CSS usa *cache-busting* automático (`app.css?v=...`) y el
> Service Worker está en **network-first**, así que los cambios visuales se ven al recargar.
> Si tras un deploy ves estilos viejos, hacé una recarga forzada (Ctrl+Shift+R) una vez.

---

## Problemas comunes

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| Solo abre la home, el resto da 404 | `mod_rewrite` apagado o `DocumentRoot` mal | Activar rewrite y apuntar a `public/` |
| Error 500 sin detalle | `APP_DEBUG=false` (correcto) | Mirar el log de Apache/Nginx |
| "could not find driver" | Falta `pdo_mysql` | Instalar la extensión y reiniciar PHP |
| La carga de Excel falla | Faltan `zip`/`gd`/`xml` | Instalar las extensiones de PhpSpreadsheet |
| No aparece "Instalar app" | Sin HTTPS, o iPhone | Configurar SSL; en iOS es manual (Compartir → Agregar a inicio) |
