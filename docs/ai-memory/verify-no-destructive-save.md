---
name: verify-no-destructive-save
description: KHÔNG gọi saveCatalog/reconcile thật khi verify — nó xóa dữ liệu; dùng transaction rollback
metadata:
  type: feedback
---

Khi kiểm thử (tinker) các hàm LƯU danh mục Trucking, TUYỆT ĐỐI không gọi thẳng trên DB thật.

`saveCatalog($key, $cfg)` → `reconcileLookup` chạy `whereNotIn('id', $keepIds)->delete()` → **XÓA mọi dòng không có trong payload**. Một lần gọi test `saveCatalog("warehouses", [2 dòng])` đã xóa sạch danh mục Kho local còn 2 dòng test.

**Why:** reconcile = "đồng bộ toàn bộ danh sách", không phải "thêm 1 dòng". Payload thiếu = xóa.

**How to apply:** Verify hàm save/reconcile bằng `DB::transaction(function(){ ...; throw new \Exception("rollback"); })` rồi nuốt exception, HOẶC chỉ test hàm đọc (config/catalogData), HOẶC tạo bản ghi nháp rồi xóa. Tổng quát: trước khi chạy lệnh GHI trên DB local (laragon/trucking.dewa.vn) để "thử", hỏi/chắc chắn nó không phá dữ liệu. Liên quan [[trucking-redesign]] (reconcile delete+recreate), [[no-seed-demo]].
