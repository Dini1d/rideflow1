<?php
define('RF_ROOT', __DIR__);
$pageTitle = 'Search Trips';
require_once RF_ROOT.'/includes/header.php';

$from  = trim($_GET['from']  ?? '');
$to    = trim($_GET['to']    ?? '');
$date  = trim($_GET['date']  ?? date('Y-m-d'));
$type  = trim($_GET['type']  ?? '');
$seats = max(1, min(10, (int)($_GET['seats'] ?? 1)));
$searched = ($from && $to);
$results  = [];

if ($searched) {
    $where  = ["s.status='scheduled'", "s.departure_date=?", "s.available_seats>=?", "r.status='active'",
               "(r.origin LIKE ? OR r.origin LIKE ?)", "(r.destination LIKE ? OR r.destination LIKE ?)"];
    $params = [$date, $seats, "%$from%", "%$from%", "%$to%", "%$to%"];
    if ($type) { $where[] = "t.type_name=?"; $params[] = $type; }
    $results = db_all("
        SELECT s.id, s.departure_date, s.departure_time, s.arrival_time, s.fare, s.available_seats,
               r.origin, r.destination, r.estimated_duration, r.distance_km,
               t.type_name, v.vehicle_name, v.capacity
        FROM schedules s
        JOIN routes r   ON r.id=s.route_id
        JOIN transport_types t ON t.id=r.type_id
        JOIN vehicles v ON v.id=s.vehicle_id
        WHERE ".implode(' AND ', $where)."
        ORDER BY s.departure_time", $params);
}
$types = db_all("SELECT type_name FROM transport_types WHERE is_active=1 ORDER BY type_name");
?>

<div class="main-content">
<div class="container">

<!-- Search bar -->
<div class="card card-pad" style="margin-bottom:1.75rem">
  <h2 style="font-size:1.2rem;margin-bottom:1rem"><i class="fas fa-search" style="color:var(--fire)"></i> Find a Trip</h2>
  <form method="GET" id="searchForm">
    <div class="search-grid route-search-grid">
      <div class="route-field-group" aria-label="Route fields">
        <div class="fld route-field">
          <label>From</label>
          <div class="fw">
            <i class="fas fa-map-marker-alt fc-icon"></i>
            <input type="text" name="from" id="search_from" class="fc has-icon" list="location-options" placeholder="Origin city…" value="<?= e($from) ?>" required>
          </div>
        </div>
        <button type="button" id="swapBtn" class="route-swap-btn" title="Swap locations" aria-label="Swap from and to locations">
          <i class="fas fa-exchange-alt" aria-hidden="true"></i>
        </button>
        <div class="fld route-field route-field-to">
          <label>To</label>
          <div class="fw">
            <i class="fas fa-map-pin fc-icon"></i>
            <input type="text" name="to" id="search_to" class="fc has-icon" list="location-options" placeholder="Destination…" value="<?= e($to) ?>" required>
          </div>
        </div>
      </div>
      <datalist id="location-options">
        <option value="Colombo">
        <option value="Kandy">
        <option value="Galle">
        <option value="Jaffna">
        <option value="Negombo">
        <option value="Matale">
        <option value="Kurunegala">
        <option value="Anuradhapura">
        <option value="Badulla">
        <option value="Batticaloa">
        <option value="Trincomalee">
        <option value="Ratnapura">
        <option value="Nuwara Eliya">
        <option value="Ella">
        <option value="Kalutara">
      </datalist>
      <div class="fld" style="margin:0">
        <label>Date</label>
        <div class="fw">
          <i class="fas fa-calendar fc-icon"></i>
          <input type="date" name="date" id="search_date" class="fc has-icon" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="fld" style="margin:0">
        <label>Type</label>
        <select name="type" class="fc">
          <option value="">All Types</option>
          <?php foreach ($types as $t): ?>
          <option value="<?= e($t['type_name']) ?>" <?= $type===$t['type_name']?'selected':'' ?>><?= e($t['type_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-fire btn-lg" style="align-self:end"><i class="fas fa-search"></i> Search</button>
    </div>
    <!-- Seat selector -->
    <div style="display:flex;align-items:center;gap:.75rem;margin-top:.85rem;flex-wrap:wrap">
      <span class="label">Seats:</span>
      <?php for ($i=1;$i<=6;$i++): ?>
      <button type="button" class="seat-btn" data-val="<?= $i ?>" onclick="this.closest('form').querySelector('[name=seats]').value=<?= $i ?>" style="width:34px;height:34px;border-radius:50%;background:<?= $seats===$i?'var(--fire)':'var(--ink3)' ?>;border:1.5px solid <?= $seats===$i?'var(--fire)':'var(--rim)' ?>;color:<?= $seats===$i?'#fff':'var(--chalk3)' ?>;cursor:pointer;font-size:.85rem;font-weight:700;transition:all .15s"><?= $i ?></button>
      <?php endfor; ?>
      <input type="hidden" name="seats" value="<?= $seats ?>">
    </div>
  </form>
</div>

<?php if ($searched): ?>
<!-- Results -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
  <h2 style="font-size:1.15rem">
    <?= count($results) ?> trip<?= count($results)!=1?'s':'' ?> found
    <?php if ($from && $to): ?>
    <span style="font-size:.85rem;color:var(--chalk3);font-family:var(--ff-body);font-weight:400"> · <?= e($from) ?> → <?= e($to) ?> on <?= date('D, M j, Y', strtotime($date)) ?></span>
    <?php endif; ?>
  </h2>
  <?php if ($results): ?>
  <div style="display:flex;gap:.5rem">
    <button onclick="sortResults('price')" class="btn btn-dark btn-sm"><i class="fas fa-sort-amount-up"></i> Price</button>
    <button onclick="sortResults('time')"  class="btn btn-dark btn-sm"><i class="fas fa-clock"></i> Time</button>
  </div>
  <?php endif; ?>
</div>

<?php if (!$results): ?>
<div class="card card-pad" style="text-align:center;padding:3rem">
  <div style="font-size:3.5rem;margin-bottom:1rem">🔍</div>
  <h3>No trips found</h3>
  <p style="margin-top:.4rem;margin-bottom:1.5rem">Try a different date, swap origin/destination, or check spelling.</p>
  <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
    <a href="<?= SITE_URL ?>/search.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&date=<?= urlencode(date('Y-m-d',strtotime($date.'+1 day'))) ?>&seats=<?= $seats ?>" class="btn btn-ghost btn-sm">Try Tomorrow</a>
    <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire btn-sm">New Search</a>
  </div>
</div>
<?php else: ?>
<div id="resultsList" style="display:flex;flex-direction:column;gap:.85rem">
<?php foreach ($results as $r):
  $tc = strtolower(str_replace([' ','-'],'',$r['type_name']));
  $tc = $tc==='threewheel'?'wheel':$tc;
?>
<div class="card" style="padding:1.25rem;transition:border-color .18s,box-shadow .18s" onmouseover="this.style.borderColor='var(--rim2)';this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.borderColor='var(--rim)';this.style.boxShadow='none'"
     data-price="<?= $r['fare'] ?>" data-time="<?= str_replace(':','',substr($r['departure_time'],0,5)) ?>">
  <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center">
    <div>
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.65rem;flex-wrap:wrap">
        <span class="type-pill tp-<?= $tc ?>"><?= e($r['type_name']) ?></span>
        <?= status_badge($r['available_seats'] > 10 ? 'active' : ($r['available_seats'] > 3 ? 'pending' : 'bad')) ?>
        <span style="font-size:.75rem;color:var(--chalk4)"><?= e($r['vehicle_name']) ?></span>
      </div>
      <div style="font-size:1.1rem;font-weight:700;color:var(--chalk);display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
        <?= e($r['origin']) ?>
        <span style="color:var(--fire)">→</span>
        <?= e($r['destination']) ?>
      </div>
      <div style="display:flex;gap:1.25rem;flex-wrap:wrap;font-size:.82rem;color:var(--chalk3)">
        <span><i class="fas fa-clock" style="color:var(--fire)"></i> <?= substr($r['departure_time'],0,5) ?> → <?= $r['arrival_time']?substr($r['arrival_time'],0,5):'–' ?></span>
        <span><i class="fas fa-hourglass-half"></i> <?= e($r['estimated_duration']) ?></span>
        <?php if ($r['distance_km']): ?><span><i class="fas fa-road"></i> <?= $r['distance_km'] ?> km</span><?php endif; ?>
        <span><i class="fas fa-chair" style="color:var(--ok)"></i> <?= $r['available_seats'] ?> seat<?= $r['available_seats']!=1?'s':'' ?> left</span>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-family:var(--ff-disp);font-size:1.65rem;font-weight:900;color:var(--fire);line-height:1">Rs.<?= number_format($r['fare'],0) ?></div>
      <div style="font-size:.75rem;color:var(--chalk4);margin-bottom:.75rem"><?= $seats > 1 ? 'Rs.'.number_format($r['fare']*$seats,0).' for '.$seats.' seats' : 'per seat' ?></div>
      <a href="<?= SITE_URL ?>/book.php?schedule=<?= $r['id'] ?>&seats=<?= $seats ?>" class="btn btn-fire">
        Book Now <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<!-- No search yet — popular routes -->
<div style="margin-top:1rem">
  <h2 style="font-size:1.15rem;margin-bottom:1rem">Popular Routes</h2>
  <div class="grid-3">
    <?php
    $popular = db_all("SELECT r.origin, r.destination, t.type_name, MIN(s.fare) min_fare
        FROM routes r JOIN transport_types t ON t.id=r.type_id
        LEFT JOIN schedules s ON s.route_id=r.id AND s.departure_date>=CURRENT_DATE
        WHERE r.status='active' GROUP BY r.id, r.origin, r.destination, t.type_name ORDER BY r.id LIMIT 6");
    foreach ($popular as $p):
      $tc = strtolower(str_replace([' ','-'],'',$p['type_name']));
      $tc = $tc==='threewheel'?'wheel':$tc;
    ?>
    <a href="<?= SITE_URL ?>/search.php?from=<?= urlencode($p['origin']) ?>&to=<?= urlencode($p['destination']) ?>&date=<?= date('Y-m-d') ?>" class="card card-pad card-link" style="text-decoration:none">
      <span class="type-pill tp-<?= $tc ?>" style="margin-bottom:.65rem;display:inline-flex"><?= e($p['type_name']) ?></span>
      <div style="font-weight:700;color:var(--chalk);margin-bottom:.35rem"><?= e($p['origin']) ?> → <?= e($p['destination']) ?></div>
      <?php if ($p['min_fare']): ?>
      <div style="font-size:.82rem;color:var(--fire)">From Rs.<?= number_format($p['min_fare'],0) ?></div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div>
</div>

<script>
function sortResults(by) {
  var list = document.getElementById('resultsList');
  if (!list) return;
  var cards = Array.from(list.children);
  cards.sort(function(a,b){
    var va = parseFloat(a.dataset[by==='price'?'price':'time']);
    var vb = parseFloat(b.dataset[by==='price'?'price':'time']);
    return va-vb;
  });
  cards.forEach(function(c){ list.appendChild(c); });
}
</script>
<?php require_once RF_ROOT.'/includes/footer.php'; ?>
