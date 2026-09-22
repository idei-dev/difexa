<?php

namespace App\UI\Screens\Communication;

use App\Contracts\PostServiceContract;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\UI\Components\Modals\RejectPostDialog;
use App\UI\Components\Modals\ViewPostDialog;
use App\UI\Screens\Admin\Concerns\HandlesScreenParameters;
use App\UI\Screens\Communication\Concerns\ManagesPostReviewSection;
use App\UI\Screens\Communication\TableModels\PostApprovalTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PostApprovalScreen extends Screen
{
    use HandlesScreenParameters;
    use ManagesPostReviewSection;

    protected Table $posts_table;
    protected Input $search_posts;
    protected Select $filter_status;
    protected Split $moderation_split;

    protected ?int $store_selected_post_id = null;

    protected PostServiceContract $postService;

    public function __construct(
        ?PostServiceContract $postService = null,
    ) {
        $this->postService = $postService ?? app(PostServiceContract::class);
    }

    public static function authorize(): bool
    {
        return self::requirePermission('communication.post_approval.access');
    }

    public static function getMenuLabel(): string
    {
        return 'Moderación de Publicaciones';
    }

    public static function getMenuIcon(): ?string
    {
        return '🛡️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth(Size::px(1280))
            ->padding(Spacing::px(16))
            ->centerHorizontal()
            ->gap(Spacing::px(14));

        // Header
        $header = UI::container('approval_header')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain();

        $header->add(
            UI::label('page_title')
                ->text('🛡️ Moderación de Publicaciones y Kiosks')
                ->fontSize('24px')
                ->bold()
        );

        $header->add(
            UI::label('page_subtitle')
                ->text('Revisión y aprobación de actividades y noticias enviadas por las unidades académicas para difusión en pantallas.')
                ->size('small')
        );

        $container->add($header);

        // Barra de búsqueda y filtros
        $toolbar = UI::container('approval_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(12))
            ->plain();

        $this->search_posts = UI::input('search_posts')
            ->placeholder('Buscar por título o autor...')
            ->width(Size::px(260))
            ->autocomplete('off')
            ->onInput('search_posts', [])
            ->debounce(400);

        /** @var list<array{value: string, label: string}> $statusOptions */
        $statusOptions = [
            ['value' => '', 'label' => 'Todos los estados'],
            ['value' => PostStatus::PENDING->value, 'label' => '🟡 Pendientes de Aprobación'],
            ['value' => PostStatus::APPROVED->value, 'label' => '🟢 Aprobados'],
            ['value' => PostStatus::REJECTED->value, 'label' => '🔴 Rechazados'],
        ];

        $this->filter_status = UI::select('filter_status')
            ->label('Estado')
            ->options($statusOptions)
            ->value(PostStatus::PENDING->value)
            ->width(Size::px(220));

        $toolbar->add($this->search_posts);
        $toolbar->add($this->filter_status);

        // Tabla de moderación
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

        if ($this->store_selected_post_id !== null) {
            $this->posts_table->select($this->store_selected_post_id);
        }

        $tableSection = UI::container('table_section')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(10))
            ->plain();

        $tableSection->add($toolbar);
        $tableSection->add($this->posts_table);

        // Panel de inspección y previsualización multimedia en vivo para el revisor
        $selectedPost = null;
        if ($this->store_selected_post_id !== null) {
            $selectedPost = Post::with(['author', 'unit'])->find($this->store_selected_post_id);
        }

        $reviewPanel = $this->buildReviewPanel($selectedPost);

        // Distribución en Split horizontal para el revisor
        $this->moderation_split = UI::split('moderation_split')
            ->horizontal()
            ->width(Size::full())
            ->minHeight(Size::px(600))
            ->minFirstSize('460px')
            ->minSecondSize('420px')
            ->splitSize('54%')
            ->addFirst($tableSection)
            ->addSecond($reviewPanel);

        $container->add($this->moderation_split);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPostsTableRowClicked(array $params): void
    {
        $postId = $this->optionalIntParam($params, 'model_id');
        if ($postId === null) {
            return;
        }

        $post = Post::find($postId);
        if (!$post) {
            $this->toast(t('toast.error'), 'danger');
            return;
        }

        $this->store_selected_post_id = $post->id;
        $this->posts_table->select($post->id);
    }

    /**
     * @param array<string, mixed> $params
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
     * @param array<string, mixed> $params
     */
    public function onOpenViewPostModal(array $params): void
    {
        $postId = $this->optionalIntParam($params, 'model_id') ?? $this->optionalIntParam($params, 'post_id');
        if ($postId === null) {
            return;
        }

        $post = Post::find($postId);
        if (!$post) {
            $this->toast(t('toast.error'), 'danger');
            return;
        }

        ViewPostDialog::open(post: $post);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitApprovePost(array $params): void
    {
        /** @var User|null $approver */
        $approver = Auth::user();
        $postId = $this->optionalIntParam($params, 'model_id') ?? $this->optionalIntParam($params, 'post_id');
        if (!$approver || $postId === null) {
            return;
        }

        try {
            $post = Post::findOrFail($postId);
            $this->postService->approve($post, $approver);
            $this->closeModal();
            $this->toast('Publicación aprobada exitosamente para difusión.', 'success');
            $this->store_selected_post_id = $post->id;
            $this->posts_table->refresh();
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onOpenRejectModal(array $params): void
    {
        $postId = $this->optionalIntParam($params, 'model_id') ?? $this->optionalIntParam($params, 'post_id');
        if ($postId === null) {
            return;
        }

        $post = Post::find($postId);
        if (!$post) {
            $this->toast(t('toast.error'), 'danger');
            return;
        }

        RejectPostDialog::open(post: $post);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitRejectPost(array $params): void
    {
        /** @var User|null $approver */
        $approver = Auth::user();
        $postId = $this->optionalIntParam($params, 'post_id');
        $reason = $this->stringParamOrDefault($params, 'rejection_reason', '');
        if (!$approver || $postId === null) {
            return;
        }

        try {
            $post = Post::findOrFail($postId);
            $this->postService->reject($post, $approver, $reason);
            $this->closeModal();
            $this->toast('Publicación rechazada y devuelta al autor.', 'warning');
            $this->store_selected_post_id = $post->id;
            $this->posts_table->refresh();
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseModal(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchPosts(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_posts']));
        $this->posts_table->setSearchTerm($search);
        $this->search_posts->value($search);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onChangePage(array $params): void
    {
        $page = $this->intParamOrDefault($params, 'page', 1);
        $this->posts_table->page($page);
    }
}
