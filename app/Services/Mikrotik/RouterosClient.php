<?php

namespace App\Services\Mikrotik;

/**
 * Low level RouterOS API (binary) client.
 *
 * Speaks the native RouterOS API protocol over a raw TCP or TLS socket, which
 * works against RouterOS 6 and 7. RouterOS 6.43+ accepts plain credential
 * login; older builds are handled with the legacy challenge/response flow.
 *
 * @see https://help.mikrotik.com/docs/display/ROS/API
 */
class RouterosClient
{
    /** @var resource|null */
    protected $socket = null;

    protected bool $loggedIn = false;

    protected int $timeout;

    protected bool $sslVerify;

    public function __construct(
        protected string $host,
        protected int $port = 8728,
        protected ?string $username = null,
        protected ?string $password = null,
        protected bool $useSsl = false,
        int $timeout = 10,
        bool $sslVerify = false,
    ) {
        $this->timeout = $timeout;
        $this->sslVerify = $sslVerify;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /** Opens the socket and authenticates. Throws RouterosException on failure. */
    public function connect(): void
    {
        $scheme = $this->useSsl ? 'ssl://' : 'tcp://';
        $target = $scheme.$this->host.':'.$this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->sslVerify,
                'verify_peer_name' => $this->sslVerify,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new RouterosException(
                sprintf('Cannot reach router at %s (%s)', $target, $errstr ?: 'connection failed'),
                $errno
            );
        }

        stream_set_timeout($socket, $this->timeout);
        stream_set_blocking($socket, true);
        $this->socket = $socket;
        $this->loggedIn = false;

        if ($this->username !== null) {
            $this->login();
        }
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->loggedIn = false;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && $this->loggedIn;
    }

    /**
     * Authenticates against RouterOS.
     *
     * Modern RouterOS (6.43+) accepts the credentials in the login command.
     * Older builds return a challenge which must be answered with an MD5 of
     * the password and the challenge bytes.
     */
    protected function login(): void
    {
        $this->writeSentence(['/login', '=name='.$this->username, '=password='.$this->password]);
        $response = $this->readSentence();

        if ($this->isDone($response)) {
            $this->loggedIn = true;

            return;
        }

        if ($this->isTrap($response)) {
            throw new RouterosException($this->trapMessage($response, 'Login failed'));
        }

        // Legacy challenge/response login.
        $challenge = null;
        foreach ($response as $word) {
            if (str_starts_with($word, '=ret=')) {
                $challenge = substr($word, 5);
            }
        }

        if ($challenge === null) {
            throw new RouterosException('Unexpected login response from router');
        }

        $this->writeSentence([
            '/login',
            '=name='.$this->username,
            '=response=00'.md5(chr(0).$this->password.pack('H*', $challenge)),
        ]);

        $second = $this->readSentence();
        if (! $this->isDone($second)) {
            throw new RouterosException($this->trapMessage($second, 'Login rejected'));
        }

        $this->loggedIn = true;
    }

    /**
     * Runs a command and returns every row of the reply.
     *
     * @param  array<string, string|int>  $attributes
     * @return array<int, array<string, string>>
     */
    public function query(string $command, array $attributes = []): array
    {
        $sentence = [$command];
        foreach ($attributes as $key => $value) {
            $sentence[] = '='.$key.'='.$value;
        }

        $this->writeSentence($sentence);

        $rows = [];
        $current = [];
        $deadline = time() + max($this->timeout, 5);

        while (true) {
            if (time() > $deadline) {
                throw new RouterosException('Timed out while reading router reply');
            }

            $reply = $this->readSentence();
            if ($reply === []) {
                continue;
            }

            $tag = $reply[0];

            if ($tag === '!re') {
                // Each !re begins a new row; the previous one is complete.
                if ($current !== []) {
                    $rows[] = $current;
                    $current = [];
                }

                foreach (array_slice($reply, 1) as $word) {
                    if (str_starts_with($word, '=')) {
                        $eq = strpos($word, '=', 1);
                        if ($eq !== false) {
                            $current[substr($word, 1, $eq - 1)] = substr($word, $eq + 1);
                        }
                    }
                }

                continue;
            }

            if ($tag === '!done') {
                if ($current !== []) {
                    $rows[] = $current;
                }

                return $rows;
            }

            if ($tag === '!trap' || $tag === '!fatal') {
                throw new RouterosException($this->trapMessage($reply, 'Router returned an error'));
            }
        }
    }

