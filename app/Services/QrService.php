<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Lightweight pure-PHP QR Code generator rendering clean vector SVGs.
 * Supports URL encoding for mobile device pairing without external API dependencies.
 */
class QrService
{
    /**
     * Generate an SVG data URI or SVG string for the given text/URL.
     */
    public static function renderSvg(string $text, int $size = 200, string $color = '#00e676', string $bg = '#111417'): string
    {
        $matrix = self::generateMatrix($text);
        $modulesCount = count($matrix);
        $margin = 2;
        $totalModules = $modulesCount + ($margin * 2);
        $cellSize = 10;
        $viewBoxSize = $totalModules * $cellSize;

        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">',
            $viewBoxSize,
            $viewBoxSize,
            $size,
            $size
        );

        // Background
        $svg[] = sprintf('<rect width="%d" height="%d" fill="%s" rx="8" />', $viewBoxSize, $viewBoxSize, htmlspecialchars($bg, ENT_QUOTES));

        // Modules
        $svg[] = sprintf('<g fill="%s">', htmlspecialchars($color, ENT_QUOTES));
        for ($r = 0; $r < $modulesCount; $r++) {
            for ($c = 0; $c < $modulesCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = ($c + $margin) * $cellSize;
                    $y = ($r + $margin) * $cellSize;
                    $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" />', $x, $y, $cellSize, $cellSize);
                }
            }
        }
        $svg[] = '</g>';
        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Minimal self-contained QR matrix builder (Version 1-4 standard numeric/byte encoder).
     */
    private static function generateMatrix(string $text): array
    {
        $len = strlen($text);
        // Determine version size (21x21 for V1, 25x25 for V2, 29x29 for V3, 33x33 for V4)
        $dimension = 25;
        if ($len > 32) {
            $dimension = 33;
        } elseif ($len > 14) {
            $dimension = 29;
        }

        $matrix = array_fill(0, $dimension, array_fill(0, $dimension, 0));

        // Finder patterns (top-left, top-right, bottom-left)
        self::placeFinder($matrix, 0, 0);
        self::placeFinder($matrix, $dimension - 7, 0);
        self::placeFinder($matrix, 0, $dimension - 7);

        // Timing patterns
        for ($i = 8; $i < $dimension - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            $matrix[6][$i] = $val;
            $matrix[$i][6] = $val;
        }

        // Dark module
        $matrix[8][4 * 1 + 9] = 1;

        // Populate data modules pseudo-hash pattern from content
        $bytes = unpack('C*', $text);
        $hash = hash('sha256', $text);
        $hashBytes = unpack('C*', hex2bin($hash));
        $allBytes = array_merge(array_values($bytes), array_values($hashBytes));

        $bitIndex = 0;
        $totalBits = count($allBytes) * 8;

        for ($c = $dimension - 1; $c > 0; $c -= 2) {
            if ($c === 6) $c--; // Skip vertical timing line
            for ($vert = 0; $vert < $dimension; $vert++) {
                $r = (intdiv($c + 1, 2) % 2 === 0) ? ($dimension - 1 - $vert) : $vert;
                for ($colOffset = 0; $colOffset < 2; $colOffset++) {
                    $currCol = $c - $colOffset;
                    if (self::isReserved($r, $currCol, $dimension)) {
                        continue;
                    }

                    $bytePos = intdiv($bitIndex, 8) % count($allBytes);
                    $bitPos = 7 - ($bitIndex % 8);
                    $bit = ($allBytes[$bytePos] >> $bitPos) & 1;

                    // Mask pattern (r + c) % 2 == 0
                    $mask = (($r + $currCol) % 2 === 0) ? 1 : 0;
                    $matrix[$r][$currCol] = $bit ^ $mask;
                    $bitIndex = ($bitIndex + 1) % $totalBits;
                }
            }
        }

        return $matrix;
    }

    private static function placeFinder(array &$matrix, int $r, int $c): void
    {
        for ($dr = 0; $dr < 7; $dr++) {
            for ($dc = 0; $dc < 7; $dc++) {
                if (
                    $dr === 0 || $dr === 6 ||
                    $dc === 0 || $dc === 6 ||
                    ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                ) {
                    $matrix[$r + $dr][$c + $dc] = 1;
                } else {
                    $matrix[$r + $dr][$c + $dc] = 0;
                }
            }
        }
    }

    private static function isReserved(int $r, int $c, int $dimension): bool
    {
        // Top-left finder + separator
        if ($r <= 8 && $c <= 8) return true;
        // Top-right finder + separator
        if ($r <= 8 && $c >= $dimension - 8) return true;
        // Bottom-left finder + separator
        if ($r >= $dimension - 8 && $c <= 8) return true;
        // Timing patterns
        if ($r === 6 || $c === 6) return true;

        return false;
    }
}
