# Guía Arquitectónica y Buenas Prácticas del Framework USIM
> **Fecha de actualización:** 2026-09-22  
> **Propósito:** Documento de referencia técnica y contexto para desarrolladores humanos y agentes de Inteligencia Artificial que trabajen en este repositorio o en aplicaciones basadas en el ecosistema USIM (*UI Services Implementation Model*).

---

## 1. Filosofía Arquitectónica

### 1.1 ¿Qué es USIM?
USIM es un framework de **Server-Driven UI (SDUI)** sobre Laravel (PHP 8.4+). A diferencia de las SPAs tradicionales (React, Vue, Angular) o Livewire/Inertia:
- **Toda la interfaz se define en PHP:** Estructura, layout, componentes, estados de validación, estilos y eventos residen y se procesan exclusivamente en el servidor backend.
- **Frontend agnóstico y ligero:** El cliente JavaScript actúa como un renderizador genérico y reconciliador de DOM. Recibe el contrato JSON inicial, dibuja la pantalla, escucha interacciones y despacha eventos al backend.
- **Ciclo de diff incremental reactivo:** Tras cada evento en `/api/ui-event`, el backend calcula una diferencia mínima (*diff JSON*) entre el árbol previo y el nuevo, y envía únicamente las mutaciones para que el cliente actualice el DOM sin recargar la página.

### 1.2 Separación de Responsabilidades
- **`Screen` (app/UI/Screens/):** Actúa como *Presenter / ViewModel*. Responsable de armar la estructura visual (`buildBaseUI`), registrar handlers de eventos (`on<ActionName>`), autorizar acceso (`authorize()`) y coordinar llamadas a los servicios.
- **`Services` (app/Services/):** Contienen toda la lógica pura de negocio, reglas de dominio, transacciones y consultas complejas a Eloquent. Las pantallas **nunca** deben contener lógica de negocio densa; deben delegarla a servicios inyectados.
- **`TableModels` (app/UI/Screens/.../TableModels/):** Configuran columnas, paginación, formateo de celdas y filtros para componentes `Table`.
- **`Modals` (app/UI/Components/Modals/):** Ventanas de diálogo reusables estructuradas que inyectan su árbol en `UIChangesCollector`.

---

## 2. Ciclo de Vida Reactivo y Differing de UI (Lección Crítica)

### 2.1 Petición Inicial (GET)
1. El usuario accede a la ruta web de la pantalla.
2. Se instancia la clase `Screen`.
3. Se ejecuta `buildBaseUI(Container $container, ...$params): void`.
4. El contenedor raíz y sus hijos se serializan a un árbol JSON completo.
5. El snapshot JSON se guarda en caché y se envía como contrato inicial al cliente web para el primer renderizado.

### 2.2 Petición de Evento (`POST /api/ui-event`)
Cuando el usuario interactúa (clic, input, cambio de página, upload, etc.):
1. El cliente envía `component_id`, `action`, `parameters` y el `storage` actual.
2. `initializeEventContext()` reconstruye el árbol de componentes desde la caché (`reconstructScreenTreeFromCache`).
3. Se almacena una instantánea del estado anterior: `$this->oldUI = $this->container->toJson()`.
4. **Inyección de almacenamiento:** Las propiedades con prefijo `store_*` se rellenan mediante reflexión con los valores del storage entrante.
5. **Inyección de referencias:** Las propiedades protegidas que coinciden con nombres de componentes (ej. `$this->posts_table`, `$this->search_posts`, `$this->moderation_split`) se enlazan automáticamente con sus instancias en el árbol deserializado.
6. **Resolución del Handler:** La acción enviada en `snake_case` (ej. `posts_table_row_clicked`) se mapea por convención al método `on<ActionName>` (ej. `onPostsTableRowClicked(array $params)`).
7. **Cálculo del Diff:** `UIDiffer` compara `$this->container->toJson()` contra `$this->oldUI`.
8. **Respuesta Delta:** Se envía un JSON con los componentes añadidos, modificados o eliminados (`parent: null`).

### 2.3 Regla de Oro de la Reactividad en Handlers
> [!IMPORTANT]
> **Cambiar propiedades de clase NO actualiza la UI por sí solo.**  
> Si un handler únicamente modifica una variable (ej. `$this->store_selected_id = $id;` o `$this->posts_table->select($id);`), pero no muta los componentes que dependen de esa variable, el JSON final será idéntico a `oldUI` y el diff devuelto estará vacío.
> 
> **Patrón correcto para actualizar paneles dependientes (ej. Splits o Detalles):**
> ```php
> public function onPostsTableRowClicked(array $params): void
> {
>     $postId = $this->optionalIntParam($params, 'model_id');
>     $post = Post::find($postId);
>     
>     $this->store_selected_post_id = $post->id;
>     $this->posts_table->select($post->id);
> 
>     // OBLIGATORIO: Modificar el contenedor secundario en el árbol
>     $secondPane = $this->moderation_split->secondPane();
>     $secondPane->clear();
>     $secondPane->add($this->buildReviewPanel($post));
> }
> ```

