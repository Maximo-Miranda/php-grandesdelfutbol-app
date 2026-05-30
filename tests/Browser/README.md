# Tests E2E con Laravel Dusk

Tests de integración real en navegador (Chrome) que validan los flujos críticos
de creación de partidos y confirmación de asistencia.

## Tests incluidos

Cada archivo cubre un escenario distinto de **confirmación de asistencia**, según
la "opción" (modo) del partido o el estado de la convocatoria. La lógica de
backend ya está cubierta por los Feature tests; estos prueban lo que un Feature
test no puede: el comportamiento del frontend (aparición/salto del modal, botones,
toasts, gating de la UI).

| Archivo | Escenario de confirmación |
|---------|---------------------------|
| `MatchOpenCallFlowTest.php` | Convocatoria general → confirma sin elegir equipo (team=null al pool), sin modal |
| `MatchSingleTeamFlowTest.php` | Un solo equipo → rosterizado confirma auto al team, sin modal |
| `MatchRosterStrictFlowTest.php` | Bayer vs Manchester sin outsiders → rosterizado a su nómina sin modal; outsider rechazado por el backend |
| `MatchRosterWithOutsidersFlowTest.php` | Nóminas + outsiders → outsider al pool; admin sortea y queda asignado |
| `MatchDeclineFlowTest.php` | El jugador declina con "No voy" → attendance declined |
| `MatchRegistrationClosedFlowTest.php` | Convocatoria cerrada → los botones de confirmar NO se renderizan (gate del frontend) |

## Setup (una sola vez)

```bash
# 1. ChromeDriver (descarga el binary local)
php artisan dusk:chrome-driver --detect

# 2. Copiar el env de ejemplo y generar APP_KEY
cp .env.dusk.example .env.dusk.local
php artisan key:generate --show              # ← copiá el output en APP_KEY de .env.dusk.local

# 3. Crear la BD Postgres dedicada para Dusk
createdb -h 127.0.0.1 -U sail grandesdelfutbol_dusk

# 4. Migrar la BD de Dusk con el flag oficial --env (carga .env.dusk.local)
php artisan migrate --env=dusk.local
```

> La flag `--env=dusk.local` es el mecanismo oficial de Laravel para correr un
> comando Artisan bajo otro archivo de entorno: carga `.env.dusk.local`, que ya
> apunta a `grandesdelfutbol_dusk`. No hace falta exportar `DB_DATABASE` a mano.

> 🔒 `.env.dusk.local` **NO se commitea** (está en `.gitignore`). Solo contiene
> credenciales locales. El `.env.dusk.example` sí se commitea como guía.

## Cómo correr los tests

### Modo fácil (un solo comando, recomendado)

```bash
composer dusk           # corre todo, navegador headless (más rápido, CI-friendly)
composer dusk:browse    # corre todo, navegador VISIBLE (Chrome se abre)
composer dusk:slow      # corre todo, navegador VISIBLE + slow-motion (1.2s entre acciones)
```

Los tres scripts arrancan el server Dusk en el puerto 8001, ejecutan la suite,
y se matan automáticamente al terminar.

### Modo manual (dos terminales)

```bash
# Terminal 1 — servidor PHP para Dusk (puerto 8001, BD aislada).
# --env=dusk.local es la flag oficial de Laravel: levanta el server bajo
# .env.dusk.local (APP_URL=8001, DB=grandesdelfutbol_dusk).
php artisan serve --env=dusk.local --port=8001

# Terminal 2 — corre los tests
php artisan dusk                                           # todos, headless
php artisan dusk --browse                                  # todos, navegador visible
```

### Filtrar y aislar tests (oficial)

`php artisan dusk` acepta **cualquier argumento del runner Pest/PHPUnit** (lo dice
la doc de Dusk). Con el server de la Terminal 1 corriendo:

```bash
# Por nombre del test (substring del título)
php artisan dusk --filter='renderiza todos los campos clave'

# Por archivo
php artisan dusk tests/Browser/MatchOpenCallFlowTest.php

# Por grupo (si etiquetás tests con #[Group])
php artisan dusk --group=smoke

# Re-correr solo los que fallaron la última vez
php artisan dusk:fails
```

