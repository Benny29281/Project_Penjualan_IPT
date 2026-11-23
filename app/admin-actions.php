<?php
function adminAccess(bool $owner=false): array {
    $user=currentUser(true);
    requireThat($user && (!$owner || $user['role']==='owner'),'Akses dashboard diperlukan.');
    return $user;
}
function adminOrderStatuses(array $order): array {
    $current=$order['status'];
    $advance=['new'=>'pending','pending'=>'paid','paid'=>'processing','processing'=>'shipped','shipped'=>'delivered','delivered'=>'complete'];
    $statuses=[$current];
    if (isset($advance[$current])) $statuses[]=$advance[$current];
    if (in_array($current,['new','pending','paid','processing'],true)) $statuses[]='cancelled';
    if ($order['paid_at'] && in_array($current,['paid','processing','shipped','delivered','complete','cancelled'],true)) $statuses[]='refunded';
    return $statuses;
}
function adminPost(string $action, array $s): string {
    if ($action==='admin_login') {
        login('admin',loginId(input('identity')),input('password',100));
        return '/admin';
    }
    if ($action==='admin_logout') {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
        return '/admin';
    }
    $user=adminAccess();
    if ($action==='order_update') {
        $number=input('number');
        atomic(function () use ($number,$user) {
            $order=lookup($number,true);
            $now=time();
            requireThat(!awaitingPaymentConfirmation($order) || $now<paymentConfirmationDeadline($order),t('payment_expired','id'));
            $next=input('status');
            $tracking=input('tracking');
            $note=input('internal_note',1800);
            $extra=$order['extra'];
            if ($user['role']==='owner' && input('extra')!=='') {
                $extra=integer(input('extra'));
                requireThat($extra===$order['extra'] || paymentPending($order),'Ongkir tambahan hanya bisa diubah sebelum pembayaran.');
            }
            requireThat(in_array($next,adminOrderStatuses($order),true),'Perubahan status tidak valid.');
            if ($next==='shipped') requireThat($tracking!=='','Nomor resi wajib diisi.');
            if ($next==='paid' && $next!==$order['status'] && !$order['demo']) {
                foreach (items($order['id']) as $item) {
                    $changed=run('UPDATE variants SET stock=stock-? WHERE id=? AND stock>=?',[$item['qty'],$item['variant_id'],$item['qty']])->rowCount();
                    requireThat($changed===1,'Stok tidak cukup untuk '.productName(decode($item['names']),'en').'.');
                }
            }
            if (in_array($next,['cancelled','refunded'],true) && in_array($order['status'],['paid','processing'],true)
                && $order['paid_at'] && !$order['demo']) {
                foreach (items($order['id']) as $item) run('UPDATE variants SET stock=stock+? WHERE id=?',[$item['qty'],$item['variant_id']]);
            }
            $total=$order['subtotal']+$order['terea']+$order['device']+$extra;
            run('UPDATE orders SET status=?,tracking=?,internal_note=?,extra=?,total=?,paid_by=?,paid_at=? WHERE id=?',
                [$next,$tracking,$note,$extra,$total,$next==='paid' && !$order['paid_at'] ? $user['id'] : $order['paid_by'],$next==='paid' ? ($order['paid_at'] ?: gmdate('Y-m-d H:i:s',$now)) : $order['paid_at'],$order['id']]);
            if ($next!==$order['status']) run('INSERT INTO timeline(order_id,actor,status) VALUES (?,?,?)',[$order['id'],$user['login'],$next]);
            audit('Pesanan',$number.' · '.$order['status'].' → '.$next.' · '.money($total));
        });
        flash('Pesanan diperbarui.');
        return '/admin/orders/'.$number;
    }
    adminAccess(true);
    switch ($action) {
        case 'product_save':
            $id=integer(input('id') ?: '0');
            $createdImages=[];
            try {
                $id=atomic(function () use ($id,&$createdImages) {
                    return saveProduct($id,$createdImages);
                });
            } catch (Throwable $e) {
                foreach ($createdImages as $image) unlink(DATA.'/'.$image);
                throw $e;
            }
            flash('Produk disimpan.');
            return '/admin/products/'.$id;
        case 'product_hide':
            $id=integer(input('id'),1);
            atomic(function () use ($id) {
                run("UPDATE products SET status='hidden' WHERE id=?",[$id]);
                audit('Produk','Sembunyikan #'.$id);
            });
            return '/admin/products';
        case 'product_delete':
            $id=integer(input('id'),1);
            requireThat(input('confirm')==='1','Konfirmasi penghapusan produk.');
            atomic(function () use ($id) {
                requireThat(!row('SELECT oi.id FROM order_items oi JOIN variants v ON v.id=oi.variant_id WHERE v.product_id=?',[$id]),'Produk memiliki riwayat pesanan. Gunakan status Hidden.');
                run('DELETE FROM products WHERE id=?',[$id]);
                audit('Produk','Hapus #'.$id);
            });
            flash('Produk dihapus.');
            return '/admin/products';
        case 'prices':
            atomic(function () {
                foreach (is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [] as $id=>$value) {
                    $v=variant((int)$id);
                    if ($v && is_string($value)) updatePrice($v,integer($value));
                }
            });
            flash('Harga disimpan.');
            return '/admin/prices';
        case 'markup':
            $group=input('category'); $edition=input('edition'); $unit=input('unit');
            $amount=integer(input('amount'),-100000000,100000000);
            if (input('type')==='percent') requireThat($amount>=-100 && $amount<=1000,'Persentase di luar batas.');
            atomic(function () use ($group,$edition,$unit,$amount) {
                $products=rows("SELECT v.*,p.name_en,p.status FROM variants v JOIN products p ON p.id=v.product_id WHERE v.price>0 AND (?='' OR p.category=?) AND (?='' OR p.edition=?) AND (?='' OR v.unit=?)",[$group,$group,$edition,$edition,$unit,$unit]);
                foreach ($products as $v) {
                    $next=input('type')==='percent' ? (int)round($v['price']*(100+$amount)/100) : $v['price']+$amount;
                    requireThat($next>=0 && $next<=100000000,'Perubahan menghasilkan harga di luar batas.');
                    updatePrice($v,$next);
                }
                audit('Harga massal',count($products).' varian · '.$amount.' '.input('type'));
            });
            flash('Harga massal diperbarui.');
            return '/admin/prices';
        case 'shipping':
            $box=integer(input('box'),1,20); $next=[];
            $values=is_array($_POST['tiers'] ?? null) ? $_POST['tiers'] : [];
            for ($qty=1;$qty<=$box;$qty++) $next[$qty]=integer(is_string($values[$qty] ?? null) ? $values[$qty] : '');
            $device=input('device');
            if ($device!=='') $device=(string)integer($device);
            $pack=input('packs'); $partial=input('partial_shipping');
            requireThat(in_array($pack,['both','pack','carton'],true) && in_array($partial,['round_up','full_only'],true),'Satuan atau aturan pack tidak valid.');
            atomic(function () use ($box,$next,$device,$pack,$partial) {
                foreach (['box'=>(string)$box,'tiers'=>json($next),'device'=>$device,'packs'=>$pack,'partial_shipping'=>$partial] as $key=>$value) saveSetting($key,$value);
                audit('Pengaturan','Ongkir dan satuan');
            });
            flash('Ongkir disimpan.');
            return '/admin/shipping';
        case 'settings':
            atomic(function () use ($s) {
                $mode=input('mode');
                requireThat(in_array($mode,['demo','live'],true),'Mode toko tidak valid.');
                $phone=input('phone') ? phone(input('phone')) : '';
                $email=input('notify');
                requireThat($email==='' || (bool)filter_var($email,FILTER_VALIDATE_EMAIL),'Email notifikasi tidak valid.');
                foreach (['qr','logo'] as $kind) {
                    if (($_FILES[$kind]['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $s[$kind]=storeImage($_FILES[$kind],$kind);
                    saveSetting($kind,$s[$kind]);
                }
                requireThat($mode==='demo' || ($phone && $s['qr']), 'Isi WhatsApp dan unggah QR pembayaran sebelum mengaktifkan mode operasional.');
                foreach (['message','bank','notice_en','notice_id','notice_zh','notice_ko'] as $key) saveSetting($key,input($key,2000));
                saveSetting('mode',$mode); saveSetting('phone',$phone); saveSetting('notify',$email);
                audit('Pengaturan','Pengaturan toko');
            });
            flash('Pengaturan toko disimpan.');
            return '/admin/settings';
        case 'user_create':
            $role=input('role');
            requireThat(in_array($role,['staff','owner'],true),'Peran tidak valid.');
            atomic(function () use ($role) {
                newUser($role,input('name'),loginId(input('identity')),input('password',100));
                audit('Pengguna','Tambah '.$role.' · '.input('identity'));
            });
            flash('Pengguna ditambahkan.');
            return '/admin/users';
        case 'user_role':
            $id=integer(input('id'),1); $role=input('role');
            requireThat($id!==$user['id'] && in_array($role,['staff','owner'],true),'Peran akun sendiri tidak bisa diubah.');
            atomic(function () use ($id,$role) {
                run("UPDATE users SET role=? WHERE id=? AND role IN ('owner','staff')",[$role,$id]);
                audit('Pengguna','Ubah #'.$id.' menjadi '.$role);
            });
            return '/admin/users';
        case 'block':
            $number=phone(input('phone'));
            atomic(function () use ($number) {
                $blocked=(bool)row('SELECT phone FROM blocked_phones WHERE phone=?',[$number]);
                if ($blocked) run('DELETE FROM blocked_phones WHERE phone=?',[$number]);
                else run('INSERT INTO blocked_phones VALUES (?)',[$number]);
                run("UPDATE users SET blocked=? WHERE role='customer' AND login=?",[(int)!$blocked,$number]);
                audit('Pelanggan',($blocked ? 'Buka blokir ' : 'Blokir ').$number);
            });
            return '/admin/customers';
        case 'import':
            $count=importProducts();
            flash($count.' baris diproses.');
            return '/admin/import';
    }
    throw new DomainException('Tindakan tidak tersedia.');
}
function updatePrice(array $variant, int $price): void {
    if ((int)$variant['price']===$price) return;
    requireThat($price>0 || $variant['status']!=='published','Produk Published harus memiliki harga pada setiap satuan.');
    run('UPDATE variants SET price=? WHERE id=?',[$price,$variant['id']]);
    run('INSERT INTO price_history(variant_id,product,unit,actor,old,new) VALUES (?,?,?,?,?,?)',
        [$variant['id'],$variant['name_en'],$variant['unit'],currentUser(true)['login'],$variant['price'],$price]);
    audit('Harga',$variant['name_en'].' · '.$variant['unit'].' · '.money($variant['price']).' → '.money($price));
}
function productSpecifications(string $text): array {
    $specifications=[];
    foreach (preg_split('/\R/u',$text) as $line) {
        if (trim($line)==='') continue;
        [$label,$value]=array_pad(array_map('trim',explode(':',$line,2)),2,'');
        requireThat($label!=='' && $value!=='','Gunakan format Nama: nilai pada setiap baris spesifikasi.');
        $specifications[]=[$label,$value];
    }
    return $specifications;
}
function saveProduct(int $id,array &$createdImages): int {
    $product=$id ? row('SELECT * FROM products WHERE id=?',[$id]) : null;
    requireThat(!$id || $product,'Produk tidak ditemukan.');
    $category=input('category'); $edition=input('edition'); $status=input('status');
    requireThat(in_array($category,CATEGORIES,true) && in_array($edition,EDITIONS,true) && in_array($status,['draft','hidden','published'],true),'Periksa kategori, edisi dan status.');
    $info=[$category,input('name_en'),input('name_id'),input('name_zh'),input('name_ko'),input('flavour'),$edition,$status];
    requireThat($info[1]!=='','Nama English wajib diisi.');
    $descriptions=decode($product['descriptions'] ?? '{}');
    foreach (['en','id','zh','ko'] as $language) {
        $key='description_'.$language;
        if (array_key_exists($key,$_POST)) $descriptions[$language]=input($key,2000);
    }
    $specifications=array_key_exists('specifications',$_POST)
        ? productSpecifications(input('specifications',2000)) : decode($product['specifications'] ?? '[]');
    array_push($info,json($descriptions),json($specifications));
    $images=$product ? decode($product['images']) : [];
    $remove=is_array($_POST['remove'] ?? null) ? array_filter($_POST['remove'],'is_string') : [];
    $images=array_values(array_diff($images,$remove));
    $uploads=$_FILES['images'] ?? [];
    $files=array_filter($uploads['error'] ?? [],fn($error)=>$error!==UPLOAD_ERR_NO_FILE);
    requireThat(count($images)+count($files)<=8,'Maksimal 8 foto per produk.');
    requireThat($status!=='published' || $images || $files,'Unggah foto sebelum Publish.');
    $units=$category==='TEREA' || in_array($category,['Esse','Marlboro'],true) ? ['pack','carton'] : ['unit'];
    $values=[];
    foreach ($units as $unit) {
        $price=integer(input('price_'.$unit) ?: '0', $status==='published' ? 1 : 0);
        $values[$unit]=[$price,integer(input('origin_'.$unit) ?: '0'),integer(input('stock_'.$unit) ?: '0',0,1000000)];
    }
    foreach ($files as $i=>$error) {
        $image=storeImage(['error'=>$error,'size'=>$uploads['size'][$i],'tmp_name'=>$uploads['tmp_name'][$i]],'product');
        $createdImages[]=$image;
        $images[]=$image;
    }
    if ($id) {
        run('UPDATE products SET category=?,name_en=?,name_id=?,name_zh=?,name_ko=?,flavour=?,edition=?,status=?,descriptions=?,specifications=?,images=?,demo=CASE WHEN ?=1 THEN 0 ELSE demo END WHERE id=?',array_merge($info,[json($images),(int)($status==='published'),$id]));
    } else {
        $id=insert('INSERT INTO products(category,name_en,name_id,name_zh,name_ko,flavour,edition,status,descriptions,specifications,images) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_merge($info,[json($images)]));
    }
    foreach (rows('SELECT * FROM variants WHERE product_id=?',[$id]) as $v) {
        if (isset($values[$v['unit']])) continue;
        requireThat(!row('SELECT id FROM order_items WHERE variant_id=?',[$v['id']]),'Kategori produk yang sudah dipesan tidak dapat diganti satuannya.');
        run('DELETE FROM variants WHERE id=?',[$v['id']]);
    }
    foreach ($values as $unit=>[$price,$origin,$stock]) {
        $v=row('SELECT * FROM variants WHERE product_id=? AND unit=?',[$id,$unit]);
        if ($v) {
            updatePrice($v+['status'=>$status,'name_en'=>$info[1]],$price);
            run('UPDATE variants SET origin=?,stock=? WHERE id=?',[$origin,$stock,$v['id']]);
        } else run('INSERT INTO variants(product_id,unit,price,origin,stock) VALUES (?,?,?,?,?)',[$id,$unit,$price,$origin,$stock]);
    }
    audit('Produk','#'.$id.' · '.$info[1].' · '.$status);
    return $id;
}
function csvColumns(): array {
    return ['Category','Edition','English Name','Indonesian Name','Chinese Name','Korean Name','Flavour','Pack Price','Carton Price','Custom Price','Origin Price','Pack Stock','Carton Stock','Unit Stock'];
}
function csvNumber(string $raw): int {
    $raw=trim(preg_replace('/^Rp\s*/i','',$raw));
    if ($raw==='') return 0;
    if (preg_match('/^\d{1,3}([.,]\d{3})+$/D',$raw)) $raw=str_replace(['.',','],'',$raw);
    requireThat((bool)preg_match('/^\d+$/D',$raw),'Angka IDR tidak valid: '.$raw);
    return integer(ltrim($raw,'0') ?: '0');
}
function importProducts(): int {
    $file=$_FILES['csv'] ?? [];
    requireThat(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK && $file['size']<2000000,'Unggah CSV hingga 2 MB.');
    $handle=fopen($file['tmp_name'],'rb');
    $headers=fgetcsv($handle,0,',','"','') ?: [];
    $headers=array_map(fn($val)=>strtolower(trim(str_replace("\xEF\xBB\xBF",'',(string)$val))),$headers);
    requireThat(in_array('english name',$headers,true) || in_array('product name',$headers,true),'Kolom English Name diperlukan. Gunakan template CSV.');
    $count=atomic(function () use ($handle,$headers) {
        $index=1; $count=0;
        while (($line=fgetcsv($handle,0,',','"',''))!==false) {
            $index++;
            if ($line===[null]) continue;
            try {
                requireThat(count($line)===count($headers),'Jumlah kolom tidak cocok.');
                $data=array_map(fn($v)=>trim((string)$v),array_combine($headers,$line));
                $category=$data['category'] ?? ''; $edition=($data['edition'] ?? '') ?: 'Indonesia';
                $name=$data['english name'] ?? $data['product name'] ?? '';
                requireThat(in_array($category,CATEGORIES,true) && in_array($edition,EDITIONS,true) && $name!=='','Nama, kategori, atau edisi tidak valid.');
                $p=row('SELECT * FROM products WHERE category=? AND edition=? AND name_en=?',[$category,$edition,$name]);
                $id=$p['id'] ?? insert("INSERT INTO products(category,edition,name_en,status) VALUES (?,?,?,'hidden')",[$category,$edition,$name]);
                run('UPDATE products SET name_id=?,name_zh=?,name_ko=?,flavour=? WHERE id=?',
                    [$data['indonesian name'] ?? $p['name_id'] ?? '',$data['chinese name'] ?? $p['name_zh'] ?? '',$data['korean name'] ?? $p['name_ko'] ?? '',$data['flavour'] ?? $data['flavor'] ?? $p['flavour'] ?? '',$id]);
                $units=in_array($category,['TEREA','Marlboro','Esse'],true) ? ['pack'=>'pack price','carton'=>'carton price'] : ['unit'=>'custom price'];
                foreach ($units as $unit=>$field) {
                    $v=row('SELECT * FROM variants WHERE product_id=? AND unit=?',[$id,$unit]);
                    $price=csvNumber(($data[$field] ?? '') ?: (string)($v['price'] ?? 0));
                    $stock=csvNumber(($data[$unit.' stock'] ?? '') ?: (string)($v['stock'] ?? 0));
                    $origin=csvNumber(($data[$unit.' origin price'] ?? $data['origin price'] ?? '') ?: (string)($v['origin'] ?? $price));
                    if ($v) {
                        updatePrice($v+['status'=>$p['status'],'name_en'=>$name],$price);
                        run('UPDATE variants SET stock=?,origin=? WHERE id=?',[$stock,$origin,$v['id']]);
                    } else run('INSERT INTO variants(product_id,unit,price,origin,stock) VALUES (?,?,?,?,?)',[$id,$unit,$price,$origin,$stock]);
                }
                $count++;
            } catch (DomainException $e) { throw new DomainException('Baris '.$index.' · '.$e->getMessage()); }
        }
        audit('Impor',$count.' baris CSV');
        return $count;
    });
    fclose($handle);
    return $count;
}
function reportRows(): array {
    [$start,$end]=dateRange();
    return rows("SELECT oi.category,oi.edition,json_extract(oi.names,'$.name_en') AS product,SUM(oi.qty) AS qty,SUM(oi.qty*oi.price) AS sales FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.demo=0 AND o.paid_at IS NOT NULL AND o.status NOT IN ('cancelled','refunded') AND date(o.paid_at,'+7 hours') BETWEEN ? AND ? GROUP BY oi.category,oi.edition,product ORDER BY sales DESC",[$start,$end]);
}
function dateRange(): array {
    $from=query('from'); $to=query('to');
    return [preg_match('/^\d{4}-\d{2}-\d{2}$/D',$from) ? $from : '1970-01-01',preg_match('/^\d{4}-\d{2}-\d{2}$/D',$to) ? $to : '9999-12-31'];
}
function orderRows(): array {
    [$from,$to]=dateRange();
    $status=query('status'); $search=query('q'); $q='%'.$search.'%';
    $document='%'.preg_replace('/_(03|05)$/D','',$search).'%';
    return rows("SELECT * FROM orders WHERE (?='' OR status=?) AND date(created,'+7 hours') BETWEEN ? AND ? AND (number LIKE ? OR document_number LIKE ? OR name LIKE ? OR phone LIKE ?) ORDER BY id DESC",[$status,$status,$from,$to,$q,$document,$q,$q]);
}
function adminExport(string $type): never {
    adminAccess($type!=='orders');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tobapia-'.$type.'.csv"');
    $out=fopen('php://output','wb');
    fwrite($out,"\xEF\xBB\xBF");
    $headers=match($type) {'orders'=>['Order','Name','WhatsApp','Address','Status','Subtotal','TEREA shipping','Device shipping','Extra','Total','Created','Demo'],'reports'=>['Category','Edition','Product','Qty','Sales'],'template'=>csvColumns()};
    fputcsv($out,$headers,',','"','');
    $data=match($type) {'orders'=>array_map(fn($o)=>[orderDisplayNumber($o),$o['name'],$o['phone'],$o['address'],$o['status'],$o['subtotal'],$o['terea'],$o['device'],$o['extra'],$o['total'],$o['created'],$o['demo']],orderRows()),'reports'=>reportRows(),'template'=>[]};
    foreach ($data as $line) {
        $line=array_map(fn($value)=>is_string($value) && preg_match('/^[\s]*[=+@-]/u',$value) ? "'".$value : $value,$line);
        fputcsv($out,$line,',','"','');
    }
    fclose($out);
    exit;
}
