<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Models\PayableInitialBalance;
use App\Models\PayableReport;
use App\Services\PayableReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayableReportController extends Controller
{
    public function __construct(
        private readonly PayableReportService $payable,
    ) {}

    public function index()
    {
        return view('reports.payable.index', [
            'reports'         => $this->payable->listReports(),
            'increase_dates'  => $this->payable->availableIncreaseDates(),
            'decrease_dates'  => $this->payable->availableDecreaseDates(),
        ]);
    }

    public function show(PayableReport $report)
    {
        $report->load('lines', 'creator:id,name');
        $previous = $this->payable->previousReport($report);

        // Notes/Tasks gắn vào báo cáo này (polymorphic)
        $tasks = \App\Models\Task::query()
            ->where('linkable_type', PayableReport::class)
            ->where('linkable_id', $report->id)
            ->with(['assignees:id,name', 'creator:id,name'])
            ->orderByRaw('CASE WHEN status = "done" THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get();

        return view('reports.payable.show', compact('report', 'previous', 'tasks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_date'   => ['required', 'date'],
            'increase_date' => ['nullable', 'date'],
            'decrease_date' => ['nullable', 'date'],
            'note'          => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $report = $this->payable->generate(
                reportDate:   $data['report_date'],
                increaseDate: $data['increase_date'] ?? null,
                decreaseDate: $data['decrease_date'] ?? null,
                note:         $data['note'] ?? null,
                userId:       $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('reports.payable.show', $report)
            ->with('success', 'Đã tạo báo cáo phải trả với ' . $report->lines->count() . ' NCC.');
    }

    public function destroy(PayableReport $report): RedirectResponse
    {
        $this->payable->delete($report);
        return redirect()->route('reports.payable.index')->with('success', 'Đã xoá báo cáo.');
    }

    // ---------- Initial balances ----------

    public function initialIndex()
    {
        return view('reports.payable.initial', [
            'balances'  => $this->payable->listInitialBalances(),
            'suppliers' => $this->payable->availableSuppliers(),
        ]);
    }

    public function initialStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier'        => ['required', 'string', 'max:128'],
            'opening_amount'  => ['required', 'numeric'],
            'as_of_date'      => ['nullable', 'date'],
            'note'            => ['nullable', 'string', 'max:500'],
        ]);

        $this->payable->setInitialBalance(
            supplier:  $data['supplier'],
            amount:    (float) $data['opening_amount'],
            asOfDate:  $data['as_of_date'] ?? null,
            note:      $data['note'] ?? null,
            userId:    $request->user()->id,
        );

        return back()->with('success', "Đã lưu đầu kỳ cho {$data['supplier']}.");
    }

    public function initialDestroy(PayableInitialBalance $balance): RedirectResponse
    {
        $this->payable->deleteInitialBalance($balance);
        return back()->with('success', "Đã xoá đầu kỳ cho {$balance->supplier}.");
    }
}
