<?php

namespace App\UI\Screens\Member;

use App\Contracts\PostServiceContract;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use App\UI\Components\Modals\EditPostDialog;
use App\UI\Screens\Admin\Concerns\HandlesScreenParameters;
use App\UI\Screens\Admin\Concerns\ResolvesActiveUnitContext;
use App\UI\Screens\Member\Concerns\HandlesPostMediaUpload;
use App\UI\Screens\Member\TableModels\MemberPostTableModel;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PostManager extends Screen
{
    use HandlesPostMediaUpload;
    use HandlesScreenParameters;
    use ResolvesActiveUnitContext;

    protected Table $posts_table;

    protected Input $search_posts;

    protected Button $btn_create_post;

    protected PostServiceContract $postService;

    public function __construct(
        ?PostServiceContract $postService = null,
    ) {
        $this->postService = $postService ?? app(PostServiceContract::class);
    }

    public static function authorize(): bool
    {
        return self::requirePermission('member.post_manager.access');
    }

    public static function getMenuLabel(): string
    {
        return 'Mis Publicaciones';
    }

    public static function getMenuIcon(): ?string
    {
        return '📢';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth(Size::px(1280))
            ->padding(Spacing::px(20))
            ->centerHorizontal()
            ->gap(Spacing::px(16));

        // Header con título y botón de acción principal
        $header = UI::container('posts_header')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain();

        $header->add(
            UI::label('page_title')
                ->text('📢 Mis Publicaciones y Noticias')
                ->fontSize('24px')
                ->bold()
        );

        $this->btn_create_post = UI::button('btn_create_post')
            ->label('+ Nueva Publicación')
            ->style('primary')
            ->action('open_create_post_modal');

        $header->add($this->btn_create_post);
        $container->add($header);

        // Barra de herramientas: Búsqueda
        $toolbar = UI::container('posts_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(12))
            ->plain();

        $this->search_posts = UI::input('search_posts')
            ->placeholder('Buscar por título...')
            ->width(Size::px(320))
            ->autocomplete('off')
            ->onInput('search_posts', [])
            ->debounce(400);

        $toolbar->add($this->search_posts);
        $container->add($toolbar);

        // Tabla de posts del usuario autenticado
        $this->posts_table = UI::table('posts_table')
            ->dataModel(MemberPostTableModel::class)
            ->selectionMode(SelectionMode::SINGLE)
            ->sortedBy('starts_at', 'desc')
            ->fitContainer(
                availableHeight: 550,
                rowHeight: 45,
                hasToolbar: true,
                paginated: true
            );

        $container->add($this->posts_table);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onPostsTableRowClicked(array $params): void
    {
        $postId = $this->optionalIntParam($params, 'model_id');
        if ($postId === null) {
            return;
        }

        $post = Post::find($postId);
        if (! $post) {
            $this->toast(t('toast.error'), 'danger');

            return;
        }

        $unit = $this->resolveActiveUnit();
        EditPostDialog::open(submitAction: 'save_post', post: $post, unitId: $unit?->id);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onPostsTableColumnClicked(array $params): void
    {
        $column = $this->optionalStringParam($params, 'sort_by');
        if ($column === null || $column === '') {
            return;
        }

        $this->posts_table->sortedBy($column);
        $this->posts_table->page(1);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onOpenCreatePostModal(array $params): void
    {
        $unit = $this->resolveActiveUnit();
        EditPostDialog::open(submitAction: 'save_post', unitId: $unit?->id);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSavePost(array $params): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->toast('Debes iniciar sesión.', 'danger');

            return;
        }

        $postId = $this->optionalIntParam($params, 'post_id');
        $submitNow = $this->boolParamOrDefault($params, 'submit_now', false);
        $postType = $this->stringParamOrDefault($params, 'post_type', 'text');

        try {
            $existingPost = null;
            if ($postId !== null && $postId > 0) {
                $existingPost = Post::find($postId);
            }

            $media = $this->resolvePostMedia($params, $postType, $existingPost);

            // Requisito: Si el usuario subió una imagen, el tipo de post debe cambiar a "imagen"
            if ($media['media_mime'] !== null && str_starts_with($media['media_mime'], 'image/')) {
                $postType = PostType::IMAGE->value;
            }

            $rawDevices = $params['post_devices'] ?? $params['devices'] ?? $params['device_ids'] ?? [];
            $deviceIds = [];
            if (is_array($rawDevices)) {
                $deviceIds = array_values(array_filter(
                    array_map(static fn (mixed $d): ?int => is_numeric($d) ? (int) $d : null, $rawDevices),
                    static fn (?int $id): bool => $id !== null && $id > 0
                ));
            } elseif (is_numeric($rawDevices) && (int) $rawDevices > 0) {
                $deviceIds = [(int) $rawDevices];
            }

            $data = [
                'title' => $this->stringParamOrDefault($params, 'post_title', ''),
                'content' => $this->optionalStringParam($params, 'post_content'),
                'type' => $postType,
                'media_url' => $media['media_url'],
                'media_mime' => $media['media_mime'],
                'starts_at' => $this->stringParamOrDefault($params, 'post_starts_at', now()->toDateTimeString()),
                'ends_at' => $this->stringParamOrDefault($params, 'post_ends_at', now()->addDays(7)->toDateTimeString()),
                'display_duration_sec' => $this->intParamOrDefault($params, 'post_display_duration_sec', 10),
                'is_public' => $this->boolParamOrDefault($params, 'post_is_public', false),
                'submit_now' => $submitNow,
                'device_ids' => $deviceIds,
            ];

            if ($existingPost !== null) {
                $this->postService->update($existingPost, $data, $user);
                $message = $submitNow ? 'Publicación actualizada y enviada a revisión.' : 'Publicación actualizada como borrador.';
            } else {
                $unit = $this->resolveActiveUnit();
                $unitId = $unit ? $unit->id : 1;
                $this->postService->create($data, $user, $unitId);
                $message = $submitNow ? 'Publicación creada y enviada a revisión.' : 'Publicación guardada como borrador.';
            }

            $this->closeModal();
            $this->toast($message, 'success');
            $this->posts_table->refresh();
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSubmitForApproval(array $params): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $postId = $this->optionalIntParam($params, 'model_id') ?? $this->optionalIntParam($params, 'post_id');
        if (! $user || $postId === null) {
            return;
        }

        try {
            $post = Post::findOrFail($postId);
            $this->postService->submitForApproval($post, $user);
            $this->toast('Publicación enviada a revisión.', 'success');
            $this->posts_table->refresh();
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onDeletePost(array $params): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $postId = $this->optionalIntParam($params, 'model_id') ?? $this->optionalIntParam($params, 'post_id');
        if (! $user || $postId === null) {
            return;
        }

        try {
            $post = Post::findOrFail($postId);
            $this->postService->delete($post, $user);
            $this->toast('Publicación eliminada.', 'success');
            $this->posts_table->refresh();
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSearchPosts(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_posts']));
        $this->posts_table->setSearchTerm($search);
        $this->search_posts->value($search);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onChangePage(array $params): void
    {
        $page = $this->intParamOrDefault($params, 'page', 1);
        $this->posts_table->page($page);
    }
}
