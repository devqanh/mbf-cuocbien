---
name: coded-catalog-edit
description: Danh mục có mã (Địa điểm/Kho) cho SỬA ký hiệu — reconcile khớp theo id (giữ id, không đứt link)
metadata:
  type: project
---

Danh mục **có mã** (Địa điểm, Kho — `coded:true` trong CFG_GROUPS) ở Cài đặt giờ **cho sửa cả tên lẫn ký hiệu** (trước đây ô Ký hiệu bị khóa cứng `codeLocked = !!g.coded`).

**Cách giữ an toàn:** `reconcileLookup` (HandlesPricingAndImport) **khớp theo ID trước** (mảng `{key}IdArr` = id theo chỉ số dòng, do `config()` emit trong block `if($coded)`), rồi mới fallback theo code → theo tên-mã-rỗng → tạo mới. Nhờ vậy đổi mã = **update tại chỗ, GIỮ NGUYÊN id** → không đứt `price_rows.location_id` / link kho. (Trước: khớp theo code → đổi mã = xóa+tạo lại row, churn id, đứt link.)

**Wiring idArr (phải giữ thẳng hàng với names + codeArr):**
- Backend `config()`: `$cfg[$key.'IdArr'] = $rows->pluck('id')` (vd `locationsIdArr`, `warehousesIdArr`).
- `cai-dat.jsx` CAT_KEYS + DEFAULT_CFG: thêm `locationsIdArr`/`warehousesIdArr` để gửi khi lưu.
- `config.jsx` ConfigBody: `idArrKey = sel+"IdArr"`; addItem push `null` (mục mới chưa có id), remove filter cùng chỉ số.
- `lo-hang.jsx`: auto-add (addCfg) + saveCatalogKey gửi kèm `{...}IdArr`.
- Ô ký hiệu: `codeLocked = false`. **Kho**: vẫn chặn lưu khi **trùng ký hiệu** (`hasDupCode`→blockSave, mã kho phải duy nhất vì import khớp kho theo code). **Địa điểm**: cờ `allowDupCode:true` trong CFG_GROUPS → KHÔNG chặn trùng mã, cho **nhiều TÊN dùng chung 1 ký hiệu** (`allowDup` tắt isDupCode/hasDupCode + đổi info text).
- Backend đi kèm: trong `reconcileLookup`, khi `$idArr !== null` (payload mới) thì id rỗng = dòng MỚI → **luôn create**, KHÔNG fallback gộp theo code (nếu không, thêm tên trùng mã sẽ đè vào row cũ). Fallback theo code/tên chỉ chạy khi `$idArr === null` (payload cũ/lập trình).

**Why:** user cần sửa ký hiệu cho mục tự thêm (import bảng giá / auto từ lô). Đã verify: đổi code 1 kho giữ id, kho khác không churn. Liên quan [[trucking-report-schema]].
