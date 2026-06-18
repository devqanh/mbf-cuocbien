---
name: verify-no-destructive-save
description: KHÔNG gọi saveCatalog/reconcile thật khi verify — nó xóa dữ liệu; dùng transaction rollback
metadata: 
  node_type: memory
  type: feedback
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Khi kiểm thử (tinker) các hàm LƯU danh mục Trucking, TUYỆT ĐỐI không gọi thẳng trên DB thật.

`saveCatalog($key, $cfg)` → `reconcileLookup` chạy `whereNotIn('id', $keepIds)->delete()` → **XÓA mọi dòng không có trong payload**. Một lần gọi test `saveCatalog("warehouses", [2 dòng])` đã xóa sạch danh mục Kho local còn 2 dòng test.

**Why:** reconcile = "đồng bộ toàn bộ danh sách", không phải "thêm 1 dòng". Payload thiếu = xóa.

**How to apply:** Verify hàm save/reconcile bằng `DB::transaction(function(){ ...; throw new \Exception("rollback"); })` rồi nuốt exception, HOẶC chỉ test hàm đọc (config/catalogData), HOẶC tạo bản ghi nháp rồi xóa. Tổng quát: trước khi chạy lệnh GHI trên DB local (laragon/trucking.dewa.vn) để "thử", hỏi/chắc chắn nó không phá dữ liệu. Liên quan [[trucking-redesign]] (reconcile delete+recreate), [[no-seed-demo]].

**⚠️ LẶP LẠI 2 LẦN (2026-06-18) — RẤT QUAN TRỌNG:** `saveRouteFees([...])` rồi `saveRouteFees([])` trong tinker để "dọn test" đã **XÓA SẠCH phí tuyến** của user 2 lần (user phải nhập lại). `trucking.dewa.vn` = TUNNEL ra DB local `cuocbien_dev` → ghi tinker local = đụng dữ liệu user đang dùng. **QUY TẮC CỨNG: KHÔNG chạy BẤT KỲ lệnh ghi DB nào (saveRouteFees/saveCatalog/save*/create/update/delete) trong tinker để test.** Chỉ lint + build + đọc. Nếu buộc phải test ghi: transaction rollback hoặc DB riêng. Khôi phục: dùng `Model::create(...)` ADDITIVE (không saveRouteFees).
