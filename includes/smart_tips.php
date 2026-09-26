<?php
/**
 * RideFlow — contextual smart tips for login + dashboards.
 */

function smart_login_tips(string $role): array {
    $hour = (int) date('G');
    $sets = [
        'customer' => [
            ['ico'=>'bolt','title'=>'Book in under a minute','body'=>'Search, pick seats, and get a QR ticket — all from one flow.'],
            ['ico'=>'qrcode','title'=>'Keep your QR handy','body'=>'Show your e-ticket at boarding. No printout needed.'],
            ['ico'=>'clock','title'=>'Off-peak costs less','body'=>'Mid-morning and late-afternoon trips often have more seats and calmer stations.'],
            ['ico'=>'shield-alt','title'=>'Pay your way','body'=>'Card, wallet, bank transfer, or cash at the counter — pick what fits the trip.'],
            ['ico'=>'bell','title'=>'Never miss a departure','body'=>'Turn on notifications so schedule changes reach you instantly.'],
        ],
        'driver' => [
            ['ico'=>'user-check','title'=>'Check your passenger list','body'=>'Confirm names and seats before you roll. It cuts boarding delays.'],
            ['ico'=>'play','title'=>'Start the trip in-app','body'=>'Tap Start Trip so operations and customers see live status.'],
            ['ico'=>'mug-hot','title'=>'Rest before long hauls','body'=>'A 10-minute pause before night routes keeps you sharper on the road.'],
            ['ico'=>'cloud-sun','title'=>'Read the road, not just the clock','body'=>'Rain or peak traffic? Give passengers a quick update from the dashboard.'],
            ['ico'=>'gas-pump','title'=>'Fuel and fitness first','body'=>'Vehicle check + fuel check before departure is the professional standard.'],
        ],
        'admin' => [
            ['ico'=>'hourglass-half','title'=>'Clear pending bookings first','body'=>'Unconfirmed seats lock inventory. Review the queue at the start of each shift.'],
            ['ico'=>'chart-line','title'=>'Watch today\'s pulse','body'=>'Revenue, unpaid tickets, and trips today tell you where to act, not just look.'],
            ['ico'=>'user-shield','title'=>'Approve new riders','body'=>'Pending customer accounts stall bookings. Keep verification moving.'],
            ['ico'=>'envelope-open-text','title'=>'Inbox is operations','body'=>'Unread contact messages often hide cancellations and payment issues.'],
            ['ico'=>'calendar-check','title'=>'Tomorrow is booked today','body'=>'Publish schedules 24 hours ahead so search always has live inventory.'],
        ],
    ];
    $tips = $sets[$role] ?? $sets['customer'];
    if ($hour >= 21 || $hour < 5) {
        array_unshift($tips, ['ico'=>'moon','title'=>'Night operations mode','body'=>'Keep a short checklist: confirm last trips, lock unpaid holds, then rest the queue.']);
    }
    return $tips;
}

