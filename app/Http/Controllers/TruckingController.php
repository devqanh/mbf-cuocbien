<?php

namespace App\Http\Controllers;

use App\Services\TruckingService;
use Illuminate\Http\Request;

/**
 * Chỉ còn phục vụ trang tài liệu /tailieu (cột & công thức — gửi kế toán).
 * Giao diện Luckysheet cũ đã gỡ; nghiệp vụ trucking dùng TruckingV2Controller.
 */
class TruckingController extends Controller
{
    public function __construct(private readonly TruckingService $trucking) {}

    /** /tailieu — tài liệu cột & công thức (render Markdown → HTML) cho kế toán xem. */
    public function docs()
    {
        $md    = $this->trucking->buildMarkdownDoc();
        $html  = \Illuminate\Support\Str::markdown($md, ['html_input' => 'allow']);
        $notes = file_exists(storage_path('app/trucking_notes.md'))
            ? file_get_contents(storage_path('app/trucking_notes.md'))
            : '';

        return view('trucking.docs', ['html' => $html, 'notes' => $notes]);
    }

    /** Lưu góp ý kế toán vào file. */
    public function saveNotes(Request $request): \Illuminate\Http\JsonResponse
    {
        $text = $request->input('notes', '');
        file_put_contents(storage_path('app/trucking_notes.md'), $text);

        return response()->json(['ok' => true]);
    }

    /** Tải file .md để gửi kế toán. */
    public function docsDownload(): \Symfony\Component\HttpFoundation\Response
    {
        $md = $this->trucking->buildMarkdownDoc();

        return response($md, 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="TRUCKING_COLUMNS.md"',
        ]);
    }
}
