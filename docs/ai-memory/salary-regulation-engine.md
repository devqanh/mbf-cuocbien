---
name: salary-regulation-engine
description: Quy chế lương chuyến (03/2026) = khối JSON tùy chỉnh trên từng bảng phí tuyến (book.regulation); chuyến không khớp tuyến riêng tự tính lương/phụ cấp/km/dầu theo nhóm cảng → bảng KCN hoặc bảng vùng tỉnh cũ
metadata:
  type: project
---

Từ 2026-10-08 (plan `plans/261008-1517-luong-chuyen-theo-quy-che`), giai đoạn 1 đã làm:

- **Lưu ở đâu:** cột JSON `trucking_route_fee_books.regulation` (migration 2026_10_08_000003). Áp theo "áp dụng từ ngày" của bảng phí, nên mỗi ngày chỉ có đúng 1 bộ quy chế. Tạo bảng mới (copyFrom) sẽ sao chép kèm quy chế. Lưu riêng qua `PUT /route-fee-books/{book}/regulation` (`saveRouteFeeBookRegulation`), KHÔNG đi qua nút "Lưu mục này" của tab.
- **TÙY CHỈNH HẾT** (user: "đang fix cứng, nên tùy chỉnh"). `App\Support\SalaryZones` không còn hằng ZONES/ZONE_OF/GROUPS; `defaultRegulation()` chỉ là MẪU 03/2026 cho nút "Nạp mặc định". Cấu trúc:
  - `originGroups`: [{key, name, mode: zone|kcn, codes}] — key do FE sinh (`g`+base36), dòng KCN tham chiếu theo key.
  - `zones`: [{name, provinces[], luong, phuCap, long}]
  - `kcn`: [{group, kho, luong, phuCap, km}]
  - `fuel`: {axle1|axle2: {hr, hh}}
  - `distances`: [{from, to, km}] theo chiều.
  - `cleanRegulation` chuẩn hóa: tiền bỏ mọi ký tự không phải số, mã cảng/kho viết hoa, bỏ dòng rỗng và dòng KCN có nhóm không tồn tại.
  - `zoneIndex`: tỉnh khai ở nhiều vùng thì vùng đứng trước thắng (UI có cảnh báo).
- **Vé trạm theo CHẶNG:** `distances[].toll` (chi hộ). Chuyến tính theo quy chế có Vé trạm = cộng vé các chặng qua `segmentWalk` (đi chặng 1 lần, ra cả km + toll); dòng chỉ có vé, không có km, vẫn hợp lệ. Mục đích: bỏ hẳn việc khai tuyến chỉ để có vé.
- **ĐÃ BỎ danh sách tuyến** (user 08/10: "bỏ mục này, tính theo quy chế"): Lộ trình tính MỌI chuyến theo quy chế, không còn khớp tuyến. `legPayGroup($leg, $axle, $fuels, $date)` không còn `$rfBySet`; đã xóa `routeNodeKeyByProvince` / `warehouseProvinceByCode` / `routeKhoOverride`.
  - Trang `#routeFees` đổi tên "Quy chế lương chuyến": chỉ còn chip bảng theo ngày + RegulationPanel (mở sẵn). `CAT_KEYS.routeFees = []`. `setCfgKey('routeFeeBooks')` không đánh dấu dirty.
  - Dữ liệu `trucking_route_fees` + endpoint CRUD/Excel VẪN GIỮ trong DB/backend, chỉ không dùng (module trip-cost cũ còn tham chiếu).
  - Xe ra không kéo cont (user 08/10: "cho nhập tay"): 2 khoản `editable` amount 0, gồm phụ cấp đi đường (chi trong ngày) + lương chuyến (payrollItems, kỳ lương). `needInput` giữ cảnh báo tới khi nhập ĐỦ cả 2 (nhập 0 = không trả). `amounts` override áp cho cả items lẫn payrollItems. `inputHint` = câu giải thích hiện trong popup và chip.
  - ICD ↔ Tri Phương (kcn→kcn khác nhóm): user "để lên demo xem sau", giữ tính theo nhóm lấy.
  - HP → ICD Quế Võ nhập tay: user OK.
