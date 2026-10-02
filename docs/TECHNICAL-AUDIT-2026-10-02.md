# Public site technical audit — 2 October 2026

Sources: Screaming Frog exports in `C:\Users\zurab\Desktop\issues_reports` from 1 October; Ahrefs project 9768646, crawl ending 1 October at 23:44; fresh anonymous HTTP checks on 2 October. Separate project applications were excluded from implementation and the sitemap crawl.

## Verified on the live site before this change

- 66 core sitemap URLs returned successfully with one H1 and a self-referencing canonical. No redirects, noindex pages or invalid JSON-LD were detected in this sample. Hreflang targets included in the sample referenced one another.
- HTML responses include CSP (`base-uri` and `frame-ancestors`), Referrer-Policy, X-Content-Type-Options and X-Frame-Options. Missing-header warnings for static files require hosting/CDN configuration; theme PHP cannot set headers on files served directly by the server.
- Both English and Georgian Paint responses took under one second during fresh checks. Ahrefs recorded a previous 19.55-second server response. A single historical spike does not establish an ongoing problem.
- Ahrefs' redirect chain is `http://www.zurabkostava.com/` → `https://www.zurabkostava.com/` → `https://zurabkostava.com/`. It has zero internal incoming links. Collapsing the chain requires host/edge configuration.
- The retired web-fonts Georgian font is no longer used by the active book template. Medium's 403 and Entrepreneur's 429 in the old export do not establish deleted destinations; keep these editorial links pending a human browser check.

## Changes in this batch

- Existing translated HTML routes missing a final slash now redirect with 301 to the same language and retain the query string. This covers future registered languages as well as Georgian. POST, preview, search, feed, REST, file and 404 requests are excluded. WordPress' language-stripping canonical redirect remains disabled.
- Blog cards in the shortcode and archive template use WordPress attachment output with intrinsic width/height and responsive image candidates. Existing classes and image effects remain intact.
- Book cover cards use the same attachment output. About's small favorite thumbnails declare their existing display dimensions and use asynchronous decoding.
- Breadcrumb home destinations use the current language registry for both their real href and SPA route.

## Validation and follow-up

PHP syntax checks passed for all three modified production files. Eight request-level regression cases passed, including Georgian and a registered German prefix in the fixture, query preservation, HEAD, and excluded requests. Live HTTP checks above describe the version before deployment; repeat the affected checks after WP Pusher and edge-cache refresh.

After deployment, run fresh Ahrefs/Screaming Frog crawls before comparing issue counts. Do not add artificial headings, shorten the full book HTML, delete useful external links or rewrite descriptions merely to erase advisory warnings. Editorial titles, descriptions, alt text and image compression remain with the site owner. App-specific audits remain deferred.

Deployment was verified on 2 October: missing-slash Georgian requests now reach their slash URL and preserve query strings; Georgian blog cards and About favorite thumbnails expose dimensions. The English blog/book responses initially remained in Batcache after the edge purge, while uncached query requests already exposed the new width/height and srcset markup. The cache reported a remaining lifetime below 90 seconds. Edge Cache confirmed its purge; a fresh Ahrefs crawl was started.
