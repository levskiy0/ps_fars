# FARS Image Helpers Module

This module integrates the shop with the remote [FARS](https://github.com/levskiy0/FARS) image resize service and exposes Smarty helpers for building responsive markup.

## Installation

1. Copy the `fars` directory into `<prestashop_root>/modules/` (or deploy via your preferred workflow).
2. Install and enable **FARS** from *Back Office → Modules → Module Manager*.
3. Open the module configuration screen and set the **Service URL** to the base endpoint of your FARS server (defaults to `http://127.0.0.1:9090`).
4. Clear Smarty cache if template caching is enabled so the new functions are picked up.

## Configuration

The module stores the endpoint in the `FARS_IMAGE_SERVICE` configuration key. The default value can be overridden in the BO form or via CLI:

```bash
php bin/console prestashop:config:set FARS_IMAGE_SERVICE https://fars.example.com
```

If your content references assets from additional internal domains or CDNs, add them (one per line or comma-separated) to the **Additional allowed domains** textarea in the module settings. These hosts will be treated as local when `{fars_smart_content}` rewrites `<img>` tags.

## Available Smarty helpers

### `{fars_picture}`
Outputs a `<picture>` element with AVIF, WebP, and JPEG fallbacks, backed by FARS-generated URLs.

```smarty
{fars_picture
  url='/img/p/1/2/12-large_default.jpg'
  w=400
  h=400
  class='product-cover'
  alt=$product.name
  fallbacks=[
    ['media' => '(max-width: 480px)', 'w' => 240, 'h' => 240],
    ['media' => '(min-width: 1200px)', 'w' => 600, 'h' => 600]
  ]
}
```

### `{fars_image}`
Renders only the `<img>` tag (no `<picture>` wrapper) using the same parameters as `{fars_picture}`.

```smarty
{fars_image url='/img/cms/logo.png' w=200 h=30 alt='Brand logo'}
```

### `{fars_url}`
Returns the resize URL for a given source path.

```smarty
{assign var=heroSrc value={fars_url src='/img/cms/hero.jpg' w=1440 h=720 format='webp'}}
```

### `{fars_product_url}`
Builds a resize URL for a product cover or a specific image id.

```smarty
<img src="{fars_product_url product=$product w=512 h=512 format='avif'}" alt="{$product.name}">
```

### `{fars_smart_content}`
Scans an HTML fragment and replaces standalone local `<img>` tags (relative URLs or same-domain absolute URLs) with responsive `<picture>` markup backed by FARS URLs, respecting any declared width/height. You can optionally clamp the width and control whether `loading="lazy"` is enforced:

```smarty
{$cms_block.content|fars_smart_content}
{$product.description|fars_smart_content:1000:true} {* max width 1000px, add loading="lazy" *}
{$product.description|fars_smart_content:null:null:$heroFallbacks} {* pass fallback breakpoints from a Smarty variable *}
{fars_smart_content html=$product.description max_width=1200 lazy=true fallbacks=[
  ['media' => '(max-width: 768px)', 'w' => 480, 'h' => 320],
  ['media' => '(min-width: 1200px)', 'w' => 1440, 'h' => 810]
]}
```

## Internal templates

- `views/helper.tpl` registers `{renderRemotePicture}`, which performs the actual markup generation and can be included manually in custom templates if needed.
- `views/picture.tpl` is the render template used by `{fars_picture}` and `{fars_image}`.

## Notes

- Dimensions can be integers or strings (`'300px'`, `'300'`); the module extracts numeric values. Missing width/height are preserved as `xNN` or `NNx` so that FARS can scale proportionally.
- Breakpoint fallbacks wider than the base image are skipped automatically so smaller assets do not upscale unnecessarily.
- External domains and `data:` URLs are ignored by `{fars_smart_content}`.
- Ensure the FARS server is reachable by the storefront to avoid broken images.
