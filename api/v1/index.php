<?php
/**
 * RideFlow REST API v1
 * Base: /api/v1/
 * Auth: Bearer token in Authorization header
 * Format: {"ok":true,"data":{...}} or {"ok":false,"code":N,"message":"..."}
 */
define('API_MODE', true);
define('RF_ROOT', dirname(dirname(__DIR__)));

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Authorization,Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once RF_ROOT.'/config/database.php';
require_once RF_ROOT.'/includes/helpers.php';
require_once RF_ROOT.'/includes/auth.php';

// Routing
$uri      = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts    = explode('/', $uri);
$idx      = array_search('v1', $parts);
$segs     = $idx !== false ? array_slice($parts, $idx+1) : [];
$resource = $segs[0] ?? '';
$id       = isset($segs[1]) && is_numeric($segs[1]) ? (int)$segs[1] : null;
$sub      = $segs[2] ?? ($id ? ($segs[1] ?? '') : ($segs[1] ?? ''));
if (is_numeric($segs[1]??'')) $sub = $segs[2] ?? '';

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Auth helper
$_cusr = null;
function api_user() {
    global $_cusr;
    if ($_cusr !== null) return $_cusr;
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) return null;
    $st = db()->prepare("SELECT u.* FROM users u JOIN api_tokens t ON t.user_id=u.id WHERE t.token=? AND t.expires_at>NOW() AND u.status='active' LIMIT 1");
    $st->execute([trim($m[1])]);
    $_cusr = $st->fetch() ?: null;
    if ($_cusr) db_exec("UPDATE api_tokens SET last_used=NOW() WHERE token=?", [trim($m[1])]);
    return $_cusr;
}
function api_require_auth() { $u=api_user(); if(!$u) api_err(401,'Authentication required.'); return $u; }

function gen_api_token($userId) {
    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', strtotime('+90 days'));
    try { db_exec("INSERT INTO api_tokens(user_id,token,expires_at) VALUES(?,?,?)", [$userId,$token,$exp]); }
    catch(Exception $e) {}
    return $token;
}

