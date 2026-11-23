<?php
declare(strict_types=1);

$script=str_replace('\\','/',$_SERVER['SCRIPT_NAME'] ?? '/index.php');
$base=str_ends_with($script,'/index.php') ? substr($script, 0, -strlen('/index.php')) : '';
define('BASE',$base==='/' ? '' : $base);
$route=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
define('ROUTE',rtrim(substr($route,strlen(BASE)),'/') ?: '/');
define('LOCALE',in_array($_COOKIE['tp_language'] ?? '',['en','id','zh','ko'],true) ? $_COOKIE['tp_language'] : 'en');

require __DIR__.'/app/i18n.php';
require __DIR__.'/app/core.php';
require __DIR__.'/app/delivery.php';
require __DIR__.'/app/store-actions.php';
require __DIR__.'/app/admin-actions.php';
require __DIR__.'/app/layout.php';
require __DIR__.'/app/store-pages.php';
require __DIR__.'/app/admin-pages.php';
require __DIR__.'/app/pdf.php';

session_name('tobapia_session');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(array_diff_key(cookieOptions(), ['expires'=>0]));
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

$admin=ROUTE==='/admin' || str_starts_with(ROUTE,'/admin/');
$error='';
$s=[];
try {
    expireUnconfirmedOrders();
    $s=settings();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        requireThat(hash_equals(token(),input('csrf')),t('refresh'));
        $action=input('action');
        if ($action==='language') {
            $lang=input('language');
            if (in_array($lang,['en','id','zh','ko'],true)) setcookie('tp_language',$lang,cookieOptions(time()+31536000));
            go(ROUTE);
        }
        if ($action==='age' && !$admin && empty($_SESSION['age'])) {
            $_SESSION['age']=input('answer')==='yes' ? 'allowed' : 'denied';
            go(ROUTE);
        }
        if ($admin) go(adminPost($action,$s));
        requireThat(($_SESSION['age'] ?? '')==='allowed',t('age_denied'));
        if ($action!=='delivery_fields' || (ROUTE!=='/checkout' && buyCheckoutToken()===null)) go(storefrontPost($action,$s));
    }

    if (ROUTE==='/delivery-regions' && $_SERVER['REQUEST_METHOD']==='GET') {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SESSION['age'] ?? '')!=='allowed') {
            http_response_code(403);
            echo json(['error'=>t('age_denied')]);
            exit;
        }
        $parent=query('parent');
        if (!preg_match('/^(?:ID|[0-9]{2}(?:\.[0-9]{2}){0,2})$/D',$parent)) {
            http_response_code(400);
            echo json(['error'=>t('invalid_delivery_region')]);
            exit;
        }
        echo json(deliveryRegions($parent));
        exit;
    }

    if (preg_match('~^/media/([a-f0-9]{32})$~D',ROUTE,$match)) {
        $image=row('SELECT * FROM uploads WHERE id=?',[$match[1]]);
        if (!$image || !is_file(DATA.'/'.$match[1])) { http_response_code(404); exit; }
        if ($image['kind']!=='logo' && !currentUser(true) && ($_SESSION['age'] ?? '')!=='allowed') { http_response_code(403); exit; }
        if ($image['kind']==='qr' && !currentUser(true)) {
            try {
                $order=lookup(query('order'));
                requireThat($order['qr']===$image['id'] && awaitingPaymentConfirmation($order)
                    && time()<paymentConfirmationDeadline($order),t('order_missing'));
            } catch (DomainException) { http_response_code(404); exit; }
        }
        header('Content-Type: '.$image['mime']);
        readfile(DATA.'/'.$match[1]);
        exit;
    }

    if (preg_match('~^/(admin/)?orders/(TBP-[A-F0-9]{14})/pdf(?:/05)?$~D',ROUTE,$match)) {
        requireThat($admin || ($_SESSION['age'] ?? '')==='allowed',t('age_denied'));
        sendPdf(lookup($match[2],$admin),$admin ? 'id' : lang());
    }
    if (preg_match('~^/orders/(TBP-[A-F0-9]{14})/confirmation-image$~D',ROUTE,$match)) {
        try {
            requireThat(($_SESSION['age'] ?? '')==='allowed',t('age_denied'));
            lookup($match[1]);
            requireThat(is_file(EMAIL_CONFIRMATION_IMAGE),t('order_missing'));
        } catch (DomainException) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/jpeg');
        readfile(EMAIL_CONFIRMATION_IMAGE);
        exit;
    }
    if ($admin && preg_match('~^/admin/export/(orders|reports|template)$~D',ROUTE,$match)) adminExport($match[1]);
} catch (DomainException $e) {
    if (in_array($action ?? '',['confirm_order','order_update'],true)) expireUnconfirmedOrders();
    $error=$e->getMessage();
    if (($_SERVER['HTTP_ACCEPT'] ?? '')==='application/json') {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json(['error'=>$error]);
        exit;
    }
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    if (!$s) {
        echo 'Tobapia could not start. Check PDO SQLite and storage permissions. See README.md.';
        exit;
    }
    $error=t('error');
}
if (ROUTE==='/' && $_SERVER['REQUEST_METHOD']==='GET' && $error==='' && PHP_SAPI==='cli-server'
    && !ini_get('zlib.output_compression') && function_exists('ob_gzhandler')) {
    ob_start('ob_gzhandler');
}
if ($admin) adminPage($s,$error);
elseif (($_SESSION['age'] ?? '')!=='allowed') agePage($s,$error);
else storePage($s,$error);
