(function(){var fit=function(){var el=document.getElementById('trk-root');if(!el)return;el.style.height=Math.max(360,window.innerHeight-el.getBoundingClientRect().top)+'px';};window.addEventListener('resize',fit);window.__fitTrkRoot=fit;setTimeout(fit,0);})();

/* Dialog xác nhận dùng chung (đẹp hơn window.confirm). Trả về Promise<boolean>.
   opts: { title, text(HTML), confirmText(HTML), cancelText, danger, icon } */
window.confirmAction = function(opts){
  opts = opts || {};
  var danger = !!opts.danger;
  var accent = danger ? 'var(--danger)' : 'var(--accent)';
  var weak = danger ? '#fce8e8' : 'var(--accent-weak)';
  var icon = opts.icon || (danger ? 'bi-exclamation-triangle' : 'bi-question-circle');
  return new Promise(function(resolve){
    var ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;z-index:3000;background:rgba(16,19,23,.40);backdrop-filter:blur(2px);display:grid;place-items:center;padding:24px;animation:fadeIn .12s ease;';
    var card = document.createElement('div');
    card.style.cssText = 'width:min(440px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 50px -16px rgba(16,19,23,.45),0 8px 24px -12px rgba(16,19,23,.25);overflow:hidden;font-family:inherit;';
    card.innerHTML =
      '<div style="padding:20px 22px 4px;display:flex;align-items:flex-start;gap:13px;">'
      + '<div style="width:38px;height:38px;border-radius:10px;flex-shrink:0;display:grid;place-items:center;background:'+weak+';color:'+accent+';font-size:18px;"><i class="bi '+icon+'"></i></div>'
      + '<div style="flex:1;min-width:0;padding-top:1px;">'
      + '<div style="font-size:16px;font-weight:700;color:var(--ink);letter-spacing:-.01em;">'+(opts.title||'Xác nhận')+'</div>'
      + '<div style="font-size:13.5px;color:var(--ink-2);margin-top:5px;line-height:1.55;">'+(opts.text||'')+'</div>'
      + '</div></div>'
      + '<div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 18px;">'
      + '<button data-a="cancel" style="padding:9px 18px;font-size:14px;font-weight:500;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink-2);cursor:pointer;">'+(opts.cancelText||'Hủy')+'</button>'
      + '<button data-a="ok" style="padding:9px 18px;font-size:14px;font-weight:600;border:none;border-radius:10px;background:'+accent+';color:#fff;cursor:pointer;box-shadow:0 1px 2px rgba(16,19,23,.2);">'+(opts.confirmText||'Đồng ý')+'</button>'
      + '</div>';
    ov.appendChild(card);
    document.body.appendChild(ov);
    var closed = false;
    function done(v){ if(closed) return; closed = true; document.removeEventListener('keydown', onKey); if(ov.parentNode) ov.parentNode.removeChild(ov); resolve(v); }
    function onKey(e){ if(e.key==='Escape') done(false); else if(e.key==='Enter') done(true); }
    ov.addEventListener('mousedown', function(e){ if(e.target===ov) done(false); });
    card.querySelector('[data-a=cancel]').onclick = function(){ done(false); };
    card.querySelector('[data-a=ok]').onclick = function(){ done(true); };
    document.addEventListener('keydown', onKey);
    var okb = card.querySelector('[data-a=ok]'); if(okb) okb.focus();
  });
};

/* Toast feedback nhẹ cho thao tác lưu/thêm/xoá. Auto-close 2.4s, stack ở góc phải.
   type: 'success' (mặc định) | 'error' | 'info' | 'warn' */
window.trkToast = function(msg, type){
  type = type || 'success';
  var palette = {
    success: { bg: '#16a34a', icon: 'bi-check-circle-fill' },
    error:   { bg: '#dc2626', icon: 'bi-exclamation-triangle-fill' },
    info:    { bg: '#2a6fdb', icon: 'bi-info-circle-fill' },
    warn:    { bg: '#e0a92e', icon: 'bi-exclamation-circle-fill' },
  };
  var p = palette[type] || palette.success;
  var stack = document.getElementById('trk-toast-stack');
  if (!stack){
    stack = document.createElement('div');
    stack.id = 'trk-toast-stack';
    stack.style.cssText = 'position:fixed;top:72px;right:16px;z-index:10000;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
    document.body.appendChild(stack);
  }
  var t = document.createElement('div');
  t.style.cssText = 'pointer-events:auto;background:#fff;color:#101317;border:1px solid var(--line);border-left:4px solid '+p.bg+';box-shadow:0 8px 24px -8px rgba(16,19,23,.22);border-radius:10px;padding:10px 14px;font-size:13.5px;font-weight:500;display:flex;align-items:center;gap:8px;min-width:200px;max-width:360px;transform:translateX(120%);transition:transform .22s ease,opacity .22s ease;opacity:0;';
  t.innerHTML = '<i class="bi '+p.icon+'" style="color:'+p.bg+';font-size:16px;flex:none;"></i><span style="line-height:1.35;">'+String(msg||'').replace(/</g,'&lt;')+'</span>';
  stack.appendChild(t);
  requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.style.transform='translateX(0)'; t.style.opacity='1'; }); });
  setTimeout(function(){
    t.style.transform = 'translateX(120%)'; t.style.opacity = '0';
    setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 260);
  }, 2400);
};

