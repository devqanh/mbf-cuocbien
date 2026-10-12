# Phương án: Lương chuyến – Phụ cấp đi đường – Dầu khoán theo Quy chế 03/2026/QC-MBF

Status: GIAI ĐOẠN 1 ĐÃ LÀM (08/10/2026, chưa commit) — khối quy chế TÙY CHỈNH trên từng bảng phí tuyến
(`book.regulation`: nhóm cảng zone|kcn, vùng tên + tỉnh cũ, KCN, định mức dầu, km chặng) + engine Lộ trình.
Thay cho mục 3.1 cũ: KHÔNG thêm cột vùng/km vào kho — kho chỉ gán tỉnh cũ, km lấy từ bảng chặng. Giai đoạn 2–3 còn lại.
Nguồn: "Phụ lục quy chế 2026 – update 07.09.2026" (áp dụng từ 07/09/2026)
Liên quan: Cài đặt › Phí tuyến (`#routeFees`), Cài đặt › Kho (`#warehouses`), Lộ trình (`/lo-trinh`), Kỳ lương (`/phi-xe`).
Thay thế đề xuất trước: `plans/261008-0026-phi-tuyen-all-in-one` (gộp vào đây, khớp đúng quy chế).

---

## 1. Quy chế → các thành phần cần tính

| # | Thành phần | Cách tính trong quy chế | Phạm vi |
|---|---|---|---|
| A | **Lương chuyến** | Theo **vùng đến** (xuất phát Hải Phòng) hoặc theo **KCN** (xuất phát ICD Quế Võ / Sowatco Tri Phương) | mỗi chuyến |
| B | **Phụ cấp đi đường** ("Đường") | Như A, cột riêng | mỗi chuyến |
| C | **Dầu khoán** | **Km × định mức L/100km**: xe 2 cầu 35; xe 1 cầu Hàng–Rỗng 26, Hàng–Hàng 30 | mỗi chuyến |
| D | Trợ cấp cộng vào "Đường" | Lạch Huyện 1 chiều +50k · Quay đầu tuyến ngoài HP +100k · Lưu ca xe +300k | mỗi chuyến (có/không) |
| E | Chạy ≥ 3 chuyến/ngày | +100k vào "Đường" | mỗi ngày |
| F | **Ghép kho** | Lương = chuyến xa nhất · phụ cấp thêm theo **bậc km chạy thêm** (<45km 100k … 350–400km 500k); ICD TP: ghép < 60km +100k | mỗi chuyến |
| G | **Kẹp cont / Kết hợp** | Lương + phụ cấp = chuyến xa nhất · +300k phụ cấp · lương +100k (tuyến dài) / +50k (tuyến ngắn) | mỗi chuyến |
| H | **Cắt kéo container** | Phụ cấp: Hàng–Hàng Dài–Dài +150k, Dài–Ngắn +50k; Rỗng–Rỗng Dài–Dài −150k, Dài–Ngắn −50k | mỗi chuyến |
| I | **Chuyên cần** | 3.000.000đ/tháng nếu ≥ 24 công (gồm ngày công ty không xếp xe); xin nghỉ < 24 công → 0; tháng lễ tết: 3.000.000/30 × công thực tế | mỗi lái / tháng |
| J | Dầu đoạn Bãi ↔ ICD (lấy vỏ, đi mooc) | Công ty trả dầu theo **km thực tế** | phát sinh |

Bảng tiền (A, B) và km quy chế:
- **Xuất phát Hải Phòng** (theo vùng, km 1 chiều đo theo kho): Hải Phòng 100k/300k · Hải Dương, HY chưa qua trạm 200k/400k · Thái Bình 250k/400k · HY qua trạm 250k/450k · HN, BN, BG, Hà Nam, NB, NĐ 300k/500k · TN, PT, HB, VP, TH, LS, TQ, BK 450k/600k · YB, NA, CB, HG 650k/800k.
- **ICD (Quế Võ)**: KCN Quế Võ 150k/200k/40km · Tiên Sơn 150k/250k/64km · Thăng Long 200k/300k/150km (km khứ hồi).
- **Sowatco Tri Phương (TP)**: KCN Quế Võ 150k/200k/60km · Tiên Sơn 150k/200k/40km · Thăng Long 250k/200k/130km · Ghép kho +100k (<60km).

