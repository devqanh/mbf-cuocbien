<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingContType;
use App\Models\TruckingExtVendor;
use App\Models\TruckingShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * IMPORT CẬP NHẬT LÔ HÀNG — sửa hàng loạt lô ĐÃ CÓ bằng file Excel (không tạo, không xóa lô).
 * Khác "Import lô" (tạo mới từ bảng kế hoạch) và "Import CSHT" (ghi chi phí theo cont).
 *
 * Nguyên tắc an toàn (user chốt 2026-07-25):
 *  - Khóa khớp: ID lô ưu tiên; thiếu ID mới khớp Số cont và cont phải DUY NHẤT (dữ liệu thật
 *    có cont quay vòng dùng lại) — nhập nhằng là lỗi, chặn cả file.
 *  - Ô TRỐNG = giữ nguyên giá trị cũ. Muốn xóa phải gõ '--' (chống dán thiếu cột làm mất data).
 *  - ALL-OR-NOTHING: 1 dòng lỗi là không ghi gì.
 *  - Ghi qua saveShipment(..., $only) nên chỉ đụng ĐÚNG ô đã đổi và hưởng nguyên chuẩn hóa,
 *    suy is_barge/barge_cont, recompute derived như popup sửa lô.
 */
trait HandlesShipmentUpdateImport
{
    /** Ký hiệu XÓA giá trị (ô trống chỉ có nghĩa "giữ nguyên"). */
    private const CLEAR_TOKEN = '--';

    /**
     * Field được phép cập nhật => [nhãn hiển thị, kiểu kiểm tra].
     * Cố ý KHÔNG có: khách hàng / số lượng / chi phí / doanh thu (đổi là lệch bảng kê đã chốt,
     * và chi phí đã có luồng riêng).
     */
    private function updatableShipmentFields(): array
    {
        return [
            'gioXeDen'     => ['Giờ xe đến', 'datetime'],
            'gioXeRa'      => ['Giờ xe ra', 'datetime'],
            'gioDenDuKien' => ['Giờ đến dự kiến', 'datetime'],
            'bksVao'       => ['Biển số vào', 'text'],
            'bksRa'        => ['Biển số ra', 'text'],
            'contNo'       => ['Số cont', 'text'],
            'contType'     => ['Loại cont', 'contType'],
            'declNo'       => ['Số tờ khai', 'text'],
            'inv'          => ['Invoice', 'text'],
            'from'         => ['Nơi lấy', 'location'],
            'to'           => ['Nơi hạ', 'location'],
            'kho'          => ['Kho', 'kho'],
            'bargeDrop'    => ['Nơi hạ sà lan', 'bargeDrop'],
            'cutOff'       => ['Cắt máng', 'datetime'],   // 364/364 lô đang lưu đúng dạng ISO → kiểm định dạng được
            'driver'       => ['Tài xế', 'text'],
            'extVendor'    => ['Nhà xe ngoài', 'extVendor'],
            'ghiChu'       => ['Ghi chú', 'text'],
        ];
    }

    /** Cột DB tương ứng để đọc giá trị CŨ (dựng diff). */
    private function updatableFieldColumns(): array
    {
        return [
            'gioXeDen' => 'gio_xe_den', 'gioXeRa' => 'gio_xe_ra', 'gioDenDuKien' => 'gio_den_du_kien',
            'bksVao' => 'bks_vao', 'bksRa' => 'bks_ra', 'contNo' => 'cont_no', 'contType' => 'cont_type',
            'declNo' => 'declaration_no', 'inv' => 'inv', 'from' => 'from_loc', 'to' => 'to_loc',
            'kho' => 'kho', 'bargeDrop' => 'barge_drop', 'cutOff' => 'cut_off', 'driver' => 'driver',
            'extVendor' => 'ext_vendor', 'ghiChu' => 'ghi_chu',
        ];
    }

