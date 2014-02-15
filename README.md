# Snidely

A handlebars.js like templating engine optimized for php.

## This software is very much alpha quality. Do not use it in your projects.

## Changes from Mustache and Handlebars.js

1. Snidely works with simple arrays as data and is not meant to work with complex objects.

## Specific Changes from Mustache

1. When including a partial mustache will indent that entire partial.
Snidely just includes the partial without indenting it.

## Specific Changes from Handlebars.js

1. When looping through an array with a `{{#section}}` or `{{#each}}`, 
Snidely always assigns a value to `@key` even when looping over a numeric array.

2. Handlebars.js can output arrays by concatenating all of the elements with a ",".
Snidely won't do this. Use the `{{join array sep=","}}` helper to do this in Snidely.

3. Some values such as boolean `true` and `false` output differently in php and javascript.
Don't depend on a particular format for booleans in your template. 
Use the `{{iif bool "true" "false"}}` to specifically format booleans.

4. Snidely templates are built up using php's echo and output buffering.
When adding helper functions this must be taken into consideration.
    
    * Inline helpers wrapped in `{{ }}` or `{{{ }}}` should return their results as a string.
    * Block level helpers should echo out their results. If you want to manipulate the content
    of a block helper before displaying it then you should capture it using `ob_begin()` and `ob_get_clean()`.
    
5. The `this` data will always refer to the current context even if your data contains an key named "this".
If you need to refer to the "this" key then use `{{./this}}`.

6. Handlebars.js has an odd behavior where wrapping a value of `0` in a `{{#with}}` will result in any
child expressions returning a `0` instead of an empty string.