> ⚠️ **No** filtres con `composer dusk -- --filter=...`. El script `composer dusk`
> embebe `php artisan dusk` dentro de `concurrently`, así que los argumentos
> extra se pegan al final del comando `concurrently` y se ignoran (corre la suite
> entera). Para filtrar, usá `php artisan dusk --filter=...` directo contra el
> server de la Terminal 1.

### Acceder a la app de Dusk en el navegador

Mientras el server Dusk esté corriendo, abrí `http://localhost:8001` en tu
navegador. Verás la app conectada a `grandesdelfutbol_dusk` (BD aislada de
desarrollo).

> ⚠️ El server Dusk debe correr en el puerto **8001** apuntando a
> `grandesdelfutbol_dusk`. No usar el puerto 8000 (que es el dev del usuario,
> apunta a la BD de desarrollo).

### Ver los tests al detalle (slow-mo)

Los tests usan una macro `slowMo()` que pausa entre acciones cuando la env var
`DUSK_SLOW` está seteada. Tres formas de activarlo:

```bash
# Opción 1: composer script (más fácil)
composer dusk:slow

# Opción 2: env var custom (delay en ms)
DUSK_SLOW=2000 php artisan dusk --browse                 # 2 segundos
DUSK_SLOW=500 php artisan dusk --browse                  # medio segundo

# Opción 3: usar el default (800ms) con cualquier valor truthy
DUSK_SLOW=1 php artisan dusk --browse
```

Sin la env var, `slowMo()` es no-op y la suite corre a velocidad normal.

Si querés frenar un test específico aún más, agregá `pause(ms)` extra:

```php
$browser->scrollIntoView('@match-confirm')
    ->pause(3000)              // ← pausa fija de 3s
    ->click('@match-confirm')
    ->slowMo()                 // ← respeta DUSK_SLOW si está activo
    ->pause(2000);
```

## Cómo se aisla del entorno de dev

| Pieza | Dev | Dusk |
|-------|-----|------|
| BD Postgres | `grandesdelfutbol` | `grandesdelfutbol_dusk` |
| Puerto PHP | 8000 | 8001 |
| Vite assets | dev server (5173) | bundles compilados (`public/build`) |
| Config env (server) | `.env` | `.env.dusk.local` vía `--env=dusk.local` |
| Config env (test) | `phpunit.xml` | `phpunit.dusk.xml` (fuerza DB/URL/drivers) |

Dos piezas oficiales hacen el aislamiento, sin variables inline:

- **El server** se levanta con `php artisan serve --env=dusk.local`, que carga
  `.env.dusk.local` (APP_URL 8001, DB `grandesdelfutbol_dusk`).
- **El proceso de test** (`php artisan dusk`) usa `phpunit.dusk.xml`, que fuerza
  `DB_DATABASE=grandesdelfutbol_dusk`, `APP_URL`, drivers neutros, etc. con
  `force="true"`.

El `AppServiceProvider` detecta el entorno de Dusk (`str_starts_with(env, 'dusk')`,
cubre tanto `dusk` del test como `dusk.local` del server) y fuerza a Vite a usar
los bundles compilados (el dev server de Vite tiene CORS pinned a `localhost:8000`).

Para que los tests vean los cambios de Vue, corré `npm run build` antes.

## Estrategia de selectors

- Inputs nativos: selector por `#id` (ej. `#title`, `#max_players`).
- Botones / triggers críticos: atributo `dusk="..."` (ej. `@match-create-submit`,
  `@match-confirm`, `@match-decline`, `@match-auto-assign`).
- Reka UI Selects: `dusk` en el `<SelectTrigger>` (ej. `@match-team-a-trigger`).

Si necesitás un nuevo selector estable, agregá `dusk="match-..."` al
componente Vue. Mantené el prefijo `match-` para esta área.

## Truco para el banner PWA

El banner "Instala Grandes del Futbol" puede tapar el botón Submit en headless.
Cada test lo dismissea con un `script` inline:

```js
document.querySelectorAll('.fixed, [class*="bottom-"]').forEach(el => {
    if (el.textContent && el.textContent.includes("Instala")) el.remove();
});
```

## Limpieza entre tests

Usan `DatabaseTruncation` (no `RefreshDatabase`) porque Dusk hace requests
desde un proceso separado al test y no puede compartir transacciones. La
estrategia trunca tablas entre tests sin re-migrar — rápido y reliable.
