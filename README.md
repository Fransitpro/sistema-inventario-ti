# Sistema de Inventario TI

Aplicación web para la gestión del inventario de activos de TI (equipos, empleados, asignaciones, reparaciones y bajas), con soporte multi-sucursal, generación de actas en PDF, reportes exportables a Excel/PDF y copias de seguridad de la base de datos.

Desarrollado en **PHP procedural** (sin framework) con **MySQL/MariaDB**, e instalado localmente sobre **Laragon** (Apache + PHP + MySQL) en Windows.

---

## 1. Requisitos

| Componente | Versión mínima | Notas |
|---|---|---|
| PHP | 8.1 | Se usa `str_starts_with()` y `mysqli_sql_exception`. Probado con PHP 8.1.27. |
| MySQL / MariaDB | MySQL 8.0 / MariaDB 10.5 | Se usan `utf8mb4`, transacciones y `TRUNCATE`. |
| Extensiones PHP | `mysqli`, `mbstring`, `iconv`, `json` | `mysqli` es la única vía de acceso a datos (no se usa PDO). |
| Servidor web | Apache con `mod_php` | La carpeta `public/` es la raíz del sitio. |

No se requiere Composer: la librería FPDF está vendorizada dentro del proyecto (`fpdf/`).

---

## 2. Estructura del proyecto

```
inventario_ti/
├── config/
│   └── database.php          # Credenciales y conexión mysqli ($conexion)
├── public/                   # Raíz web (document root)
│   ├── *.php                 # Páginas y procesos del sistema
│   ├── assets/js/            # Scripts propios (exportación A4)
│   ├── css/                  # Estilos del layout y del login
│   └── uploads/              # Archivos subidos por el usuario
├── includes/                 # Lógica compartida (API JSON, login)
├── templates/                # Layout común: header, footer, reportes
├── uploads/                  # Actas, devoluciones, bajas y evidencias
├── fpdf/                     # Librería FPDF 1.86 vendorizada
└── .gitignore
```

Las páginas fuera de `public/` no son accesibles directamente por el navegador; todas se sirven desde `public/`.

---

## 3. Configuración de la base de datos

La conexión se centraliza en `config/database.php`, que expone la variable `$conexion` (instancia de `mysqli`) con charset `utf8mb4`.

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";              // ¡no versionar credenciales reales!
$name = "inventario_ti";

