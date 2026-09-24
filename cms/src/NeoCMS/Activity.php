<?php

namespace NeoCMS;

/** Records one operational event in both the Dashboard activity feed and the daily audit log. */
final class Activity
{
    public function __construct(private FileStore $store, private Logger $logger)
    {
    }

    public function record(string $user, string $action, string $target): void
    {
        $entry = ['created' => date(DATE_ATOM), 'user' => $user, 'action' => $action, 'target' => $target];
        // Retaining the latest 250 entries keeps the dashboard useful without growing forever.
        $this->store->update('activity', function (array $entries) use ($entry) {
            $entries[] = $entry;
            return array_slice($entries, -250);
        });
        $this->logger->write($action . ': ' . $target, $user);
    }
}
