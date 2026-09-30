<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$connectionUri = getenv('TEST_RABBITMQ_CONNECTION_URI');
if (! is_string($connectionUri) || $connectionUri === '') {
    $connectionUri = $_ENV['TEST_RABBITMQ_CONNECTION_URI'] ?? $_SERVER['TEST_RABBITMQ_CONNECTION_URI'] ?? false;
}

$runningOnGitHubActions = static function (): bool {
    $githubActions = getenv('GITHUB_ACTIONS');
    if (is_string($githubActions) && $githubActions !== '' && $githubActions !== '0') {
        return true;
    }

    $fromServer = $_ENV['GITHUB_ACTIONS'] ?? $_SERVER['GITHUB_ACTIONS'] ?? null;

    return is_string($fromServer) && $fromServer !== '' && $fromServer !== '0';
};

if (is_string($connectionUri) && $connectionUri !== '') {
    if ($runningOnGitHubActions() && ! str_contains($connectionUri, 'rabbit_node_1')) {
        putenv('TEST_RABBITMQ_CONNECTION_URI=amqp://testuser:testpassword@rabbit_node_1:5672/testvhost');
    }

    return;
}

if ($runningOnGitHubActions()) {
    putenv('TEST_RABBITMQ_CONNECTION_URI=amqp://testuser:testpassword@rabbit_node_1:5672/testvhost');

    return;
}

/** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fileExists (sync bootstrap before the event loop runs) */
if (file_exists('/.dockerenv')) {
    putenv('TEST_RABBITMQ_CONNECTION_URI=amqp://testuser:testpassword@host.docker.internal:5672/testvhost');

    return;
}

putenv('TEST_RABBITMQ_CONNECTION_URI=amqp://testuser:testpassword@127.0.0.1:5672/testvhost');
