CORS Adminer Bundle
--------

We love Adminer! And we had to bring it back. This bundle brings Adminer into Pimcore Studio.

Also shoutout to Blackbit (https://github.com/BlackbitDigitalCommerce) for the original implementation for Pimcore 12. We separeted this from the amazing DataDirector Bundle into a standalone bundle.

> **Branch 2026.x is Pimcore 2026.x and Pimcore Studio only.** The classic admin UI was
> removed from Pimcore 2026, so this branch ships no ExtJS integration. For Pimcore 11 use
> branch `1.x`, for Pimcore 12.3 with the classic admin use `2.x`.

# Installation

Requires Pimcore 2026.1 or newer with `pimcore/studio-ui-bundle` (a hard dependency — the
bundle is a Studio plugin).

## 1. `composer.json` — allow the Adminer advisory, then require the bundle

Composer refuses to install `vrana/adminer` 4.17 because of advisory
`PKSA-5hbx-ykrq-c4p8` (CVE-2026-25892, a DoS in `?script=version`, affects `>=4.6.2,<5.4.2`):

```
- Root composer.json requires vrana/adminer ^4.17, found vrana/adminer[v4.17.0, v4.17.1]
  but these were not loaded, because they are affected by security advisories
```

Adminer is only reachable for logged-in Pimcore admins (see *Access control*), so the
endpoint is not exposed. To install, add the advisory to the ignore list in your project's
`composer.json`:

```json
{
    "config": {
        "audit": {
            "ignore": ["PKSA-5hbx-ykrq-c4p8"]
        }
    }
}
```

Projects that already keep an ignore list under `config.policy.advisories.ignore-id` can add
the id there instead. Then:

```bash
composer require cors/adminer
```

## 2. `config/bundles.php` — register the bundle

```php
return [
    // …
    \CORS\Bundle\AdminerBundle\CORSAdminerBundle::class => ['all' => true],
];
```

Restrict it to the environments that should have database access, e.g.
`['dev' => true, 'staging' => true]`.

## 3. Publish the assets — in this order

```bash
bin/console cache:clear        # warmup unpacks the Studio build archive into the bundle
bin/console assets:install     # publishes it to public/bundles/corsadminer
```

The order matters: `BuildArchiveExtractor` unpacks `src/Resources/build-dist/build-<id>.zip`
into the bundle's `Resources/public/studio` during cache warmup, so warmup has to run before
the assets are published. In a Docker build both commands must run at image build time —
the extractor writes into `vendor/`, which is usually read-only at runtime. If you publish
with `--symlink`, the webserver container needs the same `vendor/` mount as PHP, otherwise
the Studio plugin's `exposeRemote.js` 404s and the plugin is silently absent.

**No security configuration is needed** — see *Access control*.

## 4. Verify

```bash
bin/console debug:router | grep -i adminer      # cors_adminer + two proxy routes
curl -sk -o /dev/null -w '%{http_code}\n' \
  https://<host>/pimcore-studio/api/cors-adminer/adminer          # 401/403 when logged out
curl -sk -o /dev/null -w '%{http_code}\n' \
  https://<host>/bundles/corsadminer/studio/<build-id>/static/js/remoteEntry.js   # 200
```

Then open Studio and pick **System → Adminer**.

# Access control

The routes live under the Studio API prefix:

| Route | Path |
| --- | --- |
| `cors_adminer` | `/pimcore-studio/api/cors-adminer/adminer` |
| asset proxy | `/pimcore-studio/api/cors-adminer/adminer/static/{path}` |
| externals proxy | `/pimcore-studio/api/cors-adminer/externals/{path}` |

That prefix is what every Studio installation already protects, so the bundle needs no
firewall or `access_control` of its own:

- the `pimcore_studio` firewall (`^/pimcore-studio/api(/.*)?$`) authenticates the request from
  the session token the Studio login writes — which is what makes the widget's iframe work;
- the `- { path: ^/pimcore-studio/api, roles: ROLE_PIMCORE_USER }` access-control rule of a
  standard Studio setup keeps anonymous requests out.

On top of that the controller itself serves **Pimcore admin users only** and answers everyone
else with 403 — Adminer connects with the credentials of the Pimcore database connection and
its own login always succeeds, so the route must establish who is calling and may not depend
on the host project's configuration. The user is read from the security token, falling back to
the `pimcore_admin` session context.

If you change `pimcore_studio_backend.url_prefix`, adjust
`DefaultController::ROUTE_PREFIX` expectations accordingly — the routes are registered under
the Studio default prefix, and only the controller's own admin check would still apply.

## Webserver note

The asset proxy serves URLs ending in `.css` and `.js`. If your webserver resolves those from
disk before passing the request to PHP (the classic Pimcore nginx recipe does, usually with an
exception for `/admin`), add an exception for the Adminer path, e.g.:

```nginx
location ~* ^/pimcore-studio/api/cors-adminer {
    rewrite .* /index.php$is_args$args last;
}
```

## Rate limiting

Studio's `RateLimitSubscriber` applies to everything under the API prefix: 500 requests per
minute per client IP, shared with the rest of Studio. A page view in Adminer costs a handful
of requests, so this is only worth knowing if you script against it.

## Troubleshooting

- **401 on the widget although you are logged in** — your project changed
  `pimcore_studio_backend.url_prefix`, so the routes fall outside the `pimcore_studio`
  firewall. Either keep the default prefix or add a firewall for
  `^/pimcore-studio/api/cors-adminer` with `context: pimcore_admin`.
- **403 with "Adminer is available to Pimcore admin users only"** — the logged-in user is not
  a Pimcore admin. That is by design.
- **No Adminer entry in Studio** — the plugin assets are not reachable. Request
  `/bundles/corsadminer/studio/<build-id>/static/js/remoteEntry.js`; a 404 means the publish
  step ran in the wrong order, or the webserver cannot follow the asset symlink.
- **Adminer opens but is unstyled** — the webserver is resolving the proxy's `.css`/`.js`
  URLs from disk; see *Webserver note*.

# Pimcore Studio

This bundle ships with a Pimcore Studio plugin so that Adminer is available in the new Studio interface.

## Studio assets

The Studio plugin ships as a build archive in `src/Resources/build-dist/build-<id>.zip`;
Pimcore's `BuildArchiveExtractor` unpacks it into `src/Resources/public/studio` at cache
warmup, so a project needs no npm. The build id is a hash of the frontend sources, and the
synced frontend-build workflow refreshes the archive on every push to a version branch.

The npm version of `@pimcore/studio-ui-bundle` in `assets/package.json` has to match the
composer-installed PHP bundle of the target installation; the shared module federation
singletons break otherwise. The archive on this branch is built against 2026.2.8.

To rebuild locally after changing the frontend code (Node 22):

```bash
cd <bundle-root>/assets
npm install
npm run build        # writes the archive; commit it together with the source change
```

During development `npm run dev-server` inside `assets` starts the rsbuild dev server.

# License
MIT and therefore POCL compatible
