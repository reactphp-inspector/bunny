<?php

declare(strict_types=1);

$makefilePath = __DIR__ . '/../Makefile';
if (! is_file($makefilePath)) {
    return;
}

$makefile = file_get_contents($makefilePath);
if (! is_string($makefile)) {
    return;
}

$makefile = (string) preg_replace(
    '/^(task-list-ci-dos:.*\n\t@echo ")\[[^\]]*\]("( ## Count: )[^\\n]*)/m',
    '$1[]$2 0',
    $makefile,
);

$makefile = (string) preg_replace(
    '/^(unit-testing-raw: ## Run tests )##\*D\*##(\^unit-tests\^##)/m',
    '$1##$2',
    $makefile,
);

$makefile = str_replace(
    "unit-testing-raw: ## Run tests ^unit-tests^##\n",
    "unit-testing-raw: ## Run tests ##^unit-tests^##\n",
    $makefile,
);

file_put_contents($makefilePath, $makefile);
