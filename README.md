# WPFVariables

Define reusable variables once in the WordPress admin and print them anywhere with a shortcode.

Author: [Kushan Ekanayake](https://www.kushanekanayake.me/)
Requires: WordPress 5.8+, PHP 7.4+
License: GPL-2.0-or-later

## How it works

1. Open **WPFVariables** in the admin sidebar.
2. Add a variable, for example `phone`, pick a type and set its value.
3. Use `[wpfv-phone]` in posts, pages, widgets or any page builder.

Each variable is registered as its own shortcode on `init`, so WordPress core and page builders resolve it natively. The rendered output is cached like any other page content by your caching plugin.

## Field types

| Type | Output |
| --- | --- |
| Text | Escaped text |
| Textarea | Escaped text with line breaks |
| HTML | Visual editor content, rendered as HTML |
| Code | Raw code from a CodeMirror editor |
| Image | Full size `<img>` from the media library |
| URL | Escaped URL |
| Number | The number |
| Color | Hex color value |
| Yes / No | `1` or `0` |

Variable names accept lowercase letters, numbers, hyphens and underscores.

## Undefined shortcodes

If a `[wpfv-name]` has no matching variable:

- Administrators see a red link on the frontend that opens the settings page and creates it.
- Everyone else sees the shortcode as written.

## Live editor tracking

While editing, WPFVariables scans the editor for `[wpfv-...]` shortcodes and shows a small tooltip above each one:

- Defined: "Edit wpfv-name" with a preview of the value.
- Undefined: "Create wpfv-name".

Clicking a tooltip opens the settings page in a new tab with that variable ready to edit or create. The tooltip updates on the next scan after you save.

Supported places: the block editor, the classic editor, the site editor and the frontend builders of Divi, Elementor, Beaver Builder, Oxygen, Bricks, Brizy and WPBakery. Use the `wpfv_track_frontend` filter to enable it elsewhere:

```php
add_filter( 'wpfv_track_frontend', '__return_true' );
```

The scan interval (default 5 seconds, maximum 300) is set on the settings page. Set it to `0` to turn tracking off. The scan only contacts the server when a `[wpfv-...]` shortcode is found.

## Security

- The tracking endpoint accepts POST only, verifies a nonce, requires the `edit_posts` capability and whitelists all input.
- Settings are saved through the Settings API with a nonce and the `manage_options` capability, and every value is sanitized by type.
- HTML and Code values are stored unfiltered only for users with `unfiltered_html`. Everyone else is filtered with `wp_kses_post`.
- Data passed to JavaScript is JSON encoded with tag, ampersand and quote escaping.

## Structure

```
wpf-variables/
  wpf-variables.php        plugin header and constants
  uninstall.php
  includes/
    bootstrap.php
    core/                  autoloader, plugin wiring, options store
    fields/                field types
    frontend/              shortcodes
    admin/                 settings page
    tracking/              tracker loader and ajax endpoint
  assets/
    css/  js/  img/
```

## Uninstall

Deleting the plugin removes the `wpfv_variables` and `wpfv_settings` options.
