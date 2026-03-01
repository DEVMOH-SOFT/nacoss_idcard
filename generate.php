<?php
require 'config.php';

$id = $_GET['id'] ?? null;
type = $_GET['type'] ?? 'png';
if (!$id) exit('Missing id');

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) exit('Not found');

function drawCard($student) {
    // card dimensions
    $width=600;
    $height=350;
    $img = imagecreatetruecolor($width,$height);
    // colors
    $white = imagecolorallocate($img,255,255,255);
    $green = imagecolorallocate($img,0,128,0);
    $black = imagecolorallocate($img,0,0,0);
    imagefilledrectangle($img,0,0,$width,$height,$white);
    // header bar
    imagefilledrectangle($img,0,0,$width,60,$green);
    // draw photo circle
    $photoPath = $student['image_path'];
    if (file_exists($photoPath)) {
        $src = imagecreatefromstring(file_get_contents($photoPath));
        $size = 150;
        $circle = imagecreatetruecolor($size,$size);
        imagesavealpha($circle,true);
        $trans = imagecolorallocatealpha($circle,0,0,0,127);
        imagefill($circle,0,0,$trans);
        $r = $size/2;
        for ($x=0;$x<$size;$x++){
            for($y=0;$y<$size;$y++){
                $dx=$x-$r;
                $dy=$y-$r;
                if($dx*$dx+$dy*$dy <= $r*$r){
                    $color = imagecolorat($src, ($x/$size)*imagesx($src), ($y/$size)*imagesy($src));
                    imagesetpixel($circle,$x,$y,$color);
                }
            }
        }
        imagecopy($img,$circle,20,80,0,0,$size,$size);
        imagedestroy($circle);
        imagedestroy($src);
    }
    // text
    $font = __DIR__.'/assets/arial.ttf'; // ensure this font exists or use system font
    $name = strtoupper($student['full_name']);
    $matric = $student['matric_no'];
    $dept = $student['department'];
    $post = $student['post'];
    imagettftext($img,24,0,200,130,$black,$font,$name);
    imagettftext($img,18,0,200,170,$black,$font,$matric);
    imagettftext($img,16,0,200,210,$black,$font,$dept);
    imagettftext($img,16,0,200,250,$black,$font,$post);
    return $img;
}

if ($type === 'png') {
    $card = drawCard($s);
    header('Content-Type: image/png');
    imagepng($card);
    imagedestroy($card);
} else {
    // generate png temp and embed into pdf
    $card = drawCard($s);
    $tmp = tempnam(sys_get_temp_dir(),'card');
    imagepng($card,$tmp);
    imagedestroy($card);
    require_once __DIR__.'/assets/fpdf.php';
    $pdf = new FPDF('L','pt',array(600,350));
    $pdf->AddPage();
    $pdf->Image($tmp,0,0,600,350);
    unlink($tmp);
    header('Content-Type: application/pdf');
    $pdf->Output('I','idcard_'.$s['matric_no'].'.pdf');
}
