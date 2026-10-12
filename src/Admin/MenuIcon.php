<?php

declare(strict_types=1);

namespace FreshetFeeds\Admin;

/**
 * The plugin's own mark, in the one shape WordPress will repaint: the
 * `$icon_url` add_menu_page() takes as a base64 SVG data URI (freshet-D252).
 *
 * wp-admin/menu-header.php gives that branch its own treatment — the URI
 * becomes a CSS background-image and the element is tagged `.svg` — and
 * wp-admin/js/svg-painter.js then decodes it and rewrites the markup to the
 * admin colour scheme's icon colour, once at rest and again on hover and for
 * the current menu. Three properties of the markup below are what make that
 * work, and none of them is decoration:
 *
 *  - exactly one `fill`, and no `stroke` anywhere. The painter rewrites
 *    `fill="..."` and nothing else, so a stroked mark would stay black — and
 *    black on the admin menu is invisible.
 *  - no `style` attribute. The painter replaces the whole of one with
 *    `style="fill:<colour>"`, so anything else in it is lost.
 *  - ASCII only. The painter round-trips through atob()/btoa(), which throw
 *    on any character above U+00FF, and a throw leaves the mark unpainted.
 *
 * The geometry is this plugin's own glyph from .wordpress-org/icon.svg with
 * the blue rounded square dropped — the menu paints its own background — and
 * the glyph fitted to a 17px box centred in the 20px core draws at, which is
 * where core's own dashicons sit optically.
 *
 * assets/menu-icon.svg carries the same markup byte for byte, for anything
 * that wants the file rather than the string, and tests/Unit/MenuIconTest.php asserts the two
 * agree — two copies of one drawing is exactly the pair that drifts.
 */
final class MenuIcon
{
    /** Kept in step with assets/menu-icon.svg — see tests/Unit/MenuIconTest.php. */
    // phpcs:ignore Generic.Files.LineLength.TooLong -- one drawing, one line; wrapping it would stop it diffing against the file.
    public const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path fill="#000" d="M1.5 7.17C8.87 7.17 12.8 11.1 12.8 18.48H9.98C9.98 12.66 7.31 10 1.5 10ZM1.5 1.52C12.67 1.52 18.5 7.34 18.5 18.48H15.67C15.67 8.9 11.11 4.35 1.5 4.35ZM4.33 13.77C5.37 13.77 6.21 14.61 6.21 15.65C6.21 16.69 5.37 17.53 4.33 17.53C3.29 17.53 2.44 16.69 2.44 15.65C2.44 14.61 3.29 13.77 4.33 13.77Z"/></svg>';

    /**
     * The mark as add_menu_page() wants its sixth argument.
     */
    public static function dataUri(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::SVG);
    }
}