## 2. Đối chiếu hệ thống hiện tại (đo T9/2026)

- **Phí tuyến** khai theo chuỗi node (Cảng–Kho–Cảng) + lít dầu cố định từng tuyến → chỉ 36/308 chuyến khớp (12%). Số lít hiện có thực chất = km × định mức (280km × 35% = 98L; × 26% = 73L) → nên **lưu km, tự tính lít**.
- **Xe chưa khai số cầu**: 58/61 xe trống, 3 xe "tai" → hiện MỌI xe bị tính dầu như 1 cầu. *Bắt buộc khai trước.*
- **Tỉnh của kho** dùng 34 tỉnh SAU sáp nhập; quy chế dùng tên CŨ (Hải Dương ≠ Hải Phòng, Thái Bình ≠ Hưng Yên, HY chia "chưa/qua trạm") → **không dùng tỉnh để tra lương được**, cần **Vùng lương** gán theo từng kho.
- **KCN = ký hiệu kho** sẵn có: QV (10 kho con), TS, TL → tra bảng ICD/TP trực tiếp.
- **Hàng / Rỗng suy được** từ Nhập/Xuất: cont Nhập = vào Hàng, ra Rỗng; Xuất = vào Rỗng, ra Hàng; cắt kéo (`ra_mode=other`) = lô vào + lô bị kéo ra. T9: 146 "Xuất vào → Xuất ra" (Rỗng–Hàng, bình thường), 2 Hàng–Hàng, 1 Rỗng–Rỗng.
- **Ghép kho** = lô có nhiều kho (T9: 2 lô). **Kẹp cont / Kết hợp / Quay đầu / Lưu ca**: hệ thống chưa có dữ liệu → cần tick ở Lộ trình.
- **Kho có tọa độ**: 19/43 → ước tính được km ban đầu (đường chim bay × hệ số), cho sửa.
- Đã có sẵn và tái dùng: bảng phí theo ngày (= phiên bản quy chế), Áp dụng cho khách, sửa lít/chi hộ từng chuyến, kỳ lương theo biển số.

## 3. Mô hình đề xuất

### 3.1 Định danh (khai 1 lần)
**Cảng** (Cài đặt › Địa điểm): thêm **Nhóm xuất phát** = `Hải Phòng` | `ICD Quế Võ` | `Sowatco Tri Phương` | (khác); cờ **Lạch Huyện** (LHP).
**Kho** (Cài đặt › Kho), thêm 2 cột:
- **Vùng lương** (chọn 1 trong 7 vùng quy chế; gợi ý theo tỉnh, sửa được — vd kho Phố Nối = "HY chưa qua trạm").
- **Km 1 chiều từ Hải Phòng** (gợi ý từ tọa độ). Km tuyến ICD/TP lấy theo bảng KCN (đã có trong quy chế).
**Vùng lương**: mỗi vùng có cờ **Dài / Ngắn** (dùng cho cắt kéo, kẹp cont).
**Xe** (Cài đặt › Đội xe): **số cầu 1 / 2** (bắt buộc).

### 3.2 Bảng quy chế (thay Phí tuyến hiện tại, vẫn theo "áp dụng từ ngày")
Trang `#routeFees` thành 1 trang "Quy chế lương chuyến" gồm các khối:
1. **Bảng vùng — xuất phát Hải Phòng**: Vùng · Lương chuyến · Phụ cấp · Dài/Ngắn.
2. **Bảng KCN — xuất phát ICD Quế Võ / Sowatco TP**: KCN (ký hiệu kho) · Lương · Phụ cấp · Km khứ hồi.
3. **Định mức dầu (L/100km)**: 1 cầu Hàng–Rỗng 26 · Hàng–Hàng 30; 2 cầu Hàng–Rỗng 35 · Hàng–Hàng 40.
4. **Trợ cấp & điều chỉnh** (số tiền sửa được): Lạch Huyện, Quay đầu, ≥3 chuyến/ngày, Lưu ca, Cắt kéo (4 ô), Kẹp/Kết hợp (+300k, +100k dài, +50k ngắn).
5. **Bậc ghép kho** (km → tiền) + ghép kho tuyến TP.
6. **Chuyên cần**: mức thưởng, ngưỡng công, cách chia ngày lễ.
7. **Ngoại lệ theo khách** (giữ "Áp dụng cho khách"): tuyến cụ thể cho khách đặc biệt, ưu tiên trước bảng chung.
- Dùng tiếp "Bảng phí theo ngày": quy chế mới = tạo bảng từ 07/09/2026, chuyến trước đó theo bảng cũ.

