---
name: coded-catalog-edit
description: "Danh mục có mã (Địa điểm/Kho) cho SỬA ký hiệu — reconcile khớp theo id (giữ id, không đứt link)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

Danh mục **có mã** (Địa điểm, Kho — `coded:true` trong CFG_GROUPS) ở Cài đặt giờ **cho sửa cả tên lẫn ký hiệu** (trước đây ô Ký hiệu bị khóa cứng `codeLocked = !!g.coded`).

**Cách giữ an toàn:** `reconcileLookup` (HandlesPricingAndImport) **khớp theo ID trước** (mảng `{key}IdArr` = id theo chỉ số dòng, do `config()` emit trong block `if($coded)`), rồi mới fallback theo code → theo tên-mã-rỗng → tạo mới. Nhờ vậy đổi mã = **update tại chỗ, GIỮ NGUYÊN id** → không đứt `price_rows.location_id` / link kho. (Trước: khớp theo code → đổi mã = xóa+tạo lại row, churn id, đứt link.)

**Wiring idArr (phải giữ thẳng hàng với names + codeArr):**
- Backend `config()` (full) VÀ `catalogData($key)` (lazy mỗi tab) ĐỀU phải emit `$out[$key.'IdArr']` trong block `if($coded)`. ⚠ Bug đã gặp: `catalogData()` quên IdArr → frontend gửi mảng rỗng khi lưu → reconcile coi mọi dòng là mới → xóa+tạo lại, đứt link. Sửa ở HandlesCatalog.php (commit 6b235bc).
- `cai-dat.jsx` CAT_KEYS + DEFAULT_CFG: thêm `locationsIdArr`/`warehousesIdArr` để gửi khi lưu.
- `config.jsx` ConfigBody: `idArrKey = sel+"IdArr"`; addItem push `null` (mục mới chưa có id), remove filter cùng chỉ số.
- `lo-hang.jsx`: auto-add (addCfg) + saveCatalogKey gửi kèm `{...}IdArr`.
- Ô ký hiệu: `codeLocked = false`. **CẢ Địa điểm LẪN Kho** giờ `allowDupCode:true` (user 2026-06-22, kho theo luồng địa điểm) → KHÔNG chặn trùng mã, cho **nhiều TÊN dùng chung 1 ký hiệu**; `allowDup` tắt isDupCode/hasDupCode + đổi info text. (Trước kho chặn dup — đã bỏ.)
- **Kho render trong NHÁNH GROUPED (allowDup) kèm Địa chỉ + Ghim GPS** (config.jsx): nhánh gom-theo-ký-hiệu vốn chỉ có ô tên (cho Địa điểm) — đã mở rộng: mỗi TÊN kho có `AddrInput` + nút "Ghim BĐ" (g.addressed/g.geo); `addRow` đẩy thêm `warehouseAddrArr`/`warehouseGeoArr` rỗng để THẲNG HÀNG theo chỉ số với codeArr/idArr; `MapPicker` chuyển ra render DÙNG CHUNG sau cả 2 nhánh coded. Nhãn UI dùng biến `noun` (kho/địa điểm) từ `g.codeNameLabel`. Backend `reconcileLookup` đã persist address/lat/lng cho warehouses (warehouseAddrArr/GeoArr) + match theo id nên dup-code kho OK.
- **Chọn Kho ở Lô hàng** (popups `whCodes`): DEDUPE mã (`[...new Set(...)]`) vì nhiều tên cùng 1 mã → MultiCombo chỉ hiện 1 mã. Lưu/khớp theo MÃ nên nhiều tên/1 mã không ảnh hưởng bảng kê/phí tuyến (normalizedCodeMap gộp cả locations+warehouses).
- Backend đi kèm: trong `reconcileLookup`, khi `$idArr !== null` (payload mới) thì id rỗng = dòng MỚI → **luôn create**, KHÔNG fallback gộp theo code (nếu không, thêm tên trùng mã sẽ đè vào row cũ). Fallback theo code/tên chỉ chạy khi `$idArr === null` (payload cũ/lập trình).
- ⚠ Cạm bẫy flexbox (commit a2eddd0): thẻ nhóm Địa điểm nằm trong container `display:flex;flexDirection:column;maxHeight;overflowY:auto`. NHIỀU thẻ → flex co (shrink) mỗi thẻ cho vừa maxHeight thay vì cuộn → thẻ bị bóp + `overflow:hidden` → hiện thành **thanh xám rỗng** (1 nhóm thì vừa nên không lộ; rất khó đoán). Fix: `flexShrink:0` trên mỗi thẻ. Bài học: item cao trong flex-column có maxHeight phải set flexShrink:0.

**Why:** user cần sửa ký hiệu cho mục tự thêm (import bảng giá / auto từ lô). Đã verify: đổi code 1 kho giữ id, kho khác không churn. Liên quan [[trucking-report-schema]].

**Bản LỖI catalog code==name có dấu — đã dọn (commit `6a99c39`, 2026-06-25). CHỐT QUAN TRỌNG:** LÔ HÀNG **GIỮ NGUYÊN tên cảng cụ thể** (vd "SITC ĐÌNH VŨ","HATECO","TÂN VŨ") trong from_loc/to_loc/barge_drop — KHÔNG gộp về ký hiệu. Ký hiệu (HPP/LHP/ICDQV) chỉ dùng để **khớp giá / gộp báo cáo / hiển thị ở Lộ trình** (qua codeMap/locCode), KHÔNG ghi vào lô. (Hướng "gộp lô về ký hiệu" ban đầu là SAI — đã revert; user phản hồi booking 39559003 phải là "SITC ĐÌNH VŨ" chứ không phải HPP.)
Bug gốc: `registerLocationCode` tạo bản `code==name` có dấu TV (vd id "TÂN VŨ"→"TÂN VŨ") song song alias đúng ("TÂN VŨ"→"HPP") → 1 tên có 2 ký hiệu, khớp giá sai. **KÝ HIỆU không bao giờ có dấu TV; chỉ TÊN mới có.** Migration `2026_06_25_000009` (CHỈ DỌN CATALOG, KHÔNG đụng lô, idempotent): bản lỗi CÓ alias sạch cùng tên → XÓA (alias đúng giữ lại); bản lỗi KHÔNG có alias (ICD Quế Võ) → SỬA code=ICDQV (giữ tên); đảm bảo alias ICD Quế Võ→ICDQV tồn tại. `locCode()` (HandlesTripAndDrivers, public) map tên→ký hiệu cho Lộ trình + dò. Xem [[lo-hang-location-filters]].
