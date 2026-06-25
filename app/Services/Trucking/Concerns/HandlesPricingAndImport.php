<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPriceBook;
use App\Models\TruckingPriceRow;
use App\Models\TruckingFuelPrice;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingRouteFee;
use App\Models\TruckingSalaryItem;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use App\Models\TruckingVehicleCost;
use App\Models\TruckingVehicleDepreciation;
use App\Models\TruckingVehicleUsage;
use App\Models\TruckingSetting;
use App\Models\TruckingAttachment;
use App\Models\TruckingPlanLink;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingVehicleCostType;
use App\Models\TruckingAssetCategory;
use App\Support\Hashid;
use App\Models\TruckingStatement;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use App\Models\User;
use App\Notifications\SpendRequestCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Tach tu TruckingV2Service - nhom HandlesPricingAndImport. */
trait HandlesPricingAndImport
{
    /** @var array<string,int>|null  trim(code|name) => location_id — memoize / request. */
    private ?array $locIdMapCache = null;

    /**
     * Bảng giá của ĐÚNG 1 khách (lazy-load cho trang Bảng giá). Dùng toBase() để KHÔNG
     * hydrate model Eloquent — chỉ đọc + serialize nên stdClass đủ; bảng giá lớn (hàng
     * trăm dòng) nhờ vậy nhẹ RAM/CPU hơn nhiều.
     */
    public function customerPriceList(string $name): array
    {
        $name = trim($name);
        if ($name === '') return [];
        $custId = TruckingCustomer::where('name', $name)->value('id');
        return $custId ? $this->customerPriceListById((int) $custId) : [];
    }

    /**
     * Bảng giá theo customer_id — bền khi khách đổi tên (dùng cho statement đã lưu có sẵn
     * customer_id). Caller nên ưu tiên gọi bản này; bản theo name chỉ là fallback.
     */
    public function customerPriceListById(int $customerId, ?int $priceBookId = null): array
    {
        if ($customerId <= 0) return [];
        // $priceBookId === null → KHÔNG có bảng giá phủ ngày → trả rỗng (định giá "chưa khớp").
        if ($priceBookId === null) return [];
        return TruckingPriceRow::where('customer_id', $customerId)->where('price_book_id', $priceBookId)
            ->orderBy('sort')->toBase()->get()
            ->map(fn ($p) => $this->priceRowToArray($p))->all();
    }

    /**
     * Cfg TỐI THIỂU cho bảng danh sách Lô hàng (không có popup): chỉ màu theo dõi (chip
     * "chưa điền" trên dòng), ngưỡng free time (cột lịch trình) và VAT mặc định (thêm lô).
     * Toàn bộ danh mục dropdown (địa điểm, kho, khách, loại cont...) lazy-load khi mở popup.
     */
    public function shipmentBoardConfig(): array
    {
        return [
            'costColors'    => TruckingCostItem::whereNotNull('color')->where('color', '!=', '')
                                  ->pluck('color', 'name')->all(),
            // Khoản "tự hiện" (auto) → list nhắc "chưa điền" trên mọi lô + popup hiện sẵn dòng.
            'costAuto'      => array_fill_keys(TruckingCostItem::where('auto', true)->pluck('name')->all(), true),
            'freeTimeHours' => TruckingSetting::get('free_time_hours', '4'),
            'vatDefault'    => [
                'hph' => TruckingSetting::get('vat_default_hph', '8'),
                'icd' => TruckingSetting::get('vat_default_icd', '0'),
            ],
        ];
    }

    /**
     * Cfg cho trang Bảng giá: chỉ khách hàng (+ đếm dòng giá cho badge) và địa điểm
     * (autocomplete trong PriceList). KHÔNG nạp các danh mục khác (kho/payer/loại cont...)
     * vì trang Bảng giá không dùng.
     */
    public function priceBookConfig(): array
    {
        $locations = TruckingLocation::orderBy('sort')->orderBy('name')->get();
        $customers = TruckingCustomer::withCount('priceRows')->with(['priceBooks' => fn ($q) => $q->withCount('priceRows')])->orderBy('name')->get();

        return [
            'locations'    => $locations->pluck('name')->all(),
            'locationCode' => $locations->filter(fn ($l) => $l->code)->mapWithKeys(fn ($l) => [$l->name => $l->code])->all(),
            'customers'    => $customers->pluck('name')->all(),
            'customerInfo' => $customers->mapWithKeys(fn ($c) => [$c->name => [
                'shortName' => $c->short_name ?? '',
                'taxCode'   => $c->tax_code ?? '',
                'phone'     => $c->phone ?? '',
                'contact'   => $c->contact ?? '',
                'email'     => $c->email ?? '',
                'termDays'  => $c->term_days !== null ? (string) $c->term_days : '',
                'address'   => $c->address ?? '',
                'note'      => $c->note ?? '',
                'priceCount' => (int) ($c->price_rows_count ?? 0),
                // Danh sách BẢNG GIÁ (price book) theo khoảng ngày của khách.
                'priceBooks' => $c->priceBooks->map(fn ($b) => [
                    'id'    => (int) $b->id,
                    'label' => $b->label ?? '',
                    'from'  => $b->period_from?->toDateString(),
                    'to'    => $b->period_to?->toDateString(),
                    'count' => (int) ($b->price_rows_count ?? 0),
                ])->values()->all(),
            ]])->all(),
        ];
    }

    /** Danh sách bảng giá (book) của 1 khách — cho FE refresh sau CRUD. */
    public function priceBooksForCustomer(int $customerId): array
    {
        return TruckingPriceBook::where('customer_id', $customerId)->withCount('priceRows')
            ->orderBy('period_from')->orderBy('id')->get()
            ->map(fn ($b) => [
                'id'    => (int) $b->id,
                'label' => $b->label ?? '',
                'from'  => $b->period_from?->toDateString(),
                'to'    => $b->period_to?->toDateString(),
                'count' => (int) ($b->price_rows_count ?? 0),
            ])->values()->all();
    }

    /** Dòng giá của 1 BOOK (lazy-load để sửa). */
    public function priceBookRows(int $bookId): array
    {
        if ($bookId <= 0) return [];
        return TruckingPriceRow::where('price_book_id', $bookId)->orderBy('sort')->toBase()->get()
            ->map(fn ($p) => $this->priceRowToArray($p))->all();
    }

    /** Book MỞ (mọi ngày) của khách — tạo nếu chưa có. Dùng làm đích mặc định khi import không chỉ book. */
    private function defaultBookId(int $customerId): int
    {
        $b = TruckingPriceBook::where('customer_id', $customerId)
            ->whereNull('period_from')->whereNull('period_to')->orderBy('id')->first();
        $b ??= TruckingPriceBook::create(['customer_id' => $customerId, 'label' => 'Mặc định (mọi ngày)', 'sort' => 0]);
        return (int) $b->id;
    }

