<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use ReactInspector\Bunny\BunnyInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(BunnyInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry ReactPHP Filesystem auto-instrumentation', E_USER_WARNING);

    return;
}

BunnyInstrumentation::register();
