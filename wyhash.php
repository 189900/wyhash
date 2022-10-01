<?php

declare(strict_types=1);

namespace n189900\Wyhash;

use GMP;

class Hasher
{
    public const UPDATE_SIZE = 64;
    public const ROUND_SIZE = 48;

    private static array $primes = [];

    private GMP $seed;
    private int $length = 0;

    private GMP $one;
    private GMP $two;

    private string $tail = '';

    public function __construct(string $seed = '0')
    {
        if (self::$primes === []) {
            self::$primes = [
                gmp_init('0xa0761d6478bd642f'),
                gmp_init('0xe7037ed1a0b428db'),
                gmp_init('0x8ebc6af09c88c6e3'),
                gmp_init('0x589965cc75374cc3'),
            ];
        }

        $this->seed = gmp_init($seed) ^ self::$primes[0];
        $this->one = clone $this->seed;
        $this->two = clone $this->seed;
    }

    public static function hash(string $buffer, string $seed = '0'): string
    {
        $wh = new self($seed);
        $length = mb_strlen($buffer, '8bit');

        $a = gmp_init(0);
        $b = gmp_init(0);
        if ($length <= 16) {
            if ($length >= 4) {
                $a = $wh->readBytes(4, $buffer) << 32
                    | $wh->readBytes(4, $buffer, ($length >> 3) << 2);
                $b = $wh->readBytes(4, $buffer, $length - 4) << 32
                    | $wh->readBytes(4, $buffer, $length - 4 - (($length >> 3) << 2));
            } elseif ($length > 0) {
                $a = $wh->wyr3($buffer, $length);
            }
        } else {
            $i = $length;
            $offset = 0;
            if ($i > 48) {
                $see1 = clone $wh->seed;
                $see2 = clone $wh->seed;
                do {
                    $wh->seed = $wh->mix(
                        $wh->readBytes(8, $buffer, $offset) ^ self::$primes[1],
                        $wh->readBytes(8, $buffer, $offset + 8) ^ $wh->seed,
                    );
                    $see1 = $wh->mix(
                        $wh->readBytes(8, $buffer, $offset + 16) ^ self::$primes[2],
                        $wh->readBytes(8, $buffer, $offset + 24) ^ $see1,
                    );
                    $see2 = $wh->mix(
                        $wh->readBytes(8, $buffer, $offset + 32) ^ self::$primes[3],
                        $wh->readBytes(8, $buffer, $offset + 40) ^ $see2,
                    );
                    $i -= 48;
                    $offset += 48;
                } while ($i > 48);
                $wh->seed ^= $see1 ^ $see2;
            }
            while ($i > 16) {
                $wh->seed = $wh->mix(
                    $wh->readBytes(8, $buffer, $offset) ^ self::$primes[1],
                    $wh->readBytes(8, $buffer, $offset + 8) ^ $wh->seed,
                );
                $i -= 16;
                $offset += 16;
            }
            $a = $wh->readBytes(8, $buffer, $offset + $i - 16);
            $b = $wh->readBytes(8, $buffer, $offset + $i - 8);
        }

        $result = $wh->mix(
            self::$primes[1] ^ $length,
            $wh->mix(
                $a ^ self::$primes[1],
                $b ^ $wh->seed,
            ),
        );

        return str_pad(gmp_strval($result, 16), 16, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts 64 bytes, passes first 48 bytes to round and retains the rest.
     */
    public function update(string $buffer): void
    {
        $length = mb_strlen($buffer, '8bit');
        assert($length == self::UPDATE_SIZE);

        if ($this->tail !== '') {
            $buffer = $this->tail . $buffer;
            $length += mb_strlen($this->tail, '8bit');
        }

        $aligned = $length - ($length % self::ROUND_SIZE);
        $this->round(substr($buffer, 0, $aligned));

        $this->tail = (string) substr($buffer, $aligned);
    }

    /**
     * Accepts the final chunk of less than 64 bytes and returns the hash.
     */
    public function final(string $buffer): string
    {
        $length = mb_strlen($buffer, '8bit');
        assert($length < self::UPDATE_SIZE);

        if ($this->tail !== '') {
            $buffer = $this->tail . $buffer;
            $length += mb_strlen($this->tail, '8bit');
            $this->tail = '';
        }

        if ($length > self::ROUND_SIZE) {
            $aligned = $length - ($length % self::ROUND_SIZE);
            $this->round(substr($buffer, 0, $aligned));

            $a = $this->readBytes(8, $buffer, -16);
            $b = $this->readBytes(8, $buffer, -8);

            $buffer = substr($buffer, $aligned);
            $length -= $aligned;
        }

        if ($hasOneAndTwo = $this->length >= self::ROUND_SIZE) {
            $this->seed ^= $this->one ^ $this->two;
        }

        if ($hasOneAndTwo || $length > 16) {
            foreach (array_slice(str_split($buffer, 16), 0, -1) as $chunk) {
                $this->seed = $this->mix(
                    $this->readBytes(8, $chunk) ^ self::$primes[1],
                    $this->readBytes(8, $chunk, 8) ^ $this->seed,
                );
            }
            $a ??= $this->readBytes(8, $buffer, -16);
            $b ??= $this->readBytes(8, $buffer, -8);
        } elseif ($length >= 4) {
            $a = $this->readBytes(4, $buffer) << 32
                | $this->readBytes(4, $buffer, ($length >> 3) << 2);
            $b = $this->readBytes(4, $buffer, $length - 4) << 32
                | $this->readBytes(4, $buffer, $length - 4 - (($length >> 3) << 2));
        } elseif ($length > 0) {
            $a = $this->wyr3($buffer, $length);
        }
        $this->length += $length;

        $result = $this->mix(
            self::$primes[1] ^ $this->length,
            $this->mix(
                ($a ?? gmp_init(0)) ^ self::$primes[1],
                ($b ?? gmp_init(0)) ^ $this->seed,
            ),
        );

        return str_pad(gmp_strval($result, 16), 16, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts a 48-byte aligned buffer.
     */
    private function round(string $buffer): void
    {
        $length = mb_strlen($buffer, '8bit');
        assert($length % self::ROUND_SIZE == 0);

        for ($offset = 0; $offset < $length; $offset += self::ROUND_SIZE) {
            $this->seed = $this->mix(
                $this->readBytes(8, $buffer, $offset) ^ self::$primes[1],
                $this->readBytes(8, $buffer, $offset + 8) ^ $this->seed,
            );
            $this->one = $this->mix(
                $this->readBytes(8, $buffer, $offset + 16) ^ self::$primes[2],
                $this->readBytes(8, $buffer, $offset + 24) ^ $this->one,
            );
            $this->two = $this->mix(
                $this->readBytes(8, $buffer, $offset + 32) ^ self::$primes[3],
                $this->readBytes(8, $buffer, $offset + 40) ^ $this->two,
            );
        }

        $this->length += $length;
    }

    private function readBytes(int $bytes, string $buffer, int $offset = 0): GMP
    {
        return gmp_import(substr($buffer, $offset, $bytes), $bytes, GMP_LITTLE_ENDIAN);
    }

    private function wyr3(string $buffer, int $length): GMP
    {
        return $this->readBytes(1, $buffer) << 16
            | $this->readBytes(1, $buffer, $length >> 1) << 8
            | $this->readBytes(1, $buffer, -1);
    }

    private function mum(GMP &$a, GMP &$b): void
    {
        $r = $a * $b;
        $b = $r >> 64;
        $a = $r ^ ($b << 64);
    }

    private function mix(GMP $a, GMP $b): GMP
    {
        $this->mum($a, $b);

        return $a ^ $b;
    }
}

(ini_get('zend.assertions') === '1' && ini_get('assert.active') === '1') || die('assertions disabled');

foreach ([
    ['', '42bc986dc5eec4d3'],
    ['a', '84508dc903c31551'],
    ['abc', '0bc54887cfc9ecb1'],
    ['message digest', '6e2ff3298208a67c'],
    ['abcdefghijklmnopqrstuvwxyz', '9a64e42e897195b9'],
    ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', '9199383239c32554'],
    ['12345678901234567890123456789012345678901234567890123456789012345678901234567890', '7c1ccf6bba30f5a5'],
] as $seed => [$msg, $hash]) {
    echo $msg . '=' . ($eh = Hasher::hash($msg, (string) $seed)) . "\n";
    assert($eh === $hash);

    $h = new Hasher((string) $seed);
    $chunks = str_split($msg, 64);
    $tail = array_pop($chunks);
    foreach ($chunks as $chunk) {
        $h->update($chunk);
    }
    echo $msg . '=' . ($eh = $h->final($tail)) . "\n";
    assert($eh === $hash);
}

echo "\n";
foreach ([
    ['a', '0x1', '84508dc903c31551'],
    ['ab', '0x2', '06f4d2359eb619e0'],
    ['abc', '0x3', 'edd59f916c82d920'],
    ['abcd', '0x4', '9013a52af4b8c311'],
    ['abcde', '0x5', '451c8ad7a1d4e30c'],
    ['abcdef', '0x6', '929ccd94eb1c7878'],
    ['abcdefg', '0x7', '1f9702aecba6ed27'],
    ['abcdefgh', '0x8', '11f94459656c2d53'],
    ['abcdefghi', '0x9', 'f022ee9e5364ccb2'],
    ['abcdefghij', '0xa', '699ebdda5ac660b1'],
    ['abcdefghijk', '0xb', '84179c6e6fce0fb2'],
    ['abcdefghijkl', '0xc', 'cb85cefdac8160b5'],
    ['abcdefghijklm', '0xd', 'eded2a77ccf86b11'],
    ['abcdefghijklmn', '0xe', '17c9e80467cd756d'],
    ['abcdefghijklmno', '0xf', '07abfeb3b0faa367'],
    ['abcdefghijklmnop', '0x10', '1975bb261e82c8a3'],
    ['abcdefghijklmnopq', '0x11', 'ffec211aeea1c6bf'],
    ['abcdefghijklmnopqr', '0x12', 'f9b3d4200029fe5a'],
    ['abcdefghijklmnopqrs', '0x13', '1b4db5fe3b176326'],
    ['abcdefghijklmnopqrst', '0x14', '92a81b2a949efca4'],
    ['abcdefghijklmnopqrstu', '0x15', '189a56a731f494a2'],
    ['abcdefghijklmnopqrstuv', '0x16', '3860410470fc3baf'],
    ['abcdefghijklmnopqrstuvw', '0x17', '80a65c4a7d3ea931'],
    ['abcdefghijklmnopqrstuvwx', '0x18', 'a2b00c63c416dacb'],
    ['abcdefghijklmnopqrstuvwxy', '0x19', 'e83f6fab645c002d'],
    ['abcdefghijklmnopqrstuvwxyz', '0x1a', 'ecb882165ba99420'],

    ['abcdefghijklmnopqrstuvwxyz1234567890123456789012', '0x1b', 'b43f94f2a56f15aa'],
    ['abcdefghijklmnopqrstuvwxyz12345678901234567890123', '0x1c', '7feb635da5d73429'],
] as [$msg, $seed, $hash]) {
    echo $msg . '=' . ($eh = Hasher::hash($msg, $seed)) . "\n";
    assert($eh === $hash);

    $h = new Hasher((string) $seed);
    $chunks = str_split($msg, 64);
    $tail = array_pop($chunks);
    foreach ($chunks as $chunk) {
        $h->update($chunk);
    }
    echo $msg . '=' . ($eh = $h->final($tail)) . "\n";
    assert($eh === $hash);
}

echo "\ndone\n";
