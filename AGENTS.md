# Instrucciones para el Agente en Proyecto USIM (difexa)

## Contexto general del repositorio

- Este repositorio es una aplicación Laravel 11/12+ (PHP 8.4+) creada a partir de `composer create-project idei/usim-project difexa`.
- **NO es el monorepo del framework**: es una **aplicación consumidora** del ecosistema USIM (UI Services Implementation Model).
- El paquete `idei/usim` está instalado como dependencia Composer (`vendor/idei/usim/`).
- Todo el código de negocio, modelos, servicios, pantallas y pruebas viven dentro de esta aplicación (`app/`, `config/`, `database/`, `resources/`, `routes/`, `tests/`).
- No existen carpetas `packages/idei/usim/` ni scripts de release del framework en este repositorio.

## Modelo mental de la Arquitectura USIM

- **Backend-Driven / Server-Driven UI**: La interfaz de usuario se define enteramente en PHP mediante builders y clases `Screen`.
- **Renderizador genérico**: El frontend JavaScript consume el contrato JSON inicial generado por el backend y aplica diffs incrementales reactivos tras cada evento.
- **Source of truth en Backend**: La lógica de negocio, validaciones, autorización y estado residen siempre en PHP. No asumas frameworks de frontend como React, Vue o Livewire.
- **Persistencia de estado**: Las propiedades de clase con prefijo `store_*` se persisten automáticamente entre requests. Usar el sufijo `_crypt` (ej. `store_token_crypt`) únicamente para valores sensibles que requieran cifrado.
- **Ciclo reactivo**: `Restaurar estado -> Ejecutar handler on<ActionName> -> Calcular diff de UI -> Enviar delta JSON al cliente`.

## Estructura del proyecto (`difexa`)

```text
/difexa
|- artisan
|- start.sh                         # flujo principal con RoadRunner / Octane
|- composer.json
|- package.json
|- README.md
|- app/
|  |- Contracts/                    # Interfaces de servicios de la app
|  |- Http/
|  |  \- Controllers/               # Controladores REST/API puntuales
|  |- Models/                       # Modelos Eloquent (User, Device, etc.)
|  |- Providers/                    # Service Providers
|  |- Services/                     # Servicios de dominio y lógica de negocio
|  |- UI/
|     |- Components/
|     |  |- DataTable/              # Modelos y adaptadores de tablas (TranslationKeysTableModel, etc.)
|     |  \- Modals/                 # Diálogos y modales (EditUserDialog, LoginDialog, DevicePairingDialog, etc.)
|     \- Screens/                   # Pantallas completas de la aplicación
|        |- Home.php
|        |- Menu.php
|        |- Registered.php
|        |- Admin/                  # UsersManager, TranslateManager, Concerns, TableModels
|        |- Auth/                   # Login, Profile, ForgotPassword, ResetPassword, EmailVerified
|        \- Device/                 # DevicePairingScreen, KioskScreen
|- config/
|  |- app.php
|  |- auth.php
|  |- permission.php
|  |- usim.php                      # Configuración de USIM en la app
|- database/
|  |- factories/
|  |- migrations/
|  \- seeders/
|- resources/
|- routes/
|  |- web.php
|  |- api.php
|  \- console.php
|- scripts/
|- storage/
\- tests/
   |- Feature/                      # Feature tests de pantallas y servicios
   |- Unit/                         # Tests unitarios
   |- Support/
   |- Traits/
   |- Pest.php
   \- TestCase.php
```

## Convenciones y Definición de "Screen"

Cuando se trabaje con pantallas o UI en este proyecto:
- Cada `Screen` es un servicio UI stateful que extiende `Screen` e implementa `buildBaseUI(Container $container, ...$params): void`.
- **Componentes**: Se crean utilizando el builder `UI::*` (`UI::container()`, `UI::input()`, `UI::button()`, `UI::card()`, `UI::table()`, `UI::select()`, `UI::checkbox()`, etc.).
- **Handlers de eventos**: Resueltos por convención `action` en `snake_case` -> método `on<ActionName>(array $params)` en PascalCase (ej. `save_user` -> `onSaveUser(array $params)`).
- **Control de acceso y autorización**: Métodos `authorize()` o `checkAccess()` dentro de la `Screen`.
- **Navegación y Metadata**: `getMenuLabel()`, `getMenuIcon()` y `getRoutePath()`.
- **Feedback UI**: Usar los helpers provistos por la pantalla (`$this->toast(...)`, `$this->modal(...)`, `$this->redirect(...)`, etc.).

## Reglas para cambios en el código

- Toda pantalla nueva debe ubicarse en `app/UI/Screens/` (y registrarse en `Menu.php` o configuración correspondiente si forma parte de la navegación).
- Todo modal interactivo reusable debe ubicarse en `app/UI/Components/Modals/`.
- La lógica de negocio debe delegarse a clases en `app/Services/` en lugar de sobrecargar la Screen.
- No duplicar validaciones en frontend si pueden resolverse centralizadas en backend.
- Preservar nombres deterministas para IDs de componentes para asegurar diffs limpios.

## Testing

- Escribir tests con **Pest** usando el patrón de pruebas de pantallas USIM: `uiScenario(...)->component(...)->expect(...)`.
- Probar contrato inicial, disparo de eventos, cambios de estado en `store_*` y respuestas en diffs.
- Utilizar `Notification::fake()` y mocks de servicios cuando sea necesario.

## Ejecución local

- El servidor se inicia habitualmente mediante `./start.sh` (RoadRunner / Octane) o `php artisan serve`.
- Para refrescar cache o descubrir pantallas: `php artisan optimize:clear` y comandos de descubrimiento USIM.

## General

Al final de cada respuesta dime "Ready!" para que sepa que terminaste de responder.
