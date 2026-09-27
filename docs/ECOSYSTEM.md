# Zurab Kostava Personal Ecosystem

This repository is the presentation layer and current integration host for Zurab Kostava's public identity, creative work, and web applications. Changes should preserve the site as one coherent experience while keeping each product independently maintainable.

## Product domains

| Domain | WordPress surface | Responsibility |
| --- | --- | --- |
| Identity | Identity admin, About | Profile, skills, vitals, social links, personal narrative |
| Publishing | Posts, pages, categories, tags | English and Georgian editorial content |
| Works | Music, Books, Tools, Visual Hub, Welcome Music | Structured portfolio objects and their presentation |
| Applications | WordEvo, Reader, Book Engine, Encrolib, Instavery | Product-specific UI, storage, authentication, PWA and offline behavior |
| Platform | Language, routing, assets, SEO, analytics, indexing, security | Shared behavior and system boundaries |

## Target boundaries

### Theme

The theme owns templates, visual components, styles, progressive enhancement, and the public SPA shell. Theme files should not be the permanent owner of business data, database migrations, authentication, or general-purpose APIs.

### ZK Core

Reusable platform behavior should move gradually into a `zk-core` plugin or must-use plugin. It will own custom post types, metadata registration, migrations, translations, REST endpoints, analytics, indexing, and the shared admin navigation. Extraction must preserve option names, post types, meta keys, URLs, and rendered HTML until dedicated migrations are available.

### Applications

Each embedded application keeps an explicit boundary:

- a single WordPress page template or route entry;
- an asset directory with stable file-based versions;
- an explicit storage owner;
- documented authentication and privacy behavior;
- its own focused tests;
- no direct dependency on unrelated theme UI code.

## Platform modules

New shared PHP code belongs under `inc/platform/`. Feature code should move out of `functions.php` in behavior-preserving slices:

1. security and request boundaries;
2. asset loading and cache versions;
3. content types and metadata;
4. localization and routing;
5. SEO and structured data;
6. analytics and indexing;
7. admin screens and migrations.

`functions.php` should eventually become a small bootstrap that loads these modules.

## Localization model

English is the canonical content source. Georgian fields use the existing `_zk_*_ka` post and term metadata so current content remains compatible. All consumers must obtain the active language from one language service. PHP and JavaScript route handling must agree on:

- English root: `/`
- Georgian root: `/ka/`
- English document language: `en-US`
- Georgian document language: `ka-GE`
- canonical and `hreflang` pairs for every indexable route

Database repair and seed work runs as versioned migrations. Public page requests must remain read-only apart from deliberate analytics and user-owned application state.

### Language Center

`Language Center` is the administrative source of truth for enabled languages and translation coverage. English remains the source language and Georgian keeps its existing `_zk_*_ka` metadata. New languages use the same metadata convention, for example `_zk_title_de`, `_zk_excerpt_de`, and `_zk_content_de`.

New languages are created in a disabled state. Their content can be prepared and audited before they are enabled in the public switcher, alternate-language metadata, and sitemap. Language definitions include a code, locale, native name, and URL prefix. Language definitions are never deleted automatically, so disabling a language preserves its translations.

## Security rules

- No maintenance script may execute from a public browser request.
- Every privileged mutation requires capability and CSRF checks.
- Public endpoints require strict input limits, safe remote requests, and rate limits.
- A public endpoint must never accept an arbitrary server-side fetch target.
- Analytics updates must be bound to the visitor/session that created the row.
- Secrets belong in server configuration or protected WordPress settings, never committed source.
- Supabase anonymous keys are treated as public identifiers; Row Level Security is the real authorization boundary.

## Delivery workflow

1. Work from a clean Git state.
2. Make one bounded change set.
3. Run PHP syntax validation, JavaScript syntax checks, and all focused Node tests.
4. Verify English and Georgian direct loads plus SPA transitions.
5. Deploy to staging, clear the relevant page/object cache, and run the smoke matrix.
6. Promote to production only after staging passes; keep the previous deploy available for rollback.

## Current roadmap

### P0 — stability and security

- close public maintenance entry points;
- constrain remote fetch and analytics endpoints;
- protect administrative forms;
- fix SPA language semantics;
- replace per-request asset cache busting.

### P1 — content architecture

- extract content types, meta registration, and migrations;
- build a translation coverage screen;
- centralize translated labels and route generation;
- remove remaining database writes from public render paths.

### P2 — application boundaries

- replace global Reader progress with authenticated user/device state;
- document and verify Supabase RLS for WordEvo, Book Engine, and Instavery;
- isolate application service workers and cache scopes;
- give each app a stable release/version manifest.

### P3 — quality and operations

- add browser smoke tests for desktop and mobile;
- add accessibility, performance, SEO, and structured-data checks;
- document Porkbun deployment, backup, cache purge, and rollback;
- add a WordPress System Health panel for dependencies and endpoint status.
