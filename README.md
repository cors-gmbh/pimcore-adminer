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

## Build the Studio assets

1. Change into the bundle's `assets` directory and install the dependencies:
   ```bash
   cd <bundle-root>/assets
   npm install
   ```
2. Build the Studio bundle (outputs to `src/Resources/public/studio`):
   ```bash
   npm run build
   ```
3. Re-install the bundle assets in your Pimcore project if needed:
   ```bash
   bin/console assets:install --symlink --relative
   ```

During development you can run `npm run dev` inside the `assets` directory to get an incremental build with file watching.

# License
MIT and therefore POCL compatible
