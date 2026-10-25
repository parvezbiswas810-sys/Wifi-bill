<?php

namespace Tests\Feature;

use App\Services\Mikrotik\RouterosClient;
use App\Services\Mikrotik\RouterosException;
use App\Services\MikrotikService;
use Tests\TestCase;

/**
 * Verifies the RouterOS wire protocol encoding and reply parsing.
 *
 * The client is exercised against a local socket server that speaks the
 * RouterOS API framing, so no real router is needed.
 */
class MikrotikProtocolTest extends TestCase
{
    public function test_uptime_conversion(): void
    {
        $service = new MikrotikService;

        $this->assertSame(604800, $service->uptimeToSeconds('1w'));
        $this->assertSame(86400 + 7200, $service->uptimeToSeconds('1d2h'));
        $this->assertSame(3725, $service->uptimeToSeconds('1h2m5s'));
        $this->assertNull($service->uptimeToSeconds(null));
        $this->assertNull($service->uptimeToSeconds('garbage'));
    }

    public function test_length_encoding_round_trips_through_a_socket_pair(): void
    {
        [$clientSocket, $serverSocket] = $this->socketPair();

        // Write a sentence using the client's own framing, then read it back
        // with an independent decoder to confirm the encoding is correct.
        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function writeWords(array $words): void
            {
                $this->writeSentence($words);
            }
        };

        $reflection = new \ReflectionClass(RouterosClient::class);
        $socketProp = $reflection->getProperty('socket');
        $socketProp->setAccessible(true);
        $socketProp->setValue($client, $clientSocket);

        $words = ['/ppp/secret/print', '=name=testuser', str_repeat('x', 200)];
        $client->writeWords($words);

