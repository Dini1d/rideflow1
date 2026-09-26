<?php
/**
 * Admin Topbar
 */
?>
<div class="topbar">
  <div style="display:flex;align-items:center;gap:.75rem">
    <button class="hamburger" onclick="document.getElementById('adminSidebar').classList.toggle('open')" aria-label="Menu" style="background:none;border:none;cursor:pointer;color:var(--chalk);font-size:1.1rem;padding:.3rem">
      <i class="fas fa-bars"></i>
    </button>
    <span class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Admin Panel' ?></span>
  </div>
  <div class="topbar-right">
    <span id="adminClock" style="font-size:.82rem;color:var(--chalk3);font-family:var(--ff-mono)"></span>
    <a href="<?= SITE_URL ?>" target="_blank" class="btn btn-dark btn-sm"><i class="fas fa-globe"></i></a>
    <div style="display:flex;align-items:center;gap:.55rem;padding:.35rem .75rem .35rem .4rem;background:var(--veil2);border:1px solid var(--rim);border-radius:var(--r6)">
      <div style="width:28px;height:28px;border-radius:50%;background:var(--bad);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#fff"><?= strtoupper(substr($_SESSION['uname']??'A',0,1)) ?></div>
      <span style="font-size:.84rem;color:var(--chalk2)"><?= e(explode(' ',$_SESSION['uname']??'Admin')[0]) ?></span>
      <span style="font-size:.7rem;color:var(--bad);font-weight:700;background:rgba(239,68,68,.1);padding:.1rem .45rem;border-radius:var(--r6)">Admin</span>
    </div>
  </div>
</div>
<script>
(function(){
  function tick(){ var n=new Date(); document.getElementById('adminClock').textContent=n.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
  tick(); setInterval(tick,1000);
})();
</script>
