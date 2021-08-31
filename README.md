# evepraisal-handler

Implements PSR-15, so it should work with most frameworks.

```
composer require marcusirgens/evepraisalhandler
```

```php
$handler = new \MarcusIrgens\EvepraisalHandler\Handler(
    $myFactoryImpl,
    $myFactoryImpl,
    $myFactoryImpl,
    $myFactoryImpl,
    $myClientImpl,
    "https://example.org/callback"
);

// eg. with Slim
$slim->post("/evepraisal", $handler);
```