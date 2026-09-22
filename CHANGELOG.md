# Changelog - difexa

Todas las modificaciones notables realizadas en este repositorio se documentan en este archivo.
Este archivo sirve de contexto técnico detallado para desarrolladores y sesiones de IA posteriores.

---

## [2026-09-22]

### 1. Requerimiento de Aspect Ratio 16:9 en el Uploader Multimedia
- **Objetivo**: Asegurar que las imágenes subidas para difusión en tótems y pantallas Kiosk cumplan con la proporción 16:9, activando el editor de recorte (`ImageCropEditor`) cuando sea necesario.
- **Archivos modificados**:
  - [`app/UI/Components/Modals/EditPostDialog.php`](file:///workspaces/difexa/app/UI/Components/Modals/EditPostDialog.php): Configurado `UI::uploader('post_uploader')->aspect('16:9')->size(3)`.
  - [`app/UI/Screens/Member/Concerns/HandlesPostMediaUpload.php`](file:///workspaces/difexa/app/UI/Screens/Member/Concerns/HandlesPostMediaUpload.php): Configurado `->aspect('16:9')` en el builder del backend.
  - [`public/vendor/idei/usim/js/components/uploader/index.js`](file:///workspaces/difexa/public/vendor/idei/usim/js/components/uploader/index.js) y [`vendor/idei/usim/resources/assets/js/components/uploader/index.js`](file:///workspaces/difexa/vendor/idei/usim/resources/assets/js/components/uploader/index.js):
    - Actualizado `isSingleImageMode` para activarse también si `this.config.aspect_ratio` está definido.
    - En `handleFiles()`, si `aspect_ratio` está configurado y el archivo es imagen, se invoca `checkAndCropImage()` para validar la tolerancia del 1% y abrir el canvas de recorte si difiere de 16:9.

---

### 2. Corrección de Reactividad y Etiquetas en `PostApprovalScreen`
- **Problema**: Al hacer clic en las filas de la tabla de publicaciones pendientes, el split derecho no mostraba la información del post. Además, las etiquetas de la tabla y la barra de herramientas presentaban truncados y desalineación vertical.
- **Causa raíz técnica**:
  - En USIM (Server-Driven UI), `onPostsTableRowClicked` ejecutaba `$this->posts_table->select(...)`, pero no modificaba el contenedor del segundo panel (`$this->moderation_split->secondPane()`), por lo que el `UIDiffer` no generaba diffs del panel de inspección.
  - El selector `filter_status` tenía `->label('Estado')`, generando una etiqueta superior flotante que rompía la alineación horizontal con el input de búsqueda.
  - Las opciones del dropdown (`"🟡 Pendientes de Aprobación"`) excedían el ancho del trigger, mostrándose cortadas con elipsis (`"Pendientes de Aprobaci..."`).
  - `PostApprovalTableModel` definía 7 columnas que totalizaban 1080px dentro de un panel split de ~600px, truncando la cabecera `ESTADO` a `ES...`.
  - Las unidades sin traducción retornaban la clave en crudo `unit.<slug>.display_name` debido a que `UsimUnit::getDisplayNameAttribute()` delega en `trans($this->translation_key)`.
- **Solución implementada**:
  - **Reactividad del Split**: En `onPostsTableRowClicked`, `onSubmitApprovePost` y `onSubmitRejectPost`, se actualizó la invocación reactiva:
    ```php
    $secondPane = $this->moderation_split->secondPane();
    $secondPane->clear();
    $secondPane->add($this->buildReviewPanel($post));
    ```
  - **Corrección visual de la Toolbar**: Se removió `->label('Estado')` y se simplificaron las etiquetas de las opciones (`'🟡 Pendientes'`, `'🟢 Aprobados'`, `'🔴 Rechazados'`, `'Todos los estados'`).
  - **Ajuste de Columnas**: En `PostApprovalTableModel`, se redujeron las columnas a 5 esenciales (`title: 200px`, `author: 130px`, `unit: 130px`, `type: 90px`, `status: 110px`, total 660px), trasladando fechas y kioskos al panel derecho de detalle.
  - **Fallback de Nombres de Unidad**: En `PostApprovalTableModel`, `ManagesPostReviewSection` y `ViewPostDialog`, se implementó fallback:
    ```php
    $displayName = $post->unit->display_name;
    $unitName = (!empty($displayName) && $displayName !== $post->unit->translation_key)
        ? $displayName
        : ucfirst($post->unit->slug);
    ```
  - **Filtro reactivo por Estado**: Se enlazó el select con `onChange('filter_by_status')`, persistiendo el estado en `$store_filter_status` y aplicando `setStatusFilter()` en el table model al paginar, ordenar o buscar.

---

### 3. Selección de Dispositivos Smart TV en Publicaciones
- **Objetivo**: Permitir que el autor de una publicación seleccione específicamente qué dispositivos con rol `smart-tv` difundirán el post, limitando las opciones a:
  1. Dispositivos con rol `smart-tv` de la **unidad actual** (seleccionada en la barra de menú / contexto activo).
  2. Dispositivos con rol `smart-tv` **públicos o institucionales** (asignados a la unidad `main` o sin asignación de unidad).
- **Modificaciones en Base de Datos y Modelos**:
  - **Migración**: [`database/migrations/2026_09_22_000000_create_device_post_table.php`](file:///workspaces/difexa/database/migrations/2026_09_22_000000_create_device_post_table.php) creando la tabla pivot `device_post` (`post_id`, `device_id`, timestamps, índice único compuesto).
  - [`app/Models/Post.php`](file:///workspaces/difexa/app/Models/Post.php): Añadida relación `devices(): BelongsToMany<Device, $this>`.
  - [`app/Models/Device.php`](file:///workspaces/difexa/app/Models/Device.php): Añadida relación `posts(): BelongsToMany<Post, $this>`.
- **Capa de Servicios**:
  - [`app/Services/Device/DeviceService.php`](file:///workspaces/difexa/app/Services/Device/DeviceService.php): Implementado método `getSmartTvDevicesForUnit(?int $unitId): Collection`:
    - Filtra por rol `smart-tv` o `smart_tv` tanto en roles globales como en roles de equipo Spatie (`globalRoles` y `roles`).
    - Filtra por pertenencia a `$unitId` O condición de público/institucional (`isPublic()`).
  - [`app/Contracts/PostServiceContract.php`](file:///workspaces/difexa/app/Contracts/PostServiceContract.php) y [`app/Services/Post/PostService.php`](file:///workspaces/difexa/app/Services/Post/PostService.php):
    - Añadida validación y sincronización del arreglo `device_ids` en `create()` y `update()`.
  - [`app/Services/Post/KioskPostResolver.php`](file:///workspaces/difexa/app/Services/Post/KioskPostResolver.php):
    - Modificada la consulta de posts para Kiosks para incluir publicaciones dirigidas explícitamente al dispositivo (`orWhereHas('devices', ...)`).
- **Interfaz de Usuario (SDUI)**:
  - [`app/UI/Components/Modals/EditPostDialog.php`](file:///workspaces/difexa/app/UI/Components/Modals/EditPostDialog.php):
    - Acepta `$unitId` para resolver la unidad activa contextual.
    - Renderiza el grupo de checkboxes `post_devices` con los Smart TVs disponibles etiquetados (`📺 Nombre (Institucional / Público)` o `📺 Nombre (Unidad)`).
    - Pre-selecciona los dispositivos previamente asociados en `$post->devices`.
  - [`app/UI/Screens/Member/PostManager.php`](file:///workspaces/difexa/app/UI/Screens/Member/PostManager.php):
    - Pasa `unitId` resuelta desde `resolveActiveUnit()` a `EditPostDialog::open()`.
    - En `onSavePost()`, extrae `post_devices` y lo envía como `device_ids` a `PostService`.
  - [`app/UI/Components/Modals/ViewPostDialog.php`](file:///workspaces/difexa/app/UI/Components/Modals/ViewPostDialog.php) y [`app/UI/Screens/Communication/Concerns/ManagesPostReviewSection.php`](file:///workspaces/difexa/app/UI/Screens/Communication/Concerns/ManagesPostReviewSection.php):
    - Muestra en la ficha técnica los Smart TVs seleccionados (`📺 Totem 1, Totem 2`).

---

### 4. Detección y Asignación Automática de Tipo "Imagen"
- **Objetivo**: Si el usuario sube un archivo de imagen mediante el uploader, el tipo de publicación debe cambiar automáticamente a "imagen".
- **Implementación Dual (Frontend y Backend)**:
  - **Frontend (JavaScript)**:
    - En `handleFiles()` y al finalizar con éxito `uploadFile()` en `public/vendor/idei/usim/js/components/uploader/index.js` y `vendor/idei/usim/resources/assets/js/components/uploader/index.js`:
    ```javascript
    if (file.type?.startsWith('image/') || this.detectType(file.type) === 'image') {
        const postTypeSelect = document.querySelector('select[name="post_type"], select#post_type, select[data-component-name="post_type"]');
        if (postTypeSelect && postTypeSelect.value !== 'image') {
            postTypeSelect.value = 'image';
            postTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    ```
    Esto garantiza feedback visual inmediato en el selector del modal.
  - **Backend (PHP)**:
    - En [`PostManager::onSavePost()`](file:///workspaces/difexa/app/UI/Screens/Member/PostManager.php):
    ```php
    $media = $this->resolvePostMedia($params, $postType, $existingPost);

    if ($media['media_mime'] !== null && str_starts_with($media['media_mime'], 'image/')) {
        $postType = PostType::IMAGE->value;
    }
    ```
    - En [`HandlesPostMediaUpload`](file:///workspaces/difexa/app/UI/Screens/Member/Concerns/HandlesPostMediaUpload.php), se añadió fallback para aceptar tanto `post_uploader` directo como `post_uploader_temp_ids` en JSON.

---

### 5. Suite de Pruebas y Control de Calidad
- **Pruebas nuevas / actualizadas**:
  - [`tests/Feature/PostDeviceSelectionTest.php`](file:///workspaces/difexa/tests/Feature/PostDeviceSelectionTest.php): 6 tests cubriendo:
    1. Filtrado de Smart TVs por unidad activa y exclusión de dispositivos de otras unidades o roles ajenos.
    2. Renderizado de checkboxes en `EditPostDialog`.
    3. Guardado y sincronización de `device_ids` en `device_post`.
    4. Detección automática de tipo imagen al subir un archivo.
    5. Detección automática de tipo imagen al proveer URL externa.
    6. Resolución de posts dirigidos específicamente al dispositivo en `KioskPostResolver`.
  - [`tests/Feature/PostApprovalScreenTest.php`](file:///workspaces/difexa/tests/Feature/PostApprovalScreenTest.php): Tests para clics en filas, actualización reactiva del panel split y filtrado por estado.
  - [`tests/Feature/PostManagerScreenTest.php`](file:///workspaces/difexa/tests/Feature/PostManagerScreenTest.php): Tests de creación, edición y envío a revisión de posts.
- **Resultado de Tests**: 100% de la suite de pruebas ejecutada con **Pest** (53 tests pasados, 210 aserciones).
- **Estilo de Código**: Formateado con **Laravel Pint**.