- **Hướng dẫn cấu hình** (user: "người chưa hiểu vẫn vào cấu hình được"): `SetupGuide` trong RegulationPanel, 7 bước có trạng thái thật + link.
  - Trạng thái lấy từ `regulationMeta.setup`: khoNoOld/khoTotal, vehNoAxle/vehTotal (mbfFleet, axle ∉ {1,2}), fuelToday, fuelLast.
  - "Tới mục này" cuộn tới khối `#reg-groups|zones|kcn|fuel|dist` (sec có scrollMarginTop 140). Trang khác mở tab mới để không mất bản nháp.
  - Nút "Ẩn hướng dẫn" nhớ bằng localStorage `trk.regGuideHidden`.
  - Sơ đồ `HowItWorks` ghi "hệ thống tính 1 chuyến thế nào", dùng TÊN nhóm cảng thật.
  - Bước 3 có chip "Dùng cho nhóm cảng" + ví dụ đọc 1 dòng. Bước 4 gom dòng KCN THEO NHÓM; dòng mồ côi để riêng.
  - MODES ghi kèm "Bước 3 / Bước 4".
- **Thử tính 1 chuyến** (user: "chưa hiểu cách khai báo"): `TripPreview` gọi `POST /route-fee-books/regulation-preview` → `previewRegulationTrip($regulation, $in)`.
  - Dùng ĐÚNG `legPayGroup(..., $regOverride)` với bản nháp chưa lưu, rồi trả từng bước: nhóm cảng 2 đầu, kho → ký hiệu / tỉnh cũ / vùng, mức tiền, km & dầu, mỗi chỗ thiếu chỉ ra "sửa ở Bước N".
  - `tryProvince` = tỉnh cũ THỬ cho kho chưa gán: chỉ ghi vào `zoneByKhoCache` của request, không lưu.
  - Danh mục kho thật có cả mục tên HPP / CEV / ICDQV… (do import tạo).
- **Kiểm tra dữ liệu ở Lộ trình** (user: "toggle để debug hộ người dùng phải nhập gì"):
  - Backend gắn `g.issues = [{type, refs[]}]`. Các type: noRegulation, noGroup, noKho, khoNoProvince, provinceNoZone, noKcn, needInput, kmMissing, noFuelPrice. `regulationTripRate` trả `issue` khi thất bại.
  - needInput tự bỏ khi đã nhập đủ khoản bắt buộc. kmMissing tự bỏ khi lít dầu đã sửa tay.
  - FE: `collectIssues` gom cả ngày (+ noAxle theo xe MBF có axle ∉ {1,2}), `ISSUE_META` mô tả cần làm gì + link. `DataCheck` bật/tắt bằng nút "Kiểm tra dữ liệu (N)" ở header, nhớ bằng localStorage `trk.lotrinhCheck`. Chip biển số cuộn tới thẻ xe `#trk-{bks}`; loại needInput thì mở popup Chi lái.
- **Trợ cấp trong ngày** (kế toán chốt 09/10), tiền ở `regulation.allowances {turnaround, thirdTrip}`. Trường tùy chọn: thiếu thì 100.000; có khai mà để trống thì 0. Panel có mục "Trợ cấp trong ngày".
  - QUAY ĐẦU ngoài HP: chỉ chuyến `tripMode = zone` (bảng vùng, kể cả ICD→HP "tính như HP") đi kho tỉnh cũ ≠ HP, với chuyến kéo cont trước đó cũng vậy.
  - Chuyến ngắn trên Bắc Ninh (`tripMode = kcn`, ICD / Tri Phương) "tính như chuyến ngắn Hải Phòng" nên KHÔNG xét quay đầu.
  - TỪ CHUYẾN THỨ 3 (user chốt 09/10): chuyến kéo cont thứ 3, 4, 5… của xe trong ngày 08→08 (bất kể đi đâu; xe ra không kéo cont không đếm) → MỖI chuyến +100k. Item key `chuyen3`, nhãn "Trợ cấp chuyến thứ N trong ngày".
  - `regulationTripRate` trả thêm `mode`; legPayGroup gắn `g.tripMode`.
