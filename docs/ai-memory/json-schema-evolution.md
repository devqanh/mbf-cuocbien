---
name: json-schema-evolution
description: "Quy ước tiến hóa schema cho cột JSON (extra_fees, salary_parts, rev/cost, free_time_rules…) — thêm trường phải tương thích ngược"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: b68a982e-c908-47ea-ac62-63eae4bc49de
---

Các cột JSON trong Trucking (vd `trucking_route_fees.extra_fees` = list `{name,amount,perDay}`, `salary_parts`, shipment `rev`/`cost`, `free_time_rules`) phải **tiến hóa tương thích ngược**: record cũ KHÔNG được vỡ khi thêm trường mới.

**Why:** user lo "JSON 3 trường sau lên 5 trường record cũ có lỗi không". Trả lời: KHÔNG, nếu đọc đúng cách.

**How to apply:**
- Luôn đọc mỗi trường KÈM MẶC ĐỊNH (FE: `f.x || ""`, `!!f.flag`; BE: `$f['x'] ?? default`, `!empty(...)`). Cột để `json nullable` + đọc `(array)($v ?? [])`.
- CHỈ THÊM trường tùy chọn → tương thích ngược (record cũ thiếu trường → rơi về default, không crash).
- TRÁNH: đổi TÊN trường đang dùng / đổi KIỂU–Ý NGHĨA trường cũ / bắt buộc trường mới phải tồn tại (đọc không default). Nếu buộc phải đổi → backfill dữ liệu hoặc đọc cả key cũ+mới.

Liên quan [[route-pays-lo-trinh]] (extra_fees repeater).
