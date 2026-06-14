---
name: trucking-demo-command
description: Lệnh seed/clear dữ liệu demo Phí xe nội bộ
metadata:
  type: reference
---

`php artisan trucking:demo` seed demo Phí xe nội bộ T6/2026 (5 lái xe + 8 xe MBF + 9 phí tuyến khớp bảng giá Canon + giá dầu + 24 lô booking `DEMO6-*`). `php artisan trucking:demo clear` xóa sạch demo.

Command tại `app/Console/Commands/TruckingDemo.php`. CHỈ đụng dữ liệu demo tự tạo (tag cố định: booking DEMO6-, biển số/lái xe/tuyến theo hằng số, giá dầu note 'DEMO', kỳ phí xe có lô demo) — KHÔNG đụng địa điểm/kho/khách/bảng giá (dữ liệu thật).

**Why:** User chủ động xin seed 1 lần để xem Phí xe tính đúng, rồi tự clear cho hệ thống mới tinh trước khi nhân sự nhập thật. Không mâu thuẫn [[no-seed-demo]] (chỉ seed khi user yêu cầu rõ). Liên quan [[phi-xe-batch-model]].
**How to apply:** Chạy trên server (sau git pull) để hiện trên trucking.dewa.vn; seed là local-only nếu chạy ở máy dev.