---

## 3. Persistencia de Estado (`store_*` y `UIStateManager`)

1. **Propiedades `protected ?type $store_<nombre>`:**
   - Se serializan automáticamente al cliente en el payload `storage`.
   - Se reinyectan en cada request interactivo de la pantalla.
   - Utilizar `$store_*_crypt` únicamente para tokens, contraseñas temporales o datos sensibles que requieran cifrado simétrico automático.
   - **Ejemplo:** `protected ?string $store_filter_status = 'pending';`

2. **Sincronización con TableModels:**
   - Cuando un filtro cambia en la Screen, no basta con asignarlo a `$store_*`; debe sincronizarse con el `TableModel` antes de invocar `$this->table->page(1)` o `$this->table->refresh()`:
   ```php
   protected function syncTableFilters(): void
   {
       $model = $this->posts_table->getModel();
       if ($model instanceof PostApprovalTableModel) {
           $status = $this->store_filter_status ?? PostStatus::PENDING->value;
           $model->setStatusFilter($status !== '' ? $status : null);
       }
   }
   ```

---

## 4. Catálogo de Componentes y Reglas de Maquetación

### 4.1 Builders Fluent (`UI::*`)
- **`UI::container('name')`**: Contenedor genérico. Configurar con `->layout(LayoutType::HORIZONTAL | LayoutType::VERTICAL)`, `->gap(...)`, `->alignItems(...)`, `->justifyContent(...)`. Usar `->plain()` para omitir bordes/fondos por defecto.
- **`UI::split('name')`**: Vista dividida en dos paneles (`->horizontal()` o `->vertical()`). Configurar `->minFirstSize(...)`, `->minSecondSize(...)`, `->splitSize('50%')`, `->addFirst($ui1)`, `->addSecond($ui2)`. Acceder a paneles vía `$split->firstPane()` y `$split->secondPane()`.
- **`UI::input('name')`**: Inputs de texto, número, fecha (`datetime-local`), etc. Soportan `->onInput('action', [])` con `->debounce(400)` para búsquedas.
- **`UI::select('name')`**: Selector dropdown. Soporta `->options([['value' => '...', 'label' => '...']])`, `->value(...)`, `->onChange('action')`.
- **`UI::checkbox('name')`**: Checkbox individual o grupo múltiple.
- **`UI::table('name')`**: Tabla paginada conectada a un `dataModel(...)`.
- **`UI::uploader('name')`**: Carga de archivos con soporte multimedia y recorte.
- **`UI::button('name')`**: Botones de acción con estilos (`primary`, `secondary`, `danger`), variantes y `->action('action_name', ['param' => 'value'])`.

### 4.2 Lecciones Prácticas y Gotchas de Maquetación

1. **Evitar desalineación en Toolbars horizontales con `UI::select`:**
   - Si se invoca `UI::select('filter')->label('Estado')`, USIM genera una etiqueta flotante por encima del control que desalinea verticalmente el selector respecto a los inputs de texto adyacentes (`UI::input`).
   - *Solución:* En toolbars horizontales, **no usar `->label(...)`** en los selects inline; usar en su lugar un `placeholder` o la primera opción descriptiva (ej. `['value' => '', 'label' => 'Todos los estados']`).

2. **Truncamiento por Elipsis en Selects y Columnas:**
   - Si una opción de select es muy larga (ej. `🟡 Pendientes de Aprobación`) y el ancho del componente es fijo (ej. `220px`), el renderizador lo truncará con `...`.
   - *Solución:* Usar etiquetas cortas y concisas (ej. `🟡 Pendientes`, `🟢 Aprobados`, `🔴 Rechazados`).
   - Lo mismo aplica a las columnas de tablas dentro de splits: si el split izquierdo mide ~600px y las columnas suman 1080px, las cabeceras se cortarán a `ES...`. Ajustar la suma de anchos de columnas (`width`) para que encaje holgadamente en el contenedor visible.

3. **Selección Múltiple en USIM (Select vs Checkbox):**
   - El componente JS de `Select` en USIM está diseñado principalmente para selección simple nativa.
   - Para selecciones múltiples (ej. seleccionar Smart TVs, permisos o roles), el patrón nativo y consistente en USIM es usar `UI::checkbox`:
   ```php
   UI::checkbox('post_devices')
       ->label('📺 Dispositivos Smart TV')
       ->options($deviceOptions)
       ->selectedValues($selectedDeviceIds)
       ->vertical();
   ```
   El cliente serializa estos checkboxes como `post_devices[]` y el backend los recibe directamente como un arreglo en `$params['post_devices']`.