    /** Field ảnh hưởng SỐ TIỀN của bảng kê (đổi khi lô đã lên bảng kê → cảnh báo mạnh). */
    private const MONEY_FIELDS = ['gioXeRa', 'from', 'to', 'kho', 'contType', 'bargeDrop'];

    /** Dry-run: kiểm tra + dựng diff, KHÔNG ghi DB. */
    public function validateShipmentUpdate(string $sheet, array $rows): array
    {
        return $this->analyzeShipmentUpdate($sheet, $rows);
    }

    /** Import cập nhật — ALL-OR-NOTHING; chỉ ghi những ô thực sự đổi. */
    public function importShipmentUpdate(string $sheet, array $rows): array
    {
        $res = $this->analyzeShipmentUpdate($sheet, $rows);
        if (! $res['valid']) return $res + ['updated' => 0, 'cells' => 0];

        $plans = $res['_plans'];
        unset($res['_plans']);
        if (! $plans) return $res + ['updated' => 0, 'cells' => 0];

        // Nhật ký giá trị CŨ trước khi ghi đè — dự án chưa có bảng audit, đây là đường truy lại duy nhất.
        $this->logShipmentUpdateSnapshot($sheet, $plans);

        $updated = 0; $cells = 0;
        DB::transaction(function () use ($plans, $sheet, &$updated, &$cells) {
            foreach ($plans as $p) {
                /** @var TruckingShipment $s */
                $s = $p['ship'];
                // ghi_chu thuộc nhóm 'rev' của saveShipment — nhóm đó XÓA-TẠO LẠI doanh thu/thanh toán,
                // nên gán thẳng vào model (save() bên trong saveShipment sẽ ghi luôn), không bật $only='rev'.
                if (array_key_exists('ghiChu', $p['patch'])) $s->ghi_chu = $p['patch']['ghiChu'];
                $only = array_values(array_diff(array_keys($p['patch']), ['ghiChu']));
                $this->saveShipment($p['patch'], $sheet, $s, $only);
                $updated++;
                $cells += count($p['cells']);
            }
        });

        return $res + ['updated' => $updated, 'cells' => $cells];
    }

