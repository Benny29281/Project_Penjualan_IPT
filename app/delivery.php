<?php
const DELIVERY_LEVELS=['province','city','district','village'];

function deliveryCountryName(string $code,string $locale): string {
    if (class_exists(Locale::class)) return Locale::getDisplayRegion('und_'.$code,$locale) ?: $code;
    return ['ID'=>'Indonesia','CN'=>'China'][$code] ?? $code;
}
function deliveryCountries(): array {
    static $countries;
    if ($countries!==null) return $countries;
    $codes=explode(' ','AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW');
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
