<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Normalize;
use App\Models\OrderDrawing;
use App\Services\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Rysunki zlecenia.
 *
 * Odblokowują warunek `all_drawings_added` przy wejściu na produkcję —
 * do tej pory nie dało się go rozstrzygnąć, bo nie było czego liczyć.
 *
 * **Komplet rysunków deklaruje człowiek, nie licznik plików.** Zlecenie
 * na proste docinki nie potrzebuje żadnego rysunku, a zlecenie na
 * zabudowę może potrzebować siedmiu — system nie ma skąd wiedzieć,
 * ile ich być powinno. Dlatego flaga jest oświadczeniem konkretnej
 * osoby z datą: produkcja rusza na jego podstawie, więc musi być
 * wiadomo, kto je złożył. Sam znacznik logiczny nie odpowiada na żadne
 * pytanie zadane po fakcie.
 *
 * **Dodanie lub usunięcie rysunku cofa oświadczenie.** Po zmianie
 * kompletu deklaracja sprzed zmiany przestaje cokolwiek znaczyć,
 * a milczące jej utrzymanie wpuściłoby na produkcję zlecenie, którego
 * nikt nie obejrzał w nowym kształcie.
 */
final readonly class OrderDrawingService
{
    private const DISK = 'local';

    /** 20 MB — skan formatu A2 z telefonu mieści się z zapasem. */
    private const MAX_KILOBYTES = 20480;

    /**
     * Rysunek techniczny, skan, zdjęcie z pomiaru. DWG i DXF, bo tak
     * przychodzą projekty od architektów.
     */
    private const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'dwg', 'dxf'];

    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
        private OrderTabs $tabs = new OrderTabs(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(int $orderId): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['drawings.uploader', 'drawings.item', 'lists.items', 'status'])
            ->findOrFail($orderId);

        $rows = [];

        /** @var OrderDrawing $drawing */
        foreach ($order->drawings->sortByDesc('id') as $drawing) {
            $rows[] = [
                'id' => (int) $drawing->getKey(),
                'name' => $drawing->original_name,
                'note' => $drawing->note,
                'mime' => $drawing->mime,
                'is_image' => $drawing->isImage(),
                'size_bytes' => $drawing->size_bytes,
                'item_id' => $drawing->order_item_id,
                'item_name' => $drawing->item?->name,
                'uploaded_at' => $drawing->getRawOriginal('created_at'),
                'uploaded_by' => $drawing->uploader === null
                    ? null
                    : trim((string) $drawing->uploader->first_name . ' ' . (string) $drawing->uploader->last_name),
            ];
        }

        return [
            'order' => [
                'id' => (int) $order->getKey(),
                'number' => (int) $order->number,
                'status' => $order->status?->name,
            ],
            'tabs' => $this->tabs->counts($order),
            'drawings' => $rows,
            'complete' => [
                'declared' => $order->drawings_complete_at !== null,
                'at' => $order->drawings_complete_at?->format('d.m.Y H:i'),
                'by' => $this->declarant($order),
            ],
            // Do przypisania rysunku do konkretnej formatki — puste pole
            // znaczy „dotyczy calego zlecenia".
            'items' => $this->items($order),
            'accepts' => self::EXTENSIONS,
            'max_kilobytes' => self::MAX_KILOBYTES,
        ];
    }

    /**
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function store(int $orderId, ?UploadedFile $file, mixed $itemId, mixed $note): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make(
            ['file' => $file, 'note' => $note],
            [
                'file' => ['required', 'file', 'max:' . self::MAX_KILOBYTES, 'mimes:' . implode(',', self::EXTENSIONS)],
                'note' => ['nullable', 'string', 'max:200'],
            ],
            [
                'file.required' => 'Wskaż plik.',
                'file.max' => 'Plik jest większy niż 20 MB.',
                'file.mimes' => 'Dozwolone formaty: ' . implode(', ', self::EXTENSIONS) . '.',
            ],
        );

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        if (!$file instanceof UploadedFile) {
            return ['errors' => ['file' => ['Wskaż plik.']], 'id' => null];
        }

        $item = $this->itemOf($order, $itemId);

        if ($itemId !== null && $itemId !== '' && $item === null) {
            return ['errors' => ['order_item_id' => ['Ta pozycja nie należy do tego zlecenia.']], 'id' => null];
        }

        $path = $file->store('orders/' . $order->getKey() . '/drawings', self::DISK);

        if (!is_string($path) || $path === '') {
            return ['errors' => ['file' => ['Nie udało się zapisać pliku.']], 'id' => null];
        }

        /** @var OrderDrawing $drawing */
        $drawing = OrderDrawing::query()->create([
            'order_id' => (int) $order->getKey(),
            'order_item_id' => $item?->getKey(),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'stored_path' => $path,
            'mime' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'note' => Normalize::text(is_string($note) ? $note : null),
            'uploaded_by' => Auth::id(),
        ]);

        $this->withdraw($order, 'dodano rysunek ' . $drawing->original_name);

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'rysunek', 'before' => null, 'after' => $drawing->original_name]],
            'drawing_added',
        );

        return ['errors' => [], 'id' => (int) $drawing->getKey()];
    }

    public function file(int $orderId, int $drawingId): ?OrderDrawing
    {
        /** @var OrderDrawing|null $drawing */
        $drawing = OrderDrawing::query()
            ->where('order_id', $orderId)
            ->find($drawingId);

        if ($drawing === null || !Storage::disk(self::DISK)->exists($drawing->stored_path)) {
            return null;
        }

        return $drawing;
    }

    public function path(OrderDrawing $drawing): string
    {
        return (string) Storage::disk(self::DISK)->path($drawing->stored_path);
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function delete(int $orderId, int $drawingId): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        /** @var OrderDrawing|null $drawing */
        $drawing = OrderDrawing::query()
            ->where('order_id', $order->getKey())
            ->find($drawingId);

        if ($drawing === null) {
            return ['errors' => ['drawing' => ['Ten rysunek nie należy do tego zlecenia.']]];
        }

        $name = $drawing->original_name;

        Storage::disk(self::DISK)->delete($drawing->stored_path);
        $drawing->delete();

        $this->withdraw($order, 'usunięto rysunek ' . $name);

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'rysunek', 'before' => $name, 'after' => null]],
            'drawing_removed',
        );

        return ['errors' => []];
    }

    /**
     * Złożenie albo cofnięcie oświadczenia o komplecie rysunków.
     *
     * @return array{errors: array<string, list<string>>}
     */
    public function declare(int $orderId, bool $complete): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $before = $order->drawings_complete_at === null ? 'nie' : 'tak';
        $after = $complete ? 'tak' : 'nie';

        if ($before === $after) {
            return ['errors' => []];
        }

        $order->drawings_complete_at = $complete ? Carbon::now() : null;
        $order->drawings_complete_by = $complete ? Auth::id() : null;
        $order->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'komplet rysunków', 'before' => $before, 'after' => $after]],
            'drawings_declared',
        );

        return ['errors' => []];
    }

    /**
     * Zmiana kompletu unieważnia wcześniejsze oświadczenie — inaczej
     * na produkcję poszłoby zlecenie, którego nikt nie obejrzał
     * w nowym kształcie.
     */
    private function withdraw(Order $order, string $reason): void
    {
        if ($order->drawings_complete_at === null) {
            return;
        }

        $order->drawings_complete_at = null;
        $order->drawings_complete_by = null;
        $order->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'komplet rysunków', 'before' => 'tak', 'after' => 'nie — ' . $reason]],
            'drawings_declared',
        );
    }

    private function itemOf(Order $order, mixed $itemId): ?OrderItem
    {
        if ($itemId === null || $itemId === '' || (int) $itemId === 0) {
            return null;
        }

        /** @var OrderItem|null */
        return OrderItem::query()
            ->whereKey((int) $itemId)
            ->whereHas('list', static fn($query) => $query->where('order_id', $order->getKey()))
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Order $order): array
    {
        $rows = [];

        foreach ($order->lists as $list) {
            foreach ($list->items as $item) {
                $rows[] = [
                    'id' => (int) $item->getKey(),
                    'name' => $item->name,
                    'list' => (int) $list->number,
                ];
            }
        }

        return $rows;
    }

    private function declarant(Order $order): ?string
    {
        if ($order->drawings_complete_by === null) {
            return null;
        }

        $user = User::query()->find($order->drawings_complete_by);

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }
}