    public function createPriceBook(string $customerName, ?string $label, ?string $from, ?string $to): array
    {
        $cust = TruckingCustomer::firstOrCreate(['name' => trim($customerName)]);
        $sort = (int) (TruckingPriceBook::where('customer_id', $cust->id)->max('sort') ?? 0) + 1;
        TruckingPriceBook::create([
            'customer_id' => $cust->id, 'label' => $label ?: null,
            'period_from' => $from ?: null, 'period_to' => $to ?: null, 'sort' => $sort,
        ]);
        return ['books' => $this->priceBooksForCustomer((int) $cust->id)];
    }

    public function updatePriceBook(int $bookId, ?string $label, ?string $from, ?string $to): array
    {
        $b = TruckingPriceBook::find($bookId);
        if (! $b) return ['ok' => false];
        $b->update(['label' => $label ?: null, 'period_from' => $from ?: null, 'period_to' => $to ?: null]);
        return ['ok' => true, 'books' => $this->priceBooksForCustomer((int) $b->customer_id)];
    }

    public function deletePriceBook(int $bookId): array
    {
        $b = TruckingPriceBook::find($bookId);
        if (! $b) return ['ok' => false];
        $cid = (int) $b->customer_id;
        $b->delete();   // cascade rows
        return ['ok' => true, 'books' => $this->priceBooksForCustomer($cid)];
    }

    /** Lưu toàn bộ dòng giá của 1 BOOK — xóa-hết-tạo-lại TRONG PHẠM VI book (không đụng book khác). */
    public function savePriceBookRows(int $bookId, array $rows): array
    {
        return DB::transaction(function () use ($bookId, $rows) {
            $book = TruckingPriceBook::find($bookId);
            if (! $book) return ['ok' => false, 'priceList' => []];
            TruckingPriceRow::where('price_book_id', $bookId)->delete();
            foreach ($rows as $i => $p) {
                TruckingPriceRow::create($this->priceRowAttrs($p, $i) + ['customer_id' => $book->customer_id, 'price_book_id' => $bookId]);
            }
            $this->registerPriceRowCodes($rows);
            return ['ok' => true, 'priceList' => $this->priceBookRows($bookId)];
        });
    }

    /** Đăng ký ký hiệu FROM (địa điểm) + TO1..4 (địa điểm + kho) từ các dòng giá. */
    private function registerPriceRowCodes(array $rows): void
    {
        $whNames = [];
        foreach ($rows as $p) {
            $this->registerLocationCode($p['from'] ?? null);
            foreach (['to1', 'to2', 'to3', 'to4'] as $k) {
                $v = trim((string) ($p[$k] ?? ''));
                if ($v === '') continue;
                $this->registerLocationCode($v);
                $whNames[$v] = true;
            }
        }
        foreach (array_keys($whNames) as $name) TruckingWarehouse::firstOrCreate(['name' => $name], ['code' => $name]);
    }

    /** Lưu toàn bộ cfg (tương thích cũ) — gọi reconcile cho mọi key có mặt. */
    public function saveConfig(array $cfg): void
    {
        DB::transaction(function () use ($cfg) {
            foreach ($this->lookups() as $key => [$cls, $priced, $coded, $colored]) {
                if (array_key_exists($key, $cfg)) $this->reconcileLookup($cls, $priced, $coded, $colored, $cfg, $key);
            }
            if (array_key_exists('customers', $cfg)) $this->reconcileCustomers($cfg);
            if (array_key_exists('vehicles', $cfg))  $this->reconcileVehicles($cfg);
            $this->reconcileSettings($cfg);
        });
    }

    /** Lưu RIÊNG 1 danh mục lookup (mỗi tab Cài đặt = 1 bảng). */
    public function saveCatalog(string $cfgKey, array $cfg): void
    {
        $lk = $this->lookups();
        if (! isset($lk[$cfgKey])) {
            throw new \InvalidArgumentException("Danh mục không hợp lệ: {$cfgKey}");
        }
        [$cls, $priced, $coded, $colored] = $lk[$cfgKey];
        DB::transaction(fn () => $this->reconcileLookup($cls, $priced, $coded, $colored, $cfg, $cfgKey));
    }

    public function saveCustomers(array $cfg): void { DB::transaction(fn () => $this->reconcileCustomers($cfg)); }

    /** Đổi TÊN khách hàng (update theo id — giữ nguyên liên kết lô hàng & bảng giá). */
    public function renameCustomer(string $old, string $new): array
    {
        // Chuẩn hóa input theo same rule với observer (trim + collapse whitespace) → check trùng
        // không miss khi user gõ "Cty  X" (double space) mà DB lưu "Cty X".
        $collapse = fn ($s) => preg_replace('/\s+/u', ' ', trim((string) $s)) ?? '';
        $old = $collapse($old); $new = $collapse($new);
        if ($new === '') return ['ok' => false, 'message' => 'Tên mới không được trống'];
        $cust = TruckingCustomer::where('name', $old)->first();
        if (! $cust) return ['ok' => false, 'message' => 'Không tìm thấy khách hàng'];
        // LOẠI TRỪ chính khách đang sửa (theo id): MySQL collation không phân biệt hoa/thường nên
        // đổi mỗi kiểu hoa/thường (CANON QUẾ VÕ → Canon Quế Võ) where('name',$new) sẽ khớp lại chính
        // nó → báo trùng nhầm. Chỉ chặn khi trùng với KHÁCH KHÁC.
        if (TruckingCustomer::where('name', $new)->where('id', '!=', $cust->id)->exists()) {
            return ['ok' => false, 'message' => 'Tên khách hàng đã tồn tại'];
        }
        $cust->update(['name' => $new]);
        return ['ok' => true];
    }
    public function saveVehicles(array $cfg): void  { DB::transaction(fn () => $this->reconcileVehicles($cfg)); }
    public function saveSettings(array $cfg): void  { DB::transaction(fn () => $this->reconcileSettings($cfg)); }

    /** @return string[] danh sách cfgKey lookup hợp lệ (cho validate route). */
    public function catalogKeys(): array { return array_keys($this->lookups()); }

