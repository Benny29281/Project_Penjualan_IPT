<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('LOCALE','id');
require __DIR__.'/i18n.php';
require __DIR__.'/core.php';

if (($argv[1] ?? '')==='expire-orders') {
    try {
        fwrite(STDOUT,expireUnconfirmedOrders()." pesanan dibatalkan otomatis.\n");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR,$e->getMessage().PHP_EOL);
        exit(1);
    }
}
if (($argv[1] ?? '')!=='owner' || empty($argv[2])) {
    fwrite(STDERR,"Usage: php app/console.php owner <email> | expire-orders\n");
    exit(1);
}
try {
    $identity=loginId($argv[2]);
    $password=getenv('TOBAPIA_OWNER_PASSWORD');
    if ($password===false) {
        fwrite(STDERR,'Kata sandi Owner (minimal 10 karakter): ');
        $password=rtrim(fgets(STDIN) ?: '',"\r\n");
    }
    atomic(fn()=>newUser('owner','Owner',$identity,$password));
    fwrite(STDOUT,"Akun Owner dibuat. Masuk melalui /admin.\n");
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
}
