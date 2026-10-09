<?php

/*
 | Generates the brand images from the logo artwork in resources/brand/nebo-stage.png
 | (black ink on transparent, 784×212; the mark is columns 0–319).
 |
 |   public/icons/*.png          PWA, Apple touch and favicon icons: white mark on ink (#1A1A1A)
 |   public/favicon.ico          32 px icon for old browsers
 |   public/images/brand/*.png   full logo in ink and in white, for emails (no SVG support)
 |
 | The SVG logos in public/images/brand and public/favicon.svg are traced from the
 | same artwork. Run after changing the artwork: php scripts/generate-icons.php
 */

const INK = [0x1A, 0x1A, 0x1A];
const MARK_WIDTH = 320;

/** The artwork recoloured to one colour, keeping its alpha (anti-aliased edges). */
function nebo_recolour(GdImage $src, array $rgb): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $out = imagecreatetruecolor($w, $h);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $alpha = (imagecolorat($src, $x, $y) >> 24) & 0x7F;
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], $alpha));
        }
    }

    return $out;
}

function nebo_crop(GdImage $src, int $width): GdImage
{
    $out = imagecreatetruecolor($width, imagesy($src));
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagecopy($out, $src, 0, 0, 0, 0, $width, imagesy($src));

    return $out;
}

/** Square icon: white mark centred on ink, rounded unless maskable (full bleed, 80% safe zone). */
function nebo_icon(GdImage $mark, int $size, bool $maskable, string $path): void
{
    $scale = 4;
    $s = $size * $scale;
    $img = imagecreatetruecolor($s, $s);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagealphablending($img, true);
    $ink = imagecolorallocate($img, ...INK);

    if ($maskable) {
        imagefilledrectangle($img, 0, 0, $s, $s, $ink);
        $markWidth = $s * 0.56;
    } else {
        $r = (int) round($s * 0.22);
        imagefilledrectangle($img, $r, 0, $s - $r, $s, $ink);
        imagefilledrectangle($img, 0, $r, $s, $s - $r, $ink);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $ink);
        }
        $markWidth = $s * 0.70;
    }

    $markHeight = $markWidth * imagesy($mark) / imagesx($mark);
    imagecopyresampled($img, $mark, (int) (($s - $markWidth) / 2), (int) (($s - $markHeight) / 2), 0, 0, (int) $markWidth, (int) $markHeight, imagesx($mark), imagesy($mark));

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $s, $s);
    imagepng($out, $path, 9);
}

/** Wraps a PNG in an ICO container (PNG-compressed icons are valid ICO entries). */
function nebo_ico(string $png, string $path, int $size): void
{
    $data = file_get_contents($png);
    $header = pack('vvv', 0, 1, 1);
    $entry = pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($data), 6 + 16);
    file_put_contents($path, $header.$entry.$data);
}

$root = dirname(__DIR__);
$art = imagecreatefrompng("$root/resources/brand/nebo-stage.png");
imagesavealpha($art, true);

$whiteMark = nebo_crop(nebo_recolour($art, [255, 255, 255]), MARK_WIDTH);
$icons = "$root/public/icons";
nebo_icon($whiteMark, 192, false, "$icons/icon-192.png");
nebo_icon($whiteMark, 512, false, "$icons/icon-512.png");
nebo_icon($whiteMark, 192, true, "$icons/maskable-192.png");
nebo_icon($whiteMark, 512, true, "$icons/maskable-512.png");
nebo_icon($whiteMark, 180, true, "$icons/apple-touch-icon.png"); // iOS rounds the corners itself
nebo_icon($whiteMark, 32, false, "$icons/favicon-32.png");
nebo_ico("$icons/favicon-32.png", "$root/public/favicon.ico", 32);

// Android status-bar badge for notifications: the mark alone, white on transparent.
$badge = imagecreatetruecolor(96, 96);
imagesavealpha($badge, true);
imagealphablending($badge, false);
imagefill($badge, 0, 0, imagecolorallocatealpha($badge, 0, 0, 0, 127));
imagealphablending($badge, true);
$bw = 84;
$bh = (int) round($bw * imagesy($whiteMark) / imagesx($whiteMark));
imagecopyresampled($badge, $whiteMark, 6, (int) ((96 - $bh) / 2), 0, 0, $bw, $bh, imagesx($whiteMark), imagesy($whiteMark));
imagepng($badge, "$icons/badge-96.png", 9);

$brand = "$root/public/images/brand";
@mkdir($brand, 0755, true);
imagepng(nebo_recolour($art, INK), "$brand/nebo-stage.png", 9);
imagepng(nebo_recolour($art, [255, 255, 255]), "$brand/nebo-stage-white.png", 9);

echo "Icons and brand images written.\n";
