CORS Adminer Bundle
--------

We love Adminer! And we had to bring it back. This bundle brings back Adminer into Pimcore 12.

Also shoutout to Blackbit (https://github.com/BlackbitDigitalCommerce) for the original implementation for Pimcore 12. We separeted this from the amazing DataDirector Bundle into a standalone bundle.

# Installation

Requires Pimcore 12.3 or Pimcore 2026.x, PHP 8.3+. The classic admin UI bundle is optional —
the bundle adds its entry to whichever UI is installed.

## 1. `composer.json` — allow the Adminer advisory, then require the bundle

Composer refuses to install `vrana/adminer` 4.17 because of advisory
`PKSA-5hbx-ykrq-c4p8` (CVE-2026-25892, a DoS in `?script=version`, affects `>=4.6.2,<5.4.2`):

```
- Root composer.json requires vrana/adminer ^4.17, found vrana/adminer[v4.17.0, v4.17.1]
  but these were not loaded, because they are affected by security advisories
```

The route is admin-only (see *Access control* below), so the endpoint is not reachable
without a Pimcore admin session. To install, add the advisory to the ignore list in your
project's `composer.json`:

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

## 3. `config/packages/security.yaml` — protect the route (Studio-only installations)

**Skip this step if pimcore/admin-ui-classic-bundle is installed** — its `pimcore_admin`
firewall already covers `/admin`.

Pimcore 2026 dropped the classic admin, and with it the firewall on `/admin`. The bundle's
controller refuses anyone who is not a Pimcore admin on its own, but add the firewall so
Symfony's access control applies before the controller runs:

```yaml
security:
    firewalls:
        # … after the pimcore_studio firewall, before any request_matcher firewalls
        cors_adminer:
            pattern: ^/admin/CORSAdminerBundle
            provider: pimcore_admin        # Pimcore\Security\User\UserProvider
            context: pimcore_admin         # reuse the session token the Studio login writes
            stateless: false
            user_checker: Pimcore\Security\User\UserChecker

    access_control:
        # … before any broader ^/admin rule
        - { path: ^/admin/CORSAdminerBundle, roles: ROLE_PIMCORE_ADMIN }
```

The firewall carries no authenticator on purpose: `context: pimcore_admin` restores the token
Studio wrote at login, which is what authenticates the iframe request Studio makes for the
widget. Anonymous requests get 401.

If your `providers:` block has no `pimcore_admin` entry, add one:

```yaml
security:
    providers:
        pimcore_admin:
            id: Pimcore\Security\User\UserProvider
```

## 4. Publish the assets — in this order

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

## 5. Verify

```bash
bin/console debug:router | grep -i adminer      # cors_adminer + two proxy routes
curl -sk -o /dev/null -w '%{http_code}\n' https://<host>/admin/CORSAdminerBundle/adminer
                                                # 401/403 when not logged in — never 200
curl -sk -o /dev/null -w '%{http_code}\n' \
  https://<host>/bundles/corsadminer/studio/<build-id>/static/js/remoteEntry.js   # 200
```

Then open Studio and pick **System → Adminer**; in the classic admin it is
**Tools → System Info & Tools → Database Administration**.

# Access control

Adminer connects with the credentials of the Pimcore database connection and its own login
always succeeds, so the route decides who gets in: it serves Pimcore **admin** users only and
answers everyone else with 403. The check runs inside the controller, because the host
project's firewall cannot be relied upon — Studio-only installations have no firewall covering
`/admin` at all, which would otherwise leave the route open to anonymous requests.

The user is taken from the security token, or from the `pimcore_admin` session context that
both the classic admin and Studio write on login, which is what makes the Studio widget's
iframe request work.

## Troubleshooting

- **401/403 inside the Studio widget although you are logged in** — your Studio firewall uses
  a security context other than `pimcore_admin`. Check `context:` in
  `%pimcore_studio_backend.firewall_settings%` and use the same value for the `cors_adminer`
  firewall.
- **No Adminer entry in Studio** — the plugin assets are not reachable. Request
  `/bundles/corsadminer/studio/<build-id>/static/js/remoteEntry.js`; a 404 means step 4 ran in
  the wrong order, or the webserver cannot follow the asset symlink.
- **Adminer opens but the page is unstyled** — the `default.css` proxy route
  (`/admin/CORSAdminerBundle/adminer/static/...`) is being blocked; it is behind the same
  admin check as the main route.

# Pimcore Studio

This bundle ships with a Pimcore Studio plugin so that Adminer is available in the new Studio interface.

## Studio assets

The Studio plugin ships as a build archive in `src/Resources/build-dist/build-<id>.zip`;
Pimcore's `BuildArchiveExtractor` unpacks it into `src/Resources/public/studio` at cache
warmup, so a project needs no npm. The build id is a hash of the frontend sources, and the
synced frontend-build workflow refreshes the archive on every push to a version branch.

The npm version of `@pimcore/studio-ui-bundle` in `assets/package.json` has to match the
composer-installed PHP bundle of the target installation; the shared module federation
singletons break otherwise. The archive in this branch is built against 2026.2.8.

To rebuild locally after changing the frontend code (Node 22):

```bash
cd <bundle-root>/assets
npm install
npm run build        # writes the archive; commit it together with the source change
```

During development `npm run dev-server` inside `assets` starts the rsbuild dev server.

# License
MIT and therefore POCL compatible