- **Chi phí khác** (user 09/10: "thêm mục chi phí khác ở chi cho lái"): chi khác phát sinh thủ công có loại thứ 3, cờ `khac` (extra_items[].khac), bên cạnh tính lương và chi hộ (choHo).
  - Ý nghĩa: chi phí công ty lái chi giúp (sửa / rửa / gửi xe…). Vào tổng chi hôm nay (sub), KHÔNG tính lương, thống kê riêng: `g.khacSub` → `truck.khacTotal` → computePayroll `chiKhac` / `grandChiKhac`, và paidDaily = payTotal − choho − khac.
  - UI: popup có `exTypeSel` 3 lựa chọn (màu tím); payroll-detail có cột "Chi phí khác".
  - Lưu ý khi test PHP: `collect()->firstWhere('bks', '29E-…')` so `==` coi "29E-…" là số mũ → khớp nhầm xe; dùng so `===`.
- **Sửa tay phụ cấp + lương mọi chuyến** (user: "cho sửa để sau cần thì sửa"): item `editable` + `base` (mức quy chế); `required` chỉ cho khoản BẮT BUỘC nhập. Popup có ô Money với placeholder = quy chế; trống = theo quy chế; sửa khác thì hiện "sửa tay · quy chế X ↺". Chip Lộ trình có bút chì khi đã sửa.
- **Khác nhóm cảng** (kế toán chốt 08/10):
  - Lấy nhóm `kcn` (ICD/TP) → hạ nhóm `zone` (HP): tính như 1 chuyến HP, tức tra bảng vùng theo tỉnh cũ của kho (vd ICDQV→TS→HPP = 500k/300k), rule ghi "(tính như chuyến Hải Phòng)".
  - Lấy `zone` → hạ `kcn` (HP→QV→ICDTP): lương theo bảng vùng; tiền đường KHÔNG có định mức nên item `editable` (amount 0, `suggest` = mức vùng), `needInput` giữ cảnh báo. Popup Chi lái có ô Money; lưu `trip_overrides[cont].amounts[key]` qua `amountItems`.
  - Km cộng chặng (121,5 + 30): user xác nhận đúng.
  - ICD↔TP (kcn→kcn khác nhóm): vẫn theo nhóm lấy, CHƯA hỏi.
- **Lộ trình UI:** header xe có chip Dầu (lít, vẫn hiện khi chưa có giá), "Lương chuyến · kỳ lương" (tooltip ghi thu nhập tính lương ngày). FuelChip báo "chưa có giá dầu ngày này" thay cho 0 đ. Chuyến chưa tính được thì ghi lý do ngay dưới chuyến.
- 08/10 đã LƯU mẫu 03/2026 vào bảng "Mặc định (mọi thời điểm)" (backup cuocbien_2026_10_08_104334 trước khi lưu).
- 08/10 tối, user nạp DB mới (1500 lô, 29/08–08/10). Backup trước khi sửa: cuocbien_2026_10_08_114614.
  - Đã chép quy chế bảng #1 → bảng #2 (từ 07/09; trước đó bảng này rỗng nên mọi chuyến báo "chưa cấu hình").
  - Đã gán tỉnh cũ cho 32 kho theo địa chỉ: QV/TS/VSIP/BN → Bắc Ninh; TL/HN/NAKAWA/YOUNGFAST → Hà Nội; SEOJIN/PRODRAGON/DÂY NÂNG → Bắc Giang; WOLONG×3/AUTEL×2/HẠ LONG → Hải Phòng; HÒA PHÁT MTV/TENGXIANG/HD → Hải Dương; TÍN THÀNH → Phú Thọ.
  - Kết quả: 1223/1377 chuyến tự ra tiền, 92 chuyến cần nhập tay, 62 chuyến chưa tính được (ĐẠI KIM 28, lô thiếu kho 13, Nơi lấy QV(IM HPP) 9…).
  - Còn thiếu: km chặng Lạch Huyện + kho quanh HP, giá dầu tháng 9, vé trạm.
