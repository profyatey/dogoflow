# DogoFlow CSS build

The site no longer runs the Tailwind Play CDN script in the browser. Styles are compiled once into
`public_html/assets/css/dogoflow.css`, which every page loads through `partials/tailwind.php`.

## When to rebuild
After you add or change Tailwind classes in any PHP file, or edit `tailwind-input.css` / `tailwind.config.js`.

## How (on your computer, needs Node.js)
    cd build
    npm install          # first time only
    npm run build:css

Then upload `public_html/assets/css/dogoflow.css`. The page URL carries the file's modified time, so browsers pick up the new file automatically.

Do not upload `build/` or `node_modules/` to the web server.
