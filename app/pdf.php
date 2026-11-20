<?php
function pdfFont(string $locale,bool $bold=false): string {
    static $fonts=[];
    $key=$locale.($bold ? '-bold' : '');
    if (isset($fonts[$key])) return $fonts[$key];
    $windows=getenv('WINDIR') ?: 'C:/Windows';
    $paths=array_filter([getenv('TOBAPIA_PDF_FONT_'.strtoupper($locale)), $windows.'/Fonts/'.match($locale) {
        'zh'=>$bold ? 'msyhbd.ttc' : 'msyh.ttc',
        'ko'=>$bold ? 'malgunbd.ttf' : 'malgun.ttf',
        default=>$bold ? 'arialbd.ttf' : 'arial.ttf',
    },'/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']);
    foreach ($paths as $path) if (is_readable($path)) return $fonts[$key]=$path;
    throw new DomainException('PDF font is unavailable.');
}
function pdfFontFor(string $text,string $locale,bool $bold=false): string {
    if (preg_match('/\p{Hangul}/u',$text)) $locale='ko';
    elseif (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u',$text)) $locale='zh';
    return pdfFont($locale,$bold);
}
function pdfTextWidth(string $text,int $size,string $font): int {
    $bounds=imagettfbbox($size,0,$font,$text);
    return $bounds[2]-$bounds[0];
}
function pdfWrap(string $text,int $size,string $font,int $width): array {
    $lines=[];
    foreach (preg_split('/\R/u',$text) as $paragraph) {
        $line='';
        foreach (preg_split('//u',$paragraph,-1,PREG_SPLIT_NO_EMPTY) as $char) {
            if ($line!=='' && pdfTextWidth($line.$char,$size,$font)>$width) {
                $space=mb_strrpos($line,' ');
                $lines[]=$space ? mb_substr($line,0,$space) : $line;
                $line=$space ? ltrim(mb_substr($line,$space+1)) : '';
            }
            if ($line!=='' || $char!==' ') $line.=$char;
        }
        $lines[]=$line;
    }
    return $lines;
}
function pdfOrderPages(array $order,string $locale,string $filename,bool $priced): array {
    $margin=64; $right=1176; $bottom=1610;
    $canvases=[]; $canvas=null; $palette=[]; $y=0;

    $write=function (string $text,int $x,int $baseline,int $size=21,string $color='ink',bool $bold=false,bool $rightAlign=false) use (&$canvas,&$palette,$locale) {
        $font=pdfFontFor($text,$locale,$bold);
        if ($rightAlign) $x-=pdfTextWidth($text,$size,$font);
        imagettftext($canvas,$size,0,$x,$baseline,$palette[$color],$font,$text);
    };
    $newPage=function () use (&$canvases,&$canvas,&$palette,&$y,$margin,$write,$locale,$filename) {
        $canvas=imagecreatetruecolor(1240,1754);
        imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        $palette=[
            'ink'=>imagecolorallocate($canvas,37,56,50),
            'green'=>imagecolorallocate($canvas,38,79,65),
            'muted'=>imagecolorallocate($canvas,88,106,98),
            'line'=>imagecolorallocate($canvas,223,232,224),
            'surface'=>imagecolorallocate($canvas,236,243,236),
        ];
        $write(t('pdf_order_list',$locale),$margin,82,$canvases ? 27 : 36,'ink',true);
        $write($filename,$margin,120,18,'muted');
        $y=155;
        $canvases[]=$canvas;
    };
    $ensure=function (int $height) use (&$y,$bottom,$newPage): bool {
        if ($y+$height<=$bottom) return false;
        $newPage();
        return true;
    };
    $paragraph=function (string $text,int $size=21,string $color='ink',bool $bold=false) use (&$y,$locale,$margin,$right,$ensure,$write) {
        foreach (pdfWrap($text,$size,pdfFontFor($text,$locale,$bold),$right-$margin) as $line) {
            $ensure($size+12);
            $write($line,$margin,$y,$size,$color,$bold);
            $y+=$size+12;
        }
    };
    $newPage();
    $paragraph(t('pdf_date',$locale).': '.date('d/m/Y, H:i:s',strtotime($order['created'].' UTC')),18,'muted');
    if ($order['demo']) $paragraph(t('demo',$locale),17,'muted');
    $y+=9;
    $paragraph(t('name',$locale).': '.$order['name']);
    $paragraph(t('address',$locale),21,'ink',true);
    $paragraph($order['address'],20);
    $paragraph(t('phone_number',$locale).': +'.$order['phone'],18,'muted');
    if ($order['notes']) $paragraph(t('order_notes',$locale).': '.$order['notes'],18,'muted');

    $items=items($order['id']);
    $y+=12;
    $paragraph(t('pdf_line_items',$locale).': '.count($items),18,'muted');
    $headers=['#',t('item',$locale),t('country',$locale),t('unit',$locale),t('quantity',$locale)];
    if ($priced) array_push($headers,t('pdf_unit_price',$locale),t('subtotal',$locale));
    $widths=$priced ? [44,376,166,100,78,164,184] : [44,566,216,163,123];
    $columns=[];
    $x=$margin;
    foreach ($widths as $width) {
        $columns[]=$x;
        $x+=$width;
    }
    $renderRow=function (array $cells,bool $heading=false) use ($locale,$widths) {
        $formatted=[];
        foreach ($cells as $i=>$value) {
            $size=$heading ? 17 : ($i===1 ? 21 : 18);
            $bold=$heading || in_array($i,[1,4,6],true);
            $parts=explode("\n",$value,2);
            $lines=[];
            foreach ($parts as $part=>$text) {
                $lineSize=$part ? 17 : $size;
                $lineBold=$bold && !$part;
                foreach (pdfWrap($text,$lineSize,pdfFontFor($text,$locale,$lineBold),$widths[$i]-18) as $line) {
                    $lines[]=[$line,$lineSize,$lineBold,$heading?'green':($lineBold?'ink':'muted')];
                }
            }
            $formatted[]=$lines;
        }
        return $formatted;
    };
    $heightOf=fn(array $row)=>max(array_map('count',$row))*28+18;
    $paintRow=function (array $row,bool $heading=false) use (&$y,&$canvas,&$palette,$widths,$columns,$margin,$right,$write,$heightOf) {
        $height=$heightOf($row);
        if ($heading) imagefilledrectangle($canvas,$margin,$y,$right,$y+$height,$palette['surface']);
        foreach ($row as $column=>$lines) {
            $numeric=$column>=4;
            $x=$numeric ? $columns[$column]+$widths[$column]-9 : $columns[$column]+9;
            foreach ($lines as $index=>[$line,$size,$bold,$color]) $write($line,$x,$y+28+$index*28,$size,$color,$bold,$numeric);
        }
        $y+=$height;
        if (!$heading) imageline($canvas,$margin,$y,$right,$y,$palette['line']);
    };
    $headerRow=$renderRow($headers,true);
    $headerHeight=$heightOf($headerRow);
    $ensure($headerHeight+60);
    $paintRow($headerRow,true);
    foreach ($items as $index=>$item) {
        $names=decode($item['names']);
        $name=productName($names,$locale);
        $alternate=($names['name_zh'] ?? '')!==$name ? ($names['name_zh'] ?? '') : '';
        $cells=[(string)($index+1),$name.($alternate ? "\n".$alternate : ''),t($item['edition'],$locale),t($item['unit'],$locale),(string)$item['qty']];
        if ($priced) array_push($cells,money($item['price']),money($item['price']*$item['qty']));
        $row=$renderRow($cells);
        if ($ensure($heightOf($row))) $paintRow($headerRow,true);
        $paintRow($row);
    }
    $y+=36;
    if ($priced) {
        $totals=totals($order,$locale);
        $ensure(39*count($totals)+45);
        foreach ($totals as $label=>$value) {
            $total=$label===t('total',$locale);
            if ($total) imagefilledrectangle($canvas,$margin,$y-26,$right,$y+13,$palette['surface']);
            $write($label,$margin+9,$y,$total ? 24 : 20,$total?'ink':'muted',$total);
            $write($value,$right-9,$y,$total ? 24 : 20,'green',true,true);
            $y+=39;
        }
    }
    $y+=8;
    $paragraph(t('pdf_confirmation',$locale),18,'muted');
    if ($priced && $order['qr'] && is_file(DATA.'/'.$order['qr'])) {
        $image=imagecreatefromstring(file_get_contents(DATA.'/'.$order['qr']));
        if ($image) {
            $y+=15;
            $scale=min(420/imagesx($image),500/imagesy($image));
            $width=(int)round(imagesx($image)*$scale);
            $height=(int)round(imagesy($image)*$scale);
            $ensure($height+60);
            $paragraph(t('qr',$locale),21,'green',true);
            imagecopyresized($canvas,$image,$margin,$y-9,0,0,$width,$height,imagesx($image),imagesy($image));
            imagedestroy($image);
        }
    }
    $pages=[];
    foreach ($canvases as $index=>$page) {
        $canvas=$page;
        imageline($canvas,$margin,1667,$right,1667,$palette['line']);
        $write($filename,$margin,1695,15,'muted');
        $write(t('pdf_page',$locale).' '.($index+1).' / '.count($canvases),$right,1695,15,'muted',false,true);
        ob_start();
        imagejpeg($canvas,null,96);
        $pages[]=ob_get_clean();
        imagedestroy($canvas);
    }
    return $pages;
}
function sendPdf(array $order,string $locale,string $variant='05'): never {
    $filename=orderDisplayNumber($order).'_'.$variant;
    $pages=pdfOrderPages($order,$locale,$filename,$variant==='05');
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>'];
    $children=[];
    foreach ($pages as $i=>$page) {
        $id=3+$i*3; $children[]=$id.' 0 R';
        $objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Img '.($id+1).' 0 R >> >> /Contents '.($id+2).' 0 R >>';
        $objects[$id+1]='<< /Type /XObject /Subtype /Image /Width 1240 /Height 1754 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($page).">>\nstream\n".$page."\nendstream";
        $content="q\n595 0 0 842 0 0 cm\n/Img Do\nQ";
        $objects[$id+2]='<< /Length '.strlen($content).">>\nstream\n".$content."\nendstream";
    }
    $objects[2]='<< /Type /Pages /Count '.count($children).' /Kids ['.implode(' ',$children).'] >>';
    ksort($objects);
    $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets=[];
    foreach ($objects as $id=>$object) {
        $offsets[]=strlen($pdf);
        $pdf.=$id." 0 obj\n".$object."\nendobj\n";
    }
    $xref=strlen($pdf);
    $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) $pdf.=sprintf("%010d 00000 n \n",$offset);
    $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
}