    // --- reconcile từng bảng (dùng chung cho saveConfig & endpoint riêng) ---
    private function reconcileLookup(string $cls, bool $priced, $coded, bool $colored, array $cfg, string $key): void
    {
        // Danh mục CÓ MÃ (địa điểm/kho): định danh theo MÃ (ký hiệu) — TÊN được phép trùng.
        // Khớp & cập nhật theo mã để GIỮ id (không đứt link price_rows.location_id); mã rỗng → khớp theo tên.
        if ($coded) {
            $rawNames = $cfg[$key] ?? [];
            $codeArr  = $cfg[$coded . 'Arr'] ?? null;        // mảng mã theo chỉ số dòng (mô hình mới)
            $idArr    = $cfg[$key . 'IdArr'] ?? null;        // mảng id theo chỉ số dòng → KHỚP THEO ID để giữ id khi đổi mã
            $nameMap  = $cfg[$coded] ?? [];                  // map tên→mã (tương thích bản cũ)
            $addrArr  = $key === 'warehouses' ? ($cfg['warehouseAddrArr'] ?? null) : null;   // Kho có thêm Địa chỉ
            $geoArr   = $key === 'warehouses' ? ($cfg['warehouseGeoArr'] ?? null) : null;    // Kho có thêm Tọa độ "lat,lng"
            $keepIds = [];
            $sort = 0;
            foreach ($rawNames as $i => $rawName) {
                $name = trim((string) $rawName);
                if ($name === '') continue;
                $code = $codeArr !== null ? trim((string) ($codeArr[$i] ?? '')) : trim((string) ($nameMap[$name] ?? ''));
                $attrs = ['name' => $name, 'code' => ($code !== '' ? $code : null), 'sort' => $sort];
                if ($addrArr !== null) $attrs['address'] = (trim((string) ($addrArr[$i] ?? '')) ?: null);
                if ($geoArr !== null) { [$lat, $lng] = $this->parseLatLng($geoArr[$i] ?? ''); $attrs['lat'] = $lat; $attrs['lng'] = $lng; }
                // Ưu tiên KHỚP THEO ID (dòng đã có sẵn) → cho phép SỬA mã mà không đứt link.
                // Có idArr (payload mới, authoritative): id rỗng = dòng MỚI → LUÔN tạo mới, KHÔNG gộp theo mã
                // → cho phép NHIỀU TÊN dùng chung 1 ký hiệu (vd: Địa điểm). Không có idArr (payload cũ):
                // khớp theo mã rồi tên (mã trống) để giữ link như trước.
                $id  = $idArr !== null ? ($idArr[$i] ?? null) : null;
                $row = is_numeric($id) ? $cls::find($id) : null;
                if (! $row && $idArr === null) {
                    $row = $code !== ''
                        ? $cls::where('code', $code)->first()
                        : ($cls::where('name', $name)->whereRaw("COALESCE(code,'') = ''")->first() ?? null);
                }
                if ($row) {
                    $row->update($attrs);
                } else {
                    $row = $cls::create($attrs);
                }
                $keepIds[] = $row->id;
                $sort++;
            }
            $cls::whereNotIn('id', $keepIds ?: [0])->delete();
            return;
        }

        // Danh mục KHÔNG mã: định danh theo TÊN (như cũ).
        $names = array_values(array_filter(array_map('trim', $cfg[$key] ?? []), fn ($v) => $v !== ''));
        $cls::whereNotIn('name', $names ?: [''])->delete();
        foreach ($names as $i => $name) {
            $attrs = ['sort' => $i];
            if ($priced)  $attrs['default_price'] = isset($cfg['prices'][$name]) ? $this->inMoney($cfg['prices'][$name]) : null;
            // Chỉ ghi đè color/auto/vat khi payload CÓ gửi (thêm nhanh từ popup không gửi → GIỮ nguyên, không xoá).
            if ($colored && array_key_exists('costColors', $cfg)) $attrs['color'] = $cfg['costColors'][$name] ?? null;
            if ($colored && array_key_exists('costAuto', $cfg))   $attrs['auto']  = ! empty($cfg['costAuto'][$name]);
            if ($colored && array_key_exists('costVat', $cfg))    $attrs['vat']   = isset($cfg['costVat'][$name]) && $cfg['costVat'][$name] !== '' ? (float) $cfg['costVat'][$name] : null;
            $cls::updateOrCreate(['name' => $name], $attrs);
        }
    }

    /** Parse "lat,lng" (hoặc "lat lng") → [float,float] hoặc [null,null] nếu rỗng/sai/ngoài phạm vi. */
    private function parseLatLng($raw): array
    {
        $s = trim((string) $raw);
        if ($s === '') return [null, null];
        if (! preg_match('/(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)/', $s, $m)) return [null, null];
        $lat = (float) $m[1]; $lng = (float) $m[2];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return [null, null];
        return [$lat, $lng];
    }

    private function reconcileCustomers(array $cfg): void
    {
        // Collapse whitespace để khớp với observer (DB lưu "Cty X"); nếu chỉ trim, payload "Cty  X"
        // sẽ KHÔNG match DB → whereNotIn xóa nhầm khách + cascade. Backfill migration đã chuẩn hóa DB,
        // ở đây chuẩn hóa thêm payload là chốt chặn cuối.
        $collapse = fn ($v) => preg_replace('/\s+/u', ' ', trim((string) $v)) ?? '';
        $names = array_values(array_filter(array_map($collapse, $cfg['customers'] ?? []), fn ($v) => $v !== ''));
        TruckingCustomer::whereNotIn('name', $names ?: [''])->delete();
        // Re-key customerInfo theo tên đã collapse — tránh miss contact/priceList khi key payload còn raw.
        $info = [];
        foreach (($cfg['customerInfo'] ?? []) as $k => $v) $info[$collapse($k)] = $v;
        foreach ($names as $name) {
            $d = $info[$name] ?? [];
            $cust = TruckingCustomer::updateOrCreate(['name' => $name], [
                'short_name' => $d['shortName'] ?? null,
                'tax_code'   => $d['taxCode'] ?? null,
                'phone'      => $d['phone'] ?? null,
                'contact'    => $d['contact'] ?? null,
                'email'      => $d['email'] ?? null,
                'term_days'  => isset($d['termDays']) && $d['termDays'] !== '' ? (int) $d['termDays'] : null,
                'address'    => $d['address'] ?? null,
                'note'       => $d['note'] ?? null,
            ]);
            // GHI CHÚ: bảng giá KHÔNG còn lưu ở đây. Bảng giá theo BOOK (khoảng ngày) → lưu qua endpoint
            // riêng (savePriceBookRows) để không xóa nhầm các book khác. /customers chỉ lưu thông tin khách.
        }
    }

    /**
     * Chuẩn hóa biển kiểm soát: viết hoa, bỏ dấu chấm, tự chèn gạch nếu thiếu (29E72123 → 29E-72123).
     * Trả chuỗi đã chuẩn hóa; null nếu hoàn toàn rỗng.
     */
    public function normalizePlate(string $raw): ?string
    {
        $p = mb_strtoupper(preg_replace('/[.\s]+/u', '', trim($raw)));
        // Tự chèn gạch ngang: 29E72123 → 29E-72123
        if ($p !== '' && ! str_contains($p, '-') && preg_match('/^(\d{2}[A-Z]{1,2})(\d+)$/', $p, $m)) {
            $p = $m[1] . '-' . $m[2];
        }
        return $p !== '' ? $p : null;
    }