- **Engine** (`HandlesShipments::legPayGroup`) gọi `regulationTripRate` cho mọi chuyến kéo cont:
  - Nhóm = nhóm chứa Nơi lấy, không có thì nhóm chứa Nơi hạ.
  - Nhóm `kcn`: tra theo ký hiệu kho, nhiều kho lấy lương cao nhất. Km khứ hồi chỉ dùng khi Nơi hạ cùng nhóm.
  - Nhóm `zone`: tra vùng chứa tỉnh cũ của kho (`khoOldProvince`), nhiều kho lấy vùng lương cao nhất.
  - Khoản ra: "Phụ cấp đi đường" (chi hằng ngày, khóa loại tính lương) + "Lương chuyến" (gom vào kỳ lương).
  - Km theo thứ tự: km khứ hồi KCN → `segmentWalk` (cộng chặng Nơi lấy → kho → Nơi hạ; tra A>B, không có thì B>A). Thiếu chặng thì trả `$g['kmMissing']`.
  - Lít dầu = km × định mức theo số cầu và Hàng–Hàng / Hàng–Rỗng. Hàng–Hàng khi leg `loadIn` (s.io Nhập) và `loadOut` (out.io Xuất) đều đúng, hoặc khi chuyến `ketHop`.
  - Output thêm: `feeSource='regulation'`, `feeRule`, `fuel.km/kmSrc/rate/load`.
- **UI:**
  - `components/config/RegulationPanel.jsx` nằm trong RouteFees, thu gọn được. Gồm: nhóm cảng, vùng (sửa tên, chọn tỉnh, thêm/xóa, đưa lên), KCN, định mức dầu, km chặng.
  - Cảnh báo khi tỉnh cũ của kho chưa thuộc vùng nào (`regulationMeta.khoProvinces`).
  - Lộ trình hiện chip tím "quy chế · …", dòng km × định mức ở FuelChip, và cảnh báo thiếu km chặng.
  - Cài đặt › Kho có gợi ý vùng `oldProvinceZone`, lấy từ quy chế của bảng áp dụng hôm nay (chưa có thì lấy mẫu).
- **Thử T9 (rollback):** 36 chuyến khớp tuyến; thêm 140 chuyến khớp theo quy chế (KCN). Phần còn lại chủ yếu là chuyến xuất phát HP mà kho chưa gán tỉnh cũ (0/43 kho). Nếu giả lập gán tỉnh thì được 302/336 chuyến.

**Why:** khai tuyến theo tổ hợp Cảng–Kho–Cảng bùng nổ số dòng. Quy chế tra theo bảng nhỏ (vùng / KCN / chặng km) nên tự phủ mọi tổ hợp; tuyến riêng chỉ còn cho ngoại lệ (vd Canon).

**How to apply:** muốn đổi mức hoặc đổi quy chế từ ngày X thì tạo bảng phí mới từ X rồi sửa khối quy chế, không sửa code. Các giai đoạn sau (cắt kéo, Lạch Huyện, ghép kho, kẹp/kết hợp, ≥3 chuyến/ngày, chuyên cần) cũng phải là số trong khối quy chế, không hard-code.

Liên quan [[route-fee-province-date]], [[route-pays-lo-trinh]], [[payroll-period-plan]], [[json-schema-evolution]].
