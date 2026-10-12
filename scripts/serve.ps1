<#
    scripts/serve.ps1 — chạy `php artisan serve` với thư mục TEMP riêng của dự án.

    VÌ SAO CẦN SCRIPT NÀY (Windows)
    ------------------------------
    `artisan serve` — và mọi lệnh Laravel có gọi tiến trình con (queue:listen, pail,
    composer dev…) — dùng Symfony Process. Trên Windows, Symfony ghi output của tiến
    trình con vào các file tạm trong %TEMP%:

        %TEMP%\sf_proc_XX.out / .err          (XX = 00, 01, 02…)
        %TEMP%\sf_proc_XX.out.lock / .err.lock   ← file khoá

    Nếu trong %TEMP% đã có sẵn file trùng tên nhưng tài khoản hiện tại KHÔNG mở/ghi
    được (file rác do tiến trình cũ hoặc phiên tài khoản khác để lại), thì
    `file_exists()` cũng bị "Access denied" ⇒ Symfony tưởng ô đó còn trống, thử tạo
    và chết ngay chứ không nhảy sang tên 02, 03:

        Symfony\Component\Process\Exception\RuntimeException
        A temporary file could not be opened to write the process output:
        fopen(C:\Users\...\AppData\Local\Temp\sf_proc_01.out.lock):
            Failed to open stream: Permission denied

    Script này trỏ TMP/TEMP (chỉ cho tiến trình PHP này) vào `storage/framework/proc-tmp`
    nên không bao giờ dính file rác trong %TEMP% của Windows nữa.

    CÁCH DÙNG
    ---------
        powershell -ExecutionPolicy Bypass -File scripts\serve.ps1
        powershell -ExecutionPolicy Bypass -File scripts\serve.ps1 -Port 8000
        powershell -ExecutionPolicy Bypass -File scripts\serve.ps1 -BindHost 0.0.0.0 -Port 3000
#>
param(
    [int]$Port = 3000,
    [string]$BindHost = '127.0.0.1'
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$tmpDir = Join-Path $root 'storage\framework\proc-tmp'

New-Item -ItemType Directory -Force -Path $tmpDir | Out-Null

# Dọn file tạm cũ của chính lần chạy trước (trong proc-tmp luôn xoá được).
Get-ChildItem -LiteralPath $tmpDir -Filter 'sf_proc_*' -Force -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

# Dọn luôn file rác sf_proc_* trong %TEMP% khi tài khoản còn quyền xoá.
# File bị "Access denied" thì bỏ qua (phải chạy PowerShell as Administrator / takeown
# mới xoá được — xem ghi chú ở trên).
Get-ChildItem -LiteralPath $env:TEMP -Filter 'sf_proc_*' -Force -ErrorAction SilentlyContinue |
    Remove-Item -Force -ErrorAction SilentlyContinue

$env:TMP = $tmpDir
$env:TEMP = $tmpDir

Write-Host "[serve] TMP/TEMP = $tmpDir"
Write-Host "[serve] URL      = http://${BindHost}:$Port"

Push-Location -LiteralPath $root
try {
    & php artisan serve "--host=$BindHost" "--port=$Port"
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