    /** Convenience helper: returns the first row of a command, or null. */
    public function queryOne(string $command, array $attributes = []): ?array
    {
        $rows = $this->query($command, $attributes);

        return $rows[0] ?? null;
    }

    /** Reads every row of a large reply, streaming rows to a callback. */
    public function each(string $command, array $attributes, callable $callback): int
    {
        $sentence = [$command];
        foreach ($attributes as $key => $value) {
            $sentence[] = '='.$key.'='.$value;
        }
        $this->writeSentence($sentence);

        $count = 0;
        $current = [];

        while (true) {
            $reply = $this->readSentence();
            if ($reply === []) {
                continue;
            }

            if ($reply[0] === '!re') {
                // Stream the completed row before starting the next one.
                if ($current !== []) {
                    $callback($current);
                    $count++;
                    $current = [];
                }

                foreach (array_slice($reply, 1) as $word) {
                    if (str_starts_with($word, '=')) {
                        $eq = strpos($word, '=', 1);
                        if ($eq !== false) {
                            $current[substr($word, 1, $eq - 1)] = substr($word, $eq + 1);
                        }
                    }
                }

                continue;
            }

            if ($reply[0] === '!done') {
                if ($current !== []) {
                    $callback($current);
                    $count++;
                }

                return $count;
            }

            if ($reply[0] === '!trap' || $reply[0] === '!fatal') {
                throw new RouterosException($this->trapMessage($reply, 'Router returned an error'));
            }
        }
    }

    /** @param array<int, string> $words */
    protected function writeSentence(array $words): void
    {
        $payload = '';
        foreach ($words as $word) {
            $payload .= $this->encodeLength(strlen($word)).$word;
        }
        $payload .= chr(0);

        $this->write($payload);
    }

    protected function write(string $data): void
    {
        if (! is_resource($this->socket)) {
            throw new RouterosException('Not connected to router');
        }

        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $chunk = @fwrite($this->socket, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RouterosException('Failed writing to router socket');
            }
            $written += $chunk;
        }
    }

    /** @return array<int, string> */
    protected function readSentence(): array
    {
        $words = [];

        while (true) {
            $length = $this->decodeLength();
            if ($length === 0) {
                return $words;
            }
            $words[] = $this->read($length);
        }
    }

    protected function read(int $length): string
    {
        if (! is_resource($this->socket)) {
            throw new RouterosException('Not connected to router');
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (! empty($meta['timed_out'])) {
                    throw new RouterosException('Router connection timed out');
                }
                throw new RouterosException('Router closed the connection unexpectedly');
            }
            $data .= $chunk;
        }

        return $data;
    }

    protected function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x4000) {
            $length |= 0x8000;

            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;

            return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;

            return chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        return chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF)
            .chr(($length >> 8) & 0xFF).chr($length & 0xFF);
    }

    protected function decodeLength(): int
    {
        $byte = ord($this->read(1));

        if (($byte & 0x80) === 0) {
            return $byte;
        }
        if (($byte & 0xC0) === 0x80) {
            return (($byte & 0x3F) << 8) + ord($this->read(1));
        }
        if (($byte & 0xE0) === 0xC0) {
            $rest = $this->read(2);

            return (($byte & 0x1F) << 16) + (ord($rest[0]) << 8) + ord($rest[1]);
        }
        if (($byte & 0xF0) === 0xE0) {
            $rest = $this->read(3);

            return (($byte & 0x0F) << 24) + (ord($rest[0]) << 16)
                + (ord($rest[1]) << 8) + ord($rest[2]);
        }

        $rest = $this->read(4);

        return (ord($rest[0]) << 24) + (ord($rest[1]) << 16) + (ord($rest[2]) << 8) + ord($rest[3]);
    }

    /** @param array<int, string> $sentence */
    protected function isDone(array $sentence): bool
    {
        return ($sentence[0] ?? null) === '!done';
    }

    /** @param array<int, string> $sentence */
    protected function isTrap(array $sentence): bool
    {
        return in_array($sentence[0] ?? null, ['!trap', '!fatal'], true);
    }

    /** @param array<int, string> $sentence */
    protected function trapMessage(array $sentence, string $fallback): string
    {
        foreach ($sentence as $word) {
            if (str_starts_with($word, '=message=')) {
                return substr($word, 9);
            }
        }

        return $fallback;
    }
}
