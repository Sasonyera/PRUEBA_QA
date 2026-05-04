# PROYECTO_FINAL

Proyecto dividido en 2 aplicaciones:

- `admin_clinica` → Frontend Angular
- `api_clinica` → Backend Laravel + MySQL

Este README está pensado para que cualquier compañera/o pueda clonar, instalar y ejecutar todo desde cero en **Linux** o **Windows**.

---

## 1) Requisitos (Linux y Windows)

Instalar:

- Git
- Node.js 18+ y npm
- PHP 8.1+
- Composer
- MySQL 8+

### Opción A: Linux Mint / Ubuntu

```bash
sudo apt update
sudo apt install -y git curl unzip software-properties-common

# Node.js y npm (si no lo tienes)
sudo apt install -y nodejs npm

# PHP 8.1 + extensiones necesarias para Laravel
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.1 php8.1-cli php8.1-common php8.1-mbstring php8.1-xml php8.1-curl php8.1-mysql php8.1-bcmath php8.1-zip php8.1-intl

# MySQL
sudo apt install -y mysql-server

# Composer
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Opción B: Windows 10/11

Instalar desde sus sitios oficiales:

- Git for Windows
- Node.js 18+
- Composer
- MySQL Server 8+
- (Opcional) Laragon o XAMPP solo para tener MySQL fácil de gestionar

Validar en PowerShell:

```powershell
git --version
node -v
npm -v
php -v
composer --version
mysql --version
```

---

## 2) Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd PROYECTO_FINAL
```

En Windows PowerShell es igual:

```powershell
git clone <URL_DEL_REPOSITORIO>
cd PROYECTO_FINAL
```

---

## 3) Configurar la base de datos (MySQL)

---

## 4) Backend (Laravel) - api_clinica

### Linux/macOS

```bash
cd api_clinica
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
```

### Windows (PowerShell)

```powershell
cd api_clinica
copy .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
```

Editar `.env` y dejar:



Migrar y seed:

```bash
php artisan optimize:clear
php artisan migrate --seed
```

Iniciar backend:

```bash
php artisan serve
```

API en: `http://127.0.0.1:8000`

Usuario demo (seed):


---

## 5) Frontend (Angular) - admin_clinica

En otra terminal:

```bash
cd admin_clinica
npm install
npm start
```

En Windows PowerShell es igual:

```powershell
cd admin_clinica
npm install
npm start
```

Frontend en: `http://localhost:4200`

---

## 6) Prueba rápida con Postman

### Login (sin token)

- Método: `POST`
- URL: `http://127.0.0.1:8000/api/auth/login`
- Header: `Content-Type: application/json`
- Body JSON:



La respuesta devuelve `access_token`.

### Endpoint protegido

Agregar header:

`Authorization: Bearer TU_ACCESS_TOKEN`

---

## 7) Errores comunes

1. **Access denied for user**
   - Revisar usuario/clave/host en MySQL y `.env`.
   - Ejecutar `php artisan optimize:clear`.

2. **Unknown column `users.deleted_at`**
   - Ejecutar migraciones pendientes: `php artisan migrate`.

3. **401 Unauthorized**
   - Hacer login nuevamente y usar token nuevo.
   - Verificar que no se esté enviando un token viejo.

---

## 8) Qué subimos a GitHub y qué NO

Sí se sube:
- Código fuente
- Migraciones
- Seeders
- README

No se sube (ya ignorado por `.gitignore`):
- `node_modules`
- `vendor`
- `.env`
- builds/cache/logs

---

## 9) Flujo recomendado de trabajo en equipo

1. Crear rama nueva:
```bash
git checkout -b feature/nombre-cambio
```

2. Hacer cambios, commit y push:
```bash
git add .
git commit -m "feat: descripción corta"
git push origin feature/nombre-cambio
```

3. Abrir Pull Request para revisión.