$conexion = new mysqli($host, $user, $pass, $name);
if ($conexion->connect_error) {
    die("Error de Conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");
```

Pasos para la instalación:

1. Crear la base de datos:
   ```sql
   CREATE DATABASE inventario_ti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importar el esquema y los datos iniciales (dump `.sql`).
3. Configurar las credenciales en `config/database.php`.
4. Crear el primer usuario administrador mediante `public/crear_admin.php`
   (**eliminar este archivo después del primer uso**).
5. Dar permisos de escritura a Apache/PHP sobre `uploads/`.
6. Apuntar el dominio virtual de Laragon a `public/`.

---

## 4. Módulos del sistema

### Equipos
| Página | Función |
|---|---|
| `equipos.php` | Listado, búsqueda con DataTables y filtros. |
| `equipo_agregar.php` | Alta de equipos (valida y normaliza códigos duplicados). |
| `equipo_editar.php` | Edición de equipos y cambio de estado. |
| `equipo_detalle.php` | Ficha del equipo con historial de reparaciones. |
| `equipo_dar_de_baja.php` | Baja de un equipo con registro en `bajas`. |
| `equipo_enviar_reparacion.php` | Envío del equipo a reparación. |

### Empleados
`empleados.php`, `empleado_agregar.php`, `empleado_editar.php`,
`procesar_empleado.php`, `inventario_empleado.php` (inventario por empleado).

### Asignaciones y devoluciones
`asignaciones.php`, `asignacion_agregar.php`, `asignar_equipo.php`,
`asignacion_devolver.php`, `procesar_devolucion.php`, `devoluciones.php`,
`asignacion_detalle_devolucion.php` y carga de actas firmadas
(`asignacion_subir_acta.php`, `asignacion_subir_acta_devolucion.php`).

### Reparaciones
`reparaciones.php`, `reparacion_editar.php`, `reparacion_finalizar.php`,
`reparacion_actualizar_marca.php` (actualización de marca/modelo vía AJAX).

### Bajas
`bajas.php`, `baja_editar.php`, `equipo_dar_de_baja.php`.

### Actas y documentos PDF
| Archivo | Documento generado |
|---|---|
| `generar_acta.php` | Acta de entrega / asignación. |
| `generar_acta_devolucion.php` | Acta de devolución. |
| `generar_acta_baja.php` | Acta de baja. |
| `descargar_acta.php`, `ver_acta_baja.php`, `ver_evidencia.php` | Consulta y descarga de archivos almacenados. |

### Reportes
`reportes.php` (contenedor) incluye `templates/reportes.php`, un bloque de tarjetas
reutilizable que genera reportes con botones **Excel**, **PDF** e **Imprimir**,
respetando el filtro de sucursal.

### Catálogos
`gestion_catalogos.php` y `catalogo_editar.php`: CRUD de tipos de equipo, marcas,
modelos, áreas, cargos y sucursales.

### Administración (solo administradores)
`gestion_usuarios.php`, `usuario_agregar.php`, `usuario_editar.php`,
`configuracion.php`, `backup.php`, `generar_backup.php`,
`restaurar_backup.php`, `reset_system.php`, `procesar_reset.php`.

### Utilidades AJAX
`obtener_modelos.php`, `obtener_cargos.php`, `obtener_empleados_por_sucursal.php`,
`obtener_equipos_por_sucursal.php` e `includes/api.php` devuelven JSON para los
formularios en cascada.

---

## 5. Base de datos

No hay un archivo `.sql` versionado actualmente; el esquema se infiere del código.
Tablas principales:

**Seguridad y configuración**
- `usuarios` — credenciales (password con hash), sucursal y estado.
- `roles` — catálogo de roles.
- `usuario_roles` — tabla puente usuario ↔ rol.
- `configuracion` — pares clave/valor (ej. `moneda_simbolo`).

**Catálogos**
- `sucursales`, `areas`, `cargos` (depende de `areas`), `tipos_equipo`,
  `marcas`, `modelos` (depende de `marcas`).

**Operación**
- `equipos` — activos de TI: `codigo_inventario`, `numero_serie`, `cod_pat`, `ip`,
  sucursal, tipo, marca, modelo, fecha de adquisición, características,
  proveedor y `estado`.
- `empleados` — `dni` (único), nombres, apellidos, área, cargo, sucursal.
- `asignaciones` — entrega/devolución, estados y rutas de actas y evidencias.
- `reparaciones` — ingreso/salida, motivo, proveedor de servicio, costo.
- `bajas` — fecha, motivo, observaciones y acta de baja.

**Estados principales**

| Tabla | Valores |
|---|---|
| `equipos.estado` | `Disponible`, `Asignado`, `En Reparacion`, `De Baja` |
| `asignaciones.estado_asignacion` | `Activa`, `Finalizada` |
| `reparaciones.estado_reparacion` | `En Proceso`, `Finalizada` |
| Catálogos y empleados | `Activo` / inactivo |

`reset_system.php` vacía las tablas de datos (reparaciones, bajas, asignaciones,
equipos, empleados y catálogos) y conserva usuarios, roles y configuración.

---

## 6. Autenticación, roles y sucursales

1. `login.php` valida el correo y la contraseña con `password_verify()`.
2. La sesión guarda `user_id`, `user_nombre`, `user_email`, `user_rol`,
   `user_sucursal_id` y la configuración global.
3. `templates/header.php` verifica `$_SESSION['user_id']` en cada página; si no
   existe, redirige al login y envía cabeceras anti-caché.
4. El rol se valida explícitamente en las páginas administrativas
   (`administrador` / `admin`).
5. **Multi-sucursal:** si el usuario tiene `user_sucursal_id`, todas las consultas
   se filtran por esa sucursal; un administrador sin sucursal asignada ve todas.

---

## 7. Dependencias front-end

Cargadas por CDN desde `templates/header.php`, `templates/footer.php` y `login.php`:

- Bootstrap 5.3.3 + Bootstrap Icons 1.11.3
- jQuery 3.7.1
- DataTables 1.13.6 + Buttons 2.4.1 (Excel, CSV, impresión)
- JSZip 3.10.1, pdfMake 0.1.53 (con sus fuentes virtuales)
- Chart.js (gráficos del dashboard)

Locales: `public/css/style.css`, `public/css/login-style.css` y
`public/assets/js/exportar-a4.js` (estilos de impresión A4 apaisado).

> Se requiere conexión a internet para los CDN en el entorno actual.

---

## 8. Uso cotidiano

1. Acceder a `public/login.php` e iniciar sesión.
2. **Empleados:** registrar al personal con su DNI, área y cargo.
3. **Equipos:** dar de alta los activos con su código de inventario y datos de
   adquisición.
4. **Asignación:** asignar un equipo disponible; el estado cambia a `Asignado` y
   se puede cargar el acta firmada.
5. **Devolución:** registrar la devolución con evidencias; el equipo vuelve a
   `Disponible`.
6. **Reparación:** enviar el equipo a reparar y finalizar la reparación cuando
   regrese.
7. **Baja:** registrar el retiro definitivo del activo.
8. **Reportes:** consultar y exportar los listados a Excel, PDF o impresión.

---

## 9. Copias de seguridad

- `generar_backup.php` genera y descarga un dump `.sql` completo.
- `restaurar_backup.php` restaura un `.sql` subido (requiere permisos de escritura).
- Ambas operaciones exigen rol administrador.

---

## 10. Estado actual y recomendaciones

Detectado al momento de documentar:

1. **`config/database.php` aparece eliminado en el árbol de trabajo** (existe en
   Git). Restaurarlo con `git checkout config/database.php` o recrearlo antes de
   usar la aplicación.
2. **`public/_pagina.php` es un bypass de autenticación** (fuerza una sesión de
   administrador e incluye páginas por parámetro `?p=`). **Eliminarlo antes de
   desplegar en producción.**
3. **`public/crear_admin.php`** tiene credenciales fijas y muestra la contraseña en
   pantalla; debe borrarse tras crear el primer administrador.
4. `generar_backup.php` define credenciales por separado en lugar de usar
   `config/database.php`: unificar.
5. Falta versionar el dump `.sql` del esquema para facilitar instalaciones.
6. La comparación de roles no es uniforme: algunas páginas usan
   `strtolower(...) === 'administrador'` y otras `!== 'Administrador'`.
   Estandarizar.
7. **No versionar credenciales reales.** Conviene agregar `config/database.php`
   al `.gitignore` y mantener solo un `database.example.php`.
8. `uploads/` contiene evidencias y actas de usuarios; considerar excluirlo del
   repositorio si contiene información sensible.

---

## 11. Flujo de trabajo con Git

Rama principal: `main`.

```bash
git status                      # revisar cambios pendientes
git add -A
git commit -m "Descripción del cambio"
git log --oneline               # historial
```

Ignorar los avisos `LF will be replaced by CRLF` si se trabaja en Windows con
`core.autocrlf` activo; no afectan al contenido de los archivos.
