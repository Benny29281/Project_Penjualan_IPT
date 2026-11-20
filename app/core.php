<?php
declare(strict_types=1);
require __DIR__.'/seed.php';
date_default_timezone_set('Asia/Jakarta');
define('DATA', PHP_SAPI==='cli' || PHP_SAPI==='cli-server' ? (getenv('TOBAPIA_DATA_DIR') ?: __DIR__.'/../storage') : __DIR__.'/../storage');

function db(): PDO {
    static $db;
    if ($db) return $db;
    if (!is_dir(DATA)) mkdir(DATA, 0700);
    $db = new PDO('sqlite:'.DATA.'/tobapia.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    if ((int)$db->query('PRAGMA user_version')->fetchColumn()<3) {
        atomic(function () use ($db) {
            $version=(int)$db->query('PRAGMA user_version')->fetchColumn();
            if ($version<1) {
                $db->exec(file_get_contents(__DIR__.'/schema.sql'));
                if (!row("SELECT 1 FROM settings WHERE key='initialized'")) {
                    foreach (['box'=>4,'tiers'=>'{"1":400000,"2":400000,"3":450000,"4":450000}','packs'=>'both','partial_shipping'=>'round_up',
                        'mode'=>'demo','device'=>'','qr'=>'','logo'=>'','phone'=>'','bank'=>'','notice_en'=>'','notice_id'=>'','notice_zh'=>'','notice_ko'=>'',
                        'message'=>'{order}','notify'=>'','initialized'=>'1'] as $key=>$value) saveSetting($key, (string)$value);
                    seed();
                }
            }
            if ($version<2) {
                $db->exec("ALTER TABLE products ADD COLUMN descriptions TEXT NOT NULL DEFAULT '{}';
                    ALTER TABLE products ADD COLUMN specifications TEXT NOT NULL DEFAULT '[]';
                    PRAGMA user_version=2;");
            }
            if ($version<3) {
                $db->exec("ALTER TABLE orders ADD COLUMN delivery_details TEXT NOT NULL DEFAULT '{}';
                    ALTER TABLE orders ADD COLUMN document_number TEXT;
                    WITH daily AS (
                        SELECT id, strftime('%Y%m%d',created,'+7 hours') AS order_day,
                            ROW_NUMBER() OVER (PARTITION BY date(created,'+7 hours') ORDER BY created,id) AS sequence
                        FROM orders
                    )
                    UPDATE orders SET document_number=(
                        SELECT printf('%s_%04d',order_day,sequence) FROM daily WHERE daily.id=orders.id
                    );
                    CREATE UNIQUE INDEX orders_document_number ON orders(document_number);
                    PRAGMA user_version=3;");
            }
        });
    }
    return $db;
}
function run(string $sql, array $params=[]): PDOStatement {
    $statement = db()->prepare($sql);
    $statement->execute(array_values($params));
    return $statement;
}
function rows(string $sql, array $params=[]): array { return run($sql,$params)->fetchAll(); }
function row(string $sql, array $params=[]): ?array { return run($sql,$params)->fetch() ?: null; }
function insert(string $sql, array $params=[]): int { run($sql,$params); return (int) db()->lastInsertId(); }
function atomic(callable $fn): mixed {
    $db = db();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $fn();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
function settings(): array { return array_column(rows('SELECT key,value FROM settings'), 'value', 'key'); }
function saveSetting(string $key, string $value): void { run('INSERT INTO settings VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key,$value]); }
function audit(string $action, string $detail): void { run('INSERT INTO audit(actor,action,detail) VALUES (?,?,?)',[currentUser(true)['login'] ?? 'system',$action,$detail]); }
function h(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function json(mixed $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
function decode(string $value): array { return json_decode($value,true) ?: []; }
function money(int $amount): string { return 'Rp'.number_format($amount, 0, ',', '.'); }
function shortDate(?string $date): string {
    if (!$date) return '';
    return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y, H:i').' WIB';
}
function input(string $key, int $max=200): string { return mb_substr(trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : ''),0,$max); }
function query(string $key): string { return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : ''; }
function requireThat(bool $test, string $message): void { if (!$test) throw new DomainException($message); }
function integer(string $value, int $min=0, int $max=100000000): int {
    $number = filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
    requireThat($number !== false,t('invalid_number'));
    return $number;
}
function phone(string $value): string {
    requireThat((bool)preg_match('/^[+0-9\s().-]+$/D',$value), t('invalid_phone'));
    $number = preg_replace('/\D/','',$value);
    if (str_starts_with($number,'0')) $number = '62'.substr($number,1);
    requireThat((bool)preg_match('/^[1-9]\d{7,14}$/D',$number),t('invalid_phone'));
    return $number;
}
function cookieOptions(int $expires=0): array {
    return ['expires'=>$expires,'path'=>BASE ?: '/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax'];
}
function path(string $route='/'): string { return BASE.$route; }
function go(string $route): never { header('Location: '.path($route),true,303); exit; }
function flash(string $message, bool $error=false): void { $_SESSION['flash'] = [$message,$error]; }
function token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.h(token()).'">'; }
function form(string $action, string $class='', bool $files=false, ?string $target=null): string {
    return '<form method="post" action="'.h(path($target ?? ROUTE)).'" class="'.h($class).'"'.($files ? ' enctype="multipart/form-data"' : '').'>'.csrf().'<input type="hidden" name="action" value="'.h($action).'">';
}
function currentUser(bool $admin=false): ?array {
    $id = $_SESSION[$admin ? 'admin' : 'customer'] ?? 0;
    return $id ? row("SELECT * FROM users WHERE id=? AND blocked=0 AND role ".($admin ? "IN ('owner','staff')" : "='customer'"),[$id]) : null;
}
function rate(string $scope, int $limit, int $duration): void {
    $hash = hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $allowed = atomic(function () use ($hash,$limit,$duration) {
        $now = time();
        run('DELETE FROM attempts WHERE time < ?',[$now-86400]);
        $count = (int) row('SELECT COUNT(*) AS n FROM attempts WHERE scope=? AND time>?',[$hash,$now-$duration])['n'];
        if ($count >= $limit) return false;
        run('INSERT INTO attempts VALUES (?,?)',[$hash,$now]);
        return true;
    });
    requireThat($allowed,t('rate'));
}
function login(string $role, string $identity, string $password): void {
    rate('login-'.$role,12,900);
    $candidate = $role === 'customer'
        ? row("SELECT * FROM users WHERE role='customer' AND login=?",[$identity])
        : row("SELECT * FROM users WHERE role IN ('owner','staff') AND login=?",[$identity]);
    $hash = $candidate['password'] ?? '$2y$10$C8xvA0xHmH4AyW88y47JtemirUlJo8kuOuZA4V.nf7dBxpfDHvksG';
    requireThat(password_verify($password,$hash) && $candidate && !$candidate['blocked'],t('login_invalid'));
    session_regenerate_id(true);
    if ($role === 'customer') {
        atomic(function () use ($candidate) {
            $merged=decode(row('SELECT cart FROM users WHERE id=?',[$candidate['id']])['cart']);
            foreach (guestCart() as $id=>$quantity) $merged[$id]=max($quantity,$merged[$id] ?? 0);
            saveGuestCart([]);
            $_SESSION['customer']=$candidate['id'];
            saveCart($merged);
        });
    } else $_SESSION['admin'] = $candidate['id'];
}
function loginId(string $value): string {
    return filter_var($value,FILTER_VALIDATE_EMAIL) ? strtolower($value) : phone($value);
}
function newUser(string $role, string $name, string $identity, string $password): int {
    requireThat(mb_strlen($name)>=2 && strlen($password)>=10 && strlen($password)<=72,t('account_rules'));
    requireThat(!row("SELECT id FROM users WHERE ".($role==='customer' ? "role='customer'" : "role IN ('owner','staff')")." AND login=?",[$identity]),t('login_used'));
    return insert('INSERT INTO users(role,login,password,name) VALUES (?,?,?,?)',[$role,$identity,password_hash($password,PASSWORD_DEFAULT),$name]);
}
function guestCartKey(): string {
    $key=$_COOKIE['tp_cart'] ?? '';
    return is_string($key) && preg_match('/^[0-9a-f]{64}$/D',$key) ? $key : '';
}
function guestCart(): array {
    $key=guestCartKey();
    return $key ? decode(row('SELECT cart FROM guest_carts WHERE token=? AND touched>?',[$key,time()-2592000])['cart'] ?? '{}') : ($_SESSION['cart'] ?? []);
}
function saveGuestCart(array $cart): void {
    unset($_SESSION['cart']);
    $key=guestCartKey();
    if (!$cart) {
        if ($key) run('DELETE FROM guest_carts WHERE token=?',[$key]);
        return;
    }
    $key=$key ?: bin2hex(random_bytes(32));
    $now=time();
    run('DELETE FROM guest_carts WHERE touched <= ?',[$now-2592000]);
    run('INSERT INTO guest_carts VALUES (?,?,?) ON CONFLICT(token) DO UPDATE SET cart=excluded.cart,touched=excluded.touched',[$key,json($cart),$now]);
    setcookie('tp_cart',$key,cookieOptions($now+2592000));
    $_COOKIE['tp_cart']=$key;
}
function cart(): array { return ($user=currentUser()) ? decode($user['cart']) : guestCart(); }
function saveCart(array $cart): void {
    if ($user=currentUser()) {
        run('UPDATE users SET cart=? WHERE id=?',[json($cart),$user['id']]);
        unset($_SESSION['cart']);
    } else saveGuestCart($cart);
}
function names(array $product): array {
    return array_intersect_key($product,array_flip(['name_en','name_id','name_zh','name_ko']));
}
function productName(array $product, ?string $locale=null): string {
    return ($product['name_'.($locale ?? lang())] ?? '') ?: ($product['name_en'] ?? '');
}
function eligible(array $v, array $s): bool {
    if (!$v['price'] || !($v['status']==='published' || ($s['mode']==='demo' && $v['demo'] && $v['status']==='draft'))) return false;
    return !in_array($v['unit'],['pack','carton'],true) || $s['packs']==='both' || $s['packs']===$v['unit'];
}
function variant(int $id): ?array {
    return row('SELECT v.*,p.category,p.edition,p.status,p.demo,p.name_en,p.name_id,p.name_zh,p.name_ko FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?',[$id]);
}
function shipping(int $packs, int $devices, array $s): array {
    $cartons = $s['partial_shipping']==='full_only' ? intdiv($packs,10) : intdiv($packs+9,10);
    $box = (int)$s['box'];
    $tiers = decode($s['tiers']);
    $terea = intdiv($cartons,$box)*$tiers[$box] + ($tiers[$cartons % $box] ?? 0);
    return [$terea,$devices && $s['device']!=='' ? $devices*(int)$s['device'] : null];
}
function quote(array $cart, array $s): array {
    $items=[]; $subtotal=0; $packs=0; $devices=0; $invalid=false; $stock=[];
    foreach ($cart as $id=>$quantity) {
        $v=variant((int)$id);
        if (!$v || !eligible($v,$s) || $quantity<1 || $quantity>99 || $quantity>$v['stock']) {
            $invalid=true;
            continue;
        }
        $v['qty']=$quantity;
        $items[]=$v;
        $subtotal+=(int)$v['price']*$quantity;
        $packs+=($v['unit']==='carton' ? 10 : ($v['unit']==='pack' ? 1 : 0))*$quantity;
        $devices+=($v['unit']==='unit' ? $quantity : 0);
    }
    [$terea,$device]=shipping($packs,$devices,$s);
    return compact('items','subtotal','terea','device','invalid')+['total'=>$subtotal+$terea+$device];
}
function items(int $id): array { return rows('SELECT * FROM order_items WHERE order_id=? ORDER BY id',[$id]); }
function orderDisplayNumber(array $order): string { return $order['document_number'] ?: $order['number']; }
function nextOrderDocumentNumber(string $created): string {
    $day=date('Ymd',strtotime($created.' UTC'));
    $last=row("SELECT MAX(CAST(substr(document_number,10) AS INTEGER)) AS sequence FROM orders WHERE document_number GLOB ?",[$day.'_[0-9]*'])['sequence'];
    return sprintf('%s_%04d',$day,(int)$last+1);
}
function lookup(string $number, bool $admin=false): array {
    $order=row('SELECT * FROM orders WHERE number=?',[$number]);
    $user=currentUser();
    requireThat($order && ($admin ? currentUser(true) : (($user && $order['customer_id']===$user['id']) || isset($_SESSION['owned'][$number]))), t('order_missing'));
    return $order;
}
function orderLines(array $order, string $locale): array {
    $lines = [];
    foreach (items($order['id']) as $item) {
        $lines[] = $item['category'].' · '.productName(decode($item['names']),$locale).' · '.t($item['unit'],$locale).' × '.$item['qty'].'  |  '.money($item['price']).'  |  '.money($item['price']*$item['qty']);
    }
    return $lines;
}
function paymentPending(array $order): bool { return in_array($order['status'],['new','pending'],true); }
function totals(array $data, ?string $locale=null): array {
    $lines = [t('subtotal',$locale)=>money($data['subtotal'])];
    if ($data['terea']) $lines[t('terea_shipping',$locale)] = money($data['terea']);
    if ($data['device']!==null) $lines[t('device_shipping',$locale)] = money($data['device']);
    if (!empty($data['extra'])) $lines[t('extra_shipping',$locale)] = money($data['extra']);
    return $lines + [t('total',$locale)=>money($data['total'])];
}
function storeImage(array $file, string $kind): string {
    requireThat(($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && $file['size'] <= 6000000, 'Pilih gambar dengan ukuran maksimal 6 MB.');
    $info = @getimagesize($file['tmp_name']);
    requireThat($info && in_array($info['mime'],['image/png','image/jpeg','image/webp'],true) && $info[0]*$info[1] <= 12000000, 'Gunakan gambar PNG, JPEG, atau WebP yang valid.');
    $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    requireThat($image !== false,'Gambar tidak dapat dibaca.');
    $id = bin2hex(random_bytes(16));
    if ($kind==='product' && max($info[0],$info[1])>1000) {
        $image=imagescale($image,max(1,(int)round($info[0]*1000/max($info[0],$info[1]))),max(1,(int)round($info[1]*1000/max($info[0],$info[1]))));
    }
    $mime=$kind==='product' ? 'image/webp' : 'image/png';
    $written=$kind==='product' ? imagewebp($image,DATA.'/'.$id,86) : imagepng($image,DATA.'/'.$id);
    imagedestroy($image);
    try {
        requireThat($written,'Gagal menyimpan gambar.');
        run('INSERT INTO uploads VALUES (?,?,?)',[$id,$mime,$kind]);
    } catch (Throwable $e) {
        if (is_file(DATA.'/'.$id)) unlink(DATA.'/'.$id);
        throw $e;
    }
    return $id;
}
function imageUrl(string $id): string { return path('/media/'.$id); }
function warning(array $s): string { return $s['notice_'.lang()] ?: t('warning'); }
