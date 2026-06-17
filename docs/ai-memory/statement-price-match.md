---
name: statement-price-match
description: "Bảng kê khớp bảng giá theo CẢNG + NHÀ MÁY, quy về ký hiệu (bỏ dấu); có chẩn đoán \"dò\""
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

So khớp giá bảng kê = backend `HandlesStatementPricing::priceShipment` (nguồn chân lý; frontend `makePricer/priceFor` trong ui.jsx ĐÃ legacy, không còn gọi). Dùng bởi statementCandidates / statementReprice / statementsDrift.

**Cấu trúc bảng giá** (`trucking_price_rows`): `from`/`loc` = CẢNG/khu vực, `to1..to4` = **NHÀ MÁY**, `kind` (có khi rỗng), `conn` (Connect/Disconnect), fee 20/40 + dầu. (Dữ liệu thật ghi tên KHÔNG dấu, vd `loc="LACH HUYEN"`.)

**Quy tắc khớp (user chốt 2026-06) — 3 VAI TRÒ + loại + kết nối.** Cột bảng giá: `from`=điểm ĐI · `loc`=điểm HẠ · `to1..to4`=NHÀ MÁY. Lô khớp khi:
- ĐI: `from_loc` == `from` (dòng giá).
- HẠ: `to_loc` == `loc` (dòng giá). ⚠ `loc` là ĐIỂM HẠ, KHÔNG phải khu vực chung — đừng lẫn với `from`.
- NHÀ MÁY: **`kho`** == `to1..to4`. Lô không có kho → không khớp.
- kind: rỗng ở bảng giá = áp mọi loại; có thì so lowercase.
- conn: ưu tiên trùng (Connect/Disconnect ảnh hưởng giá), fallback bỏ qua.
- VD đã verify: lô from_loc=HN, kho=TL, to_loc=LHP, External CRU, Connect → dòng `from=HN to1=TL loc=LACH HUYEN` = 3.150.000 + 2.541.048. Tuyến hiển thị = ĐI → NHÀ MÁY → HẠ.

**Khớp theo KÝ HIỆU, bỏ dấu** (user: "LACH HUYEN cũng là ký hiệu LHP"): `normalizedCodeMap()` build map `Str::ascii→upper(code|name) => KÝ HIỆU` gộp Địa điểm + Kho; `$rc()` quy mọi giá trị (tên có/không dấu, hoặc code) về cùng ký hiệu trước khi so. ⇒ "LẠCH HUYỆN"="LACH HUYEN"="LHP". Đây là lý do trước đây không khớp (so chuỗi thô). Liên quan [[coded-catalog-edit]] (ký hiệu unique) + [[mbf-import]] (lưu ký hiệu vào from_loc/to_loc/kho).

**Chẩn đoán "dò"** (user: kế toán dò khó): `pr.diag = {hasPrice, cang, nhaMay, kind, conn}`. UI bảng kê (ui.jsx StatementDetailBody) khi `!matched` hiện dòng "🔍 Đã dò bảng giá: cảng X → nhà máy Y · loại Z · conn — không có dòng giá khớp / khách chưa có bảng giá". candidateRow thêm field `kho`.

**Lưu theo KÝ HIỆU:** form lô hàng (popups.jsx) lưu `from_loc/to_loc` = mã (locOptions value=code, hiện "Tên — Mã"), `kho` = mã (whCodes). Migration `2026_06_17_000001_normalize_shipment_codes` chuẩn hóa lô CŨ → mã (bỏ dấu, idempotent; VPS chỉ cần `php artisan migrate`). Báo cáo Excel (`StatementExcelExporter`) tự resolve mã→TÊN nên gửi khách vẫn rõ. Khớp giá robust vì $rc + data đã là mã. `statementCandidates` lọc kỳ ở SQL (index gio_xe_ra) cho scale 50k.

**Lưu ý vận hành:** bảng kê ĐÃ LƯU giữ snapshot cũ (matched/phaiThu lúc tạo); phải bấm **Tính lại** để áp logic khớp mới rồi Lưu. Cảnh báo "⚠ chưa khớp" ở ui.jsx ~L397 (xem) / ~L278 (tạo).
