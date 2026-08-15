<?php
declare(strict_types=1);
require __DIR__ . '/../api/auth.php';
$user = require_auth();
$canOrder = has_permission((int)$user['id'], 'orders.create');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mahameru — Sales Workspace</title>
<link rel="stylesheet" href="assets/app.css">
<style>
.workflow{display:grid;gap:12px;margin-top:16px}.step{border:1px solid #e5e7eb;border-radius:14px;padding:16px;background:#fff}.step.active{border-color:#2563eb}.step.locked{opacity:.6}.step-head{display:flex;justify-content:space-between;gap:12px;align-items:center}.badge{font-size:12px;border-radius:999px;padding:4px 9px;background:#f3f4f6}.badge.active{background:#dbeafe;color:#1d4ed8}.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.button[aria-disabled="true"]{pointer-events:none;opacity:.45}.status{margin-top:12px;white-space:pre-wrap}.muted{line-height:1.5}
</style>
</head>
<body>
<main class="container">
<header class="topbar">
  <div><div class="brand">MAHAMERU</div><div class="muted">Sales Workspace</div></div>
  <a class="button" href="dashboard.php">Dashboard</a>
</header>
<section class="card">
  <h2>Workflow Kunjungan</h2>
  <p class="muted">Order hanya tersedia setelah check-in aktif. Status diambil dari server agar UI tidak dapat melewati Visit Engine.</p>
  <div id="workflow" class="workflow">
    <article class="step active"><div class="step-head"><strong>1. Visit & Check-In</strong><span class="badge active">MULAI</span></div><p class="muted">Pilih outlet, ambil lokasi, dan lakukan check-in sesuai validasi backend.</p><div class="actions"><a class="primary button" href="visit.php">Buka Visit / Check-In</a></div></article>
    <article id="orderStep" class="step locked"><div class="step-head"><strong>2. EC / OC Order</strong><span id="orderBadge" class="badge">TERKUNCI</span></div><p id="orderText" class="muted">Order aktif setelah server mendeteksi kunjungan ACTIVE.</p><div class="actions"><a id="orderButton" class="primary button" href="order.php" aria-disabled="true">Buka Order</a></div></article>
    <article id="checkoutStep" class="step locked"><div class="step-head"><strong>3. Check-Out</strong><span id="checkoutBadge" class="badge">TERKUNCI</span></div><p id="checkoutText" class="muted">Selesaikan durasi minimum kunjungan sebelum checkout.</p><div class="actions"><a class="button" href="visit.php">Kelola Visit</a></div></article>
  </div>
  <div id="serverStatus" class="status muted">Memeriksa status kunjungan...</div>
</section>
</main>
<script>
const canOrder = <?= $canOrder ? 'true' : 'false' ?>;
const statusEl=document.querySelector('#serverStatus'), orderStep=document.querySelector('#orderStep'), orderBadge=document.querySelector('#orderBadge'), orderText=document.querySelector('#orderText'), orderButton=document.querySelector('#orderButton'), checkoutStep=document.querySelector('#checkoutStep'), checkoutBadge=document.querySelector('#checkoutBadge'), checkoutText=document.querySelector('#checkoutText');
async function loadVisitState(){
  try{
    const response=await fetch('../api/visit.php?action=active',{headers:{'Accept':'application/json'}});
    const data=await response.json(); const visit=data&&data.success?data.visit:null;
    if(visit){
      orderStep.className='step active'; orderBadge.className='badge active'; orderBadge.textContent='AKTIF';
      orderText.textContent=`Outlet: ${visit.outlet_name||visit.outlet_code||visit.outlet_id}. Order dapat dibuat sebelum checkout.`;
      if(canOrder){orderButton.setAttribute('aria-disabled','false');orderButton.style.pointerEvents='auto';orderButton.style.opacity='1';}
      else{orderButton.setAttribute('aria-disabled','true');orderButton.textContent='Tidak punya permission';}
      checkoutStep.className='step active'; checkoutBadge.className='badge active'; checkoutBadge.textContent='AKTIF';
      checkoutText.textContent='Kunjungan aktif. Buka Visit untuk melakukan checkout setelah durasi minimum terpenuhi.';
      statusEl.textContent=`Kunjungan aktif sejak ${visit.checkin_at||'-'} pada ${visit.outlet_name||visit.outlet_code||'outlet'}.`;
    }else{
      orderStep.className='step locked';orderBadge.className='badge';orderBadge.textContent='TERKUNCI';orderButton.setAttribute('aria-disabled','true');orderButton.style.pointerEvents='none';orderButton.style.opacity='.45';orderText.textContent=canOrder?'Lakukan Check-In terlebih dahulu untuk membuka EC / OC Order.':'Akun tidak memiliki permission orders.create.';
      checkoutStep.className='step locked';checkoutBadge.className='badge';checkoutBadge.textContent='TERKUNCI';checkoutText.textContent='Belum ada kunjungan aktif.';statusEl.textContent='Tidak ada kunjungan aktif. Mulai dari Visit & Check-In.';
    }
  }catch(e){statusEl.textContent='Status kunjungan tidak dapat dimuat. Silakan buka Visit untuk verifikasi langsung dari server.';}
}
loadVisitState();
</script>
</body>
</html>
