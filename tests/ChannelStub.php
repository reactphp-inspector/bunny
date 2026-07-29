<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Bunny;

use Bunny\ChannelInterface;
use Bunny\ChannelMode;
use Bunny\Message;
use Bunny\Protocol\MethodBasicCancelOkFrame;
use Bunny\Protocol\MethodBasicConsumeOkFrame;
use Bunny\Protocol\MethodBasicQosOkFrame;
use Bunny\Protocol\MethodBasicRecoverOkFrame;
use Bunny\Protocol\MethodConfirmSelectOkFrame;
use Bunny\Protocol\MethodExchangeBindOkFrame;
use Bunny\Protocol\MethodExchangeDeclareOkFrame;
use Bunny\Protocol\MethodExchangeDeleteOkFrame;
use Bunny\Protocol\MethodExchangeUnbindOkFrame;
use Bunny\Protocol\MethodQueueBindOkFrame;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Bunny\Protocol\MethodQueueDeleteOkFrame;
use Bunny\Protocol\MethodQueuePurgeOkFrame;
use Bunny\Protocol\MethodQueueUnbindOkFrame;
use Bunny\Protocol\MethodTxCommitOkFrame;
use Bunny\Protocol\MethodTxRollbackOkFrame;
use Bunny\Protocol\MethodTxSelectOkFrame;
use Evenement\EventEmitterTrait;
use LogicException;

use function is_callable;

final class ChannelStub implements ChannelInterface
{
    use EventEmitterTrait;

    /** @var (callable(Message): mixed)|null */
    private $consumeCallback = null;

    public function getMode(): ChannelMode
    {
        return ChannelMode::Regular;
    }

    public function addReturnListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function removeReturnListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function addAckListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function removeAckListener(callable $callback): ChannelInterface
    {
        return $this;
    }

    public function close(int $replyCode = 0, string $replyText = '', bool $connectionStatus = true): void
    {
    }

    /** @param array<string, mixed> $arguments */
    public function consume(callable $callback, string $queue = '', string $consumerTag = '', bool $noLocal = false, bool $noAck = false, bool $exclusive = false, bool $nowait = false, array $arguments = [], int $concurrency = 1): MethodBasicConsumeOkFrame
    {
        $this->consumeCallback = $callback;

        $frame              = new MethodBasicConsumeOkFrame();
        $frame->consumerTag = $consumerTag !== '' ? $consumerTag : 'consumer-tag';

        return $frame;
    }

    public function deliver(Message $message): mixed
    {
        if (! is_callable($this->consumeCallback)) {
            throw new LogicException('No consumer registered.');
        }

        return ($this->consumeCallback)($message);
    }

    public function ack(Message $message, bool $multiple = false): bool
    {
        return false;
    }

    public function nack(Message $message, bool $multiple = false, bool $requeue = true): bool
    {
        return false;
    }

    public function reject(Message $message, bool $requeue = true): bool
    {
        return false;
    }

    public function get(string $queue = '', bool $noAck = false): Message|null
    {
        return null;
    }

    /** @param array<string, mixed> $headers */
    public function publish(string $body, array $headers = [], string $exchange = '', string $routingKey = '', bool $mandatory = false, bool $immediate = false): int|bool
    {
        return $mandatory ? false : 1;
    }

    public function cancel(string $consumerTag, bool $nowait = false): MethodBasicCancelOkFrame|bool
    {
        return false;
    }

    public function txSelect(): MethodTxSelectOkFrame
    {
        throw new LogicException('Not implemented.');
    }

    public function txCommit(): MethodTxCommitOkFrame
    {
        throw new LogicException('Not implemented.');
    }

    public function txRollback(): MethodTxRollbackOkFrame
    {
        throw new LogicException('Not implemented.');
    }

    public function confirmSelect(callable|null $callback = null, bool $nowait = false): MethodConfirmSelectOkFrame|bool
    {
        return false;
    }

    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): MethodBasicQosOkFrame
    {
        throw new LogicException('Not implemented.');
    }

    /** @param array<string, mixed> $arguments */
    public function queueDeclare(string $queue = '', bool $passive = false, bool $durable = false, bool $exclusive = false, bool $autoDelete = false, bool $nowait = false, array $arguments = []): MethodQueueDeclareOkFrame|bool
    {
        return false;
    }

    /** @param array<string, mixed> $arguments */
    public function queueBind(string $exchange, string $queue = '', string $routingKey = '', bool $nowait = false, array $arguments = []): MethodQueueBindOkFrame|bool
    {
        return false;
    }

    public function queuePurge(string $queue = '', bool $nowait = false): MethodQueuePurgeOkFrame|bool
    {
        return false;
    }

    public function queueDelete(string $queue = '', bool $ifUnused = false, bool $ifEmpty = false, bool $nowait = false): MethodQueueDeleteOkFrame|bool
    {
        return false;
    }

    /** @param array<string, mixed> $arguments */
    public function queueUnbind(string $exchange, string $queue = '', string $routingKey = '', array $arguments = []): MethodQueueUnbindOkFrame
    {
        throw new LogicException('Not implemented.');
    }

    /** @param array<string, mixed> $arguments */
    public function exchangeDeclare(string $exchange, string $exchangeType = 'direct', bool $passive = false, bool $durable = false, bool $autoDelete = false, bool $internal = false, bool $nowait = false, array $arguments = []): MethodExchangeDeclareOkFrame|bool
    {
        return false;
    }

    public function exchangeDelete(string $exchange, bool $ifUnused = false, bool $nowait = false): MethodExchangeDeleteOkFrame|bool
    {
        return false;
    }

    /** @param array<string, mixed> $arguments */
    public function exchangeBind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeBindOkFrame|bool
    {
        return false;
    }

    /** @param array<string, mixed> $arguments */
    public function exchangeUnbind(string $destination, string $source, string $routingKey = '', bool $nowait = false, array $arguments = []): MethodExchangeUnbindOkFrame|bool
    {
        return false;
    }

    public function recoverAsync(bool $requeue = false): bool
    {
        return false;
    }

    public function recover(bool $requeue = false): MethodBasicRecoverOkFrame
    {
        throw new LogicException('Not implemented.');
    }
}
