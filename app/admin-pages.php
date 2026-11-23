<?php
function adminNav(bool $owner): array {
    return [
        '/admin'=>'Ringkasan','/admin/orders'=>'Pesanan',
    ] + ($owner ? [
        '/admin/products'=>'Produk','/admin/prices'=>'Harga & stok','/admin/shipping'=>'Ongkir',
        '/admin/customers'=>'Pelanggan','/admin/import'=>'Impor & ekspor','/admin/reports'=>'Laporan',
        '/admin/settings'=>'Pengaturan toko','/admin/users'=>'Pengguna','/admin/audit'=>'Audit log',
    ] : []);
}
function adminPage(array $s,string $error): void {
    startPage('Dashboard','admin-page');
    $user=currentUser(true);
    if (!$user): ?>
        <main class="admin-login"><section class="panel">
            <?php brand($s,'/admin'); ?><h1>Dashboard admin</h1>
            <?php alertMessage($error); ?>
            <?=form('admin_login','fields',false,'/admin')?>
            <?php field('identity','Email atau WhatsApp','','text',true,'autocomplete="username"'); field('password','Kata sandi','','password',true,'autocomplete="current-password"'); ?>
            <button class="button primary">Masuk</button></form>
            <a class="text-button" href="<?=h(path('/'))?>">Buka katalog</a>
        </section></main>
    <?php else:
        $owner=$user['role']==='owner';
        $nav=adminNav($owner);
        ?>
        <div class="admin-layout">
            <aside class="admin-side">
                <?php brand($s,'/admin'); ?>
                <nav aria-label="Menu admin">
                    <?php foreach ($nav as $route=>$label):
                        $active=ROUTE===$route || ($route!=='/admin' && str_starts_with(ROUTE,$route.'/')); ?>
                        <a href="<?=h(path($route))?>" <?=$active?'aria-current="page"':''?>><?=h($label)?></a>
                    <?php endforeach ?>
                </nav>
                <a class="admin-back" href="<?=h(path('/'))?>">Buka katalog</a>
            </aside>
            <main class="admin-main">
                <div class="admin-bar"><span><?=h($user['name'])?> · <?=h($owner?'Owner':'Staf')?></span>
                    <?php if ($s['mode']==='demo'): ?><span class="status">Demo</span><?php endif ?>
                    <?=form('admin_logout','',false,'/admin')?><button class="text-button">Keluar</button></form>
                </div>
                <?php alertMessage($error);
                try {
                    if (preg_match('~^/admin/orders/(TBP-[A-F0-9]{14})$~D', ROUTE,$match)) {
                        adminOrder(lookup($match[1],true),$owner);
                    } elseif ($owner && preg_match('~^/admin/products/(new|\d+)$~D',ROUTE,$match)) {
                        adminProduct($match[1]==='new' ? 0 : (int)$match[1]);
                    } elseif (isset($nav[ROUTE])) {
                        match(ROUTE) {
                            '/admin'=>adminHome($owner), '/admin/orders'=>adminOrders(), '/admin/products'=>adminProducts(),
                            '/admin/prices'=>adminPrices(), '/admin/shipping'=>adminShipping($s), '/admin/customers'=>adminCustomers(),
                            '/admin/import'=>adminImport(), '/admin/reports'=>adminReports(), '/admin/settings'=>adminSettings($s),
                            '/admin/users'=>adminUsers($user['id']), '/admin/audit'=>adminAudit(),
                        };
                    } else missingPage();
                } catch (DomainException $e) { http_response_code(404); heading($e->getMessage()); }
                ?>
            </main>
        </div>
    <?php endif; endPage();
}
function adminHome(bool $owner): void {
    heading('Ringkasan','Dashboard');
    $status=array_column(rows('SELECT status,COUNT(*) AS count FROM orders GROUP BY status'),'count','status');
    $metrics=['Pesanan baru'=>$status['new'] ?? 0,'Menunggu pembayaran'=>$status['pending'] ?? 0,'Perlu dikirim'=>($status['paid'] ?? 0)+($status['processing'] ?? 0)];
    $today=new DateTimeImmutable('today');
    if ($owner) foreach (['Omzet hari ini'=>$today,'Omzet minggu ini'=>$today->modify('monday this week'),'Omzet bulan ini'=>$today->modify('first day of this month')] as $label=>$date) {
        $metrics[$label]=money((int)row("SELECT COALESCE(SUM(total),0) AS sum FROM orders WHERE demo=0 AND status NOT IN ('cancelled','refunded') AND date(paid_at,'+7 hours')>=?",[$date->format('Y-m-d')])['sum']);
    }
    ?>
    <div class="metrics"><?php foreach ($metrics as $label=>$value): ?><section class="panel metric"><p><?=h($label)?></p><strong><?=h($value)?></strong></section><?php endforeach ?></div>
    <section class="panel"><div class="section-heading"><h2>Pesanan terbaru</h2><a class="text-button" href="<?=h(path('/admin/orders'))?>">Lihat pesanan</a></div>
    <?php adminOrderTable(rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8')); ?></section>
    <?php if ($owner): $low=rows("SELECT p.id,p.name_en,v.stock,v.unit FROM products p JOIN variants v ON v.product_id=p.id WHERE p.status='published' AND v.stock<=5 ORDER BY v.stock,p.name_en LIMIT 12"); ?>
    <section class="panel"><h2>Stok menipis</h2>
    <?php if (!$low): ?><p class="muted">Belum ada SKU Published dengan stok lima atau kurang.</p><?php endif ?>
    <?php foreach ($low as $v): ?><div class="cart-row"><a href="<?=h(path('/admin/products/'.$v['id']))?>"><?=h($v['name_en'].' · '.$v['unit'])?></a><strong><?=$v['stock']?></strong></div><?php endforeach ?></section>
    <?php endif;
}
function adminOrderTable(array $list): void {
    if (!$list): ?><p class="muted">Belum ada pesanan.</p><?php return; endif ?>
    <div class="table-wrap"><table><thead><tr><th>Nomor</th><th>Pelanggan</th><th>Status</th><th>Total</th><th>Dibuat</th></tr></thead>
    <tbody><?php foreach ($list as $o): ?><tr>
        <td><a href="<?=h(path('/admin/orders/'.$o['number']))?>"><strong><?=h(orderDisplayNumber($o))?></strong></a><?= $o['demo']?' <span class="muted">Demo</span>':''?></td>
        <td><?=h($o['name'])?></td><td><?php statusTag($o['status']); ?></td><td><?=h(money($o['total']))?></td><td><?=h(shortDate($o['created']))?></td>
    </tr><?php endforeach ?></tbody></table></div>
<?php }
function filtersQuery(array $keys): string { return '?'.http_build_query(array_combine($keys,array_map('query',$keys))); }
function adminOrders(): void {
    heading('Pesanan','Dashboard'); ?>
    <section class="panel">
        <form class="toolbar" method="get">
            <?php field('q','Cari pesanan',query('q'),'search'); selectField('status','Status',[''=>'Semua']+array_combine(STATUSES,array_map(fn($v)=>t($v,'id'),STATUSES)),query('status')); field('from','Dari',query('from'),'date'); field('to','Sampai',query('to'),'date'); ?>
            <button class="button primary">Terapkan</button>
            <a class="button secondary" href="<?=h(path('/admin/export/orders').filtersQuery(['q','status','from','to']))?>">Ekspor CSV</a>
        </form>
        <?php adminOrderTable(orderRows()); ?>
    </section>
<?php }
function adminOrder(array $o,bool $owner): void {
    heading(orderDisplayNumber($o),'Detail pesanan');
    if ($o['demo']): ?><p class="alert">Pesanan demo</p><?php endif;
    $statuses=adminOrderStatuses($o);
    ?>
    <div class="columns">
        <section class="panel">
            <div class="section-heading"><h2>Detail pesanan</h2><?php statusTag($o['status']); ?></div>
            <?php orderItemsTable($o); showTotals($o); ?>
            <h3>Penerima</h3><p><?=h($o['name'])?> · +<?=h($o['phone'])?></p><p class="break-lines"><?=h($o['address'])?></p>
            <?php if ($o['notes']): ?><p class="break-lines"><?=h($o['notes'])?></p><?php endif ?>
            <h3>Pernyataan usia</h3><p><?=h($o['age_statement'])?></p><p class="muted"><?=h(shortDate($o['age_at']))?></p>
            <?php if ($o['paid_at']): ?><p>Verifikasi pembayaran <?=h(shortDate($o['paid_at']))?> · <?=h(row('SELECT name FROM users WHERE id=?',[$o['paid_by']])['name'] ?? '')?></p><?php endif ?>
            <?php if (paymentPending($o)): ?>
                <?php if ($o['confirmation_sent_at']): ?><p>Konfirmasi pelanggan diterima <?=h(shortDate($o['confirmation_sent_at']))?></p>
                <?php else: ?><p><?=h(t('payment_deadline','id'))?> <?=h(shortDate(gmdate('Y-m-d H:i:s',paymentConfirmationDeadline($o))))?></p><?php endif ?>
            <?php endif ?>
            <div class="actions"><a class="button secondary" href="<?=h(path(ROUTE.'/pdf/05'))?>"><?=h(t('download','id'))?></a>
            <?php if (!$o['demo']): ?><a class="button secondary" href="https://wa.me/<?=h($o['phone'])?>" target="_blank" rel="noopener">Hubungi via WhatsApp</a><?php endif ?></div>
        </section>
        <div class="stack">
            <section class="panel"><h2>Proses pesanan</h2><?=form('order_update','fields')?>
            <input type="hidden" name="number" value="<?=h($o['number'])?>">
            <?php selectField('status','Status',array_combine($statuses,array_map(fn($x)=>t($x,'id'),$statuses)),$o['status']);
            if (in_array('refunded',$statuses,true) && $o['status']!=='refunded'): ?><p class="muted">Pilih Dana dikembalikan setelah dana dikirim kembali ke pelanggan.</p><?php endif;
            field('tracking','Nomor resi',$o['tracking']); area('internal_note','Catatan internal',$o['internal_note']);
            if ($owner && paymentPending($o)) field('extra','Ongkir tambahan (IDR)',(string)$o['extra'],'number',false,'min="0"'); ?>
            <button class="button primary">Simpan perubahan</button></form></section>
            <section class="panel"><h2>Riwayat status</h2><?php timeline($o); ?></section>
        </div>
    </div>
<?php }
function adminProducts(): void {
    heading('Produk','Katalog');
    $category=query('category'); $edition=query('edition'); $status=query('status'); $search='%'.query('q').'%';
    $editions=array_combine(EDITIONS,array_map(fn($value)=>t($value,'id'),EDITIONS));
    $list=rows("SELECT p.*,COUNT(v.id) AS variants FROM products p LEFT JOIN variants v ON p.id=v.product_id
        WHERE (?='' OR p.category=?) AND (?='' OR p.edition=?) AND (?='' OR p.status=?) AND p.name_en LIKE ?
        GROUP BY p.id ORDER BY p.id DESC",[$category,$category,$edition,$edition,$status,$status,$search]);
    ?>
    <section class="panel">
        <form method="get" class="toolbar">
            <?php field('q','Cari produk',query('q'),'search');
            selectField('category','Kategori',[''=>'Semua']+choices(CATEGORIES),$category);
            selectField('edition','Edisi',[''=>'Semua']+$editions,$edition,'data-product-edition-filter');
            selectField('status','Status',[''=>'Semua','draft'=>'Draft','published'=>'Published','hidden'=>'Hidden'],$status); ?>
            <button class="button primary">Terapkan</button><a class="button secondary" href="<?=h(path('/admin/products/new'))?>">Tambah produk</a>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Kategori</th><th>Edisi</th><th>Status</th><th>Satuan</th></tr></thead><tbody>
            <?php if (!$list): ?><tr><td colspan="5">Tidak ada produk yang cocok dengan filter.</td></tr><?php endif ?>
            <?php foreach ($list as $p): ?><tr><td><a href="<?=h(path('/admin/products/'.$p['id']))?>"><strong><?=h($p['name_en'])?></strong></a></td>
            <td><?=h($p['category'])?></td><td><?=h(t($p['edition'],'id'))?></td><td><span class="status"><?=h(ucfirst($p['status']))?></span></td><td><?=$p['variants']?></td></tr><?php endforeach ?>
        </tbody></table></div>
    </section>
<?php }
function adminProduct(int $id): void {
    $p=$id ? row('SELECT * FROM products WHERE id=?',[$id]) : [];
    requireThat(!$id || $p,'Produk tidak ditemukan.');
    backLink('/admin/products',false);
    heading($p['name_en'] ?? 'Tambah produk','Katalog');
    $v=$id ? array_column(rows('SELECT * FROM variants WHERE product_id=?',[$id]),null,'unit') : [];
    $descriptions=decode($p['descriptions'] ?? '{}');
    $specifications=implode("\n",array_map(fn($spec)=>implode(': ',$spec),decode($p['specifications'] ?? '[]')));
    $images=decode($p['images'] ?? '[]');
    ?>
    <section class="panel">
    <?=form('product_save','fields',true)?>
        <input type="hidden" name="id" value="<?=$id?>">
        <div class="form-grid">
        <?php selectField('category','Kategori',choices(CATEGORIES),input('category') ?: ($p['category'] ?? ''),'data-product-category');
        selectField('edition','Edisi',array_combine(EDITIONS,array_map(fn($v)=>t($v,'id'),EDITIONS)),input('edition') ?: ($p['edition'] ?? 'Indonesia'));
        foreach (['en'=>'English','id'=>'Indonesia','zh'=>'中文','ko'=>'한국어'] as $code=>$label) field('name_'.$code,'Nama '.$label,input('name_'.$code) ?: ($p['name_'.$code] ?? ''),'text',$code==='en');
        field('flavour','Rasa',input('flavour') ?: ($p['flavour'] ?? ''));
        selectField('status','Status',['draft'=>'Draft','hidden'=>'Hidden','published'=>'Published'],input('status') ?: ($p['status'] ?? 'draft')); ?>
        </div>
        <h2>Deskripsi &amp; spesifikasi</h2>
        <div class="form-grid product-description-fields">
        <?php foreach (['id'=>'Indonesia','en'=>'English','zh'=>'中文','ko'=>'한국어'] as $code=>$label) {
            $key='description_'.$code;
            area($key,'Deskripsi · '.$label,array_key_exists($key,$_POST) ? input($key,2000) : ($descriptions[$code] ?? ''));
        } ?>
        </div>
        <?php area('specifications','Spesifikasi',array_key_exists('specifications',$_POST) ? input('specifications',2000) : $specifications); ?>
        <p class="muted">Tulis satu spesifikasi per baris, misalnya <code>Warna: Breeze Blue</code>.</p>
        <h2>Harga &amp; stok</h2>
        <div class="form-grid">
        <?php foreach (['pack','carton','unit'] as $unit): ?>
            <fieldset class="variant-form" data-unit="<?=h($unit)?>"><legend><?=h(t($unit,'id'))?></legend>
            <?php field('price_'.$unit,'Custom Price (IDR)',input('price_'.$unit) ?: (string)($v[$unit]['price'] ?? 0),'number',false,'min="0"');
            field('origin_'.$unit,'Origin Price (IDR)',input('origin_'.$unit) ?: (string)($v[$unit]['origin'] ?? 0),'number',false,'min="0"');
            field('stock_'.$unit,'Stok',input('stock_'.$unit) ?: (string)($v[$unit]['stock'] ?? 0),'number',false,'min="0"'); ?>
            </fieldset>
        <?php endforeach ?>
        </div>
        <h2>Foto produk <span class="muted">(<?=count($images)?>/8)</span></h2>
        <?php if ($images): ?><div class="media-grid"><?php foreach ($images as $index=>$image): ?>
            <label class="media-item"><img src="<?=h(imageUrl($image))?>" alt="" width="110" height="85"><span><input type="checkbox" name="remove[]" value="<?=h($image)?>"> Hapus foto <?=$index+1?></span></label>
        <?php endforeach ?></div><?php endif ?>
        <?php field('images[]','Unggah satu atau beberapa foto','','file',false,'multiple accept="image/jpeg,image/png,image/webp" aria-describedby="photo-upload-help"'); ?>
        <p class="muted" id="photo-upload-help">Pilih hingga 8 foto per produk. Format JPG, PNG, atau WebP, maksimal 6 MB per foto. Foto pertama menjadi gambar utama.</p>
        <button class="button primary">Simpan produk</button>
    </form>
    <?php if ($id): ?>
        <button class="text-button danger" type="button" data-dialog="delete-product">Hapus produk</button>
        <dialog id="delete-product"><h2>Hapus produk ini?</h2><div class="actions">
        <form method="dialog"><button class="button secondary">Batal</button></form>
        <?=form('product_delete')?><input type="hidden" name="id" value="<?=$id?>"><button class="button primary" name="confirm" value="1">Hapus permanen</button></form>
        </div></dialog>
    <?php endif ?>
    </section>
<?php }
function adminPrices(): void {
    heading('Harga & stok','Katalog');
    $p=rows('SELECT p.id AS product_id,p.name_en,p.category,v.id,v.stock,v.unit,v.price,v.origin FROM variants v JOIN products p ON p.id=v.product_id ORDER BY p.id,v.id');
    ?>
    <section class="panel"><h2>Ubah harga massal</h2><?=form('markup','toolbar')?>
        <?php selectField('category','Kategori',[''=>'Semua']+choices(CATEGORIES)); selectField('edition','Edisi',[''=>'Semua']+choices(EDITIONS)); selectField('unit','Satuan',[''=>'Semua','pack'=>'Pack','carton'=>'Slof','unit'=>'Unit']); selectField('type','Jenis',['fixed'=>'Nominal IDR','percent'=>'Persentase']); field('amount','Naik / turun','','number',true); ?>
        <button class="button primary">Terapkan</button></form>
    </section>
    <section class="panel"><h2>Harga per SKU</h2><?=form('prices','fields')?>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Satuan</th><th>Origin Price</th><th>Custom Price</th><th>Stok</th></tr></thead><tbody>
        <?php foreach ($p as $v): ?><tr><td><a href="<?=h(path('/admin/products/'.$v['product_id']))?>"><?=h($v['name_en'])?></a><br><span class="muted"><?=h($v['category'])?></span></td>
            <td><?=h(t($v['unit'],'id'))?></td><td><?=h(money($v['origin']))?></td>
            <td><input class="inline-input" type="number" name="prices[<?=$v['id']?>]" value="<?=$v['price']?>" min="0" required aria-label="<?=h($v['name_en'].' · '.t($v['unit'],'id'))?>"></td><td><?=$v['stock']?></td></tr><?php endforeach ?>
        </tbody></table></div><button class="button primary">Simpan harga</button>
    </form></section>
    <section class="panel"><h2>Riwayat harga</h2>
        <div class="table-wrap"><table><thead><tr><th>Produk</th><th>Awal</th><th>Baru</th><th>Pengubah</th><th>Waktu</th></tr></thead><tbody>
        <?php foreach (rows('SELECT * FROM price_history ORDER BY id DESC LIMIT 100') as $v): ?><tr><td><?=h($v['product'].' · '.t($v['unit'],'id'))?></td><td><?=h(money($v['old']))?></td><td><?=h(money($v['new']))?></td><td><?=h($v['actor'])?></td><td><?=h(shortDate($v['created']))?></td></tr><?php endforeach ?>
        </tbody></table></div>
    </section>
<?php }
function adminShipping(array $s): void {
    heading('Ongkir','Pengaturan'); $tiers=decode($s['tiers']); ?>
    <section class="panel"><?=form('shipping','fields')?><div class="form-grid">
        <?php field('box','Maksimal slof per kardus',$s['box'],'number',true,'min="1" max="20" data-box');
        field('device','Tarif per perangkat (IDR, kosong untuk nonaktif)',$s['device'],'number',false,'min="0"');
        selectField('packs','Satuan TEREA',['both'=>'Pack dan slof','pack'=>'Pack','carton'=>'Slof'],$s['packs']);
        selectField('partial_shipping','Ongkir pack sisa',['round_up'=>'Bulatkan ke 1 slof','full_only'=>'Hitung slof penuh saja'],$s['partial_shipping']); ?>
        </div><h2>Tarif TEREA per kardus</h2><div class="form-grid">
        <?php for ($n=1;$n<=20;$n++): ?>
        <div data-tier="<?=$n?>" <?=$n>(int)$s['box']?'hidden':''?>>
        <?php field('tiers['.$n.']',$n.' slof (IDR)',(string)($tiers[$n] ?? ''),'number',$n<=(int)$s['box'],'min="0"'); ?>
        </div><?php endfor ?>
        </div><button class="button primary">Simpan ongkir</button></form></section>
<?php }
function adminSettings(array $s): void {
    heading('Pengaturan toko','Dashboard'); ?>
    <section class="panel"><?=form('settings','fields',true)?>
    <div class="form-grid">
    <?php selectField('mode','Mode',['demo'=>'Demo lokal','live'=>'Operasional'],$s['mode']);
    field('phone','WhatsApp penerima',$s['phone'],'tel');
    field('notify','Email notifikasi pesanan',$s['notify'],'email');
    field('bank','Rekening (internal)',$s['bank']);
    field('logo','Logo toko','','file',false,'accept="image/jpeg,image/png,image/webp"');
    field('qr','QR pembayaran','','file',false,'accept="image/jpeg,image/png,image/webp"'); ?>
    </div>
    <?php if ($s['logo']): ?>
        <label class="check"><input type="checkbox" name="use_default_logo" value="1"> Gunakan logo bawaan Tobapia</label>
    <?php endif ?>
    <div class="media-grid"><?php foreach (['logo','qr'] as $kind): if (!$s[$kind]) continue; ?>
        <img class="config-image" src="<?=h(imageUrl($s[$kind]))?>" alt="<?=h(strtoupper($kind))?>" width="100" height="100">
    <?php endforeach ?></div>
    <?php area('message','Template WhatsApp. Gunakan {order} untuk isi pesanan.',$s['message']);
    foreach (['en'=>'English','id'=>'Indonesia','zh'=>'中文','ko'=>'한국어'] as $code=>$label) area('notice_'.$code,'Teks peringatan · '.$label,$s['notice_'.$code]); ?>
    <button class="button primary">Simpan pengaturan</button></form></section>
<?php }
function adminCustomers(): void {
    heading('Pelanggan','Dashboard');
    $list=rows("SELECT phone,MAX(name) AS name,COUNT(*) AS orders,SUM(CASE WHEN paid_at IS NOT NULL AND status NOT IN ('cancelled','refunded') AND demo=0 THEN total ELSE 0 END) AS total FROM orders GROUP BY phone ORDER BY MAX(id) DESC");
    $accounts=rows("SELECT name,login,created FROM users WHERE role='customer' ORDER BY id DESC");
    ?>
    <section class="panel"><h2>Riwayat berdasarkan WhatsApp</h2><div class="table-wrap"><table><thead><tr><th>Nama</th><th>WhatsApp</th><th>Pesanan</th><th>Total belanja</th><th>Akses</th></tr></thead><tbody>
    <?php foreach ($list as $c): $blocked=row('SELECT phone FROM blocked_phones WHERE phone=?',[$c['phone']]); ?>
    <tr><td><?=h($c['name'])?></td><td>+<?=h($c['phone'])?></td><td><a href="<?=h(path('/admin/orders').'?q='.rawurlencode($c['phone']))?>"><?=$c['orders']?> pesanan</a></td><td><?=h(money($c['total']))?></td>
    <td><?=form('block')?><input type="hidden" name="phone" value="<?=h($c['phone'])?>"><button class="text-button"><?=$blocked?'Buka blokir':'Blokir'?></button></form></td></tr>
    <?php endforeach ?></tbody></table></div></section>
    <section class="panel"><h2>Akun pelanggan</h2><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Login</th><th>Terdaftar</th></tr></thead><tbody>
    <?php foreach ($accounts as $a): ?><tr><td><?=h($a['name'])?></td><td><?=h($a['login'])?></td><td><?=h(shortDate($a['created']))?></td></tr><?php endforeach ?>
    </tbody></table></div></section>
<?php }
function adminImport(): void {
    heading('Impor & ekspor','Dashboard'); ?>
    <section class="panel"><h2>Impor katalog dan harga</h2><p>Unduh template atau ekspor Google Sheets ke CSV, lalu sesuaikan header dengan template Tobapia.</p>
    <div class="actions"><a class="button secondary" href="<?=h(path('/admin/export/template'))?>">Unduh template</a><a class="button secondary" href="<?=h(path('/admin/export/orders'))?>">Ekspor pesanan</a></div>
    <?=form('import','fields',true)?>
    <?php field('csv','File CSV','','file',true,'accept=".csv,text/csv"'); ?>
    <button class="button primary">Impor CSV</button></form></section>
<?php }
function adminReports(): void {
    heading('Laporan penjualan','Dashboard');
    $report=reportRows(); $editions=[];
    foreach ($report as $r) $editions[$r['edition']]=($editions[$r['edition']] ?? 0)+$r['sales'];
    arsort($editions); ?>
    <section class="panel"><form method="get" class="toolbar">
        <?php field('from','Dari',query('from'),'date'); field('to','Sampai',query('to'),'date'); ?>
        <button class="button primary">Terapkan</button><a class="button secondary" href="<?=h(path('/admin/export/reports').filtersQuery(['from','to']))?>">Ekspor laporan</a></form></section>
    <div class="metrics"><section class="panel metric"><p>Penjualan produk</p><strong><?=h(money(array_sum($editions)))?></strong></section>
    <?php foreach ($editions as $edition=>$total): ?><section class="panel metric"><p><?=h(t($edition,'id'))?></p><strong><?=h(money($total))?></strong></section><?php endforeach ?></div>
    <section class="panel"><h2>Produk terlaris</h2><div class="table-wrap"><table><thead><tr><th>Produk</th><th>Kategori</th><th>Edisi</th><th>Jumlah</th><th>Penjualan</th></tr></thead><tbody>
    <?php foreach ($report as $r): ?><tr><td><?=h($r['product'])?></td><td><?=h($r['category'])?></td><td><?=h(t($r['edition'],'id'))?></td><td><?=$r['qty']?></td><td><?=h(money($r['sales']))?></td></tr><?php endforeach ?>
    </tbody></table></div></section>
<?php }
function adminUsers(int $id): void {
    heading('Pengguna & peran','Dashboard'); ?>
    <section class="panel"><h2>Tambah pengguna</h2><?=form('user_create','toolbar')?>
    <?php field('name','Nama','','text',true); field('identity','Email atau WhatsApp','','text',true); field('password','Kata sandi','','password',true,'minlength="10"'); selectField('role','Peran',['staff'=>'Staf','owner'=>'Owner']); ?>
    <button class="button primary">Tambah</button></form></section>
    <section class="panel"><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Login</th><th>Peran</th></tr></thead><tbody>
    <?php foreach (rows("SELECT id,role,name,login FROM users WHERE role IN ('owner','staff') ORDER BY id") as $u): ?>
    <tr><td><?=h($u['name'])?></td><td><?=h($u['login'])?></td><td><?php if ($u['id']===$id): ?>Owner<?php else: ?>
    <?=form('user_role','actions user-role-form')?>
        <input type="hidden" name="id" value="<?=$u['id']?>">
        <?php selectField('role','Peran',['staff'=>'Staf','owner'=>'Owner'],$u['role']); ?>
        <button class="button secondary" type="submit">Simpan</button>
    </form>
    <?php endif ?></td></tr><?php endforeach ?>
    </tbody></table></div></section>
<?php }
function adminAudit(): void {
    heading('Audit log','Dashboard'); ?>
    <section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pengubah</th><th>Tindakan</th><th>Detail</th></tr></thead><tbody>
    <?php foreach (rows('SELECT * FROM audit ORDER BY id DESC LIMIT 250') as $record): ?><tr><td><?=h(shortDate($record['created']))?></td><td><?=h($record['actor'])?></td><td><?=h($record['action'])?></td><td><?=h($record['detail'])?></td></tr><?php endforeach ?>
    </tbody></table></div></section>
<?php }