### 3.3 Cách tính 1 chuyến (engine — thay `legPayGroup`)
1. **Nhóm xuất phát** = nhóm của Nơi lấy (không có thì Nơi hạ).
2. **Kho đích** = kho XA NHẤT của chuyến (ghép kho → xa nhất; quy chế "tính mức chuyến xa nhất").
3. **Tra tiền**: ngoại lệ khách → bảng KCN (nếu xuất phát ICD/TP) → bảng Vùng (xuất phát HP) → (không khớp: cảnh báo như hiện tại).
4. **Km** = km quy chế (KCN) hoặc km 1 chiều của kho × 2; chuyến lấy/hạ khác nơi: cộng 2 đoạn thực tế.
5. **Hàng/Rỗng** từ Nhập/Xuất của cont vào + cont ra → H–R | H–H | R–R.
6. **Dầu** = km × định mức (số cầu; xe 1 cầu xét H–H/H–R). Lít sửa tay ở Lộ trình vẫn ưu tiên.
7. **Cộng/trừ** vào "Đường": cắt kéo (H–H/R–R × Dài/Ngắn), Lạch Huyện (nếu lấy/hạ LHP), ghép kho (bậc km chạy thêm), kẹp/kết hợp, quay đầu, lưu ca.
8. **Theo ngày**: ≥3 chuyến trong ngày vận hành → +100k (1 lần/ngày).
Kết quả mỗi chuyến tách dòng: Lương chuyến · Phụ cấp đi đường · từng khoản trợ cấp · Dầu (lít × giá) — hiện thành chip ở Lộ trình, chi trong ngày / kỳ lương / chi hộ như hiện tại.

### 3.4 Kỳ lương (`/phi-xe`)
Mỗi lái: Σ lương chuyến · Σ phụ cấp & trợ cấp · **Chuyên cần** (nhập số ngày xin nghỉ → công = ngày trong kỳ − nghỉ; ≥ 24 → 3tr; tháng lễ: 3tr/30 × công) · đã chi theo ngày (tính lương) · chi hộ (riêng) · **còn phải trả**.

## 4. Ví dụ (chuyến thật T9, số km minh họa)
| Chuyến | Tra | Lương | Phụ cấp | Km | Dầu |
|---|---|---|---|---|---|
| ICDQV → QV → ICDQV, 1 cầu, Xuất (R–H) | KCN QV / ICD | 150k | 200k | 40 | 40×26% = 10,4L |
| ICDTP → TL → ICDTP, 1 cầu (H–R) | KCN TL / TP | 250k | 200k | 130 | 33,8L |
| HPP → QV → LHP, 2 cầu, cắt kéo Xuất→Xuất (R–H) | Vùng HN-BN… | 300k | 500k + 50k (Lạch Huyện) | 2×km(QV) | km×35% |
| HPP → kho Bắc Ninh, cắt kéo Nhập→Xuất (H–H), Dài–Dài | Vùng HN-BN… | 300k | 500k + 150k | … | … |
| Ghép 2 kho, chạy thêm 60km | kho xa nhất | lương xa nhất | + 150k (bậc 45–100km) | … | … |

## 5. Thay đổi theo trang
- **Cài đặt › Kho**: cột Vùng lương, Km 1 chiều từ HP (gợi ý từ tọa độ); lọc "kho chưa có vùng".
- **Cài đặt › Địa điểm**: Nhóm xuất phát, cờ Lạch Huyện.
- **Cài đặt › Đội xe**: số cầu (nhắc 58 xe chưa khai).
- **Cài đặt › Phí tuyến** → "Quy chế lương chuyến" (mục 3.2); tuyến cũ giữ làm ngoại lệ / dự phòng.
- **Lộ trình**: chip tách khoản theo quy chế; mỗi chuyến chọn nhanh **Kẹp cont · Kết hợp · Quay đầu · Lưu ca** (lưu `trip_overrides`, như chi hộ / sửa lít); hiện H–R/H–H và vùng/KCN đã tra.
- **Kỳ lương**: khối chuyên cần + tổng hợp theo lái.

