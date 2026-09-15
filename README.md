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

## 1. Require the bundle

```bash
composer require cors/adminer
```

The bundle ships Adminer 6 (`vrana/adminer ^6.1`), which is not affected by advisory
`PKSA-5hbx-ykrq-c4p8` (CVE-2026-25892). Projects that added this advisory to their
`config.audit.ignore` or `config.policy.advisories.ignore-id` list for earlier versions of the
bundle can remove the entry again.

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
bin/console debug:router | grep -i adminer      # cors_adminer + two asset routes
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
| `cors_adminer_static` | `/pimcore-studio/api/cors-adminer/adminer/static/{path}` |
| `cors_adminer_designs` | `/pimcore-studio/api/cors-adminer/adminer/designs/{path}` |

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

The asset routes serve URLs ending in `.css`, `.js` and `.svg`. If your webserver resolves those from
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
- **Adminer opens but is unstyled** — the webserver is resolving the asset routes'
  `.css`/`.js` URLs from disk; see *Webserver note*.

# Adminer integration

The bundle runs the source version of Adminer 6 from `vendor/vrana/adminer/adminer/index.php`;
the Composer package does not contain the compiled single-file `adminer.php`. The controller
provides the global `adminer_object()`, which returns an `Adminer\Plugins` instance with:

- `PimcoreAdminerPlugin`: connects with the credentials of Pimcore's Doctrine connection,
  submits Adminer's login form automatically and disables the version check and the web app
  manifest,
- `AdminerPlugins`: sticky table headers, readable Unix timestamps, remembered menu scroll
  position and table/column suggestions in *SQL command*,
- the upstream plugins `frames`, `tables-filter`, `dump-date`, `dump-json`, `dump-bz2`,
  `dump-zip`, `dump-xml`, `dump-alter` and, if the connection uses SSL, `login-ssl`,
- the Konya design as the only stylesheet.

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

## Product registration

The test application boots only with a registered Pimcore instance. The instance identifier
(`PIMCORE_INSTANCE_IDENTIFIER`) is committed in `.env`; put `PIMCORE_ENCRYPTION_SECRET` and
`PIMCORE_PRODUCT_KEY` of an instance registered at license.pimcore.com into your uncommitted
`.env.local`. CI gets the same three values from the repository secrets of the same name.

# Development

The repository doubles as a runnable Pimcore application on **Pimcore 2026 with Studio** (the
CORS bundle template: `Kernel.php`, `bin/console`, `config/`, `dev/`, `docker-compose.yaml`
including the shared `dev-compose` stack). The bundle itself is `src/`
(`CORSAdminerBundle::getPath()`, with `Resources/` inside); everything else at the repository
root only serves the dev harness, the Studio frontend build (`assets/`) or the CI, and
`.gitattributes` keeps it out of the distributed composer package.

```bash
docker compose up -d
docker compose exec -T php composer install
docker compose exec -T php vendor/bin/pimcore-install \
    --install-profile='App\InstallProfile\StudioInstallProfile' \
    --admin-username=admin --admin-password=admin --no-interaction
docker compose exec -T php bin/console cache:clear
docker compose exec -T php bin/console assets:install --symlink --relative public
```

Studio is then served at `https://cors-pimcore-adminer.dev.localhost/pimcore-studio/`, Adminer
under **System → Adminer**.

The install profile (`dev/InstallProfile/StudioInstallProfile.php`) declares the bundles and
infrastructure the harness needs (Studio backend/UI, generic data index + OpenSearch, Mercure,
Doctrine messenger transport). Connection defaults point at the dev-compose services and live
in `.env`; the Pimcore bundles are registered in `config/bundles.php`, the bundle under
development in `Kernel.php`.

The files marked "ZENTRAL VERWALTETE DATEI" (`docker-compose.yaml`, `bin/console`, most of
`config/`, `.github/workflows/static.yaml`) are synced from `cors-gmbh/shared-workflows-private`;
repository-specific additions belong in `compose.override.yaml`, `config/local/` or separate files.

Static checks run the same way as in CI. This repository is public, so the shared rule set comes
from the public `coreshop/test-setup` package instead of the private `cors/dev`:

```bash
vendor/bin/ecs check src
vendor/bin/phpstan analyse
vendor/bin/psalm
```

# License
MIT and therefore POCL compatible
