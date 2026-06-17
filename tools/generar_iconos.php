<?php

declare(strict_types=1);

/**
 * Genera los íconos PNG de la PWA con GD (sin dependencias externas).
 *
 * Uso: php tools/generar_iconos.php
 * Salida: public/assets/icons/icon-{192,512}.png, icon-maskable-512.png, apple-touch-icon.png
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo CLI.\n");
}
if (!extension_loaded('gd')) {
    exit("Se requiere la extensión GD.\n");
}

$destino = __DIR__ . '/../public/assets/icons';
if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
    exit("No se pudo crear {$destino}\n");
}

/** Dibuja el ícono (raqueta + pelota sobre fondo degradado azul) al tamaño pedido. */
function dibujar(int $size): \GdImage
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    $s = static fn (float $v): int => (int) round($v * $size / 512);

    // Fondo: degradado vertical #2563eb -> #1d4ed8.
    $top = [37, 99, 235];
    $bot = [29, 78, 216];
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        $col = imagecolorallocate(
            $im,
            (int) round($top[0] + ($bot[0] - $top[0]) * $t),
            (int) round($top[1] + ($bot[1] - $top[1]) * $t),
            (int) round($top[2] + ($bot[2] - $top[2]) * $t),
        );
        imageline($im, 0, $y, $size, $y, $col);
    }

    // Mango de la raqueta (madera).
    $wood = imagecolorallocate($im, 180, 120, 70);
    $woodDark = imagecolorallocate($im, 140, 92, 52);
    imagefilledrectangle($im, $s(212), $s(250), $s(252), $s(420), $woodDark);
    imagefilledrectangle($im, $s(218), $s(250), $s(246), $s(415), $wood);
    imagefilledellipse($im, $s(232), $s(415), $s(34), $s(34), $wood);

    // Cara de la raqueta (goma roja con borde).
    $redDark = imagecolorallocate($im, 153, 27, 27);
    $red = imagecolorallocate($im, 239, 68, 68);
    $cx = $s(232);
    $cy = $s(205);
    imagefilledellipse($im, $cx, $cy, $s(252), $s(252), $redDark);
    imagefilledellipse($im, $cx, $cy, $s(224), $s(224), $red);
    // Brillo sutil.
    $shine = imagecolorallocatealpha($im, 255, 255, 255, 95);
    imagefilledellipse($im, $s(195), $s(168), $s(90), $s(70), $shine);

    // Pelota (blanca con borde claro).
    $ballEdge = imagecolorallocate($im, 226, 232, 240);
    $ball = imagecolorallocate($im, 255, 255, 255);
    imagefilledellipse($im, $s(372), $s(150), $s(96), $s(96), $ballEdge);
    imagefilledellipse($im, $s(372), $s(150), $s(82), $s(82), $ball);

    return $im;
}

$base = dibujar(512);

$variantes = [
    'icon-512.png' => 512,
    'icon-192.png' => 192,
    'icon-maskable-512.png' => 512,
    'apple-touch-icon.png' => 180,
];

foreach ($variantes as $archivo => $size) {
    $img = $size === 512 ? $base : dibujar($size);
    imagepng($img, $destino . '/' . $archivo, 8);
    echo "  generado: {$archivo} ({$size}x{$size})\n";
    if ($size !== 512) {
        imagedestroy($img);
    }
}
imagedestroy($base);

echo "Listo.\n";
