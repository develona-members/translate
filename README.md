To install, add to the root of your composer.json file:

 ```
    "repositories": [
        {
          "type": "vcs",
          "url": "https://github.com/develona-members/translate.git"
        }
    ],
```

Then add to the `require` section:

```
, "develona-members/translate": "dev-main"
```

Then run:

```
composer update develona-members/translate
```

To publish config file and views, run:

```
php artisan vendor:publish --provider="Develona\Translate\Providers\TranslateServiceProvider"
```

Make sure the `texts_db` variable in the translate config file matches a valid database id in your Laravel installation.

Then run:

```
php artisan migrate
```

In order to create the translation tables.






