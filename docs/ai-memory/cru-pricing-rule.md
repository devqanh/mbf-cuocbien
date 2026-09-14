---
name: cru-pricing-rule
description: Khớp bảng giá lô CRU — "Internal/External CRU" là theo TUYẾN (External = xuất phát HN/BG/HY/RU), không suy từ Nhập/Xuất; chặng nhập CRU hạ tại bãi "(IM …)" khớp dòng CRU có to1 = bãi; free time cần Giờ xe đến THỰC TẾ
metadata:
  type: project
---

**Sự cố 2026-09-14** (lô 689 TEMU7457627, Canon Quế Võ, Xuất+CRU, QV(IM HPP) → kho 1 → HÀ HƯNG HẢI): "chưa khớp giá" + không ra Connect/Disconnect.

**Ý nghĩa KIND trong bảng giá Canon (đã xác nhận với user, book 31/7–31/10/2026, 496 dòng):**
- `Transportation 1 way of Import/Export`: from = cảng/ICD → to1..to4 = nhà máy (+ to4 = cảng trả) → loc = cảng.
- `Internal CRU transportation`: **CẢ 2 chặng** của chuỗi tái sử dụng cont quanh QV/TL/TS:
  - chặng NHẬP: from = cảng (HPP/ICDQV/ICDTP), **to1 = bãi "X(IM cảng)"** (duy nhất 1 điểm đến, không to4), loc = cảng bất kỳ (giá KHÔNG đổi theo loc);
  - chặng XUẤT: from = bãi "X(IM …)", to1 = nhà máy, to4 = cảng, loc = cảng (giá đổi theo cảng).
- `External CRU transportation`: chỉ xuất phát ngoài vùng (HN/BG/HY; Msk: RU), toàn Connect.
→ Code cũ gán `CRU + Xuất = External`, `CRU + Nhập = Internal` là SAI → chặng xuất QV(IM HPP)→QV→HPP không bao giờ khớp.

**Quy tắc mới (`priceShipment`, HandlesStatementPricing):** lô tích CRU khớp **cả 2 kind CRU**, tuyến quyết định (`matchPriceRow` nhận mảng kind); `kind` trả về = kind của dòng khớp. Nếu khớp thường thất bại và lô có Nơi hạ → thử **chặng nhập CRU**: chỉ các dòng CRU có ĐÚNG 1 điểm đến (`count(rcKho)===1 && rcKho[0]===loDrop`), from = nơi lấy, bỏ loc/kho, không cần cờ CRU (chặng nhập thường không tích). Phải siết "đúng 1 điểm đến" — bản đầu (khớp mọi to1..to4) làm 4 lô Msk RU→TL→HATECO lọt sang giá External CRU dù không tích CRU. Diag có `yardLeg`.

**Free time / Connect-Disconnect** = Giờ xe ra − **Giờ xe đến (thực tế, `gio_xe_den`)**; "KH đến" (`gio_den_du_kien`) KHÔNG dùng. Thiếu Giờ xe đến → conn null → KHÔNG chặn khớp giá (fallback dòng đầu tiên cùng tuyến) nhưng tier có thể sai → phải nhập Giờ xe đến. Danh sách /lo-hang cột "Đến:" là `contDen` (khác gio_xe_den) — dễ gây hiểu nhầm.

**Cách verify (đọc thuần):** `statementCandidates(tên khách, from, to)` trả `pr{matched,kind,conn,cuoc,diag}`; quét toàn bộ khách 1 kỳ → JSON trước/sau → diff (kịch bản `match-scan.php`/`match-diff.php` trong scratchpad, 311 lô 08–09/2026: 200 → 203 khớp). Thử tier bằng `DB::beginTransaction()` sửa gio_xe_den rồi `rollBack()`.

**Dữ liệu còn lệch (không phải code):** 542 (LẠCH HUYỆN → QV → QV(IM HPP)) — bảng giá Canon không có dòng nhập CRU từ LHP (chỉ HPP/ICDQV/ICDTP); 644/645/657/658 Msk RU→TL→HATECO chưa tích CRU nên không khớp External CRU; 456 Msk HN→TL→HATECO: book Msk chỉ có External từ RU; 568 QV(IM HPP)→kho 2→ICD Quế Võ không có dòng (bãi IM HPP chỉ xuất về HPP/LHP).

Liên quan [[price-books-by-date]] [[price-by-cont-type]] [[location-value-is-name]].
