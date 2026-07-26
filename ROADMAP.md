# Roadmap

## v1 (current)

- [x] Normalized Item model + ItemCollection shared across providers
- [x] Template override chain (child theme → parent theme → plugin) + loop API
- [x] MockProvider on real-shaped LinkedIn fixtures
- [x] LinkedIn bring-your-own-app connector (OAuth 2.0, Community Management API)
- [x] Image localization (LinkedIn URLs expire)
- [x] Cron prefetch + stale-while-revalidate, failure backoff
- [x] Gutenberg block (feed / layout / count)
- [x] Admin: feeds CRUD + LinkedIn connections
- [x] Paid tier = managed LinkedIn connection (`canUseProxy()`); feeds unlimited in every build
- [ ] Real-world LinkedIn E2E test (needs approved dev app + company page)
- [ ] i18n .pot generation

## v1.x

- Shortcode `[freshet-feeds feed="..."]`
- Remote license client validating against freshet.studio, overridable via the `freshet_feeds_license` filter
- Template-version status screen (headers already shipped)
- Outdated-override detection

## v2

- Vendor proxy service (api.freshet.studio) — the `ProxyLinkedInClient` stub and
  `freshet_feeds_enable_proxy` filter already define the seam; customers connect
  without creating a LinkedIn app
- ~~Provider #2/#3: RSS~~ Shipped: RSS/Atom, YouTube (keyless channel Atom), Bluesky (public API)
- Instagram/Facebook (Meta Graph — requires app review, like LinkedIn); X only if API pricing ever normalizes
- React admin polish, live template preview

## Decisions

- **CPT over custom table** for feed configs: <20 feeds even on pro, cascade
  delete free, no migrations.
- **Post meta over transients** for cached items: object caches may evict
  transients, which would break stale-while-revalidate.
- **Plain uploads dir over media library** for localized images: no library
  pollution, content-addressed by item id, pruned per fetch.
- **PSR-12 + WP security sniffs** rather than full WPCS: the codebase is PSR-4
  namespaced modern PHP; WPCS naming rules would fight it.