    /**
     * Lõi dùng chung cho dry-run và import: khớp lô → dựng patch → so sánh → lỗi/cảnh báo.
     * '_plans' (chỉ dùng nội bộ) = [['ship'=>lô, 'patch'=>[field=>giá trị], 'cells'=>[diff]]].
     */
    private function analyzeShipmentUpdate(string $sheet, array $rows): array
    {
        $fields  = $this->updatableShipmentFields();
        $columns = $this->updatableFieldColumns();
        $targets = $this->resolveUpdateTargets($sheet, $rows);   // line => lô | null
        $inStatement = $this->shipmentsInStatements(collect($targets)->filter()->pluck('id')->all());

        $errors = []; $changes = []; $warnings = []; $plans = []; $noChange = 0;
        $seen = [];   // shipment_id => line (chặn 2 dòng cùng sửa 1 lô)

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $reasons = [];
            $s = $targets[$i] ?? null;
            if (! $s) {
                $errors[] = $this->updateError($line, $row, [$this->targetReason($sheet, $rows, $i)]);
                continue;
            }
            if (isset($seen[$s->id])) {
                $errors[] = $this->updateError($line, $row, ["Lô #{$s->id} đã được sửa ở dòng {$seen[$s->id]} — 2 dòng cùng sửa 1 lô"]);
                continue;
            }
            $seen[$s->id] = $line;

            // ----- dựng patch từ các ô CÓ nội dung -----
            $patch = []; $cells = [];
            foreach (($row['values'] ?? []) as $f => $raw) {
                if (! isset($fields[$f])) continue;                       // cột lạ → bỏ qua
                if ($f === 'contNo' && trim((string) ($row['id'] ?? '')) === '') continue;   // cont là KHÓA khi không có ID
                [$label, $type] = $fields[$f];
                $raw = is_string($raw) ? trim($raw) : $raw;
                if ($raw === '' || $raw === null) continue;               // ô trống = giữ nguyên

                // Ô GIỮ NGUYÊN giá trị đang lưu (xuất → nhập lại) thì bỏ qua LUÔN, không kiểm tra:
                // dữ liệu cũ có thể không còn hợp lệ theo danh mục hiện tại (vd loại cont 20DC/40RHC
                // đang dùng nhưng danh mục thiếu) — chặn ở đây là chặn nhầm việc người dùng không sửa.
                $old = $this->updateOldValue($s, $columns[$f]);
                if ((string) $raw === $old) continue;

                $rawShow = trim((string) (($row['raws'][$f] ?? '') ?: $raw));
                $val = $this->normalizeUpdateValue($f, $type, $raw, $rawShow, $reasons, $label);
                if ($val === false) continue;                              // đã ghi lỗi

                $new = (string) ($val ?? '');
                if ($old === $new) continue;                               // chuẩn hóa xong vẫn y cũ → không ghi
                $patch[$f] = $val;
                $cells[] = ['field' => $f, 'label' => $label, 'old' => $old, 'new' => $new];
            }

            if ($reasons) { $errors[] = $this->updateError($line, $row, $reasons, $s); continue; }
            if (! $cells) { $noChange++; continue; }

            $changes[] = ['line' => $line, 'id' => $s->id, 'contNo' => $s->cont_no ?? '', 'booking' => $s->booking ?? '', 'cells' => $cells];
            $plans[] = ['ship' => $s, 'patch' => $patch, 'cells' => $cells];

            // ----- cảnh báo (không chặn) -----
            if (isset($inStatement[$s->id])) {
                $money = array_intersect(array_column($cells, 'field'), self::MONEY_FIELDS);
                $warnings[] = ['line' => $line, 'id' => $s->id, 'text' => 'Lô đã nằm trong bảng kê ' . $inStatement[$s->id]
                    . ($money ? ' — sửa ' . implode(', ', array_map(fn ($f) => $fields[$f][0], $money)) . ' làm lệch số đã chốt, vào Bảng kê bấm Tính lại' : '')];
            }
            if (isset($patch['contNo']) && $this->contNoTakenBy($sheet, $patch['contNo'], $s->id)) {
                $warnings[] = ['line' => $line, 'id' => $s->id, 'text' => "Số cont “{$patch['contNo']}” đang trùng với lô khác"];
            }
            // Giờ ra sớm hơn giờ đến: CẢNH BÁO, không chặn — 4% lô thật đang vậy, chủ yếu do
            // ra_mode='other' (giờ ra hiệu lực nằm ở cont khác). Chỉ nhắc khi người dùng động vào 2 ô này.
            if (isset($patch['gioXeDen']) || isset($patch['gioXeRa'])) {
                $den = $patch['gioXeDen'] ?? $this->outDateTime($s->gio_xe_den);
                $ra  = $patch['gioXeRa']  ?? $this->outDateTime($s->gio_xe_ra);
                if ($den && $ra && $ra < $den) {
                    $warnings[] = ['line' => $line, 'id' => $s->id, 'text' => "Giờ xe ra ({$ra}) sớm hơn Giờ xe đến ({$den})"
                        . (($s->ra_mode ?? 'self') !== 'self' ? ' — lô này lấy giờ ra từ cont khác nên có thể bình thường' : ' — kiểm tra lại nếu nhập nhầm')];
                }
            }
        }