    private function reconcileVehicles(array $cfg): void
    {
        $raw = array_values(array_filter(array_map('trim', $cfg['vehicles'] ?? []), fn ($v) => $v !== ''));
        // Chuẩn hóa (viết hoa, bỏ chấm, tự chèn gạch)
        $plates = [];
        foreach ($raw as $r) { $p = $this->normalizePlate($r); if ($p && ! in_array($p, $plates, true)) $plates[] = $p; }

        // Phát hiện ĐỔI BIỂN SỐ: so biển cũ/mới bằng dạng chuẩn hóa (bỏ gạch/cách) — nếu khớp
        // nhưng viết khác → RENAME (giữ vehicle_id) + CẬP NHẬT plate ở Lô hàng + route_pays.
        $normP  = fn ($v) => preg_replace('/[\s\-.,]/u', '', mb_strtoupper(trim((string) $v)));
        $newByN = []; foreach ($plates as $p) $newByN[$normP($p)] = $p;

        // GỘP XE TRÙNG sẵn có trong DB: nhiều xe cùng DẠNG CHUẨN HÓA (vd "29E72123" và "29E-72123")
        // → giữ 1 xe (id nhỏ nhất), repoint Lô hàng + route_pays sang xe giữ, xóa xe thừa. Phải gộp
        // TRƯỚC khi đổi format, nếu không đổi format xe này sẽ đụng unique 'plate' của xe trùng kia.
        $byNorm = [];   // normKey => [vehicle...] (sắp theo id)
        foreach (TruckingVehicle::orderBy('id')->get() as $v) $byNorm[$normP($v->plate)][] = $v;
        $survivors = [];   // normKey => xe giữ lại
        foreach ($byNorm as $nk => $list) {
            $keep = $list[0];
            for ($i = 1; $i < count($list); $i++) {
                $dup = $list[$i];
                TruckingShipment::where('vehicle_id', $dup->id)->update(['vehicle_id' => $keep->id]);
                TruckingShipment::where('bks_vao', $dup->plate)->update(['bks_vao' => $keep->plate]);
                TruckingShipment::where('bks_ra', $dup->plate)->update(['bks_ra' => $keep->plate]);
                \App\Models\TruckingRoutePay::where('vehicle_id', $dup->id)->update(['vehicle_id' => $keep->id, 'bks' => $keep->plate]);
                $dup->delete();
            }
            $survivors[$nk] = $keep;
        }

        $matchedIds = [];   // id xe đã match
        foreach ($survivors as $n => $old) {
            if (isset($newByN[$n])) {
                $newPlate = $newByN[$n];
                if ($old->plate !== $newPlate) {
                    // plate format đổi → propagate ra Lô hàng + route_pays (an toàn: mỗi normKey chỉ còn 1 xe)
                    TruckingShipment::where('vehicle_id', $old->id)->where('bks_vao', $old->plate)->update(['bks_vao' => $newPlate]);
                    TruckingShipment::where('bks_ra', $old->plate)->update(['bks_ra' => $newPlate]);
                    \App\Models\TruckingRoutePay::where('vehicle_id', $old->id)->update(['bks' => $newPlate]);
                    $old->update(['plate' => $newPlate]);
                }
                $matchedIds[] = $old->id;
                unset($newByN[$n]);
            }
        }
        // Xóa xe KHÔNG match (biển xóa hẳn)
        TruckingVehicle::whereNotIn('id', $matchedIds ?: [0])->whereNotIn('plate', $plates ?: [''])->delete();

        // Tạo / cập nhật attrs (type/axle/gps) — updateOrCreate theo plate (giờ plate đã đồng bộ)
        $usedGps = [];
        foreach ($plates as $plate) {
            // Lookup attrs by current plate HOẶC plate gốc (trước khi chuẩn hóa) — vì frontend gửi key cũ
            $lookupKeys = [$plate, str_replace('-', '', $plate)];
            $first = fn ($map) => collect($lookupKeys)->map(fn ($k) => $map[$k] ?? null)->filter()->first();
            $type = $first($cfg['vehicleType'] ?? []) ?? 'MBF';
            $axle = $type === 'MBF' ? ($first($cfg['vehicleAxle'] ?? []) ?: null) : null;
            $attrs = ['type' => $type, 'axle' => $axle];
            if (array_key_exists('vehicleGps', $cfg)) {
                $ref = $type === 'MBF' ? ($first($cfg['vehicleGps'] ?? []) ?: null) : null;
                if ($ref !== null && isset($usedGps[$ref])) $ref = null;
                if ($ref !== null) $usedGps[$ref] = $plate;
                $attrs['gps_ref'] = $ref;
            }
            TruckingVehicle::updateOrCreate(['plate' => $plate], $attrs);
        }
    }

    private function reconcileSettings(array $cfg): void
    {
        if (isset($cfg['vatDefault']['hph'])) TruckingSetting::put('vat_default_hph', $cfg['vatDefault']['hph']);
        if (isset($cfg['vatDefault']['icd'])) TruckingSetting::put('vat_default_icd', $cfg['vatDefault']['icd']);
        if (array_key_exists('freeTimeHours', $cfg)) TruckingSetting::put('free_time_hours', $cfg['freeTimeHours']);
        if (array_key_exists('freeTimeRules', $cfg)) {   // quy tắc ngưỡng theo khoảng ngày → lưu JSON
            $rules = [];
            foreach ((is_array($cfg['freeTimeRules']) ? $cfg['freeTimeRules'] : []) as $r) {
                $from = trim((string) ($r['from'] ?? ''));
                if ($from === '') continue;   // bắt buộc "từ ngày"
                $rules[] = [
                    'from'  => $from,
                    'to'    => trim((string) ($r['to'] ?? '')) ?: null,
                    'hours' => (isset($r['hours']) && $r['hours'] !== '' && $r['hours'] !== null) ? (float) $r['hours'] : null,
                    'note'  => trim((string) ($r['note'] ?? '')),
                ];
            }
            TruckingSetting::put('free_time_rules', json_encode($rules, JSON_UNESCAPED_UNICODE));
        }
        if (array_key_exists('dueWarnDays', $cfg)) {
            $d = (int) preg_replace('/[^\d]/', '', (string) $cfg['dueWarnDays']);
            TruckingSetting::put('due_warn_days', (string) ($d > 0 ? $d : 30));
        }
    }

    /** Map lower(name|code) ⇒ tên địa điểm chuẩn (để validate + chuẩn hóa NƠI LẤY/HẠ). */
    private function locationNameMap(): array
    {
        $map = [];
        foreach (TruckingLocation::get(['name', 'code']) as $l) {
            if ($l->name) $map[mb_strtolower(trim($l->name))] = $l->name;
            if ($l->code) $map[mb_strtolower(trim($l->code))] = $l->name ?: $l->code;
        }
        return $map;
    }

    /**
     * Map lower(name|code) ⇒ KÝ HIỆU kho chuẩn (để validate + chuẩn hóa cột KHO).
     * Tuyến kho dùng ký hiệu (phí xe khớp theo `routeKey` không phân biệt hoa thường),
     * nên chuẩn hóa mọi đoạn về ký hiệu (kho không có ký hiệu thì giữ tên).
     */
    private function warehouseCodeMap(): array
    {
        $map = [];
        foreach (TruckingWarehouse::get(['name', 'code']) as $w) {
            $canon = $w->code ?: $w->name;
            if ($w->name) $map[mb_strtolower(trim($w->name))] = $canon;
            if ($w->code) $map[mb_strtolower(trim($w->code))] = $canon;
        }
        return $map;
    }

