<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Field;

@include __DIR__ . '/vendor/autoload.php';
@include __DIR__ . '/helpers.php';

Kirby::plugin('avoskitchen/sanitizer', [
    'options' => [
        'cache' => [
            'purifier-definitions' => true,
        ],

        'dir' => null,
        'markdown' => false,
        'smartypants' => null,
        'allowlinks' => true,
        'autolinks' => true,
        'headingClass' => '{{ tag }}-sanitized',
    ],

    'fieldMethods' => [
        'sanitize' => function (Field $field, array $options = []): Field{
            $field->value = sanitize($field, $options);
            return $field;
        }
    ],
]);
