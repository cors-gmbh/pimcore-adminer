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
    CORS\AdminerBundle\CORSAdminerBundle::class => ['all' => true],
  ```
3. Run the installer
  ```
  bin/console pimcore:bundle:install CORSAdminerBundle
  ```

# Configuration

- Open Pimcore
- Open Tools -> System Info & Tools -> Database Administration 

# License
MIT and therefore POCL compatible
