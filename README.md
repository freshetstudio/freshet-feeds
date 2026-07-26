# Freshet Feeds

Developer-first external feeds for WordPress. LinkedIn company-page posts first; every provider feeds the same normalized item model and the same theme-overridable templates, so styling a feed is exactly like styling any other WordPress loop.

## Why

Feed plugins render fixed markup you style through *their* settings UI. This plugin inverts that: **your theme owns the markup**.

```php
// Anywhere in your theme:
foreach ( freshet_feeds( 'linkedin-main' ) as $item ) {
    printf(
        '<article><h3>%s</h3><time>%s</time><p>%s</p></article>',
        esc_html( $item->title( 'Untitled' ) ),
        esc_html( $item->date() ),
        esc_html( $item->excerpt( 30 ) )
    );
}

// Or render through the template chain:
freshet_feeds_render( 'linkedin-main', [ 'layout' => 'grid' ] );
```

## Templating

WooCommerce-style overrides. Copy any file from `templates/` into `{your-theme}/freshet-feeds/` and edit:

| Template | Overrides |
|---|---|
| `item.php` | One card — the file you'll override 90% of the time |
| `layout-grid.php`, `layout-list.php` | The loop structure |
| `feed.php` | Outer wrapper |
| `empty.php` | No-items state (error details shown to admins only) |

Custom layouts: add `{your-theme}/freshet-feeds/layout-carousel.php` and pass `layout => 'carousel'` — no plugin changes needed.

Every item exposes: `title($fallback)`, `date($format)`, `datetime()`, `content()`, `excerpt($words)`, `url()`, `hasImage()`, `image()`, `imageTag($attrs)`, `author()`, and `raw()` (the untouched provider payload). Getters return raw values — escape in your templates.

## Feeds & providers

Manage feeds under **Feeds** in wp-admin. v1 providers:

- **LinkedIn (company page)** — bring-your-own LinkedIn developer app with Community Management API access; OAuth connect under Feeds → LinkedIn connections. Posts are fetched via cron (stale-while-revalidate — pages never block on LinkedIn) and images are copied locally because LinkedIn image URLs expire.
- **RSS / Atom** — any feed URL; also covers Mastodon, subreddits and podcasts.
- **YouTube** — channel or playlist uploads via the Atom feed; no API key.
- **Bluesky** — public author feed; no credentials.
- **Mock (fixture data)** — realistic LinkedIn-shaped data with zero credentials, for building and styling templates before a live connection exists. See below.

Third-party providers plug in via the `freshet_feeds_register_providers` action.

### Mock provider — build before you have credentials

A LinkedIn developer app takes days to get through review, and evaluating the
plugin shouldn't require one at all. Create a feed with **Mock (fixture data)**
and you have items to build against in seconds:

1. **Feeds → Add feed**, set a name and slug, pick *Mock (fixture data)*.
2. Save. The feed fills immediately from `data/fixtures/linkedin-posts.json` — no
   credentials, no network call.
3. Build your `item.php` override, then render with `freshet_feeds_render( 'your-slug' )`.

**Anything built against it works unchanged on live data.** The fixture is a real
`GET /rest/posts` payload shape (LinkedIn-Version 202506) and runs through the same
`PostNormalizer` the live client uses — same `Item` objects, same template chain.
Going live means changing the feed's provider to *LinkedIn (company page)* and
picking a connection; templates don't change.

The fixture deliberately covers the cases that break markup: single image,
multi-image, shared article (the only kind with a `title`), text-only, an
image-only post with **empty commentary**, a **long multi-paragraph** one, and a
post whose image URN doesn't resolve. It holds more posts than the default feed
count, so a feed set above 5 items is genuinely exercised. Copy the file, edit it,
and point `MockProvider` at your own copy if you want to test a specific shape.

**It cannot run in production.** On a site reporting `wp_get_environment_type()`
=== `'production'` the provider is hidden in the dropdown *and*
`MockProvider::fetch()` throws — so a feed created while the site was still
`staging` stops serving fixture posts the moment the environment flips, rather
than quietly presenting them as the site owner's content.

Feeds are unlimited in every build. A separately distributed build adds a
managed LinkedIn connection — you use our approved app instead of registering
your own — plus direct support. Not yet on sale; see freshet.studio.

## Development

After cloning, arm the content guard once — `core.hooksPath` lives in
`.git/config` and so is never cloned (`npm install` does this for you):

```bash
bash .freshet/install-hooks.sh
```

The same check runs in CI on every push, where it cannot be skipped. See
`.freshet/README.md`.

```bash
composer install          # PHP deps + autoloader (required to activate)
composer test             # unit tests (Brain Monkey, no WP install needed)
composer lint             # phpcs

npm install
npm run build             # build the Gutenberg block into build/
npm run start             # block dev watch
```

Local WordPress via Herd: symlink this directory into a site's `wp-content/plugins/` and activate. For the LinkedIn OAuth flow the site must be HTTPS (`herd secure`) and the redirect URI shown on the Feeds screen must be registered in your LinkedIn app.

Pipeline smoke test:

```bash
wp freshet-feeds fetch <feed-slug> --force
wp freshet-feeds status
```
