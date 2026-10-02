<?php

declare(strict_types=1);

namespace Focal\Service\Notifications\Concerns;

/**
 * Puts a queued service notification on the connection and queue named by
 * focal-service.notifications.connection and focal-service.notifications.queue. Null (the
 * default for both) means the application's default connection and that connection's
 * default queue.
 *
 * The notification is dispatched after the open database transaction (if any) commits, so a
 * worker never picks it up before the ticket or message it refers to is visible.
 */
trait UsesServiceNotificationQueue
{
    protected function useServiceNotificationQueue(): void
    {
        $connection = config('focal-service.notifications.connection');
        $queue = config('focal-service.notifications.queue');

        $this->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
        $this->afterCommit();
    }
}