4. **Nombres de Unidad y Traducciones Fallback:**
   - `UsimUnit::getDisplayNameAttribute()` evalúa `trans($this->translation_key)`.
   - Si la clave no está en los archivos de traducción (ej. `lang/es/unit.php`), Laravel devuelve la clave en crudo: `unit.<slug>.display_name`.
   - *Solución estándar en USIM:*
   ```php
   $displayName = $unit->display_name;
   $unitLabel = (!empty($displayName) && $displayName !== $unit->translation_key)
       ? $displayName
       : ucfirst($unit->slug);
   ```

---

## 5. Tablas de Datos (`Table` y `AbstractListingTableModel`)

Para conectar bases de datos a tablas USIM:
1. Crear una clase que extienda `Idei\Usim\DataTable\AbstractListingTableModel<TModel>`.
2. Implementar:
   - `resolveListingService()`: Retorna el servicio de listado (`EloquentListingService`).
   - `getColumns()`: Arreglo de columnas con `label`, `width` y opcional `sort_by`.
   - `formatRow(object $item)`: Retorna el arreglo de celdas para una fila, incluyendo `_model_id => $item->id`.
   - `getFilters()`: Arreglo asociativo de filtros para la consulta (ej. `['status' => $this->statusFilter]`).
3. En la Screen:
   ```php
   $this->posts_table = UI::table('posts_table')
       ->dataModel(PostApprovalTableModel::class)
       ->selectionMode(SelectionMode::SINGLE)
       ->sortedBy('created_at', 'desc')
       ->fitContainer(
           availableHeight: 520,
           rowHeight: 45,
           hasToolbar: true,
           paginated: true
       );
   ```
4. Handlers requeridos en la Screen:
   - `onPostsTableRowClicked(array $params)`: Parámetro `model_id`.
   - `onPostsTableColumnClicked(array $params)`: Parámetro `sort_by`.
   - `onChangePage(array $params)`: Parámetro `page`.
   - `onSearch(array $params)`: `$this->posts_table->setSearchTerm($search);`

---

## 6. Modales y Diálogos Reusables

Los modales interactivos se ubican en `app/UI/Components/Modals/`.
- **Estructura típica:**
  ```php
  class EditPostDialog
  {
      public static function open(string $submitAction = 'save_post', ?Post $post = null, ?int $unitId = null): void
      {
          $dialog = new self();
          $format = $dialog->getUI($submitAction, $post, $unitId);
          app(UIChangesCollector::class)->add($format);
      }
      
      public function getUI(...): array
      {
          $container = UI::container('edit_post_dialog')->parent('modal')...;
          // ... construcción de UI ...
          return $container->toJson();
      }
  }
  ```
- **Cierre de Modales:** Desde cualquier Screen o handler, invocar `$this->closeModal();`.
- **Notificaciones:** Usar `$this->toast('Mensaje', 'success' | 'danger' | 'warning');`.

---

## 7. Manejo de Multimedia, Subidas y Recorte 16:9

USIM incluye un uploader basado en subidas temporales asíncronas:
1. **Frontend:** El componente sube el archivo a `/api/upload/temporary`.
2. **Tabla `temporary_uploads`:** Registra `uuid`, `path`, `mime_type`, `original_filename`, etc.
3. **Aspect Ratio y Recorte:** Al configurar `->aspect('16:9')`, el componente JS evalúa la proporción del archivo seleccionado mediante `ImageCropEditor`. Si difiere por más del 1% de tolerancia, abre la ventana modal de recorte antes de enviar el blob al servidor.
4. **Persistencia en el Formulario:** El input oculto envía `post_uploader_temp_ids` (o `post_uploader`) con el ID temporal.
5. **Backend Confirm:**
   ```php
   $uploader = UI::uploader('post_uploader')->aspect('16:9');
   $confirmed = $uploader->confirm($params, 'posts', $oldFilename);
   // Mueve de temporary a storage/app/uploads/posts/ y limpia temporary_uploads
   ```
6. **Detección Dinámica de Tipo:**
   - En JavaScript: Al subir o recortar una imagen, actualizar reactivamente cualquier selector `post_type` en el modal:
     ```javascript
     if (file.type?.startsWith('image/') || this.detectType(file.type) === 'image') {
         const postTypeSelect = document.querySelector('select[name="post_type"]');
         if (postTypeSelect && postTypeSelect.value !== 'image') {
             postTypeSelect.value = 'image';
             postTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
         }
     }
     ```
   - En Backend (`onSavePost`): Comprobar `$media['media_mime']` o extensión y forzar `type = PostType::IMAGE->value`.

