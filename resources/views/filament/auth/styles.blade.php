<style>
:root { color-scheme: dark; }
html, body, .fi-body { min-height:100%; background:#080e20 !important; }
.fi-simple-layout {
  min-height:100vh !important; padding:8px !important; box-sizing:border-box;
  display:flex !important; align-items:center !important; justify-content:center !important;
  background:
    radial-gradient(ellipse at 0% 92%, rgba(41,74,255,.28), transparent 29%),
    radial-gradient(ellipse at 100% 4%, rgba(83,70,220,.20), transparent 30%),
    linear-gradient(135deg,#101a32 0%,#080e1f 52%,#090f22 100%) !important;
}
.fi-simple-main-ctn { width:100% !important; max-width:502px !important; padding:0 !important; }
.fi-simple-main {
  box-sizing:border-box !important; width:100% !important; max-width:none !important;
  padding:30px 46px 32px !important; border:1px solid rgba(112,132,210,.48) !important;
  border-radius:28px !important; background:linear-gradient(145deg,rgba(18,29,55,.93),rgba(7,14,30,.96) 60%,rgba(13,20,43,.94)) !important;
  box-shadow:0 22px 70px rgba(0,0,0,.36), inset 0 1px 0 rgba(255,255,255,.035) !important;
  backdrop-filter:blur(18px);
}
.fi-simple-header { display:none !important; }
.viralyx-login-brand { display:flex; flex-direction:column; align-items:center; text-align:center; margin:0 0 32px; }
.viralyx-login-symbol { width:142px; height:112px; object-fit:contain; filter:drop-shadow(0 0 18px rgba(0,190,255,.18)); }
.viralyx-login-wordmark { margin-top:3px; color:#f7f8ff; font-size:34px; line-height:1.15; font-weight:850; letter-spacing:.105em; }
.viralyx-login-wordmark span { background:linear-gradient(100deg,#08c8f6,#5d48ff 55%,#bd45f5); -webkit-background-clip:text; background-clip:text; color:transparent; letter-spacing:-.02em; }
.viralyx-login-brand h1 { margin:20px 0 5px; color:#f5f6ff; font-size:18px; font-weight:700; line-height:1.4; }
.viralyx-login-brand p { margin:0; color:#a3afca; font-size:14px; line-height:1.5; }
.fi-simple-main form { gap:18px !important; }
.fi-fo-field-wrp-label { color:#e2e8fa !important; font-size:13px !important; font-weight:500 !important; }
.fi-fo-text-input, .fi-input-wrp {
  background:rgba(255,255,255,.055) !important; border-color:rgba(117,135,183,.31) !important;
  border-radius:11px !important; box-shadow:none !important; color:#f5f7ff !important;
}
.fi-input-wrp { min-height:44px; }
.fi-fo-text-input::placeholder { color:#8d9ab7 !important; }
.fi-fo-field-wrp-hint-ctn { justify-content:flex-end !important; }
.viralyx-forgot-link { color:#6c9bff !important; text-decoration:none !important; font-size:13px; white-space:nowrap; }
.fi-fo-checkbox { accent-color:#4777ff; }
.fi-fo-checkbox label, .fi-fo-checkbox-label { color:#d7def0 !important; font-size:12px !important; }
.fi-btn.fi-color-primary, .fi-btn-color-primary {
  min-height:52px !important; border:0 !important; border-radius:14px !important;
  background:linear-gradient(105deg,#2879ff 0%,#4e4af7 53%,#a22cf1 100%) !important;
  color:#fff !important; box-shadow:0 8px 24px rgba(69,76,255,.2) !important; font-weight:650 !important;
}
.fi-btn.fi-color-primary:hover, .fi-btn-color-primary:hover { filter:brightness(1.08); }
.viralyx-auth-extras { margin-top:25px; }
.viralyx-divider { display:flex; align-items:center; gap:20px; color:#a9b4ce; font-size:12px; margin:0 0 17px; }
.viralyx-divider:before, .viralyx-divider:after { content:""; height:1px; flex:1; background:rgba(126,144,190,.32); }
.viralyx-oauth-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.viralyx-oauth-button { min-height:51px; display:flex; align-items:center; justify-content:center; gap:8px; border:1px solid rgba(117,135,183,.32); border-radius:12px; background:rgba(255,255,255,.035); color:#f4f6ff; font-size:12px; font-weight:550; text-decoration:none; }
.viralyx-oauth-button svg { width:19px; height:19px; flex-shrink:0; }
.viralyx-oauth-button[aria-disabled="true"] { cursor:not-allowed; }
.viralyx-signup { display:flex; justify-content:center; align-items:center; gap:12px; flex-wrap:wrap; margin-top:26px; color:#a9b4ce; font-size:12px; }
.viralyx-signup span { color:#a9b4ce; }
.viralyx-signup strong { color:#8c5aff; font-weight:600; }
@media (max-width:560px) {
  .fi-simple-layout { padding:8px !important; align-items:center !important; }
  .fi-simple-main { padding:25px 22px 26px !important; border-radius:24px !important; }
  .viralyx-login-symbol { width:118px; height:92px; }
  .viralyx-login-wordmark { font-size:30px; }
  .viralyx-login-brand { margin-bottom:26px; }
  .viralyx-oauth-row { gap:7px; }
  .viralyx-oauth-button { gap:5px; font-size:11px; }
}
@media (max-height:760px) { .fi-simple-layout { align-items:flex-start !important; } .fi-simple-main { margin:8px 0 !important; } }
</style>
