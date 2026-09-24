<?php

namespace NeoCMS;

/**
 * Account registry: accounts from config.local.php are authoritative and locked; accounts created in the CMS live in data/users.json.
 *
 * Only active CMS accounts and config accounts ever reach Authentication, so blocking or deleting an account also ends its sessions.
 * Password hashes and invite tokens never leave this class except as the hash handed to Authentication.
 */
final class UserStore
{
    public const ROLES = ['editor', 'administrator'];
    public const INVITE_LIFETIME = 604800;
    public const MIN_PASSWORD = 12;
    public const MAX_PASSWORD = 72;

    /** Plain-language role descriptions shown on the Users screen. */
    public const ROLE_INFO = [
        'editor' => 'Edits content and saves drafts, uploads images, publishes and schedules pages, and restores revisions or deleted pages. Cannot change the site structure or manage users.',
        'administrator' => 'Everything an editor can do, plus creating, duplicating, renaming and deleting pages, templates, navigation menus, shared content, site scan, deleting media, and managing users.',
    ];

    public function __construct(private FileStore $store, private array $config)
    {
    }

    /** Build a store from the site configuration. */
    public static function fromConfig(array $config): self
    {
        $directory = (string) ($config['dataDirectory'] ?? (dirname(__DIR__, 2) . '/data'));
        return new self(new FileStore($directory), $config);
    }

    /** Arguments for `new Authentication(...)`: config credentials plus active CMS accounts, roles, and session options. */
    public static function authArgs(array $config): array
    {
        [$credentials, $roles] = self::fromConfig($config)->forAuth();
        return [$credentials, $roles, $config['security'] ?? []];
    }

    /** Map an arbitrary configured role onto the two supported roles (the old publisher role is an editor). */
    public static function normaliseRole(mixed $role): string
    {
        return $role === 'administrator' ? 'administrator' : 'editor';
    }

    /** Credentials and roles of every account allowed to sign in. */
    public function forAuth(): array
    {
        $credentials = [];
        $roles = [];
        foreach ($this->data()['users'] as $username => $record) {
            if (($record['status'] ?? '') === 'active' && is_string($record['hash'] ?? null) && $record['hash'] !== '') {
                $credentials[$username] = $record['hash'];
                $roles[$username] = self::normaliseRole($record['role'] ?? 'editor');
            }
        }
        foreach ($this->configAccounts() as $username => $account) {
            $credentials[$username] = $account['hash'];
            $roles[$username] = $account['role'];
        }
        return [$credentials, $roles];
    }

    /** The name and email shown for an account, or blanks. */
    public function profile(string $username): array
    {
        foreach ($this->accounts() as $account) {
            if ($account['username'] === $username) {
                return ['name' => $account['name'], 'email' => $account['email'], 'managed' => $account['managed']];
            }
        }
        return ['name' => '', 'email' => '', 'managed' => false];
    }

    /** Every account without secrets: config accounts first (locked), then CMS accounts. */
    public function accounts(): array
    {
        $data = $this->data();
        $list = [];
        foreach ($this->configAccounts() as $username => $account) {
            $profile = $data['profiles'][$username] ?? [];
            $list[] = [
                'username' => $username, 'name' => (string) ($profile['name'] ?? ''), 'email' => (string) ($profile['email'] ?? ''),
                'role' => $account['role'], 'status' => 'active', 'managed' => true, 'created' => '', 'inviteExpired' => false,
            ];
        }
        foreach ($data['users'] as $username => $record) {
            if (isset($this->configAccounts()[$username])) {
                continue;
            }
            $list[] = [
                'username' => $username, 'name' => (string) ($record['name'] ?? ''), 'email' => (string) ($record['email'] ?? ''),
                'role' => self::normaliseRole($record['role'] ?? 'editor'), 'status' => (string) ($record['status'] ?? 'active'),
                'managed' => false, 'created' => (string) ($record['created'] ?? ''),
                'inviteExpired' => ($record['status'] ?? '') === 'invited' && (int) ($record['invite']['expires'] ?? 0) < time(),
            ];
        }
        return $list;
    }

