/*
 * npm run build → node scripts/build.cjs
 *
 * Vì sao không gọi `vite build` trực tiếp: trên server deploy bằng aaPanel, thao tác "Fix permissions"
 * của panel chmod toàn bộ file về 644 → node_modules/.bin/vite (symlink tới vite/bin/vite.js) và binary
 * esbuild mất quyền thực thi → `sh: node_modules/.bin/vite: Permission denied` và build đổ ngay cả khi
 * git pull thành công. Script này:
 *   1. (Linux/macOS) tự cấp lại +x cho các bin Vite/esbuild cần dùng nếu bị mất.
 *   2. Chạy vite.js THẲNG bằng Node (process.execPath) — không cần exec bit trên .bin/vite.
 * Windows bỏ qua bước 1 (không có exec bit). Tham số thêm được chuyển tiếp cho vite build.
 */
const { spawnSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const nm = (...p) => path.join(root, "node_modules", ...p);

function ensureExecutable() {
  if (process.platform === "win32") return;
  const targets = [nm("vite", "bin", "vite.js"), nm("esbuild", "bin", "esbuild")];
  for (const dir of [nm(".bin"), nm("@esbuild")]) {
    let names = [];
    try { names = fs.readdirSync(dir); } catch (e) { continue; }
    for (const n of names) targets.push(dir === nm(".bin") ? path.join(dir, n) : path.join(dir, n, "bin", "esbuild"));
  }
  let fixed = 0;
  for (const t of targets) {
    try {
      const st = fs.statSync(t);   // theo symlink → chmod áp lên file đích
      if (st.isFile() && !(st.mode & 0o111)) { fs.chmodSync(t, st.mode | 0o755); fixed++; }
    } catch (e) { /* không có file này → bỏ qua */ }
  }
  if (fixed) console.log(`[build] đã cấp lại quyền thực thi cho ${fixed} file trong node_modules`);
}

ensureExecutable();
const vite = nm("vite", "bin", "vite.js");
if (!fs.existsSync(vite)) { console.error("[build] không thấy node_modules/vite — chạy `npm ci` trước."); process.exit(1); }
const r = spawnSync(process.execPath, [vite, "build", ...process.argv.slice(2)], { stdio: "inherit", cwd: root });
process.exit(r.status == null ? 1 : r.status);
