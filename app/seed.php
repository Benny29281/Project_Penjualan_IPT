<?php
const CATEGORIES = ['TEREA', 'IQOS ILUMA i', 'IQOS ILUMA i ONE', 'IQOS ILUMA i PRIME', 'Marlboro', 'Esse'];
const EDITIONS = ['Indonesia', 'South Korea', 'Japan', 'Uzbekistan', 'Italy'];
const STATUSES = ['new','pending','paid','processing','shipped','delivered','complete','cancelled','refunded'];

function seed(): void {
    $devices = [
        ['IQOS ILUMA i', 899000, 'Breeze Blue,Digital Violet,Midnight Purple,Leaf Green,Midnight Black'],
        ['IQOS ILUMA i ONE', 349000, 'Breeze Blue,Midnight Purple,Leaf Green,Midnight Black'],
        ['IQOS ILUMA i PRIME', 1599000, 'Breeze Blue,Midnight Purple,Garnet Red,Midnight Black,Aspen Green'],
    ];
    foreach ($devices as [$category, $price, $colors]) {
        foreach (explode(',', $colors) as $color) {
            seedProduct($category, $color, '', 'Indonesia', ['unit'=>$price], 1);
        }
    }
    $prd = file_get_contents(__DIR__ . '/../PRD (11).md');
    preg_match_all('/^\| ([^|]+) \| ([^|]+) \| (\d{2}\.\d{3}) \| (\d{3}\.\d{3}) \|\s*$/m', $prd, $rows, PREG_SET_ORDER);
    if (count($rows) !== 21) {
        throw new RuntimeException('The catalog seed must contain 21 Indonesian TEREA products.');
    }
    foreach ($rows as [, $name, $flavour, $pack, $carton]) {
        seedProduct('TEREA', trim($name), trim($flavour), 'Indonesia', [
            'pack'=>(int)str_replace('.', '', $pack), 'carton'=>(int)str_replace('.', '', $carton)
        ], 1);
    }
    $editions = [
        'South Korea'=>'Amber,Silver,Blue,Green,Purple Wave,Black Purple,Black Green,Black Yellow,Russet,Teak,Oasis Pearl,Sun Pearl,Arbor Pearl,Riviera Pearl,Starling Pearl,Green Zing,Summer Wave,Yugen,Shine Pearl,Glacier Twilight Pearl,Onyx Amang Pearl,Onyx Cali Pearl',
        'Uzbekistan'=>'Amber,Provence Pearl,Purple Wave,Silver,Starling Pearl,Summer Wave,Sun Pearl,Tidal Pearl,Turquoise,Twilight Pearl,Yellow',
        'Italy'=>'Amber,Bronze,Purple Wave,Teak,Silver,Sienna,Turquoise,Yellow',
        'Japan'=>'Balanced Regular,Black Menthol,Black Purple,Bold Regular,Black Ruby Menthol,Bright Menthol,Fusion Menthol,Menthol,Purple Menthol,Oasis Pearl,Mint,Regular,Rich Regular',
    ];
    foreach ($editions as $edition=>$names) {
        foreach (explode(',', $names) as $name) seedProduct('TEREA', $name, '', $edition, ['pack'=>0,'carton'=>0], 0);
    }
}
function seedProduct(string $category, string $name, string $flavour, string $edition, array $prices, int $demo): void {
    $id = insert("INSERT INTO products(category,name_en,flavour,edition,status,demo) VALUES (?,?,?,?,?,?)", [$category,$name,$flavour,$edition,$demo ? 'draft' : 'hidden',$demo]);
    foreach ($prices as $unit=>$price) {
        run('INSERT INTO variants(product_id,unit,price,origin,stock) VALUES (?,?,?,?,?)', [$id,$unit,$price,$price,$demo ? 100 : 0]);
    }
}
