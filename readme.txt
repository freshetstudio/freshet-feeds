=== Freshet Feeds ===
Contributors: kristoffbertram
Tags: feed, rss, youtube, bluesky, social-feed
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display an RSS feed, a YouTube feed or a Bluesky feed in WordPress through templates your theme owns — no iframes, no third-party JavaScript.

== Description ==

Freshet Feeds displays external feeds inside WordPress the way developers wish every feed plugin worked: **your theme owns the markup**. No vendor styling panels, no iframes, no third-party JavaScript on your pages.

Every provider — RSS/Atom, YouTube channels, Bluesky profiles — normalizes into one item model and renders through one template chain, overridable WooCommerce-style from your theme.

Use it where you would otherwise reach for a feed embed: an RSS feed block on a page, a YouTube channel feed, a social media feed from a Bluesky or Mastodon profile in a block widget area, a podcast feed's episode list. Items are rendered as your own markup, not an embed, and nothing is imported as posts.

**For developers**

* A loop API: `freshet_feeds( 'my-feed' )` returns normalized item objects; `freshet_feeds_render( 'my-feed' )` runs the full template chain.
* Template overrides: copy `item.php` into `{your-theme}/freshet-feeds/` and edit. An item hierarchy (`item-{feed-slug}.php` → `item-{provider}.php` → `item.php`) gives per-feed and per-type markup with clean fallbacks.
* Custom layouts by convention: drop `layout-carousel.php` in your theme, pass `carousel` — no registration.
* Hooks for everything: providers, template resolution, cache refresh events.

**Performance & privacy by design**

