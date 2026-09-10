---
name: deploy-aapanel-build
description: Deploy prod bằng aaPanel (script git pull → migrate → npm run build); build đổ "node_modules/.bin/vite: Permission denied" do panel chmod 644 → scripts/build.cjs tự cấp lại +x và gọi vite.js bằng node
metadata:
  type: project
---

**Prod** `mbf.dewa.vn` = VPS aaPanel `172.104.56.116` (user root; thư mục `/www/wwwroot/mbf.dewa.vn`, owner `www`). Deploy = nút Git deployment của aaPanel chạy script `/www/server/panel/data/deploy_script_git/mbf.dewa.vn_deploy_deploy.sh`: `git pull origin main` → `php artisan migrate --force` → `npm run build`. Node 24 / npm 12 trên server. Web qua Cloudflare nên không SSH theo domain.

**Sự cố 2026-09-10**: `sh: node_modules/.bin/vite: Permission denied` — `vite/bin/vite.js` + binary `esbuild` bị `-rw-r--r--` (mất exec bit, 10 file trong `.bin`), ổ KHÔNG noexec. Nguyên nhân: thao tác chỉnh quyền của panel chmod 644 toàn bộ file (kể cả node_modules). Đã chmod +x tay và build OK (13s).

**Chống tái diễn**: `npm run build` → `node scripts/build.cjs`: (Linux) tự cấp +x cho `.bin/*`, `vite/bin/vite.js`, `esbuild` bin nếu mất, rồi spawn `node node_modules/vite/bin/vite.js build` (không cần exec bit). File `.cjs` vì package.json có `"type":"module"`.

**npm 12 trên server tự ghi `allowScripts: {"esbuild@0.27.7": true}` vào package.json** (chặn postinstall dependency mặc định) và đổi `name` trong package-lock theo tên thư mục → working tree server bị M → `git pull` sẽ kẹt khi repo đổi 2 file này. Đã commit `allowScripts` vào repo cho khớp; trên server phải `git checkout -- package.json package-lock.json` trước khi pull nếu còn M. Khi nâng esbuild phải cập nhật key version trong `allowScripts`.

**Cách chạy lệnh trên VPS từ máy dev** (không sshpass): paramiko qua script tạm, mật khẩu đưa bằng env `VPS_PW`, đặt `PYTHONUTF8=1` (script deploy có emoji làm cp1252 crash). Không lưu mật khẩu vào file/memory.

Liên quan [[dev-no-build]] [[trucking-vite-architecture]].
