<?php
const EMAIL_CONFIRMATION_IMAGE = __DIR__.'/../email notification-big.jpg.jpeg';

function agePage(array $s,string $error): void {
    startPage(t('age_title'),'gate-page');
    storeHeader($s,true); ?>
<main class="gate-main">
    <section class="gate-card" aria-labelledby="age-title">
        <span class="gate-age">21<span>+</span></span>
        <?php if (($_SESSION['age'] ?? '')==='denied'): ?>
            <h1 id="age-title"><?=h(t('age_denied'))?></h1>
        <?php else: ?>
            <h1 id="age-title"><?=h(t('age_title'))?></h1>
            <p><?=h(t('age_denied'))?></p>
            <?=form('age','gate-actions')?>
              <button class="button primary" name="answer" value="yes"><?=h(t('age_yes'))?></button>
              <button class="button secondary" name="answer" value="no"><?=h(t('age_no'))?></button>
            </form>
        <?php endif; alertMessage($error); ?>
    </section>
</main>
<?php storeFooter($s,true); endPage();
}
function storePage(array $s,string $error): void {
    $isProduct=(bool)preg_match('~^/products/(\d+)(/buy)?$~D',ROUTE,$productRoute);
    $product=$isProduct ? storefrontProduct((int)$productRoute[1],$s) : null;
    $buyToken=buyCheckoutToken();
    if ($isProduct && !$product) http_response_code(404);
    $title=match(true) {
        $isProduct=>$product ? productName($product) : t('product_missing'),
        $buyToken!==null=>t('checkout'),
        default=>match(ROUTE) {'/'=>t('catalog'),'/cart'=>t('cart'),'/checkout'=>t('checkout'),'/account'=>t('account'),'/orders'=>t('orders'),'/about'=>t('about'),default=>t('order_detail')}
    };
    startPage($title,$product ? 'product-page' : '');
    storeHeader($s);
    if (ROUTE==='/') { catalog($s,$error); }
    else { ?>
    <main class="shell page-shell">
        <?php if (!$product) alertMessage($error);
        if ($isProduct) {
            if ($product) productDetail($product,isset($productRoute[2]),$error);
            else heading(t('product_missing'));
        } elseif ($buyToken) {
            cartPage($s,true,$buyToken);
        } else try {
            match(ROUTE) {
                '/cart'=>cartPage($s,false), '/checkout'=>cartPage($s,true),
                '/account'=>accountPage(), '/orders'=>ordersPage(), '/about'=>aboutPage($s),
                default=>preg_match('~^/orders/(TBP-[A-F0-9]{14})$~D',ROUTE,$match)
                    ? orderPage(lookup($match[1]),$s) : missingPage()
            };
        } catch (DomainException $e) { http_response_code(404); heading($e->getMessage()); } ?>
    </main>
    <?php }
    storeFooter($s);
    endPage();
}
function aboutPage(array $s): void { heading(t('about'),'Tobapia'); ?><div class="panel prose"><p><?=h(t('about_text'))?></p><p><?=h(warning($s))?></p></div><?php }
function missingPage(): void { http_response_code(404); heading('404'); ?><a class="button secondary" href="<?=h(path('/'))?>"><?=h(t('home'))?></a><?php }
function storefrontProduct(int $id,array $settings): ?array {
    $product=row('SELECT * FROM products WHERE id=?',[$id]);
    if (!$product) return null;
    $variants=rows('SELECT * FROM variants WHERE product_id=? ORDER BY id',[$id]);
    $product['variants']=catalogVariants($product,$variants,$settings);
    return $product['variants'] ? $product : null;
}
function catalogVariants(array $product,array $variants,array $settings): array {
    return array_values(array_filter($variants,fn($variant)=>eligible($variant+$product,$settings)));
}
function catalog(array $s,string $error): void {
    $products=rows("SELECT * FROM products WHERE status='published' OR (status='draft' AND demo=1 AND ?='demo') ORDER BY CASE WHEN category='TEREA' THEN 0 ELSE 1 END,id",[$s['mode']]);
    $byProduct=[];
    foreach (rows('SELECT * FROM variants ORDER BY id') as $v) $byProduct[$v['product_id']][]=$v;
    $visible=[];
    foreach ($products as $p) {
        $p['variants']=catalogVariants($p,$byProduct[$p['id']] ?? [],$s);
        if ($p['variants']) $visible[]=$p;
    }
    ?>
<main>
    <section class="hero" hidden>
        <div class="shell hero-inner">
            <div class="hero-copy">
                <p class="eyebrow"><span class="hero-line"></span>Tobapia</p>
                <h1><?=h(t('hero'))?><span>.</span></h1>
                <p><?=h(t('hero_text'))?></p>
                <a class="button hero-button" href="#products"><?=h(t('all'))?></a>
            </div>
            <div class="hero-pattern" aria-hidden="true"></div>
        </div>
    </section>
    <section class="shell catalog-section" id="products" tabindex="-1">
        <?php alertMessage($error); ?>
        <div class="catalog-top">
            <h1><?=h(t('all'))?></h1>
            <label class="search">
                <span class="search-icon" aria-hidden="true"></span>
                <input type="search" autocomplete="off" data-search placeholder="<?=h(t('search'))?>" aria-label="<?=h(t('search_label'))?>">
            </label>
        </div>
        <div class="filters" aria-label="<?=h(t('category'))?>">
            <span class="filter-label"><?=h(t('category'))?></span>
            <div class="chips" data-filters="category">
                <?php foreach (array_merge([''],array_slice(CATEGORIES,0,4),array_values(array_intersect(['Marlboro','Esse'],array_column($visible,'category')))) as $category): ?>
                <button type="button" data-filter="<?=h($category)?>" aria-pressed="<?=$category===''?'true':'false'?>"><?=h($category ?: t('all'))?></button>
                <?php endforeach ?>
            </div>
        </div>
        <div class="filters" aria-label="<?=h(t('country'))?>">
            <span class="filter-label"><?=h(t('country'))?></span>
            <div class="chips" data-filters="edition">
                <?php foreach (array_merge([''],EDITIONS) as $edition): ?>
                <button type="button" data-filter="<?=h($edition)?>" aria-pressed="<?=$edition===''?'true':'false'?>"><?=h($edition ? t($edition) : t('all'))?></button>
                <?php endforeach ?>
            </div>
        </div>
        <div class="catalog-meta"><span aria-live="polite"><strong data-result-count><?=count($visible)?></strong> <?=h(t('count'))?></span><span><?=h(t('units'))?></span></div>
        <p class="panel" data-empty <?=$visible ? 'hidden':''?>><?=h(t('no_products'))?> <button class="text-button" data-reset type="button"><?=h(t('reset'))?></button></p>
        <div class="catalog-grid">
            <?php foreach ($visible as $p): productCard($p); endforeach ?>
        </div>
    </section>
</main>
<?php }
function productCard(array $p): void {
    $out=!array_filter($p['variants'],fn($v)=>$v['stock']>0);
    $images=decode($p['images']);
    $title=productName($p);
    $url=path('/products/'.$p['id']);
    ?>
<article class="product-card <?=$out?'sold-out':''?>" data-product data-category="<?=h($p['category'])?>" data-edition="<?=h($p['edition'])?>" data-text="<?=h(implode(' ',names($p)).' '.$p['category'].' '.$p['flavour'])?>">
    <a class="product-art tone-<?=($p['id']-1)%7?>" href="<?=h($url)?>" tabindex="-1" aria-hidden="true">
        <?php if ($images): ?><img src="<?=h(imageUrl($images[0]))?>" alt="" width="400" height="260" loading="lazy">
        <?php else: productPlaceholder($p); endif ?>
        <?php if ($out): ?><span class="stock-tag"><?=h(t('out'))?></span><?php endif ?>
    </a>
    <div class="product-info">
        <p class="product-label"><?=h($p['category'])?> <span>·</span> <?=h(t($p['edition']))?></p>
        <h3><a href="<?=h($url)?>"><?=h($title)?></a></h3>
        <?php if ($p['flavour']): ?><p class="flavour"><?=h($p['flavour'])?></p><?php endif ?>
        <div class="product-actions">
            <?php if ($out): ?>
                <button type="button" class="button product-unavailable" disabled><?=h(t('out'))?></button>
            <?php else: ?>
                <a class="button secondary product-cart-button" href="<?=h($url)?>" aria-label="<?=h(t('add_cart').' — '.$title)?>" title="<?=h(t('add_cart'))?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 3h3l2.5 12h11.75L22 7H5.85"/>
                        <circle cx="9" cy="20" r="1"/>
                        <circle cx="18" cy="20" r="1"/>
                    </svg>
                </a>
                <a class="button primary" href="<?=h($url.'/buy')?>"><?=h(t('buy_now'))?><span class="visually-hidden"> — <?=h($title)?></span></a>
            <?php endif ?>
        </div>
    </div>
</article>
<?php }
function productPlaceholder(array $product): void {
    $pack=$product['category']==='TEREA';
    ?>
    <div class="placeholder <?=$pack?'placeholder-pack':'placeholder-device'?>" aria-hidden="true"><span><?=h($pack?'TEREA':'ILUMA')?></span></div>
<?php }
function productDetail(array $product,bool $buyNow,string $error=''): void {
    $variants=array_column($product['variants'],null,'unit');
    $cart=$buyNow ? [] : cart();
    $remaining=[];
    foreach ($variants as $unit=>$variant) $remaining[$unit]=max(0,min(99,(int)$variant['stock'])-($cart[$variant['id']] ?? 0));
    $requested=query('unit');
    $unit=isset($variants[$requested]) ? $requested : (array_key_first(array_filter($remaining)) ?? array_key_first($variants));
    $variant=$variants[$unit];
    $images=decode($product['images']);
    $name=productName($product);
    $isTerea=$product['category']==='TEREA';
    $descriptions=decode($product['descriptions']);
    $description=($descriptions[lang()] ?? '') ?: (($descriptions['en'] ?? '') ?: (array_values(array_filter($descriptions,fn($text)=>$text!==''))[0] ?? ''));
    $specifications=array_merge([
        [t('category'),$product['category']],
        [t('country'),t($product['edition'])],
        [t('order_unit'),implode(' / ',array_map('t',array_keys($variants)))],
    ],decode($product['specifications']));
    ?>
<nav class="product-breadcrumb" aria-label="<?=h(t('catalog'))?>">
    <?php backLink('/#products',useHistory:false); ?>
    <a href="<?=h(path('/#products'))?>"><?=h(t('all'))?></a>
    <span aria-hidden="true">/</span>
    <span><?=h($product['category'])?></span>
</nav>
<article class="product-detail">
    <section class="detail-visual" aria-label="<?=h($name)?>" data-gallery>
        <div class="product-art detail-art tone-<?=($product['id']-1)%7?>">
            <?php if ($images): ?>
                <?php foreach ($images as $i=>$image): ?>
                    <img src="<?=h(imageUrl($image))?>" width="700" height="700" alt="<?=h($name)?>" loading="<?=$i?'lazy':'eager'?>" data-gallery-photo <?=$i?'hidden':''?>>
                <?php endforeach ?>
                <a class="detail-zoom" href="<?=h(imageUrl($images[0]))?>" data-gallery-zoom aria-label="<?=h(t('zoom_photo'))?>"><span class="search-icon" aria-hidden="true"></span></a>
            <?php else: ?>
                <?php productPlaceholder($product); ?>
            <?php endif ?>
        </div>
        <?php if (count($images)>1): ?>
        <div class="gallery-controls">
            <button class="button secondary" type="button" data-gallery-step="-1" disabled><?=h(t('prev'))?></button>
            <p class="gallery-count" aria-live="polite"><span data-gallery-current>1</span> / <?=count($images)?></p>
            <button class="button secondary" type="button" data-gallery-step="1"><?=h(t('next'))?></button>
        </div>
        <div class="gallery-thumbnails">
            <?php foreach ($images as $index=>$image): ?>
                <a href="<?=h(imageUrl($image))?>" class="gallery-thumbnail" data-gallery-index="<?=$index?>" aria-label="<?=h(t('photo').' '.($index+1))?>" <?=$index===0?'aria-current="true"':''?>>
                    <img src="<?=h(imageUrl($image))?>" width="70" height="70" alt="" loading="lazy">
                </a>
            <?php endforeach ?>
        </div>
        <?php endif ?>
        <?php if ($images): ?>
        <dialog class="photo-dialog" data-gallery-dialog aria-label="<?=h(t('zoom_photo'))?>">
            <form method="dialog"><button class="button secondary" aria-label="<?=h(t('close'))?>">×</button></form>
            <img src="<?=h(imageUrl($images[0]))?>" alt="<?=h($name)?>" width="700" height="700" loading="lazy">
        </dialog>
        <?php endif ?>
    </section>
    <div class="detail-info" id="purchase">
        <p class="eyebrow"><?=h($product['category'])?> <span aria-hidden="true">·</span> <?=h(t($product['edition']))?></p>
        <h1><?=h($name)?></h1>
        <p class="detail-price"><strong><?=h(money($variant['price']))?></strong> <span>/ <?=h(t($unit))?></span></p>
        <?php if ($product['flavour']): ?><p class="detail-flavour"><?=h($product['flavour'])?></p><?php endif ?>
        <?php if ($description!=='' || $isTerea): ?>
        <section class="detail-description">
            <?php if ($description!==''): ?>
                <h2><?=h(t('product_description'))?></h2>
                <p class="break-lines"><?=h($description)?></p>
            <?php endif ?>
            <?php if ($isTerea): ?><button type="button" class="text-button" data-dialog="unit-guide"><?=h(t('unit_guide'))?></button><?php endif ?>
        </section>
        <?php endif ?>
        <div class="detail-choices">
            <p><?=h(t('choose_unit'))?></p>
            <ul class="unit-options">
                <?php foreach ($variants as $option=>$available): ?>
                    <li>
                        <a class="unit-option <?=$available['stock']<=0?'unit-unavailable':''?>" href="<?=h(path(ROUTE).'?unit='.rawurlencode($option))?>" <?=$option===$unit?'aria-current="true"':''?>>
                            <?=h(t($option))?>
                            <?php if ($available['stock']<=0): ?><span class="visually-hidden"> (<?=h(t('out'))?>)</span><?php endif ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
        <?=form($buyNow ? 'buy_now' : 'add_product',$buyNow ? 'detail-form' : 'detail-form add-to-cart-form',false,ROUTE.'?unit='.rawurlencode($unit))?>
            <input type="hidden" name="variant" value="<?=(int)$variant['id']?>">
            <?php if ($remaining[$unit]): ?>
                <div class="detail-quantity">
                    <label for="product-quantity"><?=h(t('quantity'))?></label>
                    <div class="quantity-control">
                        <button type="button" data-quantity-step="-1" aria-label="<?=h(t('decrease'))?>" disabled>−</button>
                        <input type="number" id="product-quantity" name="quantity" min="1" max="<?=$remaining[$unit]?>" value="1" required>
                        <button type="button" data-quantity-step="1" aria-label="<?=h(t('increase'))?>" <?=$remaining[$unit]===1?'disabled':''?>>+</button>
                    </div>
                </div>
            <?php endif ?>
            <button class="button primary full detail-add" <?=$remaining[$unit]?'':'disabled'?>><?=h(t($remaining[$unit] ? ($buyNow ? 'buy_now' : 'add_cart') : ($variant['stock']>0 ? 'cart_limit' : 'out')))?></button>
        </form>
        <?php alertMessage($error); ?>
        <?php if (!$buyNow): ?>
            <p class="alert product-cart-status" role="status" data-cart-status data-failure="<?=h(t('refresh'))?>" data-limit="<?=h(t('cart_limit'))?>" hidden></p>
        <?php endif ?>
        <?php if ($variant['stock']>0): ?>
            <a class="detail-alternate text-button" href="<?=h(path('/products/'.$product['id'].($buyNow ? '' : '/buy')).'?unit='.rawurlencode($unit).'#purchase')?>"><?=h(t($buyNow ? 'add_cart' : 'buy_now'))?></a>
        <?php endif ?>
        <?php if ($isTerea): ?>
        <dialog id="unit-guide" aria-labelledby="unit-guide-title">
            <h2 id="unit-guide-title"><?=h(t('unit_guide'))?></h2>
            <p><?=h(t('units'))?></p>
            <form method="dialog" class="actions"><button class="button secondary"><?=h(t('close'))?></button></form>
        </dialog>
        <?php endif ?>
        <details class="detail-shipping">
            <summary><?=h(t('shipping_info'))?></summary>
            <p><?=h(t('shipping_checkout'))?></p>
        </details>
        <section class="detail-specifications">
            <h2><?=h(t('specifications'))?></h2>
            <dl>
                <?php foreach ($specifications as [$label,$value]): ?><div><dt><?=h($label)?></dt><dd><?=h($value)?></dd></div><?php endforeach ?>
            </dl>
        </section>
    </div>
</article>
<?php }
function stepper(array $variant,int $qty): void {
    $max=min(99,(int)$variant['stock']);
    echo form('cart','stepper');
    ?>
    <input type="hidden" name="variant" value="<?=(int)$variant['id']?>">
    <button type="submit" name="delta" value="-1" aria-label="<?=h(t('decrease'))?>" <?=$qty===0?'disabled':''?>>−</button>
    <output><?=$qty?></output>
    <button type="submit" name="delta" value="1" aria-label="<?=h(t('increase'))?>" <?=$qty>=$max?'disabled':''?> data-max="<?=$max?>">+</button>
    </form>
<?php }
function deliveryFields(): void {
    $countries=deliveryCountries();
    $country=input('delivery_country',2) ?: 'ID';
    if (!isset($countries[$country])) $country='ID';
    $local=$country==='ID';
    $parent=$local ? 'ID' : '';
    $postal='';
    $parentPrompts=['province'=>'select_country_first','city'=>'select_province_first','district'=>'select_city_first','village'=>'select_district_first'];
    ?>
    <div class="delivery-fields" data-delivery-fields data-region-url="<?=h(path('/delivery-regions'))?>" data-region-placeholder="<?=h(t('select_region'))?>">
        <p class="muted"><?=h(t('delivery_required_fields'))?></p>
        <fieldset class="delivery-section">
            <legend><?=h(t('delivery_contact'))?></legend>
            <div class="delivery-field-grid">
                <?php field('name',t('name'),input('name') ?: (currentUser()['name'] ?? ''),'text',true,'autocomplete="shipping name" minlength="2" maxlength="200"');
                field('phone',t('phone_number'),input('phone'),'tel',true,'autocomplete="shipping tel" maxlength="30" aria-describedby="delivery-phone-help"'); ?>
            </div>
            <p class="muted delivery-help" id="delivery-phone-help"><?=h(t('international_phone'))?></p>
        </fieldset>
        <fieldset class="delivery-section">
            <legend><?=h(t('delivery_address'))?></legend>
            <div class="delivery-address-fields">
                <?php selectField('delivery_country',t('delivery_country'),$countries,$country,'autocomplete="shipping country" required data-delivery-country'); ?>
                <fieldset class="delivery-field-grid delivery-region-group" data-indonesian-regions <?=$local?'':'hidden disabled'?>>
                    <legend class="visually-hidden"><?=h(t('delivery_regions'))?></legend>
                    <?php foreach (DELIVERY_LEVELS as $level):
                        $options=$parent ? array_column(deliveryRegions($parent),null,'id') : [];
                        $selected=input('delivery_'.$level.'_id',13);
                        if (!isset($options[$selected])) $selected='';
                        ?>
                        <label class="field">
                            <span><?=h(t('delivery_'.$level))?></span>
                            <select name="delivery_<?=$level?>_id" data-region-select data-parent-prompt="<?=h(t($parentPrompts[$level]))?>" required <?=$parent?'':'disabled'?>>
                                <option value=""><?=h(t($parent ? 'select_region' : $parentPrompts[$level]))?></option>
                                <?php foreach ($options as $region): ?>
                                    <option value="<?=h($region['id'])?>" data-postal="<?=h($region['postal'])?>" <?=$selected===$region['id']?'selected':''?>><?=h($region['name'])?></option>
                                <?php endforeach ?>
                            </select>
                        </label>
                        <?php $parent=$selected; $postal=$options[$selected]['postal'] ?? ''; ?>
                    <?php endforeach ?>
                </fieldset>
                <fieldset class="delivery-field-grid delivery-region-group" data-manual-regions <?=$local?'hidden disabled':''?>>
                    <legend class="visually-hidden"><?=h(t('delivery_regions'))?></legend>
                    <?php foreach (DELIVERY_LEVELS as $level) field('delivery_'.$level,t('delivery_'.$level),input('delivery_'.$level,120),'text',true,'maxlength="120"'); ?>
                </fieldset>
                <p class="muted" data-manual-help <?=$local?'hidden':''?>><?=h(t('manual_delivery_regions'))?></p>
                <p class="muted" role="status" data-region-loading hidden><?=h(t('delivery_loading'))?></p>
                <div class="delivery-region-error" data-region-error-box hidden>
                    <p role="status"><?=h(t('delivery_load_error'))?></p>
                    <button class="text-button" type="button" data-region-retry><?=h(t('delivery_retry'))?></button>
                </div>
                <noscript><button class="button secondary" type="submit" name="action" value="delivery_fields" formnovalidate><?=h(t('load_regions'))?></button></noscript>
                <?php field('postal_code',t('postal_code'),$postal ?: input('postal_code',16),'text',true,'autocomplete="shipping postal-code" maxlength="16" data-delivery-postal'.($local ? ' inputmode="numeric" pattern="[0-9]{5}"' : '').($postal ? ' readonly' : '')); ?>
                <p class="muted delivery-help" data-postal-automatic <?=$postal?'':'hidden'?>><?=h(t('delivery_postal_automatic'))?></p>
                <?php area('address',t('street_address'),input('address',1200),true,1200,'autocomplete="shipping street-address" aria-describedby="delivery-street-help"'); ?>
                <p class="muted delivery-help" id="delivery-street-help"><?=h(t('delivery_street_help'))?></p>
            </div>
        </fieldset>
        <?php area('notes',t('notes'),input('notes',800),false,800); ?>
    </div>
<?php }
function cartPage(array $s,bool $checkout,?string $buyToken=null): void {
    ?>
    <header class="page-title">
        <div class="order-catalog-kicker">
            <?php backLink($checkout && $buyToken===null ? '/cart' : '/#products'); ?>
            <p class="eyebrow"><?=h(t('catalog'))?></p>
        </div>
        <h1><?=h(t($checkout ? 'checkout' : 'cart'))?></h1>
    </header>
    <?php
    $cart=$buyToken ? ($_SESSION['buy_now'][$buyToken] ?? []) : cart();
    $quote=quote($cart,$s);
    if ($buyToken && (!$cart || $quote['invalid'])): ?>
        <div class="panel empty"><p><?=h(t($cart ? 'unavailable' : 'buy_expired'))?></p><a class="button primary" href="<?=h(path('/#products'))?>"><?=h(t('all'))?></a></div>
    <?php return; endif;
    if (!$cart): ?><div class="panel empty"><p><?=h(t('empty_cart'))?></p><a class="button primary" href="<?=h(path('/#products'))?>"><?=h(t('all'))?></a></div><?php return; endif ?>
    <div class="columns">
        <section class="panel">
            <?php if ($checkout && !$quote['invalid']): ?>
            <h2><?=h(t('delivery'))?></h2>
            <?=form('checkout','fields')?>
            <input type="hidden" name="nonce" value="<?=h($buyToken ?? ($_SESSION['checkout_nonce'] ??= bin2hex(random_bytes(24))))?>">
            <?php deliveryFields(); ?>
            <label class="check"><input type="checkbox" name="age" value="1" required <?=input('age')==='1'?'checked':''?>> <span><?=h(t('age_statement'))?></span></label>
            <button class="button primary" data-delivery-submit><?=h(t('place'))?></button>
            </form>
            <?php else: ?>
                <?php if ($quote['invalid']): ?><p class="alert alert-error"><?=h(t('unavailable'))?></p><?php endif ?>
                <?php foreach ($cart as $id=>$qty): $v=variant((int)$id); $available=$v && eligible($v,$s) && $qty<=$v['stock']; ?>
                <div class="cart-row">
                    <div><strong><?=h($v ? $v['category'].' · '.productName($v) : '#'.$id)?></strong><p><?=h($v ? t($v['unit']).' · '.money($v['price']) : t('unavailable'))?></p></div>
                    <?php if ($available): stepper($v,$qty); else: echo form('cart','',false,'/cart'); ?>
                        <input type="hidden" name="variant" value="<?=(int)$id?>"><button class="text-button" name="delta" value="<?=-(int)$qty?>"><?=h(t('remove'))?></button>
                    </form><?php endif ?>
                </div>
                <?php endforeach ?>
                <button class="text-button" type="button" data-dialog="clear-cart"><?=h(t('clear'))?></button>
                <dialog id="clear-cart"><h2><?=h(t('clear_question'))?></h2><div class="actions">
                    <form method="dialog"><button class="button secondary"><?=h(t('cancel'))?></button></form>
                    <?=form('clear','',false,'/cart')?><button class="button primary"><?=h(t('clear'))?></button></form>
                </div></dialog>
            <?php endif ?>
        </section>
        <aside class="panel order-summary">
            <h2><?=h(t($buyToken ? 'order_detail' : 'cart'))?></h2>
            <?php if ($checkout): foreach ($quote['items'] as $item): ?>
                <div class="summary-item"><span><?=h(productName($item))?> · <?=h(t($item['unit']))?> × <?=$item['qty']?></span><strong><?=h(money($item['price']*$item['qty']))?></strong></div>
            <?php endforeach; endif; showTotals($quote); ?>
            <?php if ($quote['terea'] && $s['partial_shipping']==='round_up'): ?><p class="muted"><?=h(t('pack_shipping'))?></p><?php endif ?>
            <?php if (!$checkout && !$quote['invalid']): ?><a class="button primary full" href="<?=h(path('/checkout'))?>"><?=h(t('checkout'))?></a><?php endif ?>
        </aside>
    </div>
    <?php if (!$checkout && is_file(__DIR__.'/../image.png')): ?>
        <section class="panel cart-reminder" aria-labelledby="cart-reminder-title">
            <h2 id="cart-reminder-title"><?=h(t('cart_reminder'))?></h2>
            <p id="cart-reminder-note"><?=h(t('cart_reminder_note'))?></p>
            <a class="cart-reminder-link" href="<?=h(path('/image.png'))?>" target="_blank" rel="noopener" aria-describedby="cart-reminder-note">
                <img src="<?=h(path('/image.png'))?>" width="1376" height="768" loading="lazy" alt="<?=h(t('cart_reminder_image'))?>">
            </a>
        </section>
    <?php endif ?>
    <div class="toast" aria-live="polite" data-toast hidden></div>
<?php }
function accountPage(): void {
    heading(t('account'),'Tobapia');
    if ($user=currentUser()): ?>
        <section class="panel prose"><h2><?=h(t('hello'))?>, <?=h($user['name'])?></h2><p><?=h($user['login'])?></p>
        <div class="actions"><a class="button primary" href="<?=h(path('/orders'))?>"><?=h(t('orders'))?></a><?=form('customer_logout')?><button class="button secondary"><?=h(t('logout'))?></button></form></div></section>
    <?php else: ?>
        <div class="columns account-columns">
            <section class="panel"><h2><?=h(t('login'))?></h2><?=form('customer_login','fields')?>
            <?php field('identity',t('identity'),'','text',true,'autocomplete="username"'); field('password',t('password'),'','password',true,'autocomplete="current-password"'); ?>
            <button class="button primary"><?=h(t('login'))?></button></form></section>
            <section class="panel"><h2><?=h(t('signup'))?></h2><?=form('signup','fields')?>
            <?php field('name',t('name'),'','text',true,'autocomplete="name"'); field('identity',t('identity'),'','text',true,'autocomplete="username"'); field('password',t('password'),'','password',true,'autocomplete="new-password" minlength="10"'); ?>
            <span class="muted"><?=h(t('password_hint'))?></span><button class="button secondary"><?=h(t('signup'))?></button></form></section>
        </div>
    <?php endif;
}
function ordersPage(): void {
    heading(t('orders'),'Tobapia');
    $user=currentUser();
    $list=$user ? rows('SELECT * FROM orders WHERE customer_id=? ORDER BY id DESC',[$user['id']]) : [];
    ?>
    <div class="columns">
        <section class="panel">
        <?php if (!$user): ?><a class="button secondary" href="<?=h(path('/account'))?>"><?=h(t('login'))?></a>
        <?php elseif (!$list): ?><p><?=h(t('no_orders'))?></p>
        <?php else: foreach ($list as $order): ?>
            <div class="cart-row"><div><a href="<?=h(path('/orders/'.$order['number']))?>"><strong><?=h(orderDisplayNumber($order))?></strong></a><p><?=h(shortDate($order['created']))?></p></div>
            <div class="order-list-meta"><?php statusTag($order['status']); ?><strong><?=h(money($order['total']))?></strong></div></div>
        <?php endforeach; endif ?>
        </section>
        <section class="panel"><h2><?=h(t('find_order'))?></h2>
            <?=form('track','fields')?>
            <?php field('number',t('order_number'),'','text',true,'placeholder="YYYYMMDD_0001"'); field('phone',t('phone_number'),'','tel',true,'autocomplete="tel"'); ?>
            <button class="button primary"><?=h(t('open_order'))?></button>
            </form>
        </section>
    </div>
<?php }
function orderPage(array $order,array $s): void {
    $url='/orders/'.$order['number'];
    $downloadUrl=path($url.'/pdf/05');
    $paymentIsPending=paymentPending($order);
    $confirmationSent=!empty($order['confirmation_sent_at']);
    $confirmationNotice=$paymentIsPending || $confirmationSent ? null : t('confirm_order_unavailable');
    ?>
    <div class="order-detail-page">
        <nav class="order-breadcrumb" aria-label="<?=h(t('orders'))?>">
            <?php backLink('/orders'); ?>
            <a href="<?=h(path('/orders'))?>"><?=h(t('orders'))?></a>
        </nav>
        <header class="order-header">
            <h1><?=h(t('order_detail'))?></h1>
            <div class="order-header-meta">
                <strong class="order-number"><?=h(orderDisplayNumber($order))?></strong>
                <time datetime="<?=h(gmdate(DATE_ATOM,strtotime($order['created'].' UTC')))?>"><?=h(shortDate($order['created']))?></time>
            </div>
            <div class="order-statuses">
                <?php statusTag($order['status']); ?>
                <?php if ($order['demo']): ?><span class="order-demo"><?=h(t('demo'))?></span><?php endif ?>
            </div>
        </header>
        <div class="order-content">
            <section class="panel order-items-panel">
                <h2><?=h(t('order_summary'))?></h2>
                <?php orderItemsTable($order,true); ?>
                <div class="order-totals"><?php showTotals($order); ?></div>
            </section>
            <div class="order-sidebar stack">
                <?php if ($paymentIsPending): orderPaymentPanel($order,$s,$downloadUrl,$confirmationSent); endif ?>
                <section class="panel"><h2><?=h(t('timeline'))?></h2>
                    <?php timeline($order); ?>
                    <?php if ($order['tracking']): ?><p class="order-tracking"><strong><?=h(t('tracking'))?></strong> <?=h($order['tracking'])?></p><?php endif ?>
                </section>
            </div>
            <div class="order-delivery-stack stack">
                <section class="panel">
                    <h2><?=h(t('delivery'))?></h2>
                    <dl class="order-recipient">
                        <div><dt><?=h(t('recipient'))?></dt><dd><?=h($order['name'])?></dd></div>
                        <div><dt><?=h(t('phone_number'))?></dt><dd>+<?=h($order['phone'])?></dd></div>
                        <div class="order-address"><dt><?=h(t('address'))?></dt><dd class="break-lines"><?=h($order['address'])?></dd></div>
                        <?php if ($order['notes']): ?><div class="order-address"><dt><?=h(t('notes'))?></dt><dd class="break-lines"><?=h($order['notes'])?></dd></div><?php endif ?>
                    </dl>
                </section>
                <div class="order-document-actions">
                    <?php if (!$paymentIsPending): ?><a class="button secondary" href="<?=h($downloadUrl)?>"><?=h(t('download'))?></a><?php endif ?>
                    <?=form('reorder')?><input type="hidden" name="number" value="<?=h($order['number'])?>"><button class="button secondary"><?=h(t('reorder'))?></button></form>
                    <button type="button" class="button primary" data-dialog="confirm-order"><?=h(t('confirm_order'))?></button>
                </div>
            </div>
        </div>
    </div>
    <?php orderConfirmationDialog($order,$downloadUrl,$confirmationNotice); ?>
    <div class="toast" aria-live="polite" data-toast hidden></div>
<?php }
function orderConfirmationEmailLink(array $order): void { ?>
    <a class="button secondary" href="<?=h('mailto:'.ORDER_CONFIRMATION_EMAIL.'?subject='.rawurlencode(confirmationSubject($order)))?>"><?=h(ORDER_CONFIRMATION_EMAIL)?></a>
<?php }
function orderConfirmationDialog(array $order,string $downloadUrl,?string $notice): void { ?>
    <dialog id="confirm-order" aria-labelledby="confirm-order-title">
        <h2 id="confirm-order-title"><?=h(t('confirm_order'))?></h2>
        <?php if ($notice!==null): ?>
            <p><?=h($notice)?></p>
            <form method="dialog" class="actions">
                <button class="button secondary"><?=h(t('close'))?></button>
                <a class="button primary" href="<?=h(path('/'))?>"><?=h(t('home'))?></a>
            </form>
        <?php else: ?>
        <div class="order-payment-email">
            <p><?=h(t('confirm_order_instructions'))?></p>
            <div class="actions">
                <a class="button secondary" href="<?=h($downloadUrl)?>"><?=h(t('download'))?></a>
                <?php orderConfirmationEmailLink($order); ?>
            </div>
        </div>
        <?=form('confirm_order','fields')?>
            <label class="check">
                <input type="checkbox" name="confirmation_ack" value="1" required>
                <span><?=h(t('confirm_order_ack'))?></span>
            </label>
            <div class="actions">
                <button class="button primary" type="submit" disabled><?=h(t('confirm_order_send'))?></button>
                <button class="button secondary" formmethod="dialog" formnovalidate><?=h(t('cancel'))?></button>
            </div>
        </form>
        <?php endif ?>
    </dialog>
<?php }
function orderPaymentPanel(array $order,array $settings,string $downloadUrl,bool $confirmationSent): void {
    $totals=totals($order);
    $lines=['Tobapia · '.orderDisplayNumber($order),...orderLines($order,lang()),...array_map(fn($label,$value)=>$label.' '.$value,array_keys($totals),array_values($totals)),
        t('name').' '.$order['name'],t('whatsapp').' +'.$order['phone'],t('address').' '.$order['address'],t('notes').' '.$order['notes']];
    $summary=implode("\n",$lines);
    $template=$settings['message'];
    $message=str_contains($template,'{order}') ? str_replace('{order}',$summary,$template) : ($template ? $template."\n".$summary : $summary);
    ?>
    <section class="panel qr-panel">
        <h2><?=h(t('payment'))?></h2>
        <?php if ($order['qr']): $qr=imageUrl($order['qr']).'?order='.rawurlencode($order['number']); ?>
            <?php if ($order['demo']): ?><p class="order-payment-message"><?=h(t('demo_payment_warning'))?></p><?php endif ?>
            <p class="payment-instruction"><?=h(t('payment_scan_note'))?></p>
            <a class="qr-link" href="<?=h($qr)?>" target="_blank" rel="noopener" aria-label="<?=h(t('qr_open'))?>"><img src="<?=h($qr)?>" width="240" height="240" alt="<?=h(t('qr'))?>"></a>
        <?php else: ?><p class="order-payment-message"><?=h(t('qr_missing'))?></p><?php endif ?>
        <div class="order-payment-email">
            <?php if ($confirmationSent): ?>
                <p role="status"><?=h(t('confirm_order_sent'))?></p>
            <?php else: ?>
                <p><?=h(t('confirm_order_instructions'))?></p>
                <?php orderConfirmationEmailLink($order); ?>
            <?php endif ?>
        </div>
        <div class="actions">
            <?php if ($settings['phone'] && !$order['demo']): ?><a class="button primary" href="https://wa.me/<?=h($settings['phone'])?>?text=<?=rawurlencode($message)?>" target="_blank" rel="noopener"><?=h(t('contact'))?></a><?php endif ?>
            <button class="button secondary" type="button" data-copy="copy-order" data-copied="<?=h(t('copied'))?>"><?=h(t('copy'))?></button>
            <a class="button secondary" href="<?=h($downloadUrl)?>"><?=h(t('download'))?></a>
        </div>
        <dialog id="copy-order"><h2><?=h(t('copy'))?></h2><textarea rows="10" readonly aria-label="<?=h(t('copy'))?>"><?=h($message)?></textarea><form method="dialog"><button class="button secondary"><?=h(t('cancel'))?></button></form></dialog>
    </section>
<?php }
function orderItemsTable(array $order,bool $responsive=false): void { ?>
<div class="table-wrap <?=$responsive?'order-item-table':''?>"><table <?=$responsive?'role="table"':''?>>
    <thead><tr><th><?=h(t('item'))?></th><th><?=h(t('quantity'))?></th><th><?=h(t('price'))?></th><th><?=h(t('subtotal'))?></th></tr></thead>
    <tbody><?php foreach (items($order['id']) as $item): ?><tr>
        <td><strong><?=h(productName(decode($item['names'])))?></strong><br><span class="muted"><?=h($item['category'].' · '.t($item['unit']))?></span></td>
        <?php foreach (['quantity'=>$item['qty'],'price'=>money($item['price']),'subtotal'=>money($item['price']*$item['qty'])] as $key=>$value): ?>
            <td><?php if ($responsive): ?><span class="order-cell-label" aria-hidden="true"><?=h(t($key))?></span><?php endif ?><span><?=h($value)?></span></td>
        <?php endforeach ?>
    </tr><?php endforeach ?></tbody>
</table></div>
<?php }
function timeline(array $order): void { ?>
<ol class="timeline"><?php foreach (rows('SELECT * FROM timeline WHERE order_id=? ORDER BY id',[$order['id']]) as $entry): ?>
<li><strong><?=h(t($entry['status'],str_starts_with(ROUTE,'/admin') ? 'id' : null))?></strong><span><?=h(shortDate($entry['created']))?><?=str_starts_with(ROUTE,'/admin')?' · '.h($entry['actor']):''?></span></li>
<?php endforeach ?></ol>
<?php }
