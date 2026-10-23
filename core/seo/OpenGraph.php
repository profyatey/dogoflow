<?php
declare(strict_types=1);

/** Centralized Open Graph / social preview metadata for public DogoFlow pages. */
function og_image_url(?string $image = null): string {
    $fallback = APP_URL . '/assets/images/og/dogoflow-og.png';
    if (!$image) return $fallback;
    $image = trim($image);
    if ($image === '') return $fallback;
    if (preg_match('#^https?://#i', $image)) return $image;
    $path = '/' . ltrim($image, '/');
    return APP_URL . $path;
}

function render_og_meta(string $title, string $description, string $url, ?string $image = null, string $type = 'website'): void {
    $title = e($title);
    $description = e($description);
    $canonical = e($url);
    $ogImage = e(og_image_url($image));
    echo "<meta name=\"description\" content=\"{$description}\">\n";
    echo "<link rel=\"canonical\" href=\"{$canonical}\">\n";
    echo "<meta property=\"og:type\" content=\"" . e($type) . "\">\n";
    echo "<meta property=\"og:title\" content=\"{$title}\">\n";
    echo "<meta property=\"og:description\" content=\"{$description}\">\n";
    echo "<meta property=\"og:url\" content=\"{$canonical}\">\n";
    echo "<meta property=\"og:image\" content=\"{$ogImage}\">\n";
    echo "<meta property=\"og:image:width\" content=\"1200\">\n";
    echo "<meta property=\"og:image:height\" content=\"630\">\n";
    echo "<meta property=\"og:image:alt\" content=\"DogoFlow — {$title}\">\n";
    echo "<meta property=\"og:site_name\" content=\"DogoFlow\">\n";
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$title}\">\n";
    echo "<meta name=\"twitter:description\" content=\"{$description}\">\n";
    echo "<meta name=\"twitter:image\" content=\"{$ogImage}\">\n";
}
