<?php
require __DIR__.'/delivery-countries.php';

const DELIVERY_LEVELS=['province','city','district','village'];

function deliveryCountryName(string $code,string $locale): string {
    $index=array_search($locale,DELIVERY_COUNTRY_LOCALES,true) ?: 0;
    return DELIVERY_COUNTRY_NAMES[$code][$index] ?? $code;
}
function deliveryCountries(): array {
    static $countries;
    if ($countries!==null) return $countries;
    $codes=array_keys(DELIVERY_COUNTRY_NAMES);
    $countries=array_combine($codes,array_map(fn($code)=>deliveryCountryName($code,lang()),$codes));
    if (class_exists(Collator::class)) (new Collator(lang()))->asort($countries);
    else asort($countries,SORT_NATURAL | SORT_FLAG_CASE);
    return $countries;
}
function deliveryRegionDb(): PDO {
    static $db;
    if ($db) return $db;
    $source=__DIR__.'/delivery-regions.sqlite';
    if (!is_file($source)) throw new RuntimeException('Delivery region dataset is missing.');
    $db=new PDO('sqlite:'.$source,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA query_only=ON');
    return $db;
}
function deliveryRegions(string $parent): array {
    $query=deliveryRegionDb()->prepare('SELECT id,name,postal FROM regions WHERE parent=? ORDER BY name COLLATE NOCASE,id');
    $query->execute([$parent]);
    return $query->fetchAll();
}
function deliverySelection(): array {
    $country=input('delivery_country',2);
    $street=input('address',1200);
    $postal=input('postal_code',16);
    requireThat(array_key_exists($country,deliveryCountries()) && mb_strlen($street)>=5,t('invalid_delivery'));
    $region=[];
    $regionIds=[];
    if ($country==='ID') {
        $parent='ID';
        $query=deliveryRegionDb()->prepare('SELECT name,postal FROM regions WHERE id=? AND parent=?');
        foreach (DELIVERY_LEVELS as $level) {
            $id=input('delivery_'.$level.'_id',13);
            $query->execute([$id,$parent]);
            $selected=$query->fetch();
            requireThat((bool)$selected,t('invalid_delivery_region'));
            $region[$level]=$selected['name'];
            $regionIds[$level]=$id;
            $parent=$id;
        }
        requireThat((bool)preg_match('/^[0-9]{5}$/D',$postal) && ($selected['postal']==='' || $postal===$selected['postal']),t('invalid_postal'));
    } else {
        foreach (DELIVERY_LEVELS as $level) $region[$level]=input('delivery_'.$level,120);
        requireThat(!in_array('',$region,true),t('invalid_delivery'));
        requireThat((bool)preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\x20-]{0,15}$/uD',$postal),t('invalid_postal'));
    }
    $countryName=deliveryCountryName($country,'en');
    $address=implode("\n",[$street,$region['village'].', '.$region['district'],$region['city'].', '.$region['province'].' '.$postal,$countryName]);
    return ['details'=>['country'=>$country,'country_name'=>$countryName,'street'=>$street,'postal_code'=>$postal,'region_ids'=>$regionIds]+$region,'address'=>$address];
}
