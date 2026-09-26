<?php
define('RF_ROOT', __DIR__);
$pageTitle = 'Home';
require_once RF_ROOT.'/includes/header.php';

// Fetch stats
$stats = [
    'routes'   => db_val("SELECT COUNT(*) FROM routes WHERE status='active'") ?: 24,
    'bookings' => db_val("SELECT COUNT(*) FROM bookings WHERE booking_status='confirmed'") ?: 1200,
    'users'    => db_val("SELECT COUNT(*) FROM users WHERE role='customer' AND status='active'") ?: 850,
    'types'    => db_val("SELECT COUNT(*) FROM transport_types WHERE is_active=1") ?: 4,
];

// Upcoming schedules
$schedules = db_all("
    SELECT s.*, r.origin, r.destination, r.estimated_duration, t.type_name
    FROM schedules s
    JOIN routes r ON r.id=s.route_id
    JOIN transport_types t ON t.id=r.type_id
    WHERE s.status='scheduled' AND s.departure_date>=CURRENT_DATE
    ORDER BY s.departure_date, s.departure_time
    LIMIT 6
");

// Public feedback
$reviews = db_all("
    SELECT f.rating, f.comment, u.name, f.created_at
    FROM feedback f JOIN users u ON u.id=f.user_id
    WHERE f.is_public=1 ORDER BY f.created_at DESC LIMIT 4
");
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div class="hero-eyebrow">🇱🇰 Sri Lanka Transport Booking</div>
      <h1>Travel <span>smarter</span> across Sri Lanka</h1>
      <p>Search and book buses, trains, and three-wheelers in seconds. Instant e-tickets, secure payments, live schedules.</p>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <a href="<?= SITE_URL ?>/search.php" class="btn btn-fire btn-lg"><i class="fas fa-search"></i> Search Trips</a>
        <?php if (!is_logged_in()): ?>
        <a href="<?= SITE_URL ?>/register.php" class="btn btn-ghost btn-lg">Create Free Account</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick search -->
    <div class="search-box fade-in" style="margin-top:2.5rem">
      <div class="label" style="margin-bottom:.85rem"><i class="fas fa-search"></i> Quick Search</div>
      <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-grid route-search-grid" id="searchForm">
        <div class="route-field-group" aria-label="Route fields">
          <div class="fld route-field">
            <div class="fw">
              <i class="fas fa-map-marker-alt fc-icon"></i>
              <input type="text" name="from" id="search_from" class="fc has-icon" list="location-options" placeholder="From (Colombo, Kandy…)" required>
            </div>
          </div>
          <button type="button" id="swapBtn" class="route-swap-btn" title="Swap locations" aria-label="Swap from and to locations">
            <i class="fas fa-exchange-alt" aria-hidden="true"></i>
          </button>
          <div class="fld route-field route-field-to">
            <div class="fw">
              <i class="fas fa-map-pin fc-icon"></i>
              <input type="text" name="to" id="search_to" class="fc has-icon" list="location-options" placeholder="To (Galle, Jaffna…)" required>
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
        <div>
          <div class="fld" style="margin-bottom:0">
            <div class="fw">
              <i class="fas fa-calendar-alt fc-icon"></i>
              <input type="date" name="date" id="search_date" class="fc has-icon" min="<?= date('Y-m-d') ?>">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-fire btn-lg" style="white-space:nowrap"><i class="fas fa-search"></i> Search</button>
      </form>
      <!-- Transport type pills -->
      <div style="display:flex;gap:.5rem;margin-top:.85rem;flex-wrap:wrap">
        <?php foreach (['Bus','Train','Three-Wheel','Taxi'] as $t): ?>
        <a href="<?= SITE_URL ?>/search.php?type=<?= urlencode($t) ?>" class="type-pill tp-<?= strtolower(explode('-',$t)[0]) ?>" style="text-decoration:none;transition:opacity .15s" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
          <i class="fas fa-<?= $t==='Train'?'train':($t==='Bus'?'bus':'car') ?>"></i> <?= $t ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<section style="background:var(--ink2);border-bottom:1px solid var(--rim);padding:2.5rem 0">
  <div class="container">
    <div class="grid-4">
      <?php
      $statItems = [
        ['icon'=>'route',      'color'=>'var(--fire)',  'bg'=>'var(--fire-soft)', 'num'=>$stats['routes'],   'lbl'=>'Active Routes'],
        ['icon'=>'ticket-alt', 'color'=>'var(--ok)',    'bg'=>'rgba(34,197,94,.1)','num'=>$stats['bookings'],'lbl'=>'Bookings Made'],
        ['icon'=>'users',      'color'=>'var(--info)',  'bg'=>'rgba(56,189,248,.1)','num'=>$stats['users'],  'lbl'=>'Happy Passengers'],
        ['icon'=>'bus',        'color'=>'var(--warn)',  'bg'=>'rgba(245,158,11,.1)','num'=>$stats['types'],  'lbl'=>'Transport Types'],
      ];
      foreach ($statItems as $s): ?>
      <div class="stat-card">
        <div class="stat-ico" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>"><i class="fas fa-<?= $s['icon'] ?>"></i></div>
        <div>
          <div class="stat-num"><?= number_format($s['num']) ?></div>
          <div class="stat-lbl"><?= $s['lbl'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- UPCOMING TRIPS -->
<?php if ($schedules): ?>
<section style="padding:3.5rem 0">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
      <div>
        <h2>Today's Departures</h2>
        <p style="margin-top:.25rem">Book your seat before it fills up</p>
      </div>
      <a href="<?= SITE_URL ?>/search.php" class="btn btn-ghost btn-sm">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="grid-2">
      <?php foreach ($schedules as $sch):
        $typeClass = strtolower(str_replace(['-',' '],'',$sch['type_name']));
        $typeClass = $typeClass === 'threewheel' ? 'wheel' : (strlen($typeClass) > 5 ? substr($typeClass,0,4) : $typeClass);
      ?>
      <a href="<?= SITE_URL ?>/book.php?schedule=<?= $sch['id'] ?>" class="trip-card card-link" style="text-decoration:none">
        <div>
          <div style="margin-bottom:.6rem"><?= status_badge($sch['status']) ?> <span class="type-pill tp-<?= $typeClass ?>"><?= e($sch['type_name']) ?></span></div>
          <div class="trip-route">
            <?= e($sch['origin']) ?> <span class="trip-arrow">→</span> <?= e($sch['destination']) ?>
          </div>
          <div class="trip-meta">
            <span><i class="fas fa-clock" style="color:var(--fire)"></i> <?= substr($sch['departure_time'],0,5) ?></span>
            <span><i class="fas fa-hourglass-half" style="color:var(--chalk4)"></i> <?= e($sch['estimated_duration']) ?></span>
            <span><i class="fas fa-chair" style="color:var(--ok)"></i> <?= $sch['available_seats'] ?> seats left</span>
          </div>
        </div>
        <div>
          <div class="trip-price">Rs.<?= number_format($sch['fare'],0) ?></div>
          <div class="trip-seats"><?= date('D, M j',strtotime($sch['departure_date'])) ?></div>
          <div style="margin-top:.5rem;font-size:.78rem;color:var(--fire)">Book Now →</div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- HOW IT WORKS -->
<section style="background:var(--ink2);border-top:1px solid var(--rim);border-bottom:1px solid var(--rim);padding:3.5rem 0">
  <div class="container">
    <div style="text-align:center;margin-bottom:2.5rem">
      <h2>Book in 3 Simple Steps</h2>
      <p>From search to ticket in under 2 minutes</p>
    </div>
    <div class="grid-3">
      <?php
      $steps = [
        ['ico'=>'search','col'=>'var(--fire)','bg'=>'var(--fire-soft)','n'=>'01','t'=>'Search','d'=>'Enter your origin, destination and travel date. Filter by bus, train or three-wheel.'],
        ['ico'=>'couch', 'col'=>'var(--ok)',  'bg'=>'rgba(34,197,94,.1)','n'=>'02','t'=>'Select & Book','d'=>'Choose your preferred trip, pick your seats and complete the secure payment.'],
        ['ico'=>'ticket-alt','col'=>'var(--info)','bg'=>'rgba(56,189,248,.1)','n'=>'03','t'=>'Get Your Ticket','d'=>'Download or print your e-ticket with QR code. Show it to the conductor.'],
      ];
      foreach ($steps as $s): ?>
      <div class="card card-pad" style="text-align:center;padding:2rem 1.5rem">
        <div style="width:60px;height:60px;border-radius:var(--r4);background:<?= $s['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:<?= $s['col'] ?>;margin:0 auto .85rem">
          <i class="fas fa-<?= $s['ico'] ?>"></i>
        </div>
        <div style="font-size:.7rem;font-weight:800;letter-spacing:.15em;color:<?= $s['col'] ?>;margin-bottom:.4rem"><?= $s['n'] ?></div>
        <h3 style="margin-bottom:.5rem"><?= $s['t'] ?></h3>
        <p style="font-size:.9rem"><?= $s['d'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- REVIEWS -->
<?php if ($reviews): ?>
<section style="padding:3.5rem 0">
  <div class="container">
    <div style="text-align:center;margin-bottom:2rem">
      <h2>What Passengers Say</h2>
      <p>Real reviews from real travellers</p>
    </div>
    <div class="grid-2">
      <?php foreach ($reviews as $r): ?>
      <div class="card card-pad">
        <div style="display:flex;gap:.35rem;margin-bottom:.75rem">
          <?php for ($i=1;$i<=5;$i++): ?>
          <span style="color:<?= $i<=$r['rating']?'var(--warn)':'var(--chalk4)' ?>"><i class="fas fa-star"></i></span>
          <?php endfor; ?>
        </div>
        <p style="font-size:.9rem;line-height:1.7;margin-bottom:.85rem;color:var(--chalk2)">"<?= e($r['comment']) ?>"</p>
        <div style="display:flex;align-items:center;gap:.6rem">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--fire-soft);color:var(--fire);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem"><?= strtoupper(substr($r['name'],0,1)) ?></div>
          <div>
            <div style="font-size:.85rem;font-weight:600;color:var(--chalk)"><?= e($r['name']) ?></div>
            <div style="font-size:.73rem;color:var(--chalk4)"><?= date('M j, Y',strtotime($r['created_at'])) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CTA BANNER -->
<?php if (!is_logged_in()): ?>
<section style="background:linear-gradient(135deg,var(--fire2),var(--fire));padding:3.5rem 0;text-align:center">
  <div class="container">
    <h2 style="color:#fff;font-size:clamp(1.6rem,3vw,2.4rem);margin-bottom:.75rem">Ready to travel smarter?</h2>
    <p style="color:rgba(255,255,255,.8);margin-bottom:1.75rem;font-size:1rem">Join thousands of passengers booking with RideFlow every day.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= SITE_URL ?>/register.php" class="btn btn-lg" style="background:#fff;color:var(--fire)"><i class="fas fa-user-plus"></i> Create Free Account</a>
      <a href="<?= SITE_URL ?>/search.php" class="btn btn-lg" style="background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.4);color:#fff"><i class="fas fa-search"></i> Search First</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once RF_ROOT.'/includes/footer.php'; ?>
