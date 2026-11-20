<?php
function startPage(string $title, string $class=''): void { ?>
<!doctype html>
<html lang="<?=h(str_starts_with($class,'admin') ? 'id' : lang())?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#183e35">
<title><?=h($title)?> · Tobapia</title>
<link rel="stylesheet" href="<?=h(path('/assets/style.css'))?>">
<script defer src="<?=h(path('/assets/site.js'))?>"></script>
</head>
<body class="<?=h($class)?>">
<?php }
function endPage(): void { ?></body></html><?php }
function brand(array $s, string $target='/'): void { ?>
<a class="brand <?=$s['logo'] ? '' : 'brand-default'?>" href="<?=h(path($target))?>" aria-label="Tobapia">
    <?php if ($s['logo']): ?>
        <img src="<?=h(imageUrl($s['logo']))?>" alt="Tobapia" width="130" height="40">
    <?php else: ?>
        <img src="<?=h(path('/Tobapia-01.png'))?>" alt="Tobapia" width="500" height="125">
    <?php endif ?>
</a>
<?php }
function languages(): void {
    echo form('language','language');
    foreach (['en'=>'EN','id'=>'ID','zh'=>'中文','ko'=>'한국어'] as $code=>$label) {
        ?><button name="language" value="<?=h($code)?>" aria-pressed="<?=$code===lang() ? 'true':'false'?>"><?=h($label)?></button><?php
    }
    echo '</form>';
}
function storeHeader(array $s, bool $gate=false): void { ?>
<?php if (!$gate): ?><a class="skip" href="<?=h(path('/#products'))?>"><?=h(t('skip'))?></a><?php endif ?>
<header class="site-header">
    <div class="shell header-inner <?=$gate?'header-gate':''?>">
        <div class="header-identity">
            <?php brand($s); ?>
            <span class="header-divider" aria-hidden="true"></span>
            <span class="catalog-label"><?=h(t('catalog'))?></span>
        </div>
        <?php if (!$gate): ?>
        <nav class="header-nav" aria-label="<?=h(t('home'))?>">
            <a href="<?=h(path('/'))?>"><?=h(t('home'))?></a>
            <a href="<?=h(path('/#products'))?>"><?=h(t('all'))?></a>
            <a href="<?=h(path('/about'))?>"><?=h(t('about'))?></a>
        </nav>
        <?php endif ?>
        <div class="header-actions">
            <div class="header-preferences">
                <span class="age-pill">21+</span>
                <?php languages(); ?>
            </div>
            <?php if (!$gate): ?>
                <div class="header-shortcuts">
                    <a class="account-link" href="<?=h(path('/account'))?>" aria-label="<?=h(t('account'))?>" title="<?=h(t('account'))?>">
                        <span class="profile-icon" aria-hidden="true"></span>
                    </a>
                    <a class="cart-link" href="<?=h(path('/cart'))?>">
                        <span class="bag-icon" aria-hidden="true"></span>
                        <span class="cart-label"><?=h(t('cart'))?></span>
                        <span class="badge" data-cart-count><?=array_sum(cart())?></span>
                    </a>
                </div>
            <?php endif ?>
        </div>
    </div>
</header>
<?php if ($s['mode']==='demo'): ?><div class="demo-band"><?=h(t('demo'))?></div><?php endif ?>
<?php if (!$gate): ?><div class="warning-band"><p class="shell"><?=h(warning($s))?></p></div><?php endif ?>
<?php }
function storeFooter(array $s, bool $gate=false): void { ?>
<footer class="store-footer"><div class="shell footer-inner">
    <?php brand($s); ?>
    <p><?=h(warning($s))?></p>
    <?php if (!$gate): ?><a href="<?=h(path('/orders'))?>"><?=h(t('orders'))?></a><?php endif ?>
</div></footer>
<?php if (!$gate): ?>
<a class="whatsapp-float" href="https://wa.me/8618804519859" target="_blank" rel="noopener noreferrer" aria-label="<?=h(t('open_whatsapp').' +86 188 0451 9859')?>" title="<?=h(t('open_whatsapp'))?>">
    <svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor" aria-hidden="true">
        <path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.19.3-.77.97-.94 1.17-.17.2-.35.23-.65.08-.3-.15-1.26-.47-2.4-1.49-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.51-.67-.52l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.21 5.08 4.5.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.08 1.76-.72 2.01-1.42.25-.69.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12 21.8a9.75 9.75 0 0 1-4.97-1.36l-.36-.21-3.7.97.99-3.61-.24-.37a9.79 9.79 0 1 1 8.28 4.58zm8.33-18.12A11.75 11.75 0 0 0 1.85 17.9L.22 23.86l6.09-1.6A11.75 11.75 0 0 0 23.75 12c0-3.14-1.21-6.09-3.42-8.32z"/>
    </svg>
</a>
<?php endif ?>
<?php }
function alertMessage(string $error=''): void {
    $flash=$_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if ($error || $flash): ?><p class="alert <?=($error || $flash[1]) ? 'alert-error':''?>" role="status"><?=h($error ?: $flash[0])?></p><?php endif;
}
function field(string $key,string $label,string $value='', string $type='text', bool $required=false, string $extra=''): void { ?>
<label class="field"><span><?=h($label)?></span><input type="<?=h($type)?>" name="<?=h($key)?>" value="<?=h($value)?>" <?=$required ? 'required':''?> <?=$extra?>></label>
<?php }
function area(string $key,string $label,string $value='',bool $required=false): void { ?>
<label class="field"><span><?=h($label)?></span><textarea name="<?=h($key)?>" rows="3" maxlength="2000" <?=$required ? 'required':''?>><?=h($value)?></textarea></label>
<?php }
function selectField(string $key, string $label, array $options, string $selected='', string $extra=''): void { ?>
<label class="field"><span><?=h($label)?></span><select name="<?=h($key)?>" <?=$extra?>>
<?php foreach ($options as $value=>$text): ?><option value="<?=h($value)?>" <?=$selected===(string)$value?'selected':''?>><?=h($text)?></option><?php endforeach ?>
</select></label>
<?php }
function choices(array $values): array { return array_combine($values,$values); }
function heading(string $title,string $caption=''): void { ?>
<header class="page-title"><?php if ($caption): ?><p class="eyebrow"><?=h($caption)?></p><?php endif ?><h1><?=h($title)?></h1></header>
<?php }
function statusTag(string $status): void { ?><span class="status status-<?=h($status)?>"><?=h(t($status, str_starts_with(ROUTE,'/admin') ? 'id' : null))?></span><?php }
function showTotals(array $data): void { ?>
<dl class="totals"><?php foreach (totals($data) as $label=>$value): ?><div><dt><?=h($label)?></dt><dd><?=h($value)?></dd></div><?php endforeach ?></dl>
<?php }