* Rendering never blocks on a remote API (the only exception is a brand-new feed's very first fetch): items are cached server-side, stale content is served instantly while a background refresh runs.
* Feed images are stored locally in your uploads dir (some providers serve expiring signed image URLs — hotlinking them breaks).
* Visitors' browsers never contact the source platforms. Content lives in your DOM: real SEO, no consent baggage.

**Providers**

* **RSS / Atom** — any feed URL; also covers Mastodon, subreddits, podcasts.
* **YouTube (channel)** — keyless public channel feed, no API key required.
* **Bluesky (profile)** — public API, no authentication.
* **Mock (fixture data)** — bundled sample posts, no credentials and no network calls, for building and styling templates before a live connection exists. Hidden and blocked in `production`.

The plugin is fully functional with unlimited feeds. Full developer documentation: [freshet.studio/docs](https://freshet.studio/docs).

== External services ==

This plugin talks to external services only to fetch the feed content you configure:

* **YouTube feed** (www.youtube.com/feeds) — only for configured YouTube feeds. [Terms](https://www.youtube.com/t/terms), [Privacy](https://policies.google.com/privacy).
* **Bluesky public API** (public.api.bsky.app) — only for configured Bluesky feeds. [Terms](https://bsky.social/about/support/tos), [Privacy](https://bsky.social/about/support/privacy-policy).
* **Any RSS/Atom URL you configure** is fetched from your server on your cache schedule.

All fetching happens server-side on your cache schedule; site visitors never contact these services.

== Source code ==

The complete, unminified source — including the block editor JavaScript in `blocks/` and the build setup — ships with the plugin and is maintained publicly at [github.com/freshetstudio/freshet-feeds](https://github.com/freshetstudio/freshet-feeds). The compiled bundle in `build/` is generated from `blocks/` by running `npm install` and `npm run build` (uses @wordpress/scripts).

== Installation ==

1. Install and activate the plugin.
2. Go to **Feeds → Add feed**, pick a provider, and configure it (a feed URL, channel ID, or handle).
3. Add the **Feed** block to a page, or call `freshet_feeds_render( 'your-feed-slug' )` in your theme.

No credentials yet? Pick the **Mock (fixture data)** provider in step 2 and start building templates immediately — see the FAQ below.

== Frequently Asked Questions ==

= Can I build templates before I have a live feed set up? =

Yes — that is what the **Mock (fixture data)** provider is for. Create a feed with it and you get a bundled set of realistic sample posts (single image, multi-image, shared article, text-only, an image-only post with no text, and a long multi-paragraph one) without any credentials or network call. It is also the fastest way to evaluate the plugin.

Mock posts run through exactly the same normalizer, item model and template chain as live posts, so everything you build against them works unchanged on real data — you switch the feed's provider and nothing else.

The provider is hidden in the provider dropdown on sites reporting a `production` environment type, and refuses to fetch there even for a feed created earlier, so fixture posts cannot end up on a live site.

= Why is there no X (Twitter) provider? =

X has no free read API and no RSS. We don't build on scraping. If that changes, we'll add it.

= How do I change the markup? =

Copy any template from the plugin's `templates/` folder into `{your-theme}/freshet-feeds/` and edit it. See the [template docs](https://freshet.studio/docs/templates).

= Does it slow my site down? =

No. Feeds are fetched in the background and served from a local cache; pages never wait on a remote API (except the one-time first fetch of a newly created feed). Images are served from your own uploads directory.

= How do I display an RSS feed on a page? =

Go to **Feeds → Add feed**, pick the RSS / Atom provider and paste the feed URL. Then add the **Feed** block to the page — or to a block widget area such as a sidebar or footer — and pick the feed, a layout and an item count. In a theme template, `freshet_feeds_render( 'your-feed-slug' )` does the same. The items are rendered as HTML in your page from the plugin's templates or your theme's overrides; there is no iframe and no script from the source site.

= Can it embed a YouTube channel feed without an API key? =

Yes. The YouTube provider reads the public channel feed, so there is no API key and no quota; you enter the channel ID. The default item template shows each video as a thumbnail card that links to YouTube rather than embedding an iframe player, which keeps the page fast and keeps YouTube's scripts off it until the visitor clicks. If you want an inline player, copy `item-youtube.php` into your theme and change the markup.

= Does it show a Bluesky or Mastodon feed? =

Bluesky profiles are a provider of their own: enter the handle and the plugin reads the public API — no authentication. Reposts, replies and pins are left out, and embedded images keep their alt text. Mastodon has no dedicated provider because it does not need one: every Mastodon account exposes an RSS feed at `https://instance/@user.rss`, so use the RSS / Atom provider with that URL. The same goes for subreddits (append `/.rss`) and podcast feeds. Either way the posts render through your templates as a social media feed in your own markup, without the platform's embed script.

== Screenshots ==

1. An RSS feed rendered on the front end in the theme's own markup: thumbnails, dates, linked titles and excerpts in a grid, with no iframes and no vendor styling.
2. The Feed block selected in the block editor, with its settings panel choosing the feed, the layout and the number of items; themes can add custom layouts via `freshet-feeds/layout-{name}.php`.
3. The Feeds list in wp-admin, showing each feed's name, slug, provider, last fetch time and status, with the hint for rendering it in theme code or with the Feed block.
4. The edit screen for a feed: name, slug, provider, item count, and the field each provider needs, a feed URL for RSS / Atom, a channel ID for YouTube or a handle for Bluesky.

== Changelog ==

= 1.0.2 =
* New **Settings** tab on the Feeds screen with an opt-in to remove all plugin data (feeds, cached items and stored images) when the plugin is uninstalled.
* The wordpress.org package is trimmed to the runtime files it needs.
* Dropped the `Domain Path` header and the manual text-domain load; wordpress.org language packs load on their own.
* Listing: real screenshots, a live preview in WordPress Playground, and a readme written in the words people search for.

= 1.0.1 =
* First release published on WordPress.org.
* Fixed cached feed items being corrupted, and in some cases discarded entirely, when an item contained a quote or a non-ASCII character.

= 1.0.0 =
* Initial release: RSS/Atom, YouTube, and Bluesky providers.
* Template override chain with item hierarchy, custom layouts, loop API.
* Server-rendered Feed block, stale-while-revalidate caching, local image storage.
* WP-CLI commands (`wp freshet-feeds fetch`, `wp freshet-feeds status`).
