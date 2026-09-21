<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Device;

use App\Contracts\KioskPostResolverContract;
use App\Models\Device;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class KioskScreen extends Screen
{
    /**
     * Dispositivo / terminal Kiosk autenticado mediante guard 'device'.
     */
    public static ?string $guard = 'device';

    /**
     * El modo kiosco se despliega a pantalla completa sin barra de navegación superior.
     */
    public static bool $hasMenu = false;

    protected Carousel $device_carousel;

    protected int $store_current_index = 0;

    protected int $store_timeout_ms = 5000;

    protected KioskPostResolverContract $kioskPostResolver;

    public function __construct(
        ?KioskPostResolverContract $kioskPostResolver = null,
    ) {
        $this->kioskPostResolver = $kioskPostResolver ?? app(KioskPostResolverContract::class);
    }

    public static function authorize(): bool
    {
        $device = Auth::guard('device')->user();
        if ($device instanceof Device) {
            $units = $device->usimUnits;
            if ($units->isNotEmpty()) {
                foreach ($units as $unit) {
                    if (self::requirePermission('device.kiosk_screen.access', 'device', $unit)) {
                        return true;
                    }
                }
            }
        }

        return self::requirePermission('device.kiosk_screen.access', 'device');
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $items = $this->mediaItems();
        $this->store_current_index = $this->normalizeIndex($this->store_current_index, \count($items));

        $currentItem = $items[$this->store_current_index];
        $this->store_timeout_ms = max(1000, $currentItem['duration_ms']);

        $container
            ->title(t('screen.device.kiosk.title'))
            ->plain()
            ->padding(Spacing::px(0));

        $container->add(
            UI::carousel('device_carousel')
                ->modeAuto()
                ->autoplay(true)
                ->autoAction('carousel_tick')
                ->autoTimeoutMs($this->store_timeout_ms)
                ->loop(true)
                ->fullscreen(false)
                ->showPrev(false)
                ->showNext(false)
                ->knownCount(null)
                ->currentIndex($this->store_current_index)
                ->currentMedia($currentItem)
                ->hideIndicators()
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCarouselTick(array $params): void
    {
        $carouselName = $params['carousel_name'] ?? 'device_carousel';

        if ($carouselName !== 'device_carousel') {
            return;
        }

        $items = $this->mediaItems();
        $count = \count($items);
        $this->store_current_index = $this->normalizeIndex($this->store_current_index + 1, $count);

        $next = $items[$this->store_current_index];
        $this->store_timeout_ms = max(1000, $next['duration_ms']);

        $this->device_carousel
            ->currentIndex($this->store_current_index)
            ->currentMedia($next)
            ->autoTimeoutMs($this->store_timeout_ms)
            ->knownCount(null);
    }

    private function normalizeIndex(int $index, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return (($index % $count) + $count) % $count;
    }

    /**
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    protected function mediaItems(): array
    {
        $device = Auth::guard('device')->user();

        if ($device instanceof Device) {
            return $this->kioskPostResolver->resolveForDevice($device);
        }

        return $this->kioskPostResolver->resolvePublic();
    }
}
