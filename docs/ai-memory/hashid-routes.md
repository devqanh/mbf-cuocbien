---
name: hashid-routes
description: ID số trong URL đã đổi sang hashid (Optimus+base62 thuần PHP, không cột phụ)
metadata:
  type: reference
---

URL không còn lộ id số tuần tự — dùng **hashid** (khó đoán). Thuần PHP, KHÔNG thêm cột.

- **`App\Support\Hashid`**: Optimus (Knuth multiplicative, 31-bit, PRIME=1580030173 cố định, XOR mask từ APP_KEY) + base62. `encode(int):string`, `decode(?string):?int` (có guard khứ hồi → chuỗi rác trả null). Giới hạn id ≤ 2^31.
- **`App\Concerns\HasHashid`** trait: `getRouteKey()`=encode (route()/link tự ra hashid), `resolveRouteBinding()`=decode→find, helper `hashid()`. Gắn vào: TruckingShipment, TruckingStatement, TruckingTripCostBatch, TruckingVehicle, TruckingDriver, TruckingVehicleCost, TruckingAttachment, **Task**.
- **Nguyên tắc**: GIỮ NGUYÊN `id` số trong DB + JSON + payload (mọi logic đối chiếu/lưu không đổi). Chỉ **thêm field `hashid`** vào serialize (statementsForList/statementToArray, tripBatchesForList/tripBatchToArray, mbfVehicles/assetListRow/vehicleBase, costsOut, spendRequestHistory, driversManaged, shipmentToArray). Frontend dựng URL **path** bằng `.hashid` (body vẫn id số). publicRequestData vehicles/assets giữ id số (value trong body submit).
- Frontend: quan-ly-xe dùng ref `selHash` (FleetApp+AssetApp) cho URL, `selId` cho so khớp; yeu-cau-chi có `editHash`; CostTab onCancel(r.hashid). Deep-link notification (#asset/<id>/cost/<id>) vẫn dùng id SỐ (hash fragment client, không qua server).
- Routes: đã **bỏ `whereNumber`** cho param hashid (cost/tripCost/statement/vehicle/attachment/driver); GIỮ `whereNumber('idx')` (attachment id trong /docs/{idx} vẫn số). Controller `cancelMySpendRequest/updateMySpendRequest` đổi `int $cost`→`string $cost` + `Hashid::decode`. Các method khác model-bound nên trait tự decode.
- **CHƯA áp dụng** cho User/Role (module quản trị) — id số. Cần thì gắn trait + sửa view tương tự.

Deploy: chỉ code (không migration). Đổi APP_KEY = đổi toàn bộ hashid (link cũ chết) — bình thường không đổi. `php artisan route:clear` nếu route cache.
