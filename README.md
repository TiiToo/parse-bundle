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

```yml
// add routing
_adadgio_parse:
    resource: "@AdadgioParseBundle/Resources/config/routing.yml"
```
