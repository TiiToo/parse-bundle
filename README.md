AdadgioParseBundle
====

Set set of helper to pass HTTP complex queries and convert them easily to query repositories

## Installation

Install with composer.

`composer require adadgio/parse-bundle`

Make the following change to your `AppKernel.php` file to the registered bundles array.

```
new Adadgio\ParseBundle\AdadgioParseBundle(),
```

### Requirements

This bundles required `FosUserBundle`, `AdadgioGearBundle` and `AdadgioDQLBundle`. But don't worry, thats automatic during a composer install.

### Import routing files

```yml
// add routing
_adadgio_parse:
    resource: "@AdadgioParseBundle/Resources/config/routing.yml"
```

### Create base classes

Create an installation entity like this.

```php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Adadgio\ParseBundle\Entity\ParseInstallation;

/**
 * @ORM\Table(name="my_installation")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\MyInstallationRepository")
 */
class MyInstallation extends ParseInstallation
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Up to you, but could be nice to link the installs with your users.
     * Dont forget to edit the relationshipon the user owning side as well.
     *
     * ORM\ManyToOne(targetEntity="AppBundle\Entity\User", inversedBy="installations")
     * ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     */
    private $user;
}
```

## Configuration reference

```yml
adadgio_parse:

    # the identity of your parse app
    # static api key auth
    application:
        client_id: LLiAYKuDz7YJLxvY23FK2meX4wG05td6UYxLNJr3
        client_key: oM0KDVkG6pA84HJSafxMocFTTyZzYmDM2EGlA37f
        client_config: # optional, equivalent to parse config feature
            host: test-host.com
            protocol: test
            version: 1.1

    # misc config for external links in api returns
    miscellaneous:
        protocol: http://
        hostname: adadgio.dev

    # parse bundle internal and database settings
    settings:
        #field_prefix: m_ # optional, default null
        #never_prefixed: [ "email", "password" ] # optional, default [ "email", "password" ]
        #serialization:
            #hidden: ["password", "salt"]  # optional, defaults, when results are send from parse
        #conversion:
            #reserved: ["id", "objectId", "password", "salt", "email", "username", "confirmation_token"]  # optional, default, when results are received from parse
        #installation:
            #class: ~ # optional, default AppBundle\Entity\MyParseInstallation (if you wish to extend the install class)

    # your entities mapping(s)
    mapping:
            product:
                class: AppBundle\Entity\Product
                fields:
                    id: ~ 
                    name: ~ 
```
