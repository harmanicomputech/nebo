<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes as inline SVG (pure PHP; no Imagick needed on shared hosting).
 */
class QrCode
{
    public static function svg(string $data, int $size = 160): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($data);

        // Drop the XML declaration so the SVG can be inlined in HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
