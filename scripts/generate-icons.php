<?php

/*
 | Generates the PWA / favicon PNGs from the Nebo Stage mark (same geometry as
 | resources/views/components/ui/logo.blade.php). Run: php scripts/generate-icons.php
 */

function nebo_icon(int $size, bool $maskable, string $path): void
{
    $scale = 4;
    $s = $size * $scale;
    $img = imagecreatetruecolor($s, $s);
    imagesavealpha($img, true);
    imagealphablending($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    $red = imagecolorallocate($img, 0xCC, 0x1F, 0x1F);
    $white = imagecolorallocate($img, 255, 255, 255);
    $faint = imagecolorallocatealpha($img, 255, 255, 255, 57);
    imagefill($img, 0, 0, $transparent);

    if ($maskable) {
        // Full-bleed background; the mark sits inside the 80% safe zone.
        imagefilledrectangle($img, 0, 0, $s, $s, $red);
        $inset = 0.18;
    } else {
        $r = (int) round($s * 0.25);
        imagefilledrectangle($img, $r, 0, $s - $r, $s, $red);
        imagefilledrectangle($img, 0, $r, $s, $s - $r, $red);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $red);
        }
        $inset = 0.0;
    }

    // Map the 40x40 logo grid into the drawable area.
    $area = $s * (1 - 2 * $inset);
    $off = $s * $inset;
    $p = fn (float $x, float $y) => [(int) round($off + $x / 40 * $area), (int) round($off + $y / 40 * $area)];
    $stroke = (int) round(3.6 / 40 * $area);

    // "N": thick polyline M11 29 V12 L29 29 V12 with round joins.
    $points = [$p(11, 29), $p(11, 12), $p(29, 29), $p(29, 12)];
    for ($i = 0; $i < count($points) - 1; $i++) {
        [$x1, $y1] = $points[$i];
        [$x2, $y2] = $points[$i + 1];
        $len = max(1, hypot($x2 - $x1, $y2 - $y1));
        $nx = -($y2 - $y1) / $len * $stroke / 2;
        $ny = ($x2 - $x1) / $len * $stroke / 2;
        imagefilledpolygon($img, [
            (int) ($x1 + $nx), (int) ($y1 + $ny), (int) ($x2 + $nx), (int) ($y2 + $ny),
            (int) ($x2 - $nx), (int) ($y2 - $ny), (int) ($x1 - $nx), (int) ($y1 - $ny),
        ], $white);
    }
    foreach ($points as [$x, $y]) {
        imagefilledellipse($img, $x, $y, $stroke, $stroke, $white);
    }

    // Stage riser bar.
    [$bx1, $by1] = $p(7, 31);
    [$bx2, $by2] = $p(33, 33.4);
    imagefilledrectangle($img, $bx1, $by1, $bx2, $by2, $faint);

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $s, $s);
    imagepng($out, $path, 9);
}

$dir = __DIR__.'/../public/icons';
nebo_icon(192, false, "$dir/icon-192.png");
nebo_icon(512, false, "$dir/icon-512.png");
nebo_icon(192, true, "$dir/maskable-192.png");
nebo_icon(512, true, "$dir/maskable-512.png");
nebo_icon(180, true, "$dir/apple-touch-icon.png"); // iOS applies its own rounding
nebo_icon(32, false, "$dir/favicon-32.png");
echo "Icons written to public/icons\n";
