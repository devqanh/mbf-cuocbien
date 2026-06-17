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
    public function customerPriceListById(int $customerId): array
    {
        if ($customerId <= 0) return [];
        return TruckingPriceRow::where('customer_id', $customerId)->orderBy('sort')
            ->toBase()->get()
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
        $customers = TruckingCustomer::withCount('priceRows')->orderBy('name')->get();

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
            ]])->all(),
        ];
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
            if ($colored) $attrs['color'] = $cfg['costColors'][$name] ?? null;
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
            // Chỉ động vào bảng giá khi payload có 'priceList' (trang Bảng giá);
            // trang Cài đặt > Khách hàng không gửi priceList → giữ nguyên giá.
            if (array_key_exists('priceList', $d)) {
                $cust->priceRows()->delete();
                foreach (($d['priceList'] ?? []) as $i => $p) {
                    $cust->priceRows()->create($this->priceRowAttrs($p, $i));
                }
            }
        }
    }

    private function reconcileVehicles(array $cfg): void
    {
        $plates = array_values(array_filter(array_map('trim', $cfg['vehicles'] ?? []), fn ($v) => $v !== ''));
        TruckingVehicle::whereNotIn('plate', $plates ?: [''])->delete();
        $usedGps = [];   // 1 xe GPS chỉ gán cho 1 xe — giữ xe gán đầu, bỏ gán các xe sau nếu trùng
        foreach ($plates as $plate) {
            $type = $cfg['vehicleType'][$plate] ?? 'MBF';
            // Số cầu chỉ áp dụng cho xe MBF (xe ngoài không cần)
            $axle = $type === 'MBF' ? (($cfg['vehicleAxle'][$plate] ?? null) ?: null) : null;
            $attrs = ['type' => $type, 'axle' => $axle];
            // gps_ref (liên kết xe GPS) chỉ áp xe MBF; chỉ ghi khi payload có gửi 'vehicleGps' (tránh xóa nhầm).
            if (array_key_exists('vehicleGps', $cfg)) {
                $ref = $type === 'MBF' ? (trim((string) ($cfg['vehicleGps'][$plate] ?? '')) ?: null) : null;
                if ($ref !== null && isset($usedGps[$ref])) $ref = null;   // đã gán cho xe khác → bỏ qua
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

            // KHO — KHÔNG bắt buộc; tuyến nhiều đoạn → kiểm tra TỪNG đoạn theo danh mục Kho (tên hoặc ký hiệu)
            $kho = trim((string) ($row['kho'] ?? ''));
            if ($kho !== '') foreach ($this->khoSegments($kho) as $seg) {
                if (! isset($whMap[mb_strtolower($seg)])) $reasons[] = "Kho “{$seg}” chưa có trong danh mục kho";
            }

            // NHẬP/XUẤT — nếu có nhập, chỉ nhận Nhập / Xuất (chuẩn hóa hoa→thường, GIỮ dấu)
            $io = mb_strtolower(trim((string) ($row['io'] ?? '')));
            if ($io !== '' && ! (str_contains($io, 'nhập') || str_contains($io, 'xuất') || str_contains($io, 'import') || str_contains($io, 'export')))
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
        if (! preg_match('#(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})#', $s, $m)) return false;
        [, $d, $mo, $y] = $m;
        if (strlen($y) === 2) $y = '20' . $y;
        return checkdate((int) $mo, (int) $d, (int) $y);
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
    public function importPriceRows(string $customerName, array $rows, bool $replace = false): array
    {
        return DB::transaction(function () use ($customerName, $rows, $replace) {
            $cust = TruckingCustomer::firstOrCreate(['name' => trim($customerName)]);
            if ($replace) $cust->priceRows()->delete();   // xóa sạch bảng giá cũ trước khi nạp lại
            $created = 0; $updated = 0;
            $sort = (int) ($cust->priceRows()->max('sort') ?? 0);

            foreach ($rows as $p) {
                $attrs = $this->priceRowAttrs($p, 0);

                $q = $cust->priceRows();
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
                    $cust->priceRows()->create($attrs);
                    $created++;
                }
            }

            // Ký hiệu FROM + TO → danh mục Địa điểm; đồng thời TO → danh mục Kho.
            // (Điểm Hạ KHÔNG dùng để tạo địa điểm nữa.)
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
            foreach (array_keys($whNames) as $name) {
                TruckingWarehouse::firstOrCreate(['name' => $name], ['code' => $name]);
            }

            $cust->load('priceRows');
            return [
                'created'   => $created,
                'updated'   => $updated,
                'imported'  => $created + $updated,
                'priceList' => $cust->priceRows->map(fn ($p) => $this->priceRowToArray($p))->all(),
            ];
        });
    }

    /**
     * Copy TOÀN BỘ bảng giá từ khách NGUỒN sang khách ĐÍCH (nhân bản dòng, id mới) — cho nhanh.
     * $replace=true: xóa bảng giá hiện có của đích trước; false: chèn thêm (gộp).
     */
    public function copyPriceRows(string $from, string $to, bool $replace = false): array
    {
        $fromId = TruckingCustomer::where('name', trim($from))->value('id');
        $toId   = TruckingCustomer::where('name', trim($to))->value('id');
        if (! $fromId || ! $toId || $fromId === $toId) {
            return ['copied' => 0, 'priceList' => $toId ? $this->customerPriceList($to) : []];
        }

        return DB::transaction(function () use ($fromId, $toId, $to, $replace) {
            if ($replace) TruckingPriceRow::where('customer_id', $toId)->delete();

            $cols = ['location_id', 'loc', 'conn', 'kind', 'from', 'to1', 'to2', 'to3', 'to4',
                     'distance', 'trans_fee_40', 'trans_fee_20', 'fuel_fee_40', 'fuel_fee_20', 'sort'];
            $now  = now();
            $rows = TruckingPriceRow::where('customer_id', $fromId)->orderBy('sort')->orderBy('id')->get()
                ->map(function ($r) use ($cols, $toId, $now) {
                    $a = ['customer_id' => $toId, 'created_at' => $now, 'updated_at' => $now];
                    foreach ($cols as $c) $a[$c] = $r->$c;
                    return $a;
                })->all();
            foreach (array_chunk($rows, 500) as $chunk) TruckingPriceRow::insert($chunk);

            return ['copied' => count($rows), 'priceList' => $this->customerPriceList($to)];
        });
    }

}