    public function isManaged(string $username): bool
    {
        return isset($this->configAccounts()[$username]);
    }

    /** Set display name and email. Config accounts keep their credentials in config; only this profile overlay is stored. */
    public function setProfile(string $username, string $name, string $email): void
    {
        $name = $this->cleanName($name);
        $email = $this->cleanEmail($email);
        $managed = $this->isManaged($username);
        $this->store->update('users', function (array $data) use ($username, $name, $email, $managed): array {
            $data = $this->shape($data);
            $this->assertEmailFree($data, $email, $username);
            if ($managed) {
                $data['profiles'][$username] = ['name' => $name, 'email' => $email];
            } elseif (isset($data['users'][$username])) {
                $data['users'][$username]['name'] = $name;
                $data['users'][$username]['email'] = $email;
                $data['users'][$username]['updated'] = date(DATE_ATOM);
            } else {
                throw new \RuntimeException('Account not found');
            }
            return $data;
        });
    }

    /** Create an active CMS account with a password. */
    public function create(string $username, string $name, string $email, string $role, string $password): void
    {
        $username = trim($username);
        $name = $this->cleanName($name);
        $email = $this->cleanEmail($email);
        $this->assertRole($role);
        $this->assertPassword($password, $username, $email);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->store->update('users', function (array $data) use ($username, $name, $email, $role, $hash): array {
            $data = $this->shape($data);
            $this->assertUsernameFree($data, $username);
            $this->assertEmailFree($data, $email, $username);
            $now = date(DATE_ATOM);
            $data['users'][$username] = ['hash' => $hash, 'role' => $role, 'name' => $name, 'email' => $email, 'status' => 'active', 'created' => $now, 'updated' => $now];
            return $data;
        });
    }