## 6. Lộ trình triển khai
1. **Định danh + bảng quy chế + engine** (Lộ trình tính theo quy chế; phí tuyến cũ làm dự phòng khi chưa khai vùng/km).
2. **Loại chuyến & quy tắc ngày** (kẹp/kết hợp/quay đầu/lưu ca, ≥3 chuyến/ngày, ghép kho bậc km).
3. **Kỳ lương**: chuyên cần + tổng hợp theo lái.
Mỗi bước: kiểm thử bằng chuyến thật T9 trong transaction rollback; so tổng lương T9 theo quy chế với bảng lương kế toán đang tính tay.

## 7. Đã chốt (user 08/10/2026)
1. **Phụ cấp đi đường = Tiền đường**, chi lái hằng ngày (tính lương). **Vé trạm = chi hộ**, chi lái hằng ngày. → ĐÃ LÀM: cố định ở Phí tuyến + Lộ trình (khóa loại), kỳ lương tách chi hộ.
2. **Quay đầu tuyến ngoài Hải Phòng** = trong ngày, chuyến trước đi ngoài HP và chuyến sau cũng đi ngoài HP (vd 2 chuyến Hải Dương) → +100k trợ cấp, trả trong ngày. → ĐÃ LÀM (mỗi chuyến ngoài HP nối tiếp chuyến ngoài HP +100k; xe ra không kéo cont không xét).
3. Xét theo **TỈNH CŨ** (không dùng "vùng" trừu tượng): kho gán **Tỉnh cũ (quy chế)** — 63 tỉnh cũ, Hưng Yên tách chưa/qua trạm; vùng lương suy từ tỉnh cũ. → ĐÃ LÀM cột `trucking_warehouses.old_province` + ô ở header nhóm kho + gợi ý từ địa chỉ.
4. **Kẹp cont** = kẹp 2 cont 20' đi đóng hàng — hiện chỉ ghi ở ô ghi chú lô (T9: 3 lô "KẸP CONT …", có ghi số cont kẹp cùng) → đề xuất ô đánh dấu "Kẹp cont" (+ cont kẹp cùng) ở lô hàng. **Kết hợp** = cont nhập lấy ở HP trả nhà máy rồi dùng luôn sang nhà máy khác đóng hàng xuất — kế toán nhập Nơi lấy = **RU** (T9: 5 lô Xuất "RU → TL/TS → …") → nhận diện tự động theo Nơi lấy RU.

5. **Định mức dầu** (L/100km): xe 1 cầu Hàng–Rỗng 26 · Hàng–Hàng 30; xe **2 cầu** Hàng–Rỗng **35** · Hàng–Hàng **40** (user: 2 cầu tuyến ICD/TP "cũng chia như xe 1 cầu").
6. **Chuyên cần**: kỳ lương theo **tháng dương lịch**; **số công = số ngày (ngày vận hành 08:00 → 08:00) lái có lương chuyến** trong tháng → ≥ 24 công: 3.000.000đ; tháng lễ: 3.000.000/30 × công. (Quy chế còn tính "ngày công ty không xếp xe" → cho kế toán cộng tay.)
7. **≥ 3 chuyến/ngày**: tính **theo lái**, ngày **08:00 → 08:00** hôm sau → +100k trợ cấp (Đường).
8. Lo ngại "gán vùng theo kho phải nhập nhiều định mức": KHÔNG — mức tiền nhập theo **7 vùng, 1 lần** trong bảng quy chế; kho chỉ chọn **tỉnh cũ** 1 lần cho mỗi nhóm ký hiệu (đã làm).