        $decoded = $this->readSentence($serverSocket);
        $this->assertSame($words, $decoded);
    }

    public function test_routeros_error_reply_raises_an_exception(): void
    {
        [$clientSocket, $serverSocket] = $this->socketPair();

        // RouterOS sends !done after !trap; the client must drain it so the
        // socket is left clean for the next command on this connection.
        $this->writeSentence($serverSocket, ['!trap', '=message=no such item']);
        $this->writeSentence($serverSocket, ['!done']);

        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function queryRaw(): array
            {
                return $this->query('/ppp/secret/print');
            }
        };

        $reflection = new \ReflectionClass(RouterosClient::class);
        $socketProp = $reflection->getProperty('socket');
        $socketProp->setAccessible(true);
        $socketProp->setValue($client, $clientSocket);

        $this->expectException(RouterosException::class);
        $this->expectExceptionMessage('no such item');
        $client->queryRaw();
    }

    /**
     * A bare menu path such as /system/resource is rejected by RouterOS 6 with
     * "no such command", so the client must append the implicit read verb.
     */
    public function test_bare_menu_paths_gain_the_print_verb(): void
    {
        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function sentence(string $command, array $attributes = []): array
            {
                return $this->buildSentence($command, $attributes);
            }
        };

        $this->assertSame(['/system/resource/print'], $client->sentence('/system/resource'));
        $this->assertSame(['/system/identity/print'], $client->sentence('/system/identity'));
        $this->assertSame(['/ppp/secret/print'], $client->sentence('/ppp/secret'));
        $this->assertSame(['/ppp/active/print'], $client->sentence('/ppp/active'));

        // Commands that already name an action are left alone.
        $this->assertSame(['/ppp/secret/set'], $client->sentence('/ppp/secret/set'));
        $this->assertSame(['/system/resource/print'], $client->sentence('/system/resource/print'));
    }

    /**
     * Query filters use the `?` prefix; `=` is rejected as an unknown
     * parameter. Write commands keep `=`.
     */
    public function test_filters_use_the_query_prefix_on_read_commands(): void
    {
        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function sentence(string $command, array $attributes = []): array
            {
                return $this->buildSentence($command, $attributes);
            }
        };

        $this->assertSame(
            ['/ppp/secret/print', '?name=user1'],
            $client->sentence('/ppp/secret', ['name' => 'user1'])
        );

        $this->assertSame(
            ['/ppp/secret/set', '=.id=*1', '=disabled=yes'],
            $client->sentence('/ppp/secret/set', ['.id' => '*1', 'disabled' => 'yes'])
        );
    }

    public function test_trap_followed_by_done_still_returns_the_error(): void
    {
        [$clientSocket, $serverSocket] = $this->socketPair();

        // First command traps. RouterOS still sends !done afterwards; if the
        // client stops reading at !trap, that !done is left in the socket and
        // is later mistaken for the next command's reply.
        $this->writeSentence($serverSocket, ['!trap', '=message=no such command']);
        $this->writeSentence($serverSocket, ['!done']);

        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function queryRaw(string $command): array
            {
                return $this->query($command);
            }
        };

        $reflection = new \ReflectionClass(RouterosClient::class);
        $socketProp = $reflection->getProperty('socket');
        $socketProp->setAccessible(true);
        $socketProp->setValue($client, $clientSocket);

        try {
            $client->queryRaw('/system/resource');
            $this->fail('Expected a RouterosException for the trap reply.');
        } catch (RouterosException $e) {
            $this->assertSame('no such command', $e->getMessage());
        }

        // A second command on the same connection must parse cleanly. This
        // fails if the trailing !done from the trap was not consumed.
        $this->writeSentence($serverSocket, ['!re', '=name=user1']);
        $this->writeSentence($serverSocket, ['!done']);

        $rows = $client->queryRaw('/ppp/secret/print');

        $this->assertCount(1, $rows);
        $this->assertSame('user1', $rows[0]['name']);
    }

    public function test_result_rows_are_parsed_into_associative_arrays(): void
    {
        [$clientSocket, $serverSocket] = $this->socketPair();

        $this->writeSentence($serverSocket, ['!re', '=.id=*1', '=name=user1', '=disabled=false']);
        $this->writeSentence($serverSocket, ['!re', '=.id=*2', '=name=user2', '=disabled=true']);
        $this->writeSentence($serverSocket, ['!done']);

        $client = new class('127.0.0.1', 0) extends RouterosClient
        {
            public function queryRaw(): array
            {
                return $this->query('/ppp/secret/print');
            }
        };

        $reflection = new \ReflectionClass(RouterosClient::class);
        $socketProp = $reflection->getProperty('socket');
        $socketProp->setAccessible(true);
        $socketProp->setValue($client, $clientSocket);

        $rows = $client->queryRaw();

        $this->assertCount(2, $rows);
        $this->assertSame('user1', $rows[0]['name']);
        $this->assertSame('false', $rows[0]['disabled']);
        $this->assertSame('true', $rows[1]['disabled']);
    }

    /** @return array{0: resource, 1: resource} */
    protected function socketPair(): array
    {
        $pair = [];
        if (function_exists('stream_socket_pair')) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        }

        if (! $pair) {
            $this->markTestSkipped('stream_socket_pair is not available in this PHP build');
        }

        return $pair;
    }

    /** @param resource $socket */
    protected function writeSentence($socket, array $words): void
    {
        $payload = '';
        foreach ($words as $word) {
            $payload .= $this->encodeLength(strlen($word)).$word;
        }
        $payload .= chr(0);
        fwrite($socket, $payload);
    }

    /** @param resource $socket */
    protected function readSentence($socket): array
    {
        $words = [];
        while (true) {
            $length = $this->decodeLength($socket);
            if ($length === 0) {
                return $words;
            }
            $words[] = fread($socket, $length);
        }
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

        return chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF)
            .chr(($length >> 8) & 0xFF).chr($length & 0xFF);
    }

    /** @param resource $socket */
    protected function decodeLength($socket): int
    {
        $byte = ord(fread($socket, 1));

        if (($byte & 0x80) === 0) {
            return $byte;
        }
        if (($byte & 0xC0) === 0x80) {
            return (($byte & 0x3F) << 8) + ord(fread($socket, 1));
        }
        if (($byte & 0xE0) === 0xC0) {
            $rest = fread($socket, 2);

            return (($byte & 0x1F) << 16) + (ord($rest[0]) << 8) + ord($rest[1]);
        }
        if (($byte & 0xF0) === 0xE0) {
            $rest = fread($socket, 3);

            return (($byte & 0x0F) << 24) + (ord($rest[0]) << 16)
                + (ord($rest[1]) << 8) + ord($rest[2]);
        }

        $rest = fread($socket, 4);

        return (ord($rest[0]) << 24) + (ord($rest[1]) << 16) + (ord($rest[2]) << 8) + ord($rest[3]);
    }
}
