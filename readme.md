php-trans
============

This CodeSniffer extension is made to make the translator's job easier, when a huge php codebase have to be localized.
php-trans will take all the strings, and makes it easier to translate them.



Usage
-----

1) Execute the localizer on the designated file(s)

```
$ php ./vendor/bin/phpcs --standard=LocalizerStandard examples/toBeTranslated.php
```

2) Marked errors (not localized strings) are stored in `localize.json` (configurable).
Set the null values to something different than the key, which also must be the translated value. (localization key in the future)

3) Rerun and examinate the result:

```
$ php ./vendor/bin/phpcs --standard=LocalizerStandard examples/toBeTranslated.php --report=diff
```

4) Apply the selected changes

```
$ php ./vendor/bin/phpcbf --standard=LocalizerStandard examples/toBeTranslated.php
```

Configuration
-------------

Output file, and translator methods can be configured.
There are two translator methods: One for the basic strings, and one for sprintf (parameterized) stirngs.

Examples
--------

Basic string replacement:
```
echo "apple";
//=> 
echo _("APPLE");
```

Advanced string replacement:
```
echo "I ate ".$original." apples, but still have $left in the basket. Thats ". 
     ($left / $original * 100)."%";
//=>
echo __("Megettem %s almat, de %s meg mindig van a kosarban. Az %s%%.", 
       $number, $left, ($left / $original * 100));
```


What will not be replaced
-------------------------

 * Empty strings; 
 * Uppercase-only strings
 * query-like strings
 * items that still `null` in the translation file
 * items thats value is the same as the key in the translation file