---

## 8. Multi-Tenancy y Contexto de Unidades

1. **Resolución de Unidad Activa:**
   Utilizar el trait `ResolvesActiveUnitContext` en las pantallas:
   ```php
   use ResolvesActiveUnitContext;
   
   $unit = $this->resolveActiveUnit();
   $unitId = $unit?->id;
   ```
   El trait inspecciona en orden de precedencia:
   - `$this->store_unit` (unidad seleccionada en el menú por el usuario).
   - `request()->input('storage.store_unit')`.
   - `getPermissionsTeamId()` (equipo activo de Spatie).
   - Unidad por defecto o unidad `main`.

2. **Roles de Dispositivos (Spatie Teams vs Global):**
   - En sistemas con Spatie Teams activado (`config('permission.teams') = true`), `$device->roles` está condicionado al `team_id` activo.
   - Para consultar si un dispositivo tiene un rol sin importar la unidad (ej. verificar si es una `smart-tv`), utilizar la relación `globalRoles()` o consultar ambas:
   ```php
   $query->where(function (Builder $rq): void {
       $rq->whereHas('globalRoles', fn($gq) => $gq->whereIn('name', ['smart-tv', 'smart_tv']))
          ->orWhereHas('roles', fn($lq) => $lq->whereIn('name', ['smart-tv', 'smart_tv']));
   });
   ```

---

## 9. Patrones de Testing con Pest

Los tests de pantallas USIM se escriben con **Pest** simulando tanto la carga inicial del contrato JSON como las peticiones de eventos a `/api/ui-event`.

### 9.1 Test de Carga Inicial
```php
it('loads post approval screen for communication members', function () {
    $this->actingAs($this->reviewer);

    $ui = uiScenario($this, PostApprovalScreen::class, ['reset' => true]);

    $title = $ui->component('page_title');
    $table = $ui->component('posts_table');

    $title->expect('type')->toBe('label');
    $table->expect('type')->toBe('table');
    $ui->assertNoIssues();
});
```

### 9.2 Test de Disparo de Evento y Validación de Diff
```php
it('updates review panel when clicking a table row', function () {
    $this->actingAs($this->reviewer);

    $post = Post::factory()->pending()->create(['title' => 'Curso de IA']);

    // 1. Obtener ID del componente raíz del servicio
    $uiResponse = getScreenJson($this, PostApprovalScreen::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    // 2. Despachar evento simulando al cliente frontend
    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'posts_table_row_clicked',
        'parameters' => [
            'model_id' => $post->id,
        ],
    ]);

    // 3. Inspeccionar las mutaciones en el delta devuelto
    $response->assertOk();
    $diff = $response->json();

    $foundTitle = false;
    foreach ($diff as $item) {
        if (is_array($item) && ($item['name'] ?? null) === 'panel_post_title') {
            $foundTitle = true;
            expect($item['text'])->toBe('Curso de IA');
        }
    }
    expect($foundTitle)->toBeTrue();
});
```

### 9.3 Configuración en Tests
- Usar `uses(RefreshDatabase::class);`.
- En pruebas que involucren permisos de Spatie con equipos:
  ```php
  setPermissionsTeamId($unit->id);
  $user->givePermissionTo($permission);
  ```

---

## 10. Checklist Rápido para Crear o Modificar Pantallas

1. **Ubicación:** `app/UI/Screens/<Módulo>/<NombreScreen>.php`.
2. **Métodos obligatorios:**
   - `buildBaseUI(Container $container, ...$params): void`.
   - `authorize(): bool` (usar `self::requirePermission('...')`).
   - `getMenuLabel(): string` y `getMenuIcon(): ?string`.
3. **Persistencia de estado:** Declarar `protected ?<type> $store_<variable> = ...;` para cualquier valor que deba conservarse entre clics.
4. **Handlers:** Métodos públicos `on<ActionName>(array $params): void`.
5. **Actualización de UI en Handlers:** Siempre modificar componentes existentes o reemplazarlos dentro de sus contenedores padre (`clear()` + `add(...)`).
6. **Lógica de negocio:** Invocar servicios (`app/Services/`), no escribir lógica de persistencia compleja en la Screen.
7. **Formato:** Ejecutar `./vendor/bin/pint` antes de finalizar.
8. **Pruebas:** Ejecutar `./vendor/bin/pest tests/Feature/<Test>.php` y validar que el diff responde exactamente con lo esperado.

