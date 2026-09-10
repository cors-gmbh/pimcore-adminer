CORS Adminer Bundle
--------

We love Adminer! And we had to bring it back. This bundle brings back Adminer into Pimcore 12.

Also shoutout to Blackbit (https://github.com/BlackbitDigitalCommerce) for the original implementation for Pimcore 12. We separeted this from the amazing DataDirector Bundle into a standalone bundle.

# Installation

1. Install the Extension
  ```bash
  composer require cors/adminer
  ````
2. Register bundle in config/bundles.php
  ```
    CORS\Bundle\AdminerBundle\CORSAdminerBundle::class => ['all' => true],
  ```

# Configuration

- Open Pimcore
- Open Tools -> System Info & Tools -> Database Administration 

# Pimcore Studio

This bundle ships with a Pimcore Studio plugin so that Adminer is available in the new Studio interface.

## Studio assets

The Studio plugin ships as a build archive in `src/Resources/build-dist/build-<id>.zip`;
Pimcore's `BuildArchiveExtractor` unpacks it into `src/Resources/public/studio` at cache
warmup, so a project needs no npm. The build id is a hash of the frontend sources, and the
synced frontend-build workflow refreshes the archive on every push to a version branch.

To rebuild locally after changing the frontend code:

```bash
cd <bundle-root>/assets
npm install
npm run build        # writes the archive; commit it together with the source change
```

During development `npm run dev-server` inside `assets` starts the rsbuild dev server.

# License
MIT and therefore POCL compatible
