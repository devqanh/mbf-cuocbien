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

/** Tách từ TruckingV2Service — nhóm HandlesStatements. */
trait HandlesStatements
{
    // ===================================================================
    // STATEMENT (bảng kê) — serialize & persist
    // ===================================================================
    public function statements(): array
    {
        return TruckingStatement::with(['lines', 'payments'])->orderBy('id')->get()
            ->map(fn ($st) => $this->statementToArray($st))->all();
    }

    /**
     * Danh sách bảng kê cho trang Bảng kê (KePage) — CHỈ tóm tắt + payments, KHÔNG nạp
     * lines (snapshot từng lô) vì trang danh sách không hiển thị. Tránh hydrate hàng trăm
     * dòng statement_lines vô ích.
     *
     * SCALE: hỗ trợ limit/offset + filter (customer, kỳ period_to) để endpoint paginate
     * trả nhanh khi dữ liệu lớn. Mặc định không filter/limit → backward-compatible (boot
     * cũ vẫn trả ARRAY phẳng, KePage không phải sửa).
     *
     * @param array{customer?:?string,from?:?string,to?:?string} $filters
     */
    public function statementsForList(?int $limit = null, int $offset = 0, array $filters = []): array
    {
        $q = $this->statementsListQuery($filters);
        if ($limit !== null) $q->orderByDesc('id')->limit($limit)->offset(max(0, $offset));
        else $q->orderBy('id');   // backward-compatible: ASC khi không paginate

        $rows = $q->get()
            ->map(fn ($st) => [
                'id'       => $st->id,
                'hashid'   => Hashid::encode($st->id),
                'no'       => $st->no,
                'customer' => $st->customer_name ?? $st->customer?->name ?? '',
                'date'     => $this->outDate($st->date),
                'from'     => $this->outDate($st->period_from),
                'to'       => $this->outDate($st->period_to),
                // Tổng = TỔNG dòng (luôn đúng, không phụ thuộc cột total có thể cũ).
                'tongThu'  => (int) round((float) ($st->lines_total ?? $st->total)),
                'payments' => $st->payments->map(fn ($p) => [
                    'id'     => $p->id,
                    'date'   => $this->outDate($p->date),
                    'amount' => $this->outMoney($p->amount),
                    'note'   => $p->note ?? '',
                ])->all(),
            ])->all();

        // Khi paginate (DESC + limit): đảo chiều để CALLER nhận id-ASC như bản cũ → frontend
        // .slice().reverse() vẫn hiển thị newest-first đúng như trước.
        return $limit !== null ? array_reverse($rows) : $rows;
    }

    /** Meta cho paginate: tổng số bảng kê khớp filter (để KePage biết còn trang hay không). */
    public function statementsForListMeta(array $filters = []): array
    {
        return ['total' => (int) $this->statementsListQuery($filters)->toBase()->count('trucking_statements.id')];
    }

