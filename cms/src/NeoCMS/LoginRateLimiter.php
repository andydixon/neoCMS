<?php

namespace NeoCMS;

/**
 * Provides filesystem-backed throttling for failed login attempts.
 *
 * Limits are applied to both the source address and the source-address/username pair. This slows
 * password guessing without allowing one remote address to lock an account for everybody else.
 */
final class LoginRateLimiter
{
    private string $path;
    private int $windowSeconds;
    private int $lockoutSeconds;
    private int $maxIdentityAttempts;
    private int $maxAddressAttempts;

    /** Configure the protected state file and throttling thresholds. */
    public function __construct(string $dataDirectory, array $options = [])
    {
        $directory = rtrim($dataDirectory, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create login security directory');
        }
        @chmod($directory, 0700);

        $this->path = $directory . 'login-attempts.json';
        $this->windowSeconds = max(60, (int) ($options['loginWindowSeconds'] ?? 900));
        $this->lockoutSeconds = max(60, (int) ($options['loginLockoutSeconds'] ?? 900));
        $this->maxIdentityAttempts = max(1, (int) ($options['loginMaxAttempts'] ?? 5));
        $this->maxAddressAttempts = max($this->maxIdentityAttempts, (int) ($options['loginMaxAddressAttempts'] ?? 20));
    }

    /** Return the remaining lockout in seconds, or zero when another attempt is permitted. */
    public function retryAfter(string $address, string $username): int
    {
        return $this->withState(function (array &$state) use ($address, $username): int {
            $now = time();
            $this->prune($state, $now);
            $retryAt = max(
                (int) ($state[$this->addressKey($address)]['locked_until'] ?? 0),
                (int) ($state[$this->identityKey($address, $username)]['locked_until'] ?? 0)
            );
            return max(0, $retryAt - $now);
        });
    }

    /** Record one denied login and lock a bucket once its configured threshold is reached. */
    public function recordFailure(string $address, string $username): void
    {
        $this->withState(function (array &$state) use ($address, $username): void {
            $now = time();
            $this->prune($state, $now);
            $this->increment($state, $this->addressKey($address), $this->maxAddressAttempts, $now);
            $this->increment($state, $this->identityKey($address, $username), $this->maxIdentityAttempts, $now);
        });
    }

    /** Clear successful login buckets so typographical mistakes do not linger unnecessarily. */
    public function clear(string $address, string $username): void
    {
        $this->withState(function (array &$state) use ($address, $username): void {
            unset($state[$this->addressKey($address)], $state[$this->identityKey($address, $username)]);
            $this->prune($state, time());
        });
    }

    /** Update one attempt bucket and begin a fixed lockout when its threshold is reached. */
    private function increment(array &$state, string $key, int $limit, int $now): void
    {
        $record = $state[$key] ?? ['attempts' => [], 'locked_until' => 0];
        $record['attempts'][] = $now;
        if (count($record['attempts']) >= $limit) {
            $record['locked_until'] = $now + $this->lockoutSeconds;
            $record['attempts'] = [];
        }
        $state[$key] = $record;
    }

    /** Remove expired attempts and stale unlocked buckets to keep the state file bounded. */
    private function prune(array &$state, int $now): void
    {
        $cutoff = $now - $this->windowSeconds;
        foreach ($state as $key => &$record) {
            $record['attempts'] = array_values(array_filter(
                is_array($record['attempts'] ?? null) ? $record['attempts'] : [],
                static fn($timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
            ));
            $record['locked_until'] = (int) ($record['locked_until'] ?? 0);
            if ($record['attempts'] === [] && $record['locked_until'] <= $now) {
                unset($state[$key]);
            }
        }
        unset($record);
    }

    /** Serialise state updates beneath an exclusive lock to avoid concurrent lost updates. */
    private function withState(callable $callback)
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException('Unable to access login security state');
        }
        @chmod($this->path, 0600);

        try {
            rewind($handle);
            $decoded = json_decode((string) stream_get_contents($handle), true);
            $state = is_array($decoded) ? $decoded : [];
            $result = $callback($state);
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Unable to encode login security state');
            }
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $json . "\n") === false || !fflush($handle)) {
                throw new \RuntimeException('Unable to write login security state');
            }
            @chmod($this->path, 0600);
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Hash address data so the throttling file does not become a convenient IP-address register. */
    private function addressKey(string $address): string
    {
        return 'address:' . hash('sha256', $address);
    }

    /** Hash the combined address and case-normalised username for the tighter identity bucket. */
    private function identityKey(string $address, string $username): string
    {
        return 'identity:' . hash('sha256', $address . "\0" . strtolower($username));
    }
}