function smart_dashboard_tips(string $role, array $ctx = []): array {
    $tips = [];
    $hour = (int) date('G');

    if ($role === 'admin') {
        $pendingBk   = (int)($ctx['pending_bk'] ?? 0);
        $unpaid      = (int)($ctx['unpaid_bk'] ?? 0);
        $pendingUser = (int)($ctx['pending_users'] ?? 0);
        $unread      = (int)($ctx['unread_msg'] ?? 0);
        $todayTrips  = (int)($ctx['schedules_today'] ?? 0);
        $todayBk     = (int)($ctx['today_bookings'] ?? 0);

        if ($pendingBk > 0) {
            $tips[] = ['tone'=>'warn','ico'=>'ticket-alt','title'=>$pendingBk.' booking'.($pendingBk===1?'':'s').' waiting','body'=>'Confirm or release seats so inventory stays honest.','href'=>SITE_URL.'/admin/bookings.php','cta'=>'Review bookings'];
        }
        if ($unpaid > 0) {
            $tips[] = ['tone'=>'fire','ico'=>'credit-card','title'=>$unpaid.' unpaid ticket'.($unpaid===1?'':'s'),'body'=>'Follow up before departure windows close.','href'=>SITE_URL.'/admin/payments.php','cta'=>'Open payments'];
        }
        if ($pendingUser > 0) {
            $tips[] = ['tone'=>'info','ico'=>'user-check','title'=>$pendingUser.' rider'.($pendingUser===1?'':'s').' awaiting approval','body'=>'Verified accounts convert into paid trips faster.','href'=>SITE_URL.'/admin/users.php','cta'=>'Verify users'];
        }
        if ($unread > 0) {
            $tips[] = ['tone'=>'bad','ico'=>'inbox','title'=>$unread.' unread message'.($unread===1?'':'s'),'body'=>'Support replies keep cancellation noise down.','href'=>SITE_URL.'/admin/messages.php','cta'=>'Open inbox'];
        }
        if ($todayTrips === 0) {
            $tips[] = ['tone'=>'info','ico'=>'calendar-plus','title'=>'No trips published today','body'=>'Empty search results mean lost bookings. Add or clone a schedule.','href'=>SITE_URL.'/admin/schedules.php','cta'=>'Add schedule'];
        } else {
            $tips[] = ['tone'=>'ok','ico'=>'route','title'=>$todayTrips.' trips live today','body'=>$todayBk.' booking'.($todayBk===1?'':'s').' already sitting on those routes. Keep drivers assigned.'];
        }
        if ($hour >= 16) {
            $tips[] = ['tone'=>'ok','ico'=>'sun','title'=>'Lock tomorrow before close','body'=>'Evening is the best window to publish next-day schedules and brief drivers.'];
        }
    }

    if ($role === 'customer') {
        $unpaid   = (int)($ctx['unpaid'] ?? 0);
        $upcoming = $ctx['upcoming'] ?? null;
        $total    = (int)($ctx['total'] ?? 0);

        if ($unpaid > 0) {
            $tips[] = ['tone'=>'fire','ico'=>'wallet','title'=>'A ticket is still unpaid','body'=>'Complete payment now so your seat is not released.','href'=>SITE_URL.'/user/bookings.php','cta'=>'Pay now'];
        }
        if (is_array($upcoming)) {
            $tips[] = ['tone'=>'ok','ico'=>'map-marker-alt','title'=>'Next trip: '.$upcoming['origin'].' → '.$upcoming['destination'],'body'=>'Arrive 15 minutes early. Keep your QR ticket ready at boarding.','href'=>SITE_URL.'/user/ticket.php?booking='.(int)$upcoming['id'],'cta'=>'Open ticket'];
        } elseif ($total === 0) {
            $tips[] = ['tone'=>'fire','ico'=>'search','title'=>'Your next ride is one search away','body'=>'Popular lanes fill first. Book Colombo, Kandy, or Galle before peak hours.','href'=>SITE_URL.'/search.php','cta'=>'Find a trip'];
        } else {
            $tips[] = ['tone'=>'info','ico'=>'star','title'=>'Travel like a regular','body'=>'Save favourite routes and book mid-week for calmer stations and better seat choice.'];
        }
        if ($hour >= 18) {
            $tips[] = ['tone'=>'info','ico'=>'moon','title'=>'Night travel tip','body'=>'Keep your ticket QR offline-ready and share your trip with someone at home.'];
        }
    }

    if ($role === 'driver') {
        $next       = $ctx['next'] ?? null;
        $pax        = (int)($ctx['passengers'] ?? 0);
        $cap        = (int)($ctx['capacity'] ?? 0);
        $tripCount  = (int)($ctx['trip_count'] ?? 0);
        $departed   = !empty($ctx['has_departed']);

        if ($departed) {
            $tips[] = ['tone'=>'warn','ico'=>'flag-checkered','title'=>'Trip in motion','body'=>'When you arrive, mark Complete so ops and passengers stay in sync.'];
        }
        if (is_array($next)) {
            $load = $cap > 0 ? round(($pax / $cap) * 100) : 0;
            $tips[] = ['tone'=>'ok','ico'=>'users','title'=>'Next: '.$next['origin'].' → '.$next['destination'],'body'=>$pax.' passenger'.($pax===1?'':'s').' booked'.($cap ? ' · '.$load.'% load' : '').'. Review the list before departure.','href'=>'#passengers','cta'=>'See passengers'];
        } elseif ($tripCount === 0) {
            $tips[] = ['tone'=>'info','ico'=>'headset','title'=>'No trips on your board','body'=>'If this looks wrong, ping operations — assignments update from the admin panel.'];
        }
        if ($hour >= 20) {
            $tips[] = ['tone'=>'warn','ico'=>'moon','title'=>'Night-drive checklist','body'=>'Lights, rest, and a calm boarding pace. Update status so the network stays live.'];
        } else {
            $tips[] = ['tone'=>'ok','ico'=>'heartbeat','title'=>'Professional rhythm','body'=>'Start Trip at departure, Complete on arrival, then review tomorrow\'s first schedule.'];
        }
    }

    return array_slice($tips, 0, 3);
}

function render_smart_tips(array $tips, string $variant = 'dash'): string {
    if (!$tips) return '';
    $html = '<div class="smart-strip smart-strip-'.$variant.'">';
    $html .= '<div class="smart-strip-hd"><span class="smart-pulse"></span> Smart tips</div>';
    $html .= '<div class="smart-strip-grid">';
    foreach ($tips as $t) {
        $tone = e($t['tone'] ?? 'info');
        $html .= '<article class="smart-card smart-'.$tone.'">';
        $html .= '<div class="smart-ico"><i class="fas fa-'.e($t['ico'] ?? 'lightbulb').'"></i></div>';
        $html .= '<div><div class="smart-title">'.e($t['title']).'</div>';
        $html .= '<p>'.e($t['body'] ?? '').'</p>';
        if (!empty($t['href']) && !empty($t['cta'])) {
            $html .= '<a class="smart-cta" href="'.e($t['href']).'">'.e($t['cta']).' <i class="fas fa-arrow-right"></i></a>';
        }
        $html .= '</div></article>';
    }
    $html .= '</div></div>';
    return $html;
}
