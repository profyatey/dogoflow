<?php
declare(strict_types=1);

/**
 * Generates a unique, stable visual for stores that have no banner or logo yet.
 * The same store always gets the same artwork, derived from its slug/name and seller code.
 * Output is inline SVG delivered as a data URI, so no files are written to disk.
 */
final class StoreArt
{
    /** Wide cover artwork (used as a banner or a store card cover). */
    public static function cover(string $seed, string $name): string
    {
        $h = self::hash($seed . '|' . $name);
        $hue = $h % 360;
        $hue2 = ($hue + 35 + ($h >> 8) % 60) % 360;
        $bg = "hsl({$hue},70%,95%)";
        $c1 = "hsl({$hue},68%,62%)";
        $c2 = "hsl({$hue2},72%,70%)";
        $c3 = "hsl(" . (($hue + 200) % 360) . ",60%,45%)";

        $shapes = '';
        switch (($h >> 4) % 4) {
            case 0: // overlapping circles
                for ($i = 0; $i < 7; $i++) {
                    $x = 60 + (($h >> ($i + 3)) % 680);
                    $y = 30 + (($h >> ($i + 7)) % 260);
                    $r = 40 + (($h >> ($i + 1)) % 110);
                    $fill = [$c1, $c2, $c3][$i % 3];
                    $shapes .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$r}\" fill=\"{$fill}\" opacity=\".35\"/>";
                }
                break;
            case 1: // diagonal bands
                for ($i = 0; $i < 9; $i++) {
                    $x = -200 + $i * 110;
                    $fill = [$c1, $c2, $c3][$i % 3];
                    $shapes .= "<polygon points=\"{$x},400 " . ($x + 60) . ",0 " . ($x + 120) . ",0 " . ($x + 60) . ",400\" fill=\"{$fill}\" opacity=\".3\"/>";
                }
                break;
            case 2: // soft waves
                for ($i = 0; $i < 5; $i++) {
                    $y = 40 + $i * 60;
                    $fill = [$c1, $c2, $c3][$i % 3];
                    $shapes .= "<path d=\"M0 {$y} Q 200 " . ($y - 50 + ($h % 40)) . " 400 {$y} T 800 {$y} V 400 H 0 Z\" fill=\"{$fill}\" opacity=\".22\"/>";
                }
                break;
            default: // quarter arcs in the corners
                $shapes .= "<circle cx=\"800\" cy=\"0\" r=\"280\" fill=\"{$c1}\" opacity=\".35\"/>";
                $shapes .= "<circle cx=\"0\" cy=\"320\" r=\"200\" fill=\"{$c2}\" opacity=\".35\"/>";
                $shapes .= "<circle cx=\"620\" cy=\"260\" r=\"90\" fill=\"{$c3}\" opacity=\".25\"/>";
        }

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 800 320\" preserveAspectRatio=\"xMidYMid slice\">"
            . "<rect width=\"800\" height=\"320\" fill=\"{$bg}\"/>{$shapes}</svg>";

        return self::dataUri($svg);
    }

    /** Square avatar with the store's initials on a colour from its own palette. */
    public static function avatar(string $seed, string $name): string
    {
        $h = self::hash($seed . '|' . $name);
        $hue = $h % 360;
        $initials = self::initials($name);
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 200 200\">"
            . "<defs><linearGradient id=\"g\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\">"
            . "<stop offset=\"0\" stop-color=\"hsl({$hue},70%,52%)\"/>"
            . "<stop offset=\"1\" stop-color=\"hsl(" . (($hue + 30) % 360) . ",72%,38%)\"/>"
            . "</linearGradient></defs>"
            . "<rect width=\"200\" height=\"200\" rx=\"44\" fill=\"url(#g)\"/>"
            . "<text x=\"100\" y=\"124\" text-anchor=\"middle\" font-family=\"Arial,Helvetica,sans-serif\" font-size=\"72\" font-weight=\"700\" fill=\"#fff\">{$initials}</text>"
            . "</svg>";

        return self::dataUri($svg);
    }

    /** Up to two letters from the store name, e.g. "My shop" -> "MS", "SHOPPY" -> "SH". */
    public static function initials(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) >= 2) {
            $letters = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            $letters = strtoupper(substr($words[0] ?? 'D', 0, 2));
        }

        return htmlspecialchars($letters, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function hash(string $value): int
    {
        return (int) hexdec(substr(md5($value), 0, 8));
    }

    private static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
