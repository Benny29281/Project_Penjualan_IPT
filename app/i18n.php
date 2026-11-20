<?php
function lang(): string { return LOCALE; }
function t(string $key, ?string $lang=null): string {
    static $dict;
    $dict ??= require __DIR__.'/strings.php';
    $parts=$dict[$key] ?? null;
    if (!$parts) return $key;
    return explode('|',$parts)[array_search($lang ?? LOCALE,['en','id','zh','ko'],true) ?: 0];
}
