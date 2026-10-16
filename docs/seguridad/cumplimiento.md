# Seguridad y cumplimiento (datos de salud)

NutriTrack trata datos de salud de pacientes (dietas, observaciones, aislamiento), que son **datos sensibles** según el art. 5 de la Ley 1581 de 2012. Este documento lista lo que ya hace la plataforma y lo que debe completar cada institución. **No sustituye la asesoría jurídica.**

## Lo que hace la plataforma

| Control | Dónde |
|---|---|
| Política de tratamiento de datos pública (`/politica-de-datos`) y aceptación obligatoria al ingresar (con versión) | `PrivacyController`, `resources/views/legal/`, `EnsureAccountCompliance` |
| Verificación en dos pasos obligatoria para `admin` y `jefe_nutricion` (`TWO_FACTOR_ROLES`) | `EnsureAccountCompliance` |
| HTTPS obligatorio en producción (redirección, HSTS, cookie de sesión `secure`) | `SecurityHeaders`, `config/security.php`, `config/session.php` |
| Cabeceras: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; páginas con sesión sin caché | `SecurityHeaders` |
| Contraseñas: mínimo 10 caracteres con mayúsculas, minúsculas y números; bloqueo tras 3 intentos fallidos | `AppServiceProvider`, `FortifyServiceProvider` |
| Auditoría inmutable: accesos, intentos fallidos, bloqueos, 2FA, usuarios, roles, permisos, precios, exportaciones Excel, consultas de historial, verificación de respaldos | `AuditLog`, `AuditLogger`, `/admin/auditoria` |
| Historial de versiones de cada dieta (quién y cuándo) | `diet_histories` |
| Respaldos diarios cifrados (AES-256), limpieza y monitoreo; verificación semanal del último respaldo | `config/backup.php`, `backups:verify` |
| Tiempo real sin datos clínicos (solo el tipo de cambio) en canal privado | `DietsChanged` |

## Configuración de producción (`.env`)

```
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
TRUSTED_PROXIES=*                 # si hay balanceador o Cloudflare delante
SESSION_SECURE_COOKIE=true
BACKUP_ARCHIVE_PASSWORD=...       # obligatorio; guardarlo fuera del servidor (gestor de secretos)
TWO_FACTOR_ROLES=admin,jefe_nutricion
PRIVACY_POLICY_VERSION=2026-10-16 # cambiarla obliga a todos a aceptar de nuevo
DATA_CONTROLLER_NAME="Razón social de la institución"
DATA_CONTROLLER_NIT=...
DATA_PROTECTION_EMAIL=...
```

Además: un disco de respaldo fuera del servidor (S3 o similar) en `backup.backup.destination.disks`, y el programador de tareas activo (`schedule:run` cada minuto).

## Lo que debe hacer cada institución

- [ ] Revisar con el área jurídica el texto de `resources/views/legal/policy-text.blade.php` (es un borrador) y completar razón social, NIT y correo en el `.env`.
- [ ] Firmar con el proveedor el contrato de **transmisión de datos** (responsable → encargado), con las obligaciones de seguridad, confidencialidad, subencargados y devolución o eliminación al terminar.
- [ ] Verificar que la autorización de los pacientes (o la excepción legal aplicable, art. 6 de la Ley 1581) cubra el tratamiento de la dieta.
- [ ] Registrar la base de datos en el **RNBD** de la Superintendencia de Industria y Comercio, si la institución está obligada.
- [ ] Definir el tiempo de conservación de los registros de dietas según la normativa de historia clínica y de facturación que le aplique.
- [ ] Nombrar el área responsable de atender consultas (10 días hábiles) y reclamos (15 días hábiles).
- [ ] Revisar periódicamente `/admin/auditoria` (intentos fallidos, exportaciones, cambios de permisos).

## Prueba de restauración completa (trimestral)

`backups:verify` (lunes 03:00) comprueba que el último respaldo exista, tenga menos de `BACKUP_MAX_AGE_HOURS` (26 h), esté cifrado, que la contraseña lo abra y que el volcado contenga `diets`, `diet_histories` y `diet_dispatches`. Si falla, queda en el log y en la auditoría.

Además, una vez por trimestre, restaurar en un entorno de pruebas (nunca sobre producción):

1. Descargar el último `.zip` del disco de respaldos.
2. `unzip -P "$BACKUP_ARCHIVE_PASSWORD" respaldo.zip -d restore/` (si `unzip` no soporta AES-256, usar `7z x`).
3. `mysql -u root nutrition_restore < restore/db-dumps/mysql-nutrition.sql`
4. Apuntar una copia de la app a `nutrition_restore` y comprobar el inicio de sesión, el panel del nutricionista y el reporte del mes.
5. Anotar la fecha y el resultado.
