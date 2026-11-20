<?php
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ((in_array($uri,['/image.png','/Tobapia-01.png'],true) || preg_match('~^/assets/[a-z0-9_.-]+$~iD',$uri)) && is_file(__DIR__.$uri)) return false;
if (preg_match('~^/(app|storage)(/|$)|^/(pdf-closing\.png|email notification-big\.jpg)\.jpeg$|\.(md|sql|sqlite|ini)$~i',rawurldecode($uri))) { http_response_code(404); return true; }
$_SERVER['SCRIPT_NAME']='/index.php';
require __DIR__.'/index.php';
