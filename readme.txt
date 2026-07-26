=== Freshet Feeds ===
Contributors: kristoffbertram
Tags: feeds, linkedin, youtube, rss, bluesky
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Developer-first external feeds — LinkedIn pages, YouTube channels, RSS and Bluesky — rendered with templates your theme owns.

== Description ==

Freshet Feeds displays external feeds inside WordPress the way developers wish every feed plugin worked: **your theme owns the markup**. No vendor styling panels, no iframes, no third-party JavaScript on your pages.

Every provider — LinkedIn company pages, RSS/Atom, YouTube channels, Bluesky profiles — normalizes into one item model and renders through one template chain, overridable WooCommerce-style from your theme.

**For developers**

* A loop API: `freshet_feeds( 'my-feed' )` returns normalized item objects; `freshet_feeds_render( 'my-feed' )` runs the full template chain.
* Template overrides: copy `item.php` into `{your-theme}/freshet-feeds/` and edit. An item hierarchy (`item-{feed-slug}.php` → `item-{provider}.php` → `item.php`) gives per-feed and per-type markup with clean fallbacks.
* Custom layouts by convention: drop `layout-carousel.php` in your theme, pass `carousel` — no registration.
* Hooks for everything: providers, template resolution, cache refresh events.

**Performance & privacy by design**

* Rendering never blocks on a remote API (the only exception is a brand-new feed's very first fetch): items are cached server-side, stale content is served instantly while a background refresh runs.
* Feed images are stored locally in your uploads dir (LinkedIn image URLs expire by design — hotlinking them breaks).
* Visitors' browsers never contact the source platforms. Content lives in your DOM: real SEO, no consent baggage.

**Providers**

* **LinkedIn (company page)** — via LinkedIn's official Community Management API with your own LinkedIn developer app; you connect as an admin of your own page.
* **RSS / Atom** — any feed URL; also covers Mastodon, subreddits, podcasts.
* **YouTube (channel)** — keyless public channel feed, no API key required.
* **Bluesky (profile)** — public API, no authentication.
* **Mock (fixture data)** — bundled sample posts, no credentials and no network calls, for building and styling templates before a live connection exists. Hidden and blocked in `production`.

The plugin is fully functional with unlimited feeds. A separately distributed version with a managed LinkedIn connection service and direct support is available from [freshet.studio](https://freshet.studio). Full developer documentation: [freshet.studio/docs](https://freshet.studio/docs).

== External services ==

This plugin talks to external services only to fetch the feed content you configure:

* **LinkedIn API** (api.linkedin.com, www.linkedin.com/oauth) — only for feeds you configure with your own LinkedIn developer app; OAuth happens as your page admin. [Terms](https://www.linkedin.com/legal/l/api-terms-of-use), [Privacy](https://www.linkedin.com/legal/privacy-policy).
* **YouTube feed** (www.youtube.com/feeds) — only for configured YouTube feeds. [Terms](https://www.youtube.com/t/terms), [Privacy](https://policies.google.com/privacy).
* **Bluesky public API** (public.api.bsky.app) — only for configured Bluesky feeds. [Terms](https://bsky.social/about/support/tos), [Privacy](https://bsky.social/about/support/privacy-policy).
* **Any RSS/Atom URL you configure** is fetched from your server on your cache schedule.

All fetching happens server-side on your cache schedule; site visitors never contact these services.

== Source code ==

The complete, unminified source — including the block editor JavaScript in `blocks/` and the build setup — ships with the plugin and is maintained publicly at [github.com/kristoffbertram/freshet-feeds](https://github.com/kristoffbertram/freshet-feeds). The compiled bundle in `build/` is generated from `blocks/` by running `npm install` and `npm run build` (uses @wordpress/scripts).

== Installation ==

1. Install and activate the plugin.
2. Go to **Feeds → Add feed**, pick a provider, and configure it (a feed URL, channel ID, handle — or a LinkedIn connection).
3. Add the **Feed** block to a page, or call `freshet_feeds_render( 'your-feed-slug' )` in your theme.

No credentials yet? Pick the **Mock (fixture data)** provider in step 2 and start building templates immediately — see the FAQ below.

== Frequently Asked Questions ==

= Can it show any LinkedIn page? =

No — and no plugin honestly can. LinkedIn's official API only allows a page **admin** to read their own page's posts. Services that show arbitrary pages scrape, which breaks routinely and violates LinkedIn's terms. Freshet Feeds uses the official API only.

= Can I build templates before my LinkedIn app is approved? =

Yes — that is what the **Mock (fixture data)** provider is for. Create a feed with it and you get a bundled set of realistic LinkedIn-shaped posts (single image, multi-image, shared article, text-only, an image-only post with no text, and a long multi-paragraph one) without any credentials, developer app, or network call. It is also the fastest way to evaluate the plugin before committing to the LinkedIn app process.

Mock posts run through exactly the same normalizer, item model and template chain as live LinkedIn posts, so everything you build against them works unchanged on real data — you switch the feed's provider and nothing else.

The provider is hidden in the provider dropdown on sites reporting a `production` environment type, and refuses to fetch there even for a feed created earlier, so fixture posts cannot end up on a live site.

= Why is there no X (Twitter) provider? =

X has no free read API and no RSS. We don't build on scraping. If that changes, we'll add it.

= How do I change the markup? =

Copy any template from the plugin's `templates/` folder into `{your-theme}/freshet-feeds/` and edit it. See the [template docs](https://freshet.studio/docs/templates).

= Does it slow my site down? =

No. Feeds are fetched in the background and served from a local cache; pages never wait on a remote API (except the one-time first fetch of a newly created feed). Images are served from your own uploads directory.

== Screenshots ==

1. A feed rendered by your own theme — LinkedIn, YouTube, RSS and Bluesky items normalized into one grid.

== Changelog ==

= 1.0.0 =
* Initial release: LinkedIn (Community Management API), RSS/Atom, YouTube, and Bluesky providers.
* Template override chain with item hierarchy, custom layouts, loop API.
* Server-rendered Feed block, stale-while-revalidate caching, local image storage.
* WP-CLI commands (`wp freshet-feeds fetch`, `wp freshet-feeds status`).