    /** Query builder dùng chung cho list + meta — đẩy filter vào SQL (dùng FK index). */
    private function statementsListQuery(array $filters)
    {
        $q = TruckingStatement::with(['payments', 'customer'])
            ->withSum('lines as lines_total', 'phai_thu');
        $cust = trim((string) ($filters['customer'] ?? ''));
        if ($cust !== '') {
            $custId = $this->customerIdByName($cust);
            if ($custId) $q->where('customer_id', $custId);
            else $q->where('customer_name', $cust);
        }
        // Kỳ = period_to (mốc kết thúc kỳ). Nếu rỗng fallback date (ngày lập) để giữ trùm.
        if (! empty($filters['from'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '>=', $filters['from'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '>=', $filters['from']); });
        });
        if (! empty($filters['to'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '<=', $filters['to'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '<=', $filters['to']); });
        });
        return $q;
    }

    public function statementToArray(TruckingStatement $st): array
    {
        return [
            'id'        => $st->id,
            'hashid'    => Hashid::encode($st->id),
            'no'        => $st->no,
            'customer'  => $st->customer_name ?? $st->customer?->name ?? '',
            'info'      => $st->info ?? [],
            'date'      => $this->outDate($st->date),
            'from'      => $this->outDate($st->period_from),
            'to'        => $this->outDate($st->period_to),
            'tongThu'   => (int) round((float) $st->lines->sum('phai_thu')),   // = tổng dòng (chân lý)
            'lines'     => $st->lines->map(fn ($l) => [
                'id'        => $l->shipment_id ?? $l->id,
                'booking'   => $l->booking ?? '',
                'sheet'     => $l->sheet ?? '',
                'io'        => $l->io ?? '',
                'declNo'    => $l->decl_no ?? '',
                'contType'  => $l->cont_type ?? '',
                'inv'       => $l->inv ?? '',
                'contNo'    => $l->cont_no ?? '',
                'bks'       => $l->bks ?? '',
                'from'      => $l->from_loc ?? '',
                'to'        => $l->to_loc ?? '',
                'date'      => $this->outDate($l->date),
                'contLabel' => $l->cont_label ?? '',
                'phaiThu'   => (int) round((float) $l->phai_thu),
                'cuoc'      => (int) round((float) $l->cuoc),
                'thanhLy'   => (int) round((float) $l->thanh_ly),
                'note'      => $l->note ?? '',
                'detail'    => $l->detail,
            ])->all(),
            'payments'  => $st->payments->map(fn ($p) => [
                'id'     => $p->id,
                'date'   => $this->outDate($p->date),
                'amount' => $this->outMoney($p->amount),
                'note'   => $p->note ?? '',
            ])->all(),
        ];
    }

    public function saveStatement(array $data, ?TruckingStatement $st = null): TruckingStatement
    {
        return DB::transaction(function () use ($data, $st) {
            $customerId = null;
            if (! empty($data['customer'])) {
                $customerId = $this->customerIdByName($data['customer']);
            }

            $st ??= new TruckingStatement();
            $st->fill([
                'no'            => $data['no'] ?? $st->no,
                'customer_id'   => $customerId,
                'customer_name' => $this->str($data['customer'] ?? null),
                'info'          => $data['info'] ?? null,
                'date'          => $this->inDate($data['date'] ?? null),
                'period_from'   => $this->inDate($data['from'] ?? null),
                'period_to'     => $this->inDate($data['to'] ?? null),
                // total = TỔNG dòng (chân lý, server tự cộng) — không tin tongThu client để khỏi lệch.
                'total'         => collect($data['lines'] ?? [])->sum(fn ($l) => $this->inMoney($l['phaiThu'] ?? null) ?? 0),
            ]);
            $st->save();

            // BULK INSERT: 1 query thay vì N (bảng kê lớn có thể có hàng trăm dòng).
            // Bypass Eloquent → tự set timestamps + json_encode `detail` (cast 'array').
            $now = Carbon::now();
            $st->lines()->delete();
            $linesBatch = [];
            foreach (($data['lines'] ?? []) as $i => $l) {
                $detail = is_array($l['detail'] ?? null) ? $l['detail'] : null;
                $linesBatch[] = [
                    'statement_id' => $st->id,
                    'shipment_id'  => $l['id'] ?? null,
                    'booking'      => $this->str($l['booking'] ?? null),
                    'sheet'        => $this->str($l['sheet'] ?? null),
                    'io'           => $this->str($l['io'] ?? null),
                    'decl_no'      => $this->str($l['declNo'] ?? null),
                    'cont_type'    => $this->str($l['contType'] ?? null),
                    'inv'          => $this->str($l['inv'] ?? null),
                    'cont_no'      => $this->str($l['contNo'] ?? null),
                    'bks'          => $this->str($l['bks'] ?? null),
                    'from_loc'     => $this->str($l['from'] ?? null),
                    'to_loc'       => $this->str($l['to'] ?? null),
                    'date'         => $this->inDate($l['date'] ?? null),
                    'cont_label'   => $this->str($l['contLabel'] ?? null),
                    'phai_thu'     => $this->inMoney($l['phaiThu'] ?? null) ?? 0,
                    'cuoc'         => $this->inMoney($l['cuoc'] ?? null) ?? 0,
                    'thanh_ly'     => $this->inMoney($l['thanhLy'] ?? null) ?? 0,
                    'note'         => $this->str($l['note'] ?? null),
                    'detail'       => $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
                    'sort'         => $i,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            if ($linesBatch) \App\Models\TruckingStatementLine::insert($linesBatch);

            $st->payments()->delete();
            $paymentsBatch = [];
            foreach (($data['payments'] ?? []) as $i => $p) {
                $paymentsBatch[] = [
                    'statement_id' => $st->id,
                    'date'         => $this->inDate($p['date'] ?? null),
                    'amount'       => $this->inMoney($p['amount'] ?? null),
                    'note'         => $this->str($p['note'] ?? null),
                    'sort'         => $i,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            if ($paymentsBatch) \App\Models\TruckingStatementPayment::insert($paymentsBatch);

            return $st->fresh(['lines', 'payments']);
        });
    }
}
