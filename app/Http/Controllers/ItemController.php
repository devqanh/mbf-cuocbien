<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\SheetSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    private const SHEET_KEY = 'items_grid';

    public function index()
    {
        return view('items.index');
    }

    public function data(): JsonResponse
    {
        $items = Item::orderBy('id')->get([
            'id', 'code', 'name', 'category', 'price', 'stock', 'unit', 'note', 'is_active',
        ]);

        $snapshot = SheetSnapshot::where('key', self::SHEET_KEY)->value('payload');

        return response()->json([
            'data'     => $items,
            'snapshot' => $snapshot ? json_decode($snapshot, true) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $item = Item::create($data);

        return response()->json(['ok' => true, 'item' => $item], 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $this->validateData($request, $item->id);
        $item->update($data);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();
        return response()->json(['ok' => true]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows'     => ['required', 'array'],
            'rows.*'   => ['array'],
            'snapshot' => ['nullable', 'array'],
        ]);

        $ids = [];
        foreach ($payload['rows'] as $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            if (empty($row['code']) || empty($row['name'])) {
                continue;
            }
            $row['price']     = (float) ($row['price']  ?? 0);
            $row['stock']     = (int)   ($row['stock']  ?? 0);
            $row['is_active'] = (bool)  ($row['is_active'] ?? true);

            $item = Item::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name'      => $row['name'],
                    'category'  => $row['category'] ?? null,
                    'price'     => $row['price'],
                    'stock'     => $row['stock'],
                    'unit'      => $row['unit'] ?? null,
                    'note'      => $row['note'] ?? null,
                    'is_active' => $row['is_active'],
                ]
            );
            $ids[] = $item->id;
        }

        // Lưu snapshot (gồm style: màu nền, font, border…) nếu client gửi lên
        if (! empty($payload['snapshot'])) {
            SheetSnapshot::updateOrCreate(
                ['key' => self::SHEET_KEY],
                ['payload' => json_encode($payload['snapshot'], JSON_UNESCAPED_UNICODE)]
            );
        }

        return response()->json(['ok' => true, 'saved' => count($ids), 'ids' => $ids]);
    }

    public function resetSnapshot(): JsonResponse
    {
        SheetSnapshot::where('key', self::SHEET_KEY)->delete();
        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'      => ['required', 'string', 'max:64', 'unique:items,code' . ($ignoreId ? ",$ignoreId" : '')],
            'name'      => ['required', 'string', 'max:255'],
            'category'  => ['nullable', 'string', 'max:64'],
            'price'     => ['nullable', 'numeric', 'min:0'],
            'stock'     => ['nullable', 'integer', 'min:0'],
            'unit'      => ['nullable', 'string', 'max:32'],
            'note'      => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
