<?php

namespace App\Services\Trucking\Concerns;

use Carbon\Carbon;

/**
 * Helper chuyển đổi giá trị in/out giữa DB và frontend (tiền/số/ngày).
 * Dùng chung cho TruckingV2Service (và các trait domain) — tách ra cho gọn & dễ đọc.
 * Mọi số tiền giao tiếp frontend là chuỗi chữ số (VND, không phần lẻ).
 */
trait FormatsTruckingValues
{
    private function str(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? null : $v;
    }

    /** Chuỗi chữ số/tiền (frontend) → số nguyên DB (null nếu rỗng). */
    private function inMoney(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        $digits = preg_replace('/[^\d]/', '', (string) $v);
        return $digits === '' ? null : (int) $digits;
    }

    private function outMoney(mixed $v): string
    {
        return ($v === null) ? '' : (string) (int) round((float) $v);
    }

    /** VAT %: giữ dạng gọn ("8" thay vì "8.00"). */
    private function inNum(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        $n = preg_replace('/[^\d.]/', '', (string) $v);
        return $n === '' ? null : (float) $n;
    }

    private function outNum(mixed $v): string
    {
        if ($v === null) return '';
        $f = (float) $v;
        return floor($f) == $f ? (string) (int) $f : (string) $f;
    }

    private function inDate(?string $v): ?string
    {
        if (! $v) return null;
        try { return Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }

    private function outDate(mixed $v): string
    {
        if (! $v) return '';
        return $v instanceof Carbon ? $v->format('Y-m-d') : (string) $v;
    }

    private function inDateTime(?string $v): ?string
    {
        if (! $v) return null;
        try { return Carbon::parse($v)->format('Y-m-d H:i:s'); } catch (\Throwable) { return null; }
    }

    /** datetime-local: "YYYY-MM-DDTHH:MM". */
    private function outDateTime(mixed $v): string
    {
        if (! $v) return '';
        return $v instanceof Carbon ? $v->format('Y-m-d\TH:i') : (string) $v;
    }
}
