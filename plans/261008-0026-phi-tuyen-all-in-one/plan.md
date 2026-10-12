# Phí tuyến "all in one" — khai 1 chỗ, khớp đúng luồng Lộ trình

Status: ĐỀ XUẤT — chờ user duyệt (chưa code)
Trang: `/trucking-v2/cai-dat#routeFees` (component `components/config/RouteFees.jsx`)

## Mục tiêu (từ yêu cầu user)
- Khai báo **trong 1 thẻ tuyến** đủ mọi case, nhìn vào hiểu ngay chuyến nào sẽ áp.
- Luồng tuyến **giống Lộ trình**: Nơi lấy → Kho → Nơi hạ.
- Case 1: có khách tính theo **tuyến cụ thể** (Cảng → Kho → Cảng) — tiền + km + dầu theo tuyến đó.
- Case 2: có khách tính **từ cảng tới kho theo thành phố (tỉnh)** cho tiền đường + vé trạm; còn **km + lít dầu 1/2 cầu theo từng kho chi tiết** (1 tỉnh / 1 khu nhiều kho, km khác nhau).

## Hiện trạng (đo T9/2026, local)
- 308 chuyến kéo cont, chỉ 36 (12%) khớp phí tuyến; 52 tổ hợp lộ trình, 48 chưa có tuyến.
- 5 tuyến đã khai, tất cả theo ký hiệu kho (vd `ICDQV - QV - ICDQV` 40km/16L/12L) → **1 số km/dầu cho cả 9 kho con của QV** (QV, 1, 2, 3, 4, F9, A, B, C, D — lô lưu đúng tên kho con).
- Kho gán tỉnh: 11/43 (QV + 9 kho con → Bắc Ninh; HẠ LONG → Hải Phòng). TS, TL… chưa gán.
- Đã có sẵn: tuyến theo tỉnh (fallback), bảng phí theo ngày, "Áp dụng cho khách", "Chi tiết theo kho" (kho_overrides), sửa lít từng chuyến ở Lộ trình.

## Thiết kế thẻ tuyến (3 khối)
```
① LUỒNG ĐI — giống Lộ trình
   Nơi lấy [cảng]  →  Giao tại [kho | ký hiệu kho | tỉnh] (1..n)  →  Nơi hạ [cảng]
   Áp dụng cho khách [Mọi khách | chọn khách]
   ✓ 30 ngày qua: 42 chuyến khớp tuyến này (xem)        ← kiểm chứng trực tiếp
② TIỀN TRẢ LÁI (theo chuyến)
   Vé trạm · Tiền đường · Trợ cấp · Lương (kéo/không kéo × CRU) · Chi khác — như hiện tại
③ KM & DẦU THEO KHO — TỰ LIỆT KÊ mọi kho mà "Giao tại" bao phủ
   Kho (tên · ký hiệu · địa chỉ)   Km   Dầu 2 cầu   Dầu 1 cầu
   Mặc định (kho chưa khai)        40   16          12
   QV — Lô B1 KCN Quế Võ           38   15          11
   4  (QV)                          —    —           —     ← trống = dùng Mặc định (hiện mờ)
   F9 (QV)                          45   18          13
```
- "Giao tại" là **tỉnh** → liệt kê kho đã gán tỉnh đó; là **ký hiệu kho** có nhiều kho con → liệt kê các kho con; là **1 kho** → chỉ dòng Mặc định.
- Dòng Mặc định = km/dầu hiện tại của tuyến (giữ dữ liệu cũ). Bảng thay cho "Chi tiết theo kho" (cùng dữ liệu `kho_overrides`, không cần bấm "Thêm kho").

## Quy tắc khớp chuyến (hiện ngay trên trang)
1. Tuyến **riêng của khách**, khớp theo kho/ký hiệu kho
2. Tuyến **riêng của khách**, khớp theo tỉnh
3. Tuyến **chung**, khớp theo kho/ký hiệu kho
4. Tuyến **chung**, khớp theo tỉnh
- Km/dầu: kho của chuyến có dòng riêng → dùng dòng đó; chuyến giao **nhiều kho** → lấy kho **km lớn nhất**; còn lại → Mặc định. Sửa tay từng chuyến ở Lộ trình vẫn ưu tiên cao nhất.
- Chiều đi/về: giữ như hiện tại (A → K → B ≡ B → K → A) để không phải khai gấp đôi.
- Chuyến "ra xe không kéo cont": tuyến chỉ có Nơi lấy (không Giao tại, không Nơi hạ).

## Khung "Kiểm tra khớp Lộ trình (30 ngày)" — đầu trang
- Tổng: N chuyến · X có phí · Y chưa có. Danh sách lộ trình **chưa khớp**, xếp theo số chuyến.
- Mỗi dòng: nút **Tạo tuyến** (điền sẵn Nơi lấy / Kho / Nơi hạ) + gợi ý **gom theo tỉnh/ký hiệu** khi kho đã gán tỉnh.
- Cảnh báo kho chưa gán tỉnh (link sang Cài đặt › Kho).

## Thay đổi kỹ thuật (dự kiến)
- Không đổi cách lưu tuyến (`route` = chuỗi node có thứ tự; khớp theo tập node như cũ) → dữ liệu cũ dùng tiếp, không migration.
- FE `RouteFees.jsx`: editor 3 phần (Nơi lấy / Giao tại / Nơi hạ) ghi lại `route`; khối ③ tự liệt kê kho (cần thêm "kho con theo ký hiệu" vào `routeFeeKhoInfo` — đã có `code`).
- BE `legPayGroup`: chọn dòng kho theo quy tắc "km lớn nhất" khi nhiều kho; trả `feeRouteId` để đếm chuyến khớp.
- BE mới `routeFeeCoverage(days)` (dùng lại `routeTripByDate` để khớp y hệt Lộ trình) → số chuyến khớp mỗi tuyến + lộ trình chưa khớp; route GET lazy.
- `cleanKhoOverrides`: bỏ dòng không có số nào.

## Giai đoạn
1. Thẻ tuyến 3 khối + bảng km/dầu tự liệt kê + hiển thị quy tắc khớp.
2. Khung kiểm tra khớp 30 ngày + "N chuyến khớp" từng tuyến + Tạo tuyến điền sẵn.

## Quyết định cần user chốt
1. Chuyến giao nhiều kho → lấy kho km lớn nhất? (đề xuất: có)
2. Chiều đi/về coi là một (A→K→B ≡ B→K→A)? (đề xuất: có)
3. Làm cả 2 giai đoạn hay giai đoạn 1 trước?

## Ngoài phạm vi
- Km theo từng chặng cộng dồn (bảng chặng riêng) — đã đề xuất trước, user chọn khai all-in-one trong thẻ tuyến.
