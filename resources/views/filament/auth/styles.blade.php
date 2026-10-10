<style>
:root { color-scheme: dark; }
html, body, .fi-body { min-height:100%; background:#070b18 !important; }
.fi-simple-layout {
  min-height:100vh !important; min-height:100dvh !important; padding:10px !important; box-sizing:border-box;
  display:flex !important; align-items:center !important; justify-content:center !important;
  position:relative !important; overflow:hidden !important;
  background:
    radial-gradient(circle at 18% 18%, rgba(0,196,255,.15), transparent 18%),
    radial-gradient(circle at 82% 78%, rgba(176,44,255,.15), transparent 22%),
    radial-gradient(ellipse at 0% 92%, rgba(41,74,255,.25), transparent 29%),
    radial-gradient(ellipse at 100% 4%, rgba(83,70,220,.20), transparent 30%),
    linear-gradient(135deg,#101a32 0%,#080e1f 52%,#090f22 100%) !important;
}
.fi-simple-layout::before,
.fi-simple-layout::after,
.fi-simple-main-ctn::before,
.fi-simple-main-ctn::after {
  position:absolute; z-index:0; pointer-events:none; user-select:none;
  white-space:pre; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  color:rgba(255,255,255,.10); text-shadow:0 0 24px rgba(65,126,255,.18);
}
.fi-simple-layout::before {
  content:"✦     ◌       #       ◇          ↗       ◎\A\A     ☁        ◉       ✉       ✦          ◇\A\A  ◎          +       ◌          ☁       ↗";
  left:3%; top:6%; font-size:30px; line-height:3.8; letter-spacing:16px;
  transform:rotate(-7deg); animation:viralyx-float-a 18s ease-in-out infinite;
}
.fi-simple-layout::after {
  content:"◍       ✦          ☁       ◎        ◇\A\A     ↗       #        ◌       ✉          ✦\A\A  ◇          ◉       +          ☁       ◎";
  right:2%; bottom:3%; font-size:27px; line-height:4; letter-spacing:13px;
  transform:rotate(8deg); animation:viralyx-float-b 22s ease-in-out infinite;
}
.fi-simple-main-ctn::before {
  content:"POST  •  SHARE  •  MESSAGE"; z-index:-1; left:-220px; top:20%;
  padding:15px 24px; border:1px solid rgba(92,139,255,.16); border-radius:18px;
  background:rgba(49,80,160,.08); color:rgba(157,181,255,.16); font-size:12px;
  font-weight:700; letter-spacing:4px; transform:rotate(-12deg);
  animation:viralyx-float-c 15s ease-in-out infinite;
}
.fi-simple-main-ctn::after {
  content:"💬   ♡   ↗   ◉   ✦"; z-index:-1; right:-215px; bottom:17%;
  padding:18px 24px; border:1px solid rgba(190,92,255,.16); border-radius:18px;
  background:rgba(125,56,170,.08); color:rgba(222,186,255,.18); font-size:22px;
  letter-spacing:8px; transform:rotate(10deg); animation:viralyx-float-d 17s ease-in-out infinite;
}
@keyframes viralyx-float-a { 0%,100%{transform:translate3d(0,0,0) rotate(-7deg)} 50%{transform:translate3d(18px,12px,0) rotate(-4deg)} }
@keyframes viralyx-float-b { 0%,100%{transform:translate3d(0,0,0) rotate(8deg)} 50%{transform:translate3d(-20px,-14px,0) rotate(5deg)} }
@keyframes viralyx-float-c { 0%,100%{transform:translate3d(0,0,0) rotate(-12deg)} 50%{transform:translate3d(14px,-10px,0) rotate(-9deg)} }
@keyframes viralyx-float-d { 0%,100%{transform:translate3d(0,0,0) rotate(10deg)} 50%{transform:translate3d(-12px,10px,0) rotate(7deg)} }
.fi-simple-main-ctn { position:relative !important; z-index:2; width:100% !important; max-width:400px !important; padding:0 !important; }
.fi-simple-main {
  position:relative !important; z-index:3;
  box-sizing:border-box !important; width:100% !important; max-width:none !important;
  padding:18px 26px 20px !important; border:1px solid rgba(112,132,210,.48) !important;
  border-radius:22px !important; background:linear-gradient(145deg,rgba(18,29,55,.93),rgba(7,14,30,.96) 60%,rgba(13,20,43,.94)) !important;
  box-shadow:0 22px 70px rgba(0,0,0,.36), inset 0 1px 0 rgba(255,255,255,.035) !important;
  backdrop-filter:blur(18px);
}
.fi-simple-header { display:none !important; }
.viralyx-login-brand { display:flex; flex-direction:column; align-items:center; text-align:center; margin:0 0 14px; }
.viralyx-login-symbol { width:86px; height:66px; object-fit:contain; filter:drop-shadow(0 0 18px rgba(0,190,255,.18)); }
.viralyx-login-wordmark { margin-top:1px; color:#f7f8ff; font-size:25px; line-height:1.15; font-weight:850; letter-spacing:.105em; }
.viralyx-login-wordmark span { background:linear-gradient(100deg,#08c8f6,#5d48ff 55%,#bd45f5); -webkit-background-clip:text; background-clip:text; color:transparent; letter-spacing:-.02em; }
.viralyx-login-brand h1 { margin:8px 0 0; color:#f5f6ff; font-family:inherit; font-size:14px; font-weight:700; line-height:1.3; }\n.viralyx-login-brand h1 span { color:#8c7cff; font-family:inherit; font-weight:700; }
.viralyx-login-brand p { margin:0; color:#a3afca; font-size:13px; line-height:1.5; }
.fi-simple-main form { gap:10px !important; }
.fi-fo-field-wrp-label { color:#e2e8fa !important; font-size:13px !important; font-weight:500 !important; }
.fi-fo-text-input, .fi-input-wrp {
  background:rgba(255,255,255,.055) !important; border-color:rgba(117,135,183,.31) !important;
  border-radius:11px !important; box-shadow:none !important; color:#f5f7ff !important;
}
.fi-input-wrp { min-height:38px; }
.fi-fo-text-input::placeholder { color:#8d9ab7 !important; }
.fi-fo-field-wrp-hint-ctn { justify-content:flex-end !important; }
.viralyx-forgot-link { color:#6c9bff !important; text-decoration:none !important; font-size:13px; white-space:nowrap; }
.fi-fo-checkbox { accent-color:#4777ff; }
.fi-fo-checkbox label, .fi-fo-checkbox-label { color:#d7def0 !important; font-size:12px !important; }
.fi-btn.fi-color-primary, .fi-btn-color-primary {
  min-height:42px !important; border:0 !important; border-radius:14px !important;
  background:linear-gradient(105deg,#2879ff 0%,#4e4af7 53%,#a22cf1 100%) !important;
  color:#fff !important; box-shadow:0 8px 24px rgba(69,76,255,.2) !important; font-weight:650 !important;
}
.fi-btn.fi-color-primary:hover, .fi-btn-color-primary:hover { filter:brightness(1.08); }
.viralyx-auth-extras { margin-top:12px; }
.viralyx-divider, .viralyx-oauth-row { display:none !important; }
.viralyx-signup { margin-top:12px; }
.viralyx-divider { display:flex; align-items:center; gap:14px; color:#a9b4ce; font-size:12px; margin:0 0 13px; }
.viralyx-divider:before, .viralyx-divider:after { content:""; height:1px; flex:1; background:rgba(126,144,190,.32); }
.viralyx-oauth-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.viralyx-oauth-button { min-height:46px; display:flex; align-items:center; justify-content:center; gap:8px; border:1px solid rgba(117,135,183,.32); border-radius:12px; background:rgba(255,255,255,.035); color:#f4f6ff; font-size:12px; font-weight:550; text-decoration:none; }
.viralyx-oauth-button svg { width:19px; height:19px; flex-shrink:0; }
.viralyx-oauth-button[aria-disabled="true"] { cursor:not-allowed; }
.viralyx-signup { display:flex; justify-content:center; align-items:center; gap:12px; flex-wrap:wrap; margin-top:12px; color:#a9b4ce; font-size:12px; }
.viralyx-signup span { color:#a9b4ce; }
.viralyx-signup strong { color:#8c5aff; font-weight:600; }
@media (max-width:560px) {
  .fi-simple-layout { padding:6px !important; align-items:center !important; }
  .fi-simple-main-ctn { max-width:370px !important; }
  .fi-simple-main { padding:16px 16px 18px !important; border-radius:20px !important; }
  .viralyx-login-symbol { width:78px; height:60px; }
  .viralyx-login-wordmark { font-size:23px; }
  .viralyx-login-brand { margin-bottom:12px; }
  .fi-simple-layout::before { left:-22%; top:4%; font-size:22px; line-height:4.2; letter-spacing:9px; }
  .fi-simple-layout::after { right:-24%; bottom:4%; font-size:21px; line-height:4.4; letter-spacing:7px; }
  .fi-simple-main-ctn::before { left:-185px; font-size:10px; }
  .fi-simple-main-ctn::after { right:-180px; font-size:18px; }
}
@media (max-height:700px) { .fi-simple-layout { padding:6px !important; align-items:center !important; overflow:hidden !important; } .fi-simple-main { margin:0 !important; } .viralyx-login-brand { margin-bottom:10px; } .viralyx-login-symbol { width:72px; height:54px; } .viralyx-login-wordmark { font-size:22px; } .viralyx-login-brand h1 { margin-top:6px; font-size:13px; } .fi-simple-main form { gap:8px !important; } .fi-input-wrp { min-height:36px; } .fi-btn.fi-color-primary, .fi-btn-color-primary { min-height:40px !important; } }
@media (prefers-reduced-motion:reduce) {
  .fi-simple-layout::before, .fi-simple-layout::after, .fi-simple-main-ctn::before, .fi-simple-main-ctn::after { animation:none !important; }
}
</style>