    /** Edit a CMS account; a blank password leaves the current one. Config accounts are refused. */
    public function update(string $username, string $name, string $email, string $role, string $password = ''): void
    {
        $this->assertNotManaged($username);
        $name = $this->cleanName($name);
        $email = $this->cleanEmail($email);
        $this->assertRole($role);
        $hash = null;
        if ($password !== '') {
            $this->assertPassword($password, $username, $email);
            $hash = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->store->update('users', function (array $data) use ($username, $name, $email, $role, $hash): array {
            $data = $this->shape($data);
            $record = $data['users'][$username] ?? throw new \RuntimeException('Account not found');
            $this->assertEmailFree($data, $email, $username);
            $record['name'] = $name;
            $record['email'] = $email;
            $record['role'] = $role;
            if ($hash !== null) {
                $record['hash'] = $hash;
            }
            $record['updated'] = date(DATE_ATOM);
            $data['users'][$username] = $record;
            return $data;
        });
    }

    /** Change a CMS account's own password. Returns the new hash so the caller can refresh the session. */
    public function setPassword(string $username, string $password): string
    {
        $this->assertNotManaged($username, 'This password is managed in config.local.php');
        $profile = $this->profile($username);
        $this->assertPassword($password, $username, $profile['email']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->store->update('users', function (array $data) use ($username, $hash): array {
            $data = $this->shape($data);
            if (!isset($data['users'][$username])) {
                throw new \RuntimeException('Account not found');
            }
            $data['users'][$username]['hash'] = $hash;
            $data['users'][$username]['updated'] = date(DATE_ATOM);
            return $data;
        });
        return $hash;
    }

    /** Upgrade a stored hash after a successful login when PHP's default algorithm or cost has changed. */
    public function rehashIfNeeded(string $username, string $password): void
    {
        $record = $this->data()['users'][$username] ?? null;
        if ($record && !$this->isManaged($username) && is_string($record['hash'] ?? null) && $record['hash'] !== '' && password_needs_rehash($record['hash'], PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->store->update('users', function (array $data) use ($username, $hash): array {
                $data = $this->shape($data);
                if (isset($data['users'][$username])) {
                    $data['users'][$username]['hash'] = $hash;
                }
                return $data;
            });
        }
    }

    public function setBlocked(string $username, bool $blocked): void
    {
        $this->assertNotManaged($username);
        $this->store->update('users', function (array $data) use ($username, $blocked): array {
            $data = $this->shape($data);
            $record = $data['users'][$username] ?? throw new \RuntimeException('Account not found');
            if ($blocked) {
                $record['status'] = 'blocked';
            } elseif (($record['status'] ?? '') === 'blocked') {
                $record['status'] = ($record['hash'] ?? '') === '' ? 'invited' : 'active';
            }
            $record['updated'] = date(DATE_ATOM);
            $data['users'][$username] = $record;
            return $data;
        });
    }

    public function delete(string $username): void
    {
        $this->assertNotManaged($username);
        $this->store->update('users', function (array $data) use ($username): array {
            $data = $this->shape($data);
            if (!isset($data['users'][$username])) {
                throw new \RuntimeException('Account not found');
            }
            unset($data['users'][$username]);
            return $data;
        });
    }

    /** Create (or re-issue) a pending invitation. Returns the username and the one-time token, which is stored only as a hash. */
    public function invite(string $name, string $email, string $role, string $existing = ''): array
    {
        $name = $this->cleanName($name);
        $email = $this->cleanEmail($email);
        if ($email === '') {
            throw new \RuntimeException('An email address is required to invite someone');
        }
        $this->assertRole($role);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $username = '';
        $this->store->update('users', function (array $data) use ($name, $email, $role, $existing, $tokenHash, &$username): array {
            $data = $this->shape($data);
            $now = date(DATE_ATOM);
            $invite = ['token' => $tokenHash, 'expires' => time() + self::INVITE_LIFETIME];
            if ($existing !== '') {
                $record = $data['users'][$existing] ?? null;
                if (!$record || ($record['status'] ?? '') !== 'invited') {
                    throw new \RuntimeException('Only a pending invitation can be re-issued');
                }
                $this->assertEmailFree($data, $email, $existing);
                $username = $existing;
                $data['users'][$username] = array_merge($record, ['name' => $name, 'email' => $email, 'role' => $role, 'invite' => $invite, 'updated' => $now]);
                return $data;
            }
            $this->assertEmailFree($data, $email, '');
            $username = $this->generateUsername($data, $email);
            $data['users'][$username] = ['hash' => '', 'role' => $role, 'name' => $name, 'email' => $email, 'status' => 'invited', 'invite' => $invite, 'created' => $now, 'updated' => $now];
            return $data;
        });
        return [$username, $token];
    }

    /** Redeem an invitation: the token is single use and expires. Returns the username. Every failure gives the same message. */
    public function acceptInvite(string $token, string $password): string
    {
        $failure = 'This invitation is invalid or has expired';
        $wanted = hash('sha256', $token);
        $accepted = null;
        $hash = null;
        $data = $this->data();
        foreach ($data['users'] as $username => $record) {
            if (($record['status'] ?? '') === 'invited' && is_string($record['invite']['token'] ?? null)
                && hash_equals($record['invite']['token'], $wanted) && (int) ($record['invite']['expires'] ?? 0) >= time()) {
                $accepted = $username;
            }
        }
        if ($accepted === null || $token === '') {
            throw new \RuntimeException($failure);
        }
        $this->assertPassword($password, $accepted, (string) ($data['users'][$accepted]['email'] ?? ''));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->store->update('users', function (array $data) use ($accepted, $wanted, $hash, $failure): array {
            $data = $this->shape($data);
            $record = $data['users'][$accepted] ?? null;
            if (!$record || ($record['status'] ?? '') !== 'invited' || !hash_equals((string) ($record['invite']['token'] ?? ''), $wanted)) {
                throw new \RuntimeException($failure);
            }
            unset($record['invite']);
            $record['hash'] = $hash;
            $record['status'] = 'active';
            $record['updated'] = date(DATE_ATOM);
            $data['users'][$accepted] = $record;
            return $data;
        });
        return $accepted;
    }

    /** Password policy shared by every path that sets a password. */
    public function assertPassword(string $password, string $username = '', string $email = ''): void
    {
        $length = strlen($password);
        if ($length < self::MIN_PASSWORD) {
            throw new \RuntimeException('Passwords must be at least ' . self::MIN_PASSWORD . ' characters');
        }
        if ($length > self::MAX_PASSWORD) {
            throw new \RuntimeException('Passwords can be at most ' . self::MAX_PASSWORD . ' bytes long');
        }
        $lower = strtolower($password);
        if (($username !== '' && $lower === strtolower($username)) || ($email !== '' && $lower === strtolower($email))) {
            throw new \RuntimeException('The password must differ from the login name and email address');
        }
    }

    private function configAccounts(): array
    {
        $accounts = [];
        foreach ((array) ($this->config['authentication'] ?? []) as $username => $hash) {
            if (is_string($username) && is_string($hash) && $hash !== '') {
                $accounts[$username] = ['hash' => $hash, 'role' => self::normaliseRole(($this->config['roles'] ?? [])[$username] ?? 'editor')];
            }
        }
        return $accounts;
    }

    private function data(): array
    {
        return $this->shape($this->store->read('users'));
    }

    private function shape(array $data): array
    {
        $data['users'] = is_array($data['users'] ?? null) ? $data['users'] : [];
        $data['profiles'] = is_array($data['profiles'] ?? null) ? $data['profiles'] : [];
        return $data;
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 80 || preg_match('/[\x00-\x1f\x7f<>]/u', $name)) {
            throw new \RuntimeException('Enter a display name of up to 80 characters');
        }
        return $name;
    }

    private function cleanEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email !== '' && (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new \RuntimeException('Enter a valid email address');
        }
        return $email;
    }

    private function assertRole(string $role): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \RuntimeException('Unknown role');
        }
    }

    private function assertNotManaged(string $username, string $message = 'This account is managed in config.local.php and cannot be changed here'): void
    {
        if ($this->isManaged($username)) {
            throw new \RuntimeException($message);
        }
    }

    private function assertUsernameFree(array $data, string $username): void
    {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
            throw new \RuntimeException('Login names are 3-32 letters, numbers, dots, dashes or underscores');
        }
        foreach ([$data['users'], $this->configAccounts()] as $existing) {
            foreach (array_keys($existing) as $known) {
                if (strcasecmp((string) $known, $username) === 0) {
                    throw new \RuntimeException('That login name is already in use');
                }
            }
        }
    }

    /** Emails are unique across config overlays and CMS accounts, ignoring case. */
    private function assertEmailFree(array $data, string $email, string $exceptUsername): void
    {
        if ($email === '') {
            return;
        }
        $taken = [];
        foreach ($data['profiles'] as $username => $profile) {
            $taken[$username] = strtolower((string) ($profile['email'] ?? ''));
        }
        foreach ($data['users'] as $username => $record) {
            $taken[$username] = strtolower((string) ($record['email'] ?? ''));
        }
        foreach ($taken as $username => $known) {
            if ($known === $email && (string) $username !== $exceptUsername) {
                throw new \RuntimeException('That email address is already in use');
            }
        }
    }

    private function generateUsername(array $data, string $email): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', explode('@', $email)[0]) ?? '');
        $base = substr(str_pad($base, 3, 'x'), 0, 28);
        for ($i = 0; $i < 1000; $i++) {
            $candidate = $i === 0 ? $base : $base . $i;
            try {
                $this->assertUsernameFree($data, $candidate);
                return $candidate;
            } catch (\RuntimeException) {
            }
        }
        throw new \RuntimeException('Unable to generate a login name');
    }
}