        return [
            'valid'    => empty($errors),
            'total'    => count($rows),
            'errors'   => $errors,
            'changes'  => $changes,
            'warnings' => $warnings,
            'noChange' => $noChange,
            '_plans'   => $plans,
        ];
    }

    /** Chuẩn hóa + kiểm tra 1 ô. Trả giá trị đã chuẩn hóa (null = xóa), hoặc false nếu lỗi. */
    private function normalizeUpdateValue(string $field, string $type, $raw, string $rawShow, array &$reasons, string $label)
    {
        if (trim((string) $raw) === self::CLEAR_TOKEN) return null;   // '--' = xóa

        $v = trim((string) $raw);
        switch ($type) {
            case 'datetime':
                // Frontend gửi ISO 'Y-m-dTH:i'; rỗng mà ô có chữ = sai định dạng.
                if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) {
                    $reasons[] = "{$label} “{$rawShow}” sai định dạng (cần dd/mm/yyyy HH:MM)";
                    return false;
                }
                return $v;

            case 'location':
                return $this->resolveLocationName($v, $reasons, $label);

            case 'kho':
                $wh = $this->warehouseCodeMap();
                $segs = $this->khoSegments($v);
                if (! $segs) { $reasons[] = "{$label} “{$v}” không đọc được"; return false; }
                $out = [];
                foreach ($segs as $seg) {
                    if (! isset($wh[mb_strtolower($seg)])) { $reasons[] = "Kho “{$seg}” chưa có trong danh mục Kho"; return false; }
                    $out[] = $wh[mb_strtolower($seg)];
                }
                return implode(' → ', $out);

            case 'bargeDrop':
                $u = mb_strtoupper($v);
                if (! in_array($u, ['HPP', 'LHP'], true)) { $reasons[] = "{$label} “{$v}” không hợp lệ (chỉ nhận HPP hoặc LHP)"; return false; }
                return $u;

            case 'contType':
                $hit = TruckingContType::whereRaw('LOWER(name) = ?', [mb_strtolower($v)])->value('name');
                if (! $hit) { $reasons[] = "{$label} “{$v}” chưa có trong danh mục — thêm ở Cài đặt → Loại cont rồi làm lại"; return false; }
                return $hit;

            case 'extVendor':
                $hit = TruckingExtVendor::whereRaw('LOWER(name) = ?', [mb_strtolower($v)])->value('name');
                if (! $hit) { $reasons[] = "{$label} “{$v}” chưa có trong danh mục — thêm ở Cài đặt → Đơn vị xe ngoài rồi làm lại"; return false; }
                return $hit;

            default:
                return $v;
        }
    }

    /** Giá trị CŨ dạng chuỗi để so sánh/hiển thị diff (datetime về 'Y-m-dTH:i' như frontend gửi). */
    private function updateOldValue(TruckingShipment $s, string $col): string
    {
        $v = $s->{$col};
        if ($v === null) return '';
        if (in_array($col, ['gio_xe_den', 'gio_xe_ra', 'gio_den_du_kien'], true)) return (string) $this->outDateTime($v);
        return trim((string) $v);
    }

    /**
     * Khớp từng dòng file với 1 lô: ID trước, không có ID mới dùng Số cont (phải duy nhất).
     * @return array<int,TruckingShipment|null> theo CHỈ SỐ dòng
     */
    private function resolveUpdateTargets(string $sheet, array $rows): array
    {
        $ids   = collect($rows)->map(fn ($r) => (int) trim((string) ($r['id'] ?? '')))->filter()->unique()->values();
        $conts = collect($rows)
            ->filter(fn ($r) => trim((string) ($r['id'] ?? '')) === '')
            ->map(fn ($r) => mb_strtoupper(trim((string) ($r['contNo'] ?? ''))))
            ->filter()->unique()->values();

        $byId = $ids->isEmpty() ? collect() : TruckingShipment::ofSheet($sheet)->whereIn('id', $ids->all())->get()->keyBy('id');
        $byCont = $conts->isEmpty() ? collect() : TruckingShipment::ofSheet($sheet)
            ->whereIn(DB::raw('UPPER(cont_no)'), $conts->all())->get()
            ->groupBy(fn ($s) => mb_strtoupper(trim((string) $s->cont_no)));

        $out = [];
        foreach ($rows as $i => $r) {
            $id = (int) trim((string) ($r['id'] ?? ''));
            if ($id > 0) { $out[$i] = $byId->get($id); continue; }
            $cont = mb_strtoupper(trim((string) ($r['contNo'] ?? '')));
            $g = $cont === '' ? collect() : ($byCont[$cont] ?? collect());
            $out[$i] = $g->count() === 1 ? $g->first() : null;
        }
        return $out;
    }

    /** Lý do không khớp được lô (chỉ gọi khi target null) — nói rõ để người dùng sửa file. */
    private function targetReason(string $sheet, array $rows, int $i): string
    {
        $row = $rows[$i];
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') return "Không có lô ID {$id} trong danh sách " . mb_strtoupper($sheet);
        $cont = trim((string) ($row['contNo'] ?? ''));
        if ($cont === '') return 'Thiếu cả ID lô lẫn Số cont — không xác định được lô nào';
        $n = TruckingShipment::ofSheet($sheet)->whereRaw('UPPER(cont_no) = ?', [mb_strtoupper($cont)])->count();
        if ($n === 0) return "Số cont “{$cont}” không có trong danh sách lô";
        return "Số cont “{$cont}” trùng ở {$n} lô — thêm cột ID để chỉ đúng lô";
    }

    /** [shipment_id => "#12 (Khách · kỳ)"] cho các lô đã nằm trong bảng kê khách. */
    private function shipmentsInStatements(array $ids): array
    {
        if (! $ids) return [];
        $rows = DB::table('trucking_statement_lines as l')
            ->join('trucking_statements as st', 'st.id', '=', 'l.statement_id')
            ->whereIn('l.shipment_id', $ids)
            ->get(['l.shipment_id', 'st.id', 'st.no', 'st.customer_name', 'st.period_from', 'st.period_to']);

        $out = [];
        foreach ($rows as $r) {
            $label = '#' . ($r->no ?: $r->id) . ($r->customer_name ? ' · ' . $r->customer_name : '')
                   . ($r->period_from ? ' · ' . substr((string) $r->period_from, 0, 10) . '→' . substr((string) $r->period_to, 0, 10) : '');
            $out[$r->shipment_id] = $label;
        }
        return $out;
    }

    /** Số cont mới có đang thuộc lô khác không (cảnh báo, không chặn — dữ liệu thật có cont dùng lại). */
    private function contNoTakenBy(string $sheet, ?string $contNo, int $selfId): bool
    {
        $c = trim((string) $contNo);
        if ($c === '') return false;
        return TruckingShipment::ofSheet($sheet)->whereRaw('UPPER(cont_no) = ?', [mb_strtoupper($c)])
            ->where('id', '!=', $selfId)->exists();
    }

    /** 1 dòng lỗi (shape giống các import khác để frontend dùng chung cách hiển thị). */
    private function updateError(int $line, array $row, array $reasons, ?TruckingShipment $s = null): array
    {
        return [
            'line'    => $line,
            'id'      => (string) ($row['id'] ?? ''),
            'cont'    => (string) ($row['contNo'] ?? ($s->cont_no ?? '')),
            'reasons' => array_values(array_filter($reasons)),
        ];
    }

    /** Ghi giá trị CŨ ra file JSON để truy lại/khôi phục thủ công khi import nhầm. */
    private function logShipmentUpdateSnapshot(string $sheet, array $plans): void
    {
        $payload = [
            'at'      => now()->toDateTimeString(),
            'user'    => auth()->user()?->name ?? auth()->id(),
            'sheet'   => $sheet,
            'changes' => array_map(fn ($p) => [
                'id'    => $p['ship']->id,
                'cont'  => $p['ship']->cont_no,
                'cells' => $p['cells'],
            ], $plans),
        ];
        try {
            Storage::disk('local')->put('imports/shipment-update-' . now()->format('Ymd-His') . '.json',
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            // Không chặn import vì lỗi ghi log — nhưng phải để lại dấu vết.
            Log::warning('Không ghi được nhật ký import cập nhật lô: ' . $e->getMessage());
        }
    }
}
