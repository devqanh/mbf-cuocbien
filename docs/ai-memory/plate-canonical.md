---
name: plate-canonical
description: BKS vào/ra Lô hàng luôn lưu dạng chuẩn 15C-15877 qua mutator model + PlateNormalizer::canonical (dùng chung danh mục xe)
metadata:
  node_type: memory
  type: project
  originSessionId: e4e2c5db-8a59-4fc6-88ee-68cb3b2cba14
  modified: 2026-10-29T08:37:00.160Z
---

Biển số ở Lô hàng (bks_vao, bks_ra) tự chuẩn hóa khi lưu bằng mutator trên TruckingShipment → App\Support\PlateNormalizer::canonical ("15C 15877"/"15c-158.77"/"15C15877" → "15C-15877"; chuỗi không phải 1 biển như "50E-36177 - 50H-16679" chỉ viết hoa + gộp khoảng trắng). normalizePlate() của danh mục xe gọi lại cùng hàm. Dữ liệu cũ đã sửa bằng migration 2026_10_29_000001_canonicalize_shipment_plates (kèm route_pays.bks, ghi query thẳng, không đẻ activity log).

**Why:** user thấy danh sách Lô hàng lẫn "29E-28922" và "15C 15877" — trước đây lô lưu nguyên văn người gõ, chỉ danh mục xe chuẩn hóa.

**How to apply:** ghi biển vào lô qua model (fill/assign) để mutator chạy; nếu buộc phải `->update([...])` bằng query thì tự gọi PlateNormalizer::canonical. So khớp biển giữa nguồn khác nhau vẫn dùng PlateNormalizer::norm. Liên quan [[gps-tracking]], [[route-trips]].
