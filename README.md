CORS Adminer Bundle
--------

We love Adminer! And we had to bring it back. This bundle brings back Adminer into Pimcore 11.

Also shoutout to Blackbit (https://github.com/BlackbitDigitalCommerce) for the original implementation for Pimcore 11. We separeted this from the amazing DataDirector Bundle into a standalone bundle.

# Installation

```bash
composer require cors/adminer
````

Register bundle in config/bundles.php

CORS\AdminerBundle\CORSAdminerBundle::class => ['all' => true],

```
bin/console pimcore:bundle:install CORSAdminerBundle
```

# Configuration

- Open Pimcore
- Open Tools -> System Info & Tools -> Database Administration 