try {
    switch ($resource) {
        // Health check
        case '':
            api_ok(['service'=>'RideFlow API','version'=>'1.0','status'=>'ok']);

        // AUTH
        case 'auth':
            if ($method==='POST' && $sub==='register') {
                $r = auth_register($body['name']??'', $body['email']??'', $body['phone']??'', $body['password']??'');
                if (!$r['ok']) api_err(422, $r['msg']);
                api_ok(['user_id'=>$r['user_id'],'message'=>'Account created. Check email to verify.'], 201);
            }
            if ($method==='POST' && $sub==='login') {
                $by = !empty($body['email']) ? auth_login_email($body['email']??'', $body['password']??'')
                                             : auth_login_phone($body['phone']??'', $body['password']??'');
                if (!$by['ok']) api_err(401, $by['msg']);
                $token = gen_api_token($by['user']['id']);
                session_write_close();
                api_ok(['token'=>$token,'user'=>array_intersect_key($by['user'],array_flip(['id','name','email','phone','role','avatar_url']))]);
            }
            if ($method==='POST' && $sub==='social') {
                $r = auth_social_login($body['provider']??'', $body['social_id']??'', $body['name']??'', $body['email']??'', $body['avatar_url']??'');
                if (!$r['ok']) api_err(400, $r['msg']);
                $token = gen_api_token($r['user']['id']);
                session_write_close();
                api_ok(['token'=>$token,'user'=>array_intersect_key($r['user'],array_flip(['id','name','email','phone','role','avatar_url']))]);
            }
            if ($method==='POST' && $sub==='logout') {
                $u = api_require_auth();
                $hdr=$_SERVER['HTTP_AUTHORIZATION']??'';
                if(preg_match('/Bearer\s+(.+)/i',$hdr,$m)) db_exec("DELETE FROM api_tokens WHERE token=?",[trim($m[1])]);
                api_ok(['message'=>'Logged out.']);
            }
            api_err(404,'Auth endpoint not found.');

        // ROUTES
        case 'routes':
            if ($method !== 'GET') api_err(405,'Method not allowed.');
            if ($id) {
                $r = db_row("SELECT r.*,t.type_name FROM routes r JOIN transport_types t ON t.id=r.type_id WHERE r.id=? AND r.status='active'", [$id]);
                if (!$r) api_err(404,'Route not found.');
                $r['stops'] = db_all("SELECT * FROM route_stops WHERE route_id=? ORDER BY stop_order", [$id]);
                api_ok($r);
            }
            $tp = $_GET['type'] ?? ''; $params=[];$where=["r.status='active'"];
            if ($tp){$where[]="t.type_name=?";$params[]=$tp;}
            api_ok(paginate("SELECT r.*,t.type_name FROM routes r JOIN transport_types t ON t.id=r.type_id WHERE ".implode(' AND ',$where)." ORDER BY r.origin", $params, (int)($_GET['page']??1), 50));

        // SCHEDULES
        case 'schedules':
            if ($method !== 'GET') api_err(405,'Method not allowed.');
            if ($id) {
                $s = db_row("SELECT s.*,r.origin,r.destination,r.route_name,r.estimated_duration,t.type_name,v.vehicle_name,v.vehicle_number FROM schedules s JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id JOIN vehicles v ON v.id=s.vehicle_id WHERE s.id=?", [$id]);
                if (!$s) api_err(404,'Schedule not found.');
                api_ok($s);
            }
            $where=["s.status='scheduled'","s.departure_date>=CURRENT_DATE"]; $params=[];
            if (!empty($_GET['route_id'])){$where[]="s.route_id=?";$params[]=(int)$_GET['route_id'];}
            if (!empty($_GET['date'])){$where[]="s.departure_date=?";$params[]=$_GET['date'];}
            $sql="SELECT s.id,s.departure_date,s.departure_time,s.arrival_time,s.fare,s.available_seats,r.origin,r.destination,r.estimated_duration,t.type_name,v.vehicle_name FROM schedules s JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id JOIN vehicles v ON v.id=s.vehicle_id WHERE ".implode(' AND ',$where)." ORDER BY s.departure_date,s.departure_time";
            api_ok(paginate($sql, $params, (int)($_GET['page']??1), 30));

        // SEARCH
        case 'search':
            if ($method !== 'GET') api_err(405,'Method not allowed.');
            $from  = trim($_GET['from'] ?? ''); $to = trim($_GET['to'] ?? '');
            $date  = trim($_GET['date'] ?? date('Y-m-d'));
            $seats = max(1,(int)($_GET['seats']??1));
            if (!$from || !$to) api_err(422,'from and to required.');
            $results = db_all("
                SELECT s.id,s.departure_date,s.departure_time,s.arrival_time,s.fare,s.available_seats,
                       r.origin,r.destination,r.estimated_duration,t.type_name,v.vehicle_name
                FROM schedules s JOIN routes r ON r.id=s.route_id
                JOIN transport_types t ON t.id=r.type_id JOIN vehicles v ON v.id=s.vehicle_id
                WHERE s.status='scheduled' AND s.departure_date=? AND s.available_seats>=? AND r.status='active'
                AND (r.origin LIKE ? OR r.origin LIKE ?) AND (r.destination LIKE ? OR r.destination LIKE ?)
                ORDER BY s.departure_time",
                [$date,$seats,"%$from%","%$from%","%$to%","%$to%"]);
            api_ok(['results'=>$results,'count'=>count($results)]);

        // BOOKINGS
        case 'bookings':
            $u = api_require_auth();
            if ($method==='GET' && !$id) {
                api_ok(paginate("SELECT b.*,r.origin,r.destination,t.type_name,s.departure_date,s.departure_time FROM bookings b JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id WHERE b.user_id=? ORDER BY b.created_at DESC",[$u['id']],(int)($_GET['page']??1)));
            }
            if ($method==='GET' && $id) {
                $bk = db_row("SELECT b.*,r.origin,r.destination,r.estimated_duration,t.type_name,v.vehicle_name,s.departure_date,s.departure_time,s.arrival_time FROM bookings b JOIN schedules s ON s.id=b.schedule_id JOIN routes r ON r.id=s.route_id JOIN transport_types t ON t.id=r.type_id JOIN vehicles v ON v.id=s.vehicle_id WHERE b.id=? AND b.user_id=?", [$id,$u['id']]);
                if (!$bk) api_err(404,'Booking not found.');
                api_ok($bk);
            }
            if ($method==='POST') {
                $schedId        = (int)($body['schedule_id']??0);
                $seats          = max(1,min(10,(int)($body['seats']??1)));
                $passengerEmail = trim($body['passenger_email'] ?? $u['email'] ?? '');
                if (!$schedId) api_err(422,'schedule_id required.');
                db()->beginTransaction();
                try {
                    $sch = db_row("SELECT * FROM schedules WHERE id=? AND status='scheduled' FOR UPDATE",[$schedId]);
                    if (!$sch) { db()->rollBack(); api_err(404,'Schedule not available.'); }
                    if ($sch['available_seats']<$seats) { db()->rollBack(); api_err(409,"Only {$sch['available_seats']} seats available."); }
                    $total = $sch['fare'] * $seats;
                    $ref   = booking_ref();
                    $bkId  = db_insert("INSERT INTO bookings(user_id,schedule_id,booking_ref,seats_booked,total_fare,passenger_name,passenger_phone,passenger_email,booking_status,payment_status) VALUES(?,?,?,?,?,?,?,?,?,'pending','unpaid')",[$u['id'],$schedId,$ref,$seats,$total,$u['name'],$u['phone']??'',$passengerEmail ?: null]);
                    if (!$bkId) { db()->rollBack(); api_err(500,'Booking creation failed.'); }
                    db_exec("UPDATE schedules SET available_seats=available_seats-? WHERE id= ?",[$seats,$schedId]);
                    db()->commit();
                    $booking = db_row("SELECT * FROM bookings WHERE id=?",[$bkId]);
                    if (!$booking) api_err(500,'Booking could not be retrieved after creation.');
                    api_ok($booking,201);
                } catch(Exception $e){ db()->rollBack(); api_err(500,$e->getMessage()); }
            }
            if ($method==='DELETE' && $id) {
                $bk=db_row("SELECT * FROM bookings WHERE id=? AND user_id=?",[$id,$u['id']]);
                if (!$bk) api_err(404,'Booking not found.');
                db_exec("UPDATE bookings SET booking_status='cancelled' WHERE id=?",[$id]);
                db_exec("UPDATE schedules SET available_seats=available_seats+? WHERE id=?",[$bk['seats_booked'],$bk['schedule_id']]);
                api_ok(['message'=>'Booking cancelled.']);
            }
            api_err(405,'Method not allowed.');

        // PROFILE
        case 'profile':
            $u = api_require_auth();
            if ($method==='GET') {
                $prof = db_row("SELECT id,name,email,phone,avatar_url,created_at FROM users WHERE id=?",[$u['id']]);
                $prof['stats'] = db_row("SELECT COUNT(*) total, SUM(total_fare) spent FROM bookings WHERE user_id=?",[$u['id']]);
                api_ok($prof);
            }
            if ($method==='PUT') {
                $name=$body['name']??''; $email=$body['email']??'';
                if($name) db_exec("UPDATE users SET name=? WHERE id=?",[$name,$u['id']]);
                if($email) db_exec("UPDATE users SET email=? WHERE id=?",[$email,$u['id']]);
                api_ok(['message'=>'Profile updated.']);
            }
            api_err(405,'Method not allowed.');

        // CHAT
        case 'chat':
            if ($method!=='POST') api_err(405,'Method not allowed.');
            $msg  = trim($body['message']??'');
            $hist = is_array($body['history']??null) ? $body['history'] : [];
            if (!$msg) api_err(422,'message required.');
            $apiKey = ANTHROPIC_API_KEY ?: setting('anthropic_api_key');
            if (!$apiKey) api_err(503,'AI not configured.');
            $routes=db_all("SELECT r.origin,r.destination,t.type_name,MIN(s.fare) mf FROM routes r JOIN transport_types t ON t.id=r.type_id LEFT JOIN schedules s ON s.route_id=r.id AND s.departure_date>=CURRENT_DATE WHERE r.status='active' GROUP BY r.id LIMIT 15");
            $ctx=implode("\n",array_map(fn($r)=>"• {$r['type_name']}: {$r['origin']}→{$r['destination']} (from Rs.{$r['mf']})",$routes));
            $msgs=[];
            foreach(array_slice($hist,-6)as $h){if(isset($h['role'],$h['content'])&&in_array($h['role'],['user','assistant'],true))$msgs[]=['role'=>$h['role'],'content'=>(string)$h['content']];}
            $msgs[]=['role'=>'user','content'=>$msg];
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'model'    => 'claude-sonnet-4-20250514',
                    'max_tokens' => 350,
                    'system'   => "You are RideFlow Assistant for Sri Lanka transport booking. Routes:\n$ctx\nBe concise and helpful.",
                    'messages' => $msgs,
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: '.$apiKey,
                    'anthropic-version: 2023-06-01',
                ],
            ]);
            $res = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            $data = json_decode($res, true);
            $reply = '';
            if ($err || $http !== 200 || json_last_error() !== JSON_ERROR_NONE) {
                api_err(502,'AI response error.');
            }
            if (isset($data['content'][0]['text']) && $data['content'][0]['text'] !== '') {
                $reply = (string)$data['content'][0]['text'];
            } elseif (isset($data['completion']) && $data['completion'] !== '') {
                $reply = (string)$data['completion'];
            } elseif (isset($data['output_text']) && $data['output_text'] !== '') {
                $reply = (string)$data['output_text'];
            }
            if ($reply === '') {
                api_err(502,'AI response error.');
            }
            api_ok(['reply'=>$reply]);

        // LIVE
        case 'live':
            api_ok(['schedules_today'=>db_val("SELECT COUNT(*) FROM schedules WHERE departure_date=CURRENT_DATE AND status='scheduled'"),'active_routes'=>db_val("SELECT COUNT(*) FROM routes WHERE status='active'"),'total_users'=>db_val("SELECT COUNT(*) FROM users WHERE role='customer' AND status='active'"),'server_time'=>time()]);

        // FEEDBACK
        case 'feedback':
            if ($method!=='POST') api_err(405,'Method not allowed.');
            $u=api_require_auth();
            $bkId=(int)($body['booking_id']??0);
            if(!$bkId) api_err(422,'booking_id required.');
            $bk=db_row("SELECT id FROM bookings WHERE id=? AND user_id=?",[$bkId,$u['id']]);
            if(!$bk) api_err(404,'Booking not found.');
            try{ db_insert("INSERT INTO feedback(user_id,booking_id,rating,comment) VALUES(?,?,?,?)",[$u['id'],$bkId,max(1,min(5,(int)($body['rating']??5))),trim($body['comment']??'')]); }
            catch(Exception $e){ api_err(409,'Feedback already submitted.'); }
            api_ok(['message'=>'Thank you for your feedback!']);

        default:
            api_err(404,'Endpoint not found. GET / for endpoint list.');
    }
} catch (Exception $e) {
    api_err(500,'Server error: '.$e->getMessage());
}
