<?php
const ORDER_CONFIRMATION_EMAIL='tobapia06@gmail.com';

function confirmationSubject(array $order): string {
    return ($order['demo'] ? '[Demo] ' : '').'Confirm Order_'.orderDisplayNumber($order);
}

function buyCheckoutToken(): ?string {
    return preg_match('~^/checkout/buy/([0-9a-f]{48})$~D',ROUTE,$match) ? $match[1] : null;
}
function checkout(?string $buyToken=null): string {
    requireThat(input('age')==='1',t('age_required'));
    $nonce=input('nonce');
    if (isset($_SESSION['checkout_done'][$nonce])) return $_SESSION['checkout_done'][$nonce];
    $expected=$buyToken ?? ($_SESSION['checkout_nonce'] ?? '');
    requireThat($nonce && hash_equals($expected,$nonce),t('refresh'));
    $selection=$buyToken ? ($_SESSION['buy_now'][$buyToken] ?? []) : null;
    requireThat(!$buyToken || $selection!==[],t('buy_expired'));
    $name=input('name');
    $notes=input('notes',800);
    $delivery=deliverySelection();
    $numberInput=input('phone',30);
    requireThat($delivery['details']['country']==='ID' || str_starts_with($numberInput,'+'),t('international_phone'));
    $phone=phone($numberInput);
    requireThat(mb_strlen($name)>=2,t('invalid_delivery'));
    [$number,$notify]=atomic(function () use ($name,$delivery,$notes,$phone,$selection) {
        $s=settings();
        if ($s['mode']!=='demo') requireThat($s['qr']!=='',t('qr_missing'));
        requireThat(!row('SELECT phone FROM blocked_phones WHERE phone=?',[$phone])
            && (!isset($_SESSION['customer']) || currentUser()),t('blocked'));
        $order=quote($selection ?? cart(),$s);
        requireThat($order['items']!==[] && !$order['invalid'],t('unavailable'));
        $number='TBP-'.strtoupper(bin2hex(random_bytes(7)));
        $created=gmdate('Y-m-d H:i:s');
        $id=insert('INSERT INTO orders(number,document_number,customer_id,name,phone,address,delivery_details,notes,status,subtotal,terea,device,total,age_statement,age_at,qr,locale,demo,created) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$number,nextOrderDocumentNumber($created),currentUser()['id'] ?? null,$name,$phone,$delivery['address'],json($delivery['details']),$notes,'new',$order['subtotal'],$order['terea'],$order['device'],$order['total'],t('age_statement'),$created,$s['qr'],lang(),(int)($s['mode']==='demo'),$created]);
        foreach ($order['items'] as $item) {
            run('INSERT INTO order_items(order_id,variant_id,names,category,edition,unit,qty,price) VALUES (?,?,?,?,?,?,?,?)',
                [$id,$item['id'],json(names($item)),$item['category'],$item['edition'],$item['unit'],$item['qty'],$item['price']]);
        }
        run('INSERT INTO timeline(order_id,actor,status) VALUES (?,?,?)',[$id,$name,'new']);
        if ($selection===null) saveCart([]);
        return [$number,$s['notify']];
    });
    $_SESSION['owned'][$number]=true;
    $_SESSION['checkout_done']=array_slice([$nonce=>$number]+($_SESSION['checkout_done'] ?? []),0,10,true);
    if ($buyToken) unset($_SESSION['buy_now'][$buyToken]);
    else unset($_SESSION['checkout_nonce']);
    if ($notify && filter_var($notify,FILTER_VALIDATE_EMAIL) && !@mail($notify,'Tobapia · '.$number,'Pesanan baru '.$number.'. Buka dashboard Tobapia untuk memprosesnya.')) {
        error_log('Tobapia order notification failed for '.$number);
    }
    return $number;
}
function openOrderPayment(string $number): array {
    $order=lookup($number);
    if ($order['status']!=='new') return $order;

    $order=atomic(function () use ($number) {
        $order=lookup($number);
        if ($order['status']!=='new' || (awaitingPaymentConfirmation($order) && time()>=paymentConfirmationDeadline($order))) return $order;

        run("UPDATE orders SET status='pending' WHERE id=?",[$order['id']]);
        run("INSERT INTO timeline(order_id,actor,status) VALUES (?,'Sistem','pending')",[$order['id']]);
        $order['status']='pending';
        return $order;
    });
    if ($order['status']==='new') {
        expireUnconfirmedOrders();
        return lookup($number);
    }
    return $order;
}
function storefrontPost(string $action, array $s): string {
    switch ($action) {
        case 'buy_now':
            $id=integer(input('variant'),1);
            $qty=integer(input('quantity'),1,99);
            $selection=[$id=>$qty];
            requireThat(!quote($selection,$s)['invalid'],t('unavailable'));
            $token=bin2hex(random_bytes(24));
            $_SESSION['buy_now']=array_slice([$token=>$selection]+($_SESSION['buy_now'] ?? []),0,10,true);
            return '/checkout/buy/'.$token;
        case 'add_product':
        case 'cart':
            $id=integer(input('variant'),1);
            $isDetail=$action==='add_product';
            $delta=$isDetail ? integer(input('quantity'),1,99) : integer(input('delta'),-99,1);
            [$cart,$qty,$selected]=atomic(function () use ($id,$delta) {
                $cart=cart();
                $qty=max(0,($cart[$id] ?? 0)+$delta);
                if ($delta>0 && $qty>0) {
                    $v=variant($id);
                    requireThat($v && eligible($v,settings()) && $qty<=min(99,(int)$v['stock']),t('unavailable'));
                }
                if ($qty>0) $cart[$id]=$qty;
                else unset($cart[$id]);
                saveCart($cart);
                return [$cart,$qty,$v ?? null];
            });
            if (($_SERVER['HTTP_ACCEPT'] ?? '')==='application/json') {
                $quote=quote($cart,$s);
                $response=['qty'=>$qty,'count'=>array_sum($cart),'total'=>money($quote['total']),'subtotal'=>money($quote['subtotal'])];
                if ($isDetail) {
                    $response['remaining']=min(99,(int)$selected['stock'])-$qty;
                    $response['message']=t('added_cart');
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json($response);
                exit;
            }
            if ($isDetail) {
                flash(t('added_cart'));
                return '/products/'.$selected['product_id'].'?unit='.rawurlencode($selected['unit']).'#purchase';
            }
            return ROUTE==='/cart' ? '/cart' : '/#products';
        case 'clear':
            saveCart([]);
            return '/cart';
        case 'checkout':
            return '/orders/'.checkout(buyCheckoutToken());
        case 'confirm_order':
            requireThat((bool)preg_match('~^/orders/(TBP-[A-F0-9]{14})$~D',ROUTE,$match),t('order_missing'));
            requireThat(input('confirmation_ack')==='1',t('confirm_order_ack_required'));
            atomic(function () use ($match) {
                $order=lookup($match[1]);
                if ($order['confirmation_sent_at']) return;
                requireThat(awaitingPaymentConfirmation($order),t('confirm_order_unavailable'));
                $now=time();
                requireThat($now<paymentConfirmationDeadline($order),t('payment_expired'));
                run('UPDATE orders SET confirmation_sent_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',$now),$order['id']]);
            });
            flash(t('confirm_order_sent'));
            return '/';
        case 'signup':
            rate('signup',8,900);
            atomic(fn()=>newUser('customer',input('name'),loginId(input('identity')),input('password',100)));
            login('customer',loginId(input('identity')),input('password',100));
            return '/account';
        case 'customer_login':
            login('customer',loginId(input('identity')),input('password',100));
            return '/account';
        case 'customer_logout':
            unset($_SESSION['customer'],$_SESSION['owned'],$_SESSION['checkout_done'],$_SESSION['checkout_nonce'],$_SESSION['buy_now']);
            session_regenerate_id(true);
            return '/';
        case 'track':
            rate('track',12,900);
            $number=strtoupper(input('number'));
            $document=preg_replace('/_(03|05)$/D','',$number);
            $order=row('SELECT number FROM orders WHERE (number=? OR document_number=?) AND phone=? AND customer_id IS NULL',[$number,$document,phone(input('phone'))]);
            requireThat((bool)$order,t('order_missing'));
            $number=$order['number'];
            $_SESSION['owned'][$number]=true;
            return '/orders/'.$number;
        case 'reorder':
            $order=lookup(input('number'));
            $cart=cart();
            foreach (items($order['id']) as $item) {
                $v=$item['variant_id'] ? variant($item['variant_id']) : null;
                if ($v && eligible($v,$s) && $v['stock']>0) $cart[$v['id']]=min(99,(int)$v['stock'],($cart[$v['id']] ?? 0)+$item['qty']);
            }
            saveCart($cart);
            return '/cart';
    }
    throw new DomainException(t('error'));
}