9. Định mức **2 cầu Hàng–Hàng 40** áp cho **mọi tuyến** (cả Hải Phòng).
10. **Quay đầu**: +100k **mỗi lần** quay đầu (3 chuyến Hải Dương liên tiếp = +200k) — đúng như đã làm.
11. **Kẹp cont / Kết hợp**: thêm ô đánh dấu ở lô hàng → ĐÃ LÀM (`trucking_shipments.kep_cont`, `ket_hop`, migration 2026_10_08_000002 đánh dấu sẵn: Nơi lấy RU → Kết hợp; ghi chú "kẹp cont" → Kẹp cont). Hiện ở popup lô (cạnh Hàng CRU), nhãn ở danh sách lô và Lộ trình.

12. **Lấy và hạ khác nhóm** (sổ tay kế toán 08/10): có MỨC RIÊNG theo tuyến, không theo bảng HP hay TP:
    - HP – Canon Bắc Ninh – ICD: tiền đường (phụ cấp) 300.000 · lương 150.000
    - HP – Canon Thăng Long – ICD: tiền đường 400.000 · lương 200.000
    → khai bằng **tuyến riêng** ở Phí tuyến (Nơi lấy → KCN → Nơi hạ, + "Áp dụng cho khách"), ưu tiên TRƯỚC bảng vùng/KCN.
13. **Km = bảng khoảng cách giữa các điểm** (sổ tay kế toán) → km chuyến = cộng các chặng theo lộ trình thực tế:

| Điểm A | Điểm B | Km | | Điểm A | Điểm B | Km |
|---|---|---|---|---|---|---|
| Hải Phòng | Quế Võ | 121,5 | | Quế Võ | Thăng Long | 55 |
| Hải Phòng | Thăng Long | 143,5 | | Thăng Long | Tiên Sơn | 35 (dòng cuối ghi TS → TL 40) |
| Hải Phòng | Tiên Sơn | 133 | | Tiên Sơn | Quế Võ | 20 |
| Hải Phòng | CEV | 97,5 (dòng dưới đọc như 91,5) | | CEV | Thăng Long | 40 |
| Hải Phòng | ICD Quế Võ | 90 | | CEV | Tiên Sơn | 30 |
| Hải Phòng | VSIP | 123 | | ICD Quế Võ | Thăng Long | 85 |
| | | | | ICD Quế Võ | Tiên Sơn | 35 |
| | | | | ICD Quế Võ | Quế Võ | 20 |
| | | | | VSIP | Quế Võ | 23 |
| | | | | VSIP | ICD Quế Võ | 40 |

    Lưu ý: bảng ICD trong quy chế ghi khứ hồi ICD–TS–ICD 64km, ICD–TL–ICD 150km (≠ 2×35, 2×85 của sổ tay) → tuyến ICD/TP dùng km QUY CHẾ, tuyến khác dùng bảng chặng. Chưa có km Hải Phòng ↔ ICD Tri Phương.

14. **Bỏ danh sách tuyến** (08/10): Lộ trình tính MỌI chuyến theo quy chế; trang Phí tuyến → "Quy chế lương chuyến". Vé trạm khai theo CHẶNG (cộng như km).
15. **HP → QV → ICDTP**: km = 121,5 + 30 đúng; tiền đường KHÔNG có định mức → nhập tay từng chuyến ở popup Chi lái (gợi ý mức vùng), lương theo bảng vùng.
16. **ICD/TP → hạ HP** (vd ICDQV → TS → HPP): = 1 chuyến HP – Bắc Ninh (500k đường, 300k lương) vì lái về HP rồi phải lên lại → tra bảng vùng theo tỉnh cũ của kho.

## 8. Còn cần chốt
1. Xác nhận 2 ô chữ viết tay: HP–CEV **97,5 hay 91,5**; Thăng Long ↔ Tiên Sơn **35 hay 40** (hoặc 2 chiều khác nhau).
2. Mức "HP – Canon Bắc Ninh – ICD" chỉ áp cho **khách Canon** hay cho **mọi khách** chạy HP → Bắc Ninh → ICD? "Canon Bắc Ninh" gồm KCN Quế Võ + Tiên Sơn? "ICD" là ICD Quế Võ?
3. Km Hải Phòng ↔ ICD Tri Phương (cho 15 chuyến HPP → QV → ICDTP T9).
