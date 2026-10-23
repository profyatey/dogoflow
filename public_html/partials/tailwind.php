<?php
/**
 * Shared styles for every DogoFlow page.
 * Include once inside <head>:  require __DIR__ . '/partials/tailwind.php';  (adjust the relative path)
 *
 * The release includes a local baseline stylesheet at /assets/css/dogoflow.css.
 * The Tailwind runtime below is a compatibility fallback for hosts where the pinned
 * Tailwind CLI is not available during deployment. For the smallest production payload,
 * build the CSS locally with the pinned Tailwind version and keep the generated artifact.
 *
 * To update styles: edit build/tailwind-input.css or build/tailwind.config.js, then run
 *     npm run build:css
 */
$dfCssFile = dirname(__DIR__) . '/assets/css/dogoflow.css';
$dfCssVer  = is_file($dfCssFile) ? (string) filemtime($dfCssFile) : '1';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= url('/assets/css/dogoflow.css') ?>?v=<?= e($dfCssVer) ?>">
<script>
/* Runtime fallback for environments where the pinned Tailwind CLI is not available during deployment.
   The local stylesheet above remains the first-line dependency. */
window.tailwind = window.tailwind || {};
tailwind.config = {
  corePlugins: { container: false },
  theme: { extend: {
    colors: { brand: { DEFAULT:'#00A3FF', hover:'#0088EE', deep:'#0A1628', light:'#33B5FF', soft:'#E6F6FF' }, navy:'#0A1628', gold:'#F59E0B', canvas:'#FAFAFC', soft:'#F4F4F8', line:'#E5E7EB', edge:'#D8E4F0', frame:'#DCE6F0', heading:'#0B2545', mist:'#F3F7FB' },
    fontFamily: { sans:['"Plus Jakarta Sans"','system-ui','sans-serif'] },
    borderRadius: { 'df-sm':'10px','df-md':'18px','df-lg':'28px' },
    boxShadow: { soft:'0 2px 8px rgba(10,22,40,.06)', lift:'0 8px 24px rgba(10,22,40,.10)', panel:'0 8px 24px rgba(7,26,51,.055)' }
  }}
};
</script>
<script src="https://cdn.tailwindcss.com"></script>
