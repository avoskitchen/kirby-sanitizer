<?php

use AvosKitchen\Sanitizer\Sanitizer;

function sanitize(string $html, array $options = []): string
{
    return Sanitizer::sanitize($html, $options);
}