    /** Tách 1 chuỗi tuyến kho thành các đoạn (cùng dấu phân tách với routeKey/khoRouteDisplay). */
    private function khoSegments(string $kho): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*(?:,|→|->|–|—|\s-\s)\s*/u', trim($kho)) ?: []), fn ($s) => $s !== ''));
    }

    /**
     * Kiểm tra TRƯỚC toàn bộ dòng import (không ghi DB). Trả danh sách lỗi rõ
     * ràng theo từng dòng/booking. Quy tắc BẮT BUỘC: khách hàng + SỐ BOOKING
     * + SỐ LƯỢNG CONT. Nơi lấy/hạ KHÔNG bắt buộc — nhưng nếu có nhập mà sai
     * danh mục địa điểm thì vẫn báo (tránh sai chính tả lọt vào).
     */
    public function validateShipmentRows(array $rows): array
    {
        // toBase() để bỏ hydrate Eloquent — chỉ cần list tên cho dry-run; collapse whitespace y rule observer
        // để khớp được cả khi user paste "Cty  ABC" (double space) trên Excel.
        $custSet = array_flip(array_map(
            fn ($n) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $n)) ?? ''),
            TruckingCustomer::toBase()->pluck('name')->all()
        ));
        $locMap = $this->locationNameMap();
        $whMap = $this->warehouseCodeMap();
        $errors = [];

        foreach ($rows as $i => $row) {
            $reasons = [];
            $name = trim((string) ($row['customer'] ?? ''));
            $nameKey = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? '');
            if ($name === '')                                     $reasons[] = 'Thiếu khách hàng';
            elseif (! isset($custSet[$nameKey]))                  $reasons[] = "Khách hàng “{$name}” chưa có trong hệ thống";

            // Số booking/bill — bắt buộc
            $booking = trim((string) ($row['booking'] ?? ''));
            if ($booking === '')                                  $reasons[] = 'Thiếu số booking/bill';

            // Số lượng cont — bắt buộc, phải là số ≥ 1
            $qtyRaw = trim((string) ($row['qtyRaw'] ?? $row['qty'] ?? ''));
            $qd = preg_replace('/[^\d]/', '', (string) ($row['qty'] ?? $qtyRaw));
            if ($qtyRaw === '' && $qd === '')                     $reasons[] = 'Thiếu số lượng cont';
            elseif ($qd === '')                                   $reasons[] = "Số lượng cont “{$qtyRaw}” không phải số";
            elseif ((int) $qd < 1)                                $reasons[] = 'Số lượng cont phải ≥ 1';

            // Nơi lấy/hạ — KHÔNG bắt buộc; chỉ kiểm tra khi có nhập
            $from = trim((string) ($row['from'] ?? ''));
            if ($from !== '' && ! isset($locMap[mb_strtolower($from)])) $reasons[] = "Nơi lấy “{$from}” chưa có trong danh mục địa điểm";

            $to = trim((string) ($row['to'] ?? ''));
            if ($to !== '' && ! isset($locMap[mb_strtolower($to)]))     $reasons[] = "Nơi hạ “{$to}” chưa có trong danh mục địa điểm";

            // Nơi hạ sà lan — KHÔNG bắt buộc; nếu có thì CHỈ nhận HPP hoặc LHP (cảng hạ sà lan).
            $bargeDrop = strtoupper(trim((string) ($row['bargeDrop'] ?? '')));
            if ($bargeDrop !== '' && ! in_array($bargeDrop, ['HPP', 'LHP'], true))
                $reasons[] = "Nơi hạ sà lan “{$bargeDrop}” không hợp lệ (chỉ nhận HPP hoặc LHP)";

            // KHO — KHÔNG bắt buộc; tuyến nhiều đoạn → kiểm tra TỪNG đoạn theo danh mục Kho (tên hoặc ký hiệu)
            $kho = trim((string) ($row['kho'] ?? ''));
            if ($kho !== '') foreach ($this->khoSegments($kho) as $seg) {
                if (! isset($whMap[mb_strtolower($seg)])) $reasons[] = "Kho “{$seg}” chưa có trong danh mục kho";
            }

            // NHẬP/XUẤT — nhận MỌI cách viết (hoa/thường, có/không dấu, NFC/NFD); detect tiền tố ASCII nh/xu
            $io = mb_strtolower(trim((string) ($row['io'] ?? '')));
            if ($io !== '' && ! (str_starts_with($io, 'nh') || str_starts_with($io, 'xu') || str_starts_with($io, 'kh') || str_contains($io, 'import') || str_contains($io, 'export') || str_contains($io, 'other')))
                $reasons[] = 'NHẬP/XUẤT “' . trim((string) $row['io']) . '” không hợp lệ (chỉ nhận Nhập hoặc Xuất)';

            // Ngày / giờ đến dự kiến + cắt máng — nếu có nhập phải đúng định dạng
            $ngayRaw = trim((string) ($row['ngayRaw'] ?? ''));
            if ($ngayRaw !== '' && ! $this->isValidDateStr($ngayRaw))  $reasons[] = "Ngày đến dự kiến “{$ngayRaw}” sai định dạng (cần dd/mm/yyyy)";

            $gioRaw = trim((string) ($row['gioRaw'] ?? ''));
            if ($gioRaw !== '' && ! $this->isValidTimeStr($gioRaw))    $reasons[] = "Giờ đến dự kiến “{$gioRaw}” sai định dạng (cần HH:MM)";

            $cutRaw = trim((string) ($row['cutOffRaw'] ?? ''));
            if ($cutRaw !== '' && ! $this->isValidDateStr($cutRaw))    $reasons[] = "Cắt máng “{$cutRaw}” sai định dạng (cần dd/mm/yyyy [HH:MM])";

            if ($reasons) {
                $errors[] = ['line' => $i + 1, 'customer' => $name, 'booking' => (string) ($row['booking'] ?? ''), 'reasons' => $reasons];
            }
        }
        return $errors;
    }

    /** Có chứa NGÀY hợp lệ (dd/mm/yyyy, chấp nhận - . / và năm 2 số) không. */
    private function isValidDateStr(string $s): bool
    {
        // Hỗ trợ dd/mm/yyyy (file mẫu) VÀ yyyy-mm-dd (ISO từ frontend).
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $s, $m)) {
            $y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
        } elseif (preg_match('#(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})#', $s, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if ($y < 100) $y += 2000;
        } else {
            return false;
        }
        // Năm ngoài 2000–2099 gần chắc sai (serial Excel, lỗi gõ) → từ chối.
        if ($y < 2000 || $y > 2099) return false;
        return checkdate($mo, $d, $y);
    }

    /** Có chứa GIỜ hợp lệ (HH:MM, 00–23 : 00–59) không. */
    private function isValidTimeStr(string $s): bool
    {
        if (! preg_match('/(\d{1,2}):(\d{2})/', $s, $m)) return false;
        return (int) $m[1] <= 23 && (int) $m[2] <= 59;
    }

    /** Dry-run: chỉ kiểm tra, không import. */
    public function validateShipments(array $rows): array
    {
        $errors = $this->validateShipmentRows($rows);
        return ['valid' => empty($errors), 'total' => count($rows), 'errors' => $errors];
    }

    /**
     * Import lô hàng — ALL-OR-NOTHING: chỉ cần 1 dòng lỗi là KHÔNG import gì cả.
     * NƠI LẤY/HẠ được chuẩn hóa về tên địa điểm chuẩn.
     */
    public function importShipments(string $sheet, array $rows): array
    {
        $errors = $this->validateShipmentRows($rows);
        if ($errors) {
            return ['valid' => false, 'created' => 0, 'ships' => [], 'errors' => $errors, 'total' => count($rows)];
        }

        $vat = (string) TruckingSetting::get($sheet === 'hph' ? 'vat_default_hph' : 'vat_default_icd', '0');
        $locMap = $this->locationNameMap();
        $norm = fn ($v) => $locMap[mb_strtolower(trim((string) $v))] ?? $v;
        // Chuẩn hóa cột KHO: mỗi đoạn về ký hiệu chuẩn của danh mục Kho, nối " → " (giữ tuyến).
        $whMap = $this->warehouseCodeMap();
        $normKho = function ($v) use ($whMap) {
            $segs = $this->khoSegments((string) $v);
            if (! $segs) return $this->str($v);
            return implode(' → ', array_map(fn ($s) => $whMap[mb_strtolower($s)] ?? $s, $segs));
        };

        return DB::transaction(function () use ($sheet, $rows, $vat, $norm, $normKho) {
            $ships = [];
            foreach ($rows as $row) {
                $base = [
                    'customer'     => $row['customer'] ?? null,
                    'booking'      => $row['booking'] ?? null,
                    'inv'          => $row['inv'] ?? null,
                    'io'           => $row['io'] ?? null,
                    'qty'          => 1,
                    'contType'     => $row['contType'] ?? null,
                    'cutOff'       => $row['cutOff'] ?? null,
                    'from'         => $norm($row['from'] ?? null),
                    'to'           => $norm($row['to'] ?? null),
                    'bargeDrop'    => strtoupper(trim((string) ($row['bargeDrop'] ?? ''))) ?: null,   // HPP/LHP → đi sà lan
                    'kho'          => $normKho($row['kho'] ?? null),
                    'gioDenDuKien' => $row['gioDenDuKien'] ?? null,
                    'cost'         => ['items' => []],
                    'rev'          => ['vatRate' => $vat, 'doanhThu' => [], 'choHo' => [], 'payments' => []],
                ];
                // Ưu tiên 1: Tên container có nhiều số (mỗi dòng 1 số) → tách mỗi số 1 lô
                $conts = array_values(array_filter(array_map('trim', preg_split('/[\r\n;,]+/', (string) ($row['contNo'] ?? ''))), fn ($v) => $v !== ''));
                if (count($conts)) {
                    foreach ($conts as $cn) {
                        $ships[] = $this->shipmentToArray($this->saveShipment($base + ['contNo' => $cn], $sheet));
                    }
                    continue;
                }
                // Ưu tiên 2: cont trống → nhân bản theo SỐ LƯỢNG (để điền số cont sau)
                $qd = preg_replace('/[^\d]/', '', (string) ($row['qty'] ?? ''));
                $n = $qd === '' ? 1 : max(1, (int) $qd);
                for ($k = 0; $k < $n; $k++) {
                    $ships[] = $this->shipmentToArray($this->saveShipment($base + ['contNo' => null], $sheet));
                }
            }
            return ['valid' => true, 'created' => count($ships), 'ships' => $ships, 'errors' => [], 'total' => count($rows)];
        });
    }

    // ===================================================================
    // PRICE ROWS (bảng giá) — serialize, persist, import
    // ===================================================================
    /** 1 dòng bảng giá → shape frontend. */
    private function priceRowToArray($p): array
    {
        return [
            'id'         => $p->id,
            'locationId' => $p->location_id,
            'loc'        => $p->loc ?? '',
            'conn'       => $p->conn ?? 'Connect',
            'kind'       => $p->kind ?? '',
            'from'       => $p->from ?? '',
            'to1'        => $p->to1 ?? '',
            'to2'        => $p->to2 ?? '',
            'to3'        => $p->to3 ?? '',
            'to4'        => $p->to4 ?? '',
            'distance'   => $p->distance ?? '',
            'transFee40' => $this->outMoney($p->trans_fee_40),
            'transFee20' => $this->outMoney($p->trans_fee_20),
            'fuelFee40'  => $this->outMoney($p->fuel_fee_40),
            'fuelFee20'  => $this->outMoney($p->fuel_fee_20),
        ];
    }

    /** Thuộc tính DB của 1 dòng bảng giá (dùng cho cả lưu tay & import). */
    private function priceRowAttrs(array $p, int $i): array
    {
        return [
            'location_id'  => $this->resolveLocationId($p['loc'] ?? null),
            'loc'          => $this->str($p['loc'] ?? null),
            'conn'         => $p['conn'] ?? 'Connect',
            'kind'         => $this->str($p['kind'] ?? null),
            'from'         => $this->str($p['from'] ?? null),
            'to1'          => $this->str($p['to1'] ?? null),
            'to2'          => $this->str($p['to2'] ?? null),
            'to3'          => $this->str($p['to3'] ?? null),
            'to4'          => $this->str($p['to4'] ?? null),
            'distance'     => $this->str($p['distance'] ?? null),
            'trans_fee_40' => $this->inMoney($p['transFee40'] ?? null),
            'trans_fee_20' => $this->inMoney($p['transFee20'] ?? null),
            'fuel_fee_40'  => $this->inMoney($p['fuelFee40'] ?? null),
            'fuel_fee_20'  => $this->inMoney($p['fuelFee20'] ?? null),
            'sort'         => $i,
        ];
    }

    /**
     * Bảng [code|name → id] toàn danh mục Địa điểm, memoize / request. Dùng cho mọi lệnh
     * resolveLocationId trong cùng request (tránh N SELECT khi recompute / import nhiều lô).
     * Cache bị reset khi registerLocationCode thực sự tạo / cập nhật bản ghi.
     */
    private function locationIdMap(): array
    {
        if ($this->locIdMapCache !== null) return $this->locIdMapCache;
        $m = [];
        foreach (TruckingLocation::toBase()->get(['id', 'name', 'code']) as $l) {
            $c = trim((string) $l->code);
            $n = trim((string) $l->name);
            if ($c !== '') $m[$c] = (int) $l->id;
            if ($n !== '' && ! isset($m[$n])) $m[$n] = (int) $l->id;
        }
        return $this->locIdMapCache = $m;
    }

    /**
     * "Điểm Hạ" → location_id: CHỈ khớp địa điểm đã có (theo code rồi name),
     * KHÔNG tự tạo địa điểm mới từ điểm hạ. Danh mục Địa điểm được nạp từ
     * cột FROM + TO (xem registerLocationCode). Chưa khớp → null.
     */
    private function resolveLocationId(?string $code): ?int
    {
        $code = trim((string) $code);
        if ($code === '') return null;
        return $this->locationIdMap()[$code] ?? null;
    }

    /**
     * Đăng ký 1 KÝ HIỆU (FROM / TO) vào danh mục Địa điểm.
     * Đã có code → giữ; có name trùng nhưng thiếu code → gán code;
     * chưa có → tạo mới (name = code = ký hiệu) để user đặt tên sau.
     * Khi DB thực sự đổi → reset cache id / code / normalized code map (sibling trait).
     */
    private function registerLocationCode(?string $code): void
    {
        $code = trim((string) $code);
        if ($code === '') return;

        $loc = TruckingLocation::where('code', $code)->first()
            ?? TruckingLocation::where('name', $code)->first();

        $dirty = false;
        if (! $loc) {
            TruckingLocation::create(['name' => $code, 'code' => $code]);
            $dirty = true;
        } elseif (! $loc->code) {
            $loc->update(['code' => $code]);
            $dirty = true;
        }
        if ($dirty) {
            $this->locIdMapCache = null;
            if (property_exists($this, 'locCodeCache')) $this->locCodeCache = null;
            if (property_exists($this, 'codeMapCache')) $this->codeMapCache = null;
            if (property_exists($this, 'pricingCtxCache')) $this->pricingCtxCache = [];
        }
    }

    /**
     * Import bảng giá cho 1 khách từ các dòng đã parse (client).
     * Khóa định danh = (conn, loc, kind, from, to1..to4). Trùng → cập nhật
     * khoảng cách + phí; chưa có → tạo mới.
     */
    public function importPriceRows(string $customerName, array $rows, bool $replace = false, ?int $bookId = null): array
    {
        return DB::transaction(function () use ($customerName, $rows, $replace, $bookId) {
            $cust = TruckingCustomer::firstOrCreate(['name' => trim($customerName)]);
            $bookId = $bookId ?: $this->defaultBookId((int) $cust->id);   // không chỉ book → book mở mặc định
            if ($replace) TruckingPriceRow::where('price_book_id', $bookId)->delete();
            $created = 0; $updated = 0;
            $sort = (int) (TruckingPriceRow::where('price_book_id', $bookId)->max('sort') ?? 0);

            foreach ($rows as $p) {
                $attrs = $this->priceRowAttrs($p, 0);
                $q = TruckingPriceRow::where('price_book_id', $bookId);
                foreach (['conn', 'loc', 'kind', 'from', 'to1', 'to2', 'to3', 'to4'] as $k) {
                    $v = $attrs[$k];
                    $v === null ? $q->whereNull($k) : $q->where($k, $v);
                }
                $existing = $q->first();

                if ($existing) {
                    $existing->update([
                        'location_id'  => $attrs['location_id'],
                        'distance'     => $attrs['distance'],
                        'trans_fee_40' => $attrs['trans_fee_40'],
                        'trans_fee_20' => $attrs['trans_fee_20'],
                        'fuel_fee_40'  => $attrs['fuel_fee_40'],
                        'fuel_fee_20'  => $attrs['fuel_fee_20'],
                    ]);
                    $updated++;
                } else {
                    $attrs['sort'] = ++$sort;
                    TruckingPriceRow::create($attrs + ['customer_id' => $cust->id, 'price_book_id' => $bookId]);
                    $created++;
                }
            }

            $this->registerPriceRowCodes($rows);
            return [
                'created'   => $created,
                'updated'   => $updated,
                'imported'  => $created + $updated,
                'priceList' => $this->priceBookRows($bookId),
            ];
        });
    }

    /**
     * Copy TOÀN BỘ dòng giá từ BOOK NGUỒN sang BOOK ĐÍCH (nhân bản dòng, id mới) — cho nhanh.
     * $replace=true: xóa dòng hiện có của book đích trước; false: chèn thêm (gộp).
     */
    public function copyPriceRows(int $fromBookId, int $toBookId, bool $replace = false): array
    {
        $toBook = TruckingPriceBook::find($toBookId);
        if (! $fromBookId || ! $toBook || $fromBookId === $toBookId) {
            return ['copied' => 0, 'priceList' => $toBook ? $this->priceBookRows($toBookId) : []];
        }

        return DB::transaction(function () use ($fromBookId, $toBook, $replace) {
            if ($replace) TruckingPriceRow::where('price_book_id', $toBook->id)->delete();

            $cols = ['location_id', 'loc', 'conn', 'kind', 'from', 'to1', 'to2', 'to3', 'to4',
                     'distance', 'trans_fee_40', 'trans_fee_20', 'fuel_fee_40', 'fuel_fee_20', 'sort'];
            $now  = now();
            $rows = TruckingPriceRow::where('price_book_id', $fromBookId)->orderBy('sort')->orderBy('id')->get()
                ->map(function ($r) use ($cols, $toBook, $now) {
                    $a = ['customer_id' => $toBook->customer_id, 'price_book_id' => $toBook->id, 'created_at' => $now, 'updated_at' => $now];
                    foreach ($cols as $c) $a[$c] = $r->$c;
                    return $a;
                })->all();
            foreach (array_chunk($rows, 500) as $chunk) TruckingPriceRow::insert($chunk);

            return ['copied' => count($rows), 'priceList' => $this->priceBookRows((int) $toBook->id)];
        });
    }

    /**
     * Đọc BÁO GIÁ GỐC (sheet "import" — layout báo giá nhiều mục) → mảng dòng giá phẳng.
     *  - Mục "2. DRAYAGE BY TRIP": Loại = Connect/Disconnect (theo tiêu đề 2.x.1/2.x.2),
     *    Điểm Hạ = cảng (2.x: HAI PHONG/LACH HUYEN/ICD QV/ICD TP), KIND=C, FROM=D, TO1..4=E..H,
     *    Distance=I, Transport fee 40/20 = J/K, Fuel fee 40/20 = L/M.
     *  - Mục "3. BARGING BY TRIP" (3.1 DRY / 3.2 NOR): Loại=Non, KIND=DRY/NOR CONTAINER,
     *    FROM=D, Điểm Hạ = điểm đến (TO cuối, thường H = HPP/LHP).
     * Trả [] nếu không có sheet "import".
     */
    public function parseQuotationRows(string $path, ?string $sheet = null): array
    {
        $rd = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $rd->setReadDataOnly(true);
        $ss = $rd->load($path);
        $sh = null;
        if ($sheet !== null && $sheet !== '') {
            $sh = $ss->getSheetByName($sheet);   // sheet do user chọn
        } else {
            foreach ($ss->getSheetNames() as $n) if (mb_strtolower(trim($n)) === 'import') { $sh = $ss->getSheetByName($n); break; }
        }
        if (! $sh) return [];
        $rows = $sh->toArray(null, true, false, false);
        $num = fn ($v) => (int) preg_replace('/[^\d]/', '', (string) $v);
        // GỘP khoảng trắng + xuống dòng (ô KIND/FROM/TO trong file gốc hay có \n do wrap) → chuẩn để khớp giá.
        $cl = fn ($r, $i) => preg_replace('/\s+/u', ' ', trim((string) ($r[$i] ?? '')));
        $mode = null; $loc = null; $conn = null; $kind = null; $lastKind = ''; $out = [];
        // tiêu đề khối GHI CHÚ (không phải tuyến) — gặp thì DỪNG bắt tuyến tới mục kế tiếp.
        $isNote = fn ($c) => (bool) preg_match('/transportation fee for|combination|comeback|detention|cancel|other fee|free time/i', $c);
        foreach ($rows as $r) {
            $B = $cl($r, 1); $C = $cl($r, 2);
            if (preg_match('/^1\./', $B)) { $mode = 'skip'; continue; }
            if (preg_match('/^2\.$/', $B)) { $mode = 'drayage'; $loc = null; $conn = null; $lastKind = ''; continue; }
            if ($mode !== null && preg_match('/^2\.\d+\.?$/', $B)) {   // mục cảng (2.x)
                $loc = (stripos($C, 'PORT') !== false || stripos($C, 'ICD') !== false) ? trim(preg_replace('/\bPORT\b/i', '', $C)) : null;   // 2.5 DETENTION… → bỏ
                $conn = null; $lastKind = '';
                continue;
            }
            if (preg_match('/^2\.\d+\.\d+/', $B)) { $conn = stripos($C, 'DISCONNECT') !== false ? 'Disconnect' : (stripos($C, 'CONNECT') !== false ? 'Connect' : $conn); $lastKind = ''; continue; }
            if (preg_match('/^3\.$/', $B)) { $mode = 'barging'; $kind = null; continue; }
            if (preg_match('/^3\.1/', $B)) { $mode = 'barging'; $kind = 'DRY CONTAINER'; continue; }
            if (preg_match('/^3\.2/', $B)) { $mode = 'barging'; $kind = 'NOR CONTAINER'; continue; }
            if (preg_match('/^4\./', $B)) { $mode = 'skip'; continue; }
            // Khối ghi chú trong 1 mục cảng (vd "TRANSPORTATION FEE FOR COMBINATION/COMEBACK") → dừng bắt tuyến.
            if ($mode === 'drayage' && $isNote($C)) { $loc = null; continue; }
            // dòng dữ liệu
            $from = $cl($r, 3);
            if ($from === '' || strtoupper($from) === 'FROM' || strtoupper($C) === 'KIND') continue;
            $t40 = $num($r[9] ?? ''); $t20 = $num($r[10] ?? '');
            if ($t40 <= 0 && $t20 <= 0) continue;
            if ($mode === 'drayage' && $loc && $conn) {
                $k = $C !== '' ? $C : $lastKind; $lastKind = $k;   // KIND là ô GỘP → kéo xuống cho cả nhóm
                $out[] = ['conn' => $conn, 'loc' => $loc, 'kind' => $k, 'from' => $from, 'to1' => $cl($r, 4), 'to2' => $cl($r, 5), 'to3' => $cl($r, 6), 'to4' => $cl($r, 7), 'distance' => $num($r[8] ?? ''), 'transFee40' => $t40, 'transFee20' => $t20, 'fuelFee40' => $num($r[11] ?? ''), 'fuelFee20' => $num($r[12] ?? '')];
            } elseif ($mode === 'barging' && $kind) {
                $drop = $cl($r, 7) ?: ($cl($r, 6) ?: ($cl($r, 5) ?: $cl($r, 4)));
                $out[] = ['conn' => 'Non', 'loc' => $drop, 'kind' => $kind, 'from' => $from, 'to1' => '', 'to2' => '', 'to3' => '', 'to4' => '', 'distance' => $num($r[8] ?? ''), 'transFee40' => $t40, 'transFee20' => $t20, 'fuelFee40' => $num($r[11] ?? ''), 'fuelFee20' => $num($r[12] ?? '')];
            }
        }
        return $out;
    }

    /** Danh sách sheet trong 1 file Excel (cho user chọn). */
    public function quotationSheetNames(string $path): array
    {
        try {
            $rd = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $rd->setReadDataOnly(true);
            return $rd->load($path)->getSheetNames();
        } catch (\Throwable $e) { return []; }
    }

    /**
     * KIỂM TRA (dry-run) báo giá gốc: parse sheet đã chọn → BÁO CÁO (không ghi DB).
     * Trả sheets (để FE chọn), rows (giữ lại để Import không phải parse/upload lại) + tổng hợp.
     */
    public function validateQuotation(string $path, ?string $sheet = null): array
    {
        $sheets = $this->quotationSheetNames($path);
        if (! $sheets) return ['ok' => false, 'sheets' => [], 'msg' => 'Không đọc được file Excel.'];
        // sheet mặc định: ưu tiên 'import', không có thì sheet đầu.
        $pick = $sheet;
        if ($pick === null || $pick === '') {
            foreach ($sheets as $n) if (mb_strtolower(trim($n)) === 'import') { $pick = $n; break; }
            $pick ??= $sheets[0];
        }
        $rows = in_array($pick, $sheets, true) ? $this->parseQuotationRows($path, $pick) : [];
        // Tổng hợp báo cáo
        $by = ['Connect' => 0, 'Disconnect' => 0, 'Non' => 0];
        $kinds = []; $locs = [];
        foreach ($rows as $x) {
            $by[$x['conn']] = ($by[$x['conn']] ?? 0) + 1;
            $k = $x['kind'] !== '' ? $x['kind'] : '(trống)'; $kinds[$k] = ($kinds[$k] ?? 0) + 1;
            if ($x['loc'] !== '') $locs[$x['loc']] = true;
        }
        $warnings = [];
        if (! $rows) $warnings[] = "Sheet '{$pick}' không có dòng giá hợp lệ — chọn đúng sheet báo giá (thường tên 'import').";
        foreach ($rows as $x) { if ($x['loc'] === '' || $x['from'] === '') { $warnings[] = 'Có dòng thiếu Điểm hạ/FROM.'; break; } }
        return [
            'ok'      => count($rows) > 0,
            'sheets'  => array_values($sheets),
            'sheet'   => $pick,
            'total'   => count($rows),
            'by'      => $by,
            'kinds'   => collect($kinds)->map(fn ($v, $k) => ['kind' => $k, 'count' => $v])->values()->all(),
            'locs'    => array_keys($locs),
            'warnings' => array_values(array_unique($warnings)),
            'rows'    => $rows,   // FE giữ để Import (không parse lại)
        ];
    }

    /** Nhập báo giá gốc vào 1 BOOK: parse → thay toàn bộ (replace) hoặc gộp (merge) dòng giá. */
    public function importQuotationToBook(int $bookId, string $path, bool $replace = true, ?string $sheet = null): array
    {
        $book = TruckingPriceBook::find($bookId);
        if (! $book) return ['ok' => false, 'msg' => 'Bảng giá không tồn tại.'];
        $rows = $this->parseQuotationRows($path, $sheet);
        if (! $rows) return ['ok' => false, 'msg' => "Không đọc được dòng giá nào — kiểm tra sheet đúng định dạng báo giá."];
        $cust = TruckingCustomer::find($book->customer_id);
        $res = $replace
            ? $this->savePriceBookRows($bookId, $rows)
            : $this->importPriceRows($cust?->name ?? '', $rows, false, $bookId);
        $by = [];
        foreach ($rows as $x) $by[$x['conn']] = ($by[$x['conn']] ?? 0) + 1;
        return ['ok' => true, 'imported' => count($rows), 'by' => $by, 'priceList' => $res['priceList'] ?? $this->priceBookRows($bookId)];
    }

}
