<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tra nhật ký thao tác trong terminal — chủ yếu cho Claude Code khi rà lỗi dữ liệu
 * ("ai đổi xe 29C-18195 sang Xe ngoài?"). Mặc định in mỗi dòng 1 câu gọn; --json in JSONL đủ trường.
 */
class ActivityLogSearch extends Command
{
    protected $signature = 'activity:log
        {--subject= : Tìm trong nhãn đối tượng (biển số, số cont, mã bảng kê…)}
        {--type= : Loại model (vd TruckingVehicle)}
        {--id= : id đối tượng (dùng kèm --type)}
        {--user= : Tên hoặc email người làm}
        {--module= : Trang (lo-hang, cai-dat, quan-ly-xe, system-settings…)}
        {--event= : request|created|updated|deleted}
        {--grep= : Tìm chữ trong câu tóm tắt}
        {--request= : Mọi dòng của 1 request_id}
        {--from= : Từ ngày (YYYY-MM-DD)}
        {--to= : Đến ngày (YYYY-MM-DD)}
        {--limit=100 : Số dòng tối đa}
        {--json : In JSONL đủ trường (kèm changes)}';

    protected $description = 'Tra cứu nhật ký thao tác nhân viên (activity_logs)';

    public function handle(): int
    {
        $q = ActivityLog::query()->orderByDesc('id');
        if ($v = $this->option('subject')) $q->where('subject_label', 'like', "%{$v}%");
        if ($v = $this->option('type'))    $q->where('subject_type', $v);
        if ($v = $this->option('id'))      $q->where('subject_id', $v);
        if ($v = $this->option('module'))  $q->where('module', $v);
        if ($v = $this->option('event'))   $q->where('event', $v);
        if ($v = $this->option('grep'))    $q->where('summary', 'like', "%{$v}%");
        if ($v = $this->option('request')) $q->where('request_id', $v);
        if ($v = $this->option('from'))    $q->where('created_at', '>=', $v . ' 00:00:00');
        if ($v = $this->option('to'))      $q->where('created_at', '<=', $v . ' 23:59:59');
        if ($v = $this->option('user')) {
            $q->where(fn ($w) => $w->where('actor', 'like', "%{$v}%")
                ->orWhereIn('user_id', \App\Models\User::where('email', 'like', "%{$v}%")->pluck('id')));
        }
        $rows = $q->limit(max(1, (int) $this->option('limit')))->get()->reverse();

        foreach ($rows as $r) {
            if ($this->option('json')) {
                $this->line(json_encode($r->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }
            $req = Str::limit($r->request_id, 8, '');
            $this->line("{$r->created_at->format('Y-m-d H:i:s')} [{$req}] {$r->module} · {$r->summary}");
        }
        if ($rows->isEmpty()) $this->warn('Không có dòng nhật ký nào khớp.');
        return self::SUCCESS;
    }
}
