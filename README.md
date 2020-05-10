# Sanitizer Plugin for [Kirby](https://getkirby.com)

Handle untrusted user input, e.g. in comments or any other user-submitted form with
confidence. The Sanitizer plugin escaped all unsafe HTML tags, corrects illegally
nested HTML tags and syntax errors, while keeping a small, well-formed subset of
all the HTML tags in existance. Optionally, Markdown can also be enabled.

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby-sanitizer`.

### Git submodule

```
git submodule add https://github.com/avoskitchen/kirby-sanitizer.git site/plugins/kirby-sanitizer
```

### Composer

```
composer require avoskitchen/kirby-sanitizer
```

## Setup

Use the provided helper function `sanitize(string $html, array $options = [])` in your
templates or anywhere else, where you need for sanitize untrusted HTML input. You can
also use the corresponding field method `$field->sanitize(string $html, array $options = [])`.

## Options

| Key | Default value | Description |
|:----|:--------------|:------------|
| `dir` | `null` | Sets the text direction of the input HTML. If `null`, the current locale setting of Kirby is used. |
| `markdown` | `false` | Parse Markdown commands before sanitization. |
| `smartypants` | `null` | If not specified, Kirby’s default setting is used. |
| `allowlinks` | `true` | Allow links in output HTML. |
| `autolinks` | `true` | Automatically convert all URLs to links. If `allowlinks` is set to `false`, this option has no effect. |
| `headingClass` | `{{ tag }}-sanitized` | Class to apply to replaced headlines. Available playeholders: `{{ tag }}` = The full tag name of the replaced (`<h[1-6]>`) element / `{{ level }}` = The level (`[1-6]`) of the replaced element. |

You can set global defaults, by prepending any of the options above with the plugin namespace (`avoskitchen.sanitizer`):

```php
# site/config/config.php

return [
  'avoskitchen.sanitizer.allowlinks' => false,
];
```

## Development

I created this plugin for my own purposes. I will try my best if you report a bug, but
if you need any new features, please be aware that I don’t really have time to develop
them for your needs. But you are welcome to support the development of this plugin by
contributing code. I’m happy to help you with that, if I can.

## License

LPGL

## Credits

- [Fabian Michael](https://fabianmichael.de)

## Third-party Libraries

- [HTML Purifier](http://htmlpurifier.org/), released under the LGPL
- [html5-php](http://masterminds.github.io/html5-php/), released under the HTML5-PHP license
