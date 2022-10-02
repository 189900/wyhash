<?php declare(strict_types=1);

namespace N189900\Wyhash;

use GMP;

class State
{
    public const DEFAULT_SEED = '0';
    public const UPDATE_SIZE = 64;
    private const ROUND_SIZE = 48;

    private static array $primes = [];

    private GMP $seed;
    private GMP $one;
    private GMP $two;
    private int $length = 0;
    private string $tail = '';
    private ?string $lastSixteen = null;

    public function __construct(string $seed = null)
    {
        if (self::$primes === []) {
            self::$primes = [
                gmp_init('0xa0761d6478bd642f'),
                gmp_init('0xe7037ed1a0b428db'),
                gmp_init('0x8ebc6af09c88c6e3'),
                gmp_init('0x589965cc75374cc3'),
            ];
        }

        $this->seed = gmp_init($seed ?? self::DEFAULT_SEED) ^ self::$primes[0];
        $this->one = clone $this->seed;
        $this->two = clone $this->seed;
    }

    /**
     * Accepts a 64-byte aligned buffer, passes a 48-byte aligned chunk to round and retains the rest.
     */
    public function update(string $buffer): void
    {
        $length = mb_strlen($buffer, '8bit');
        assert($length % self::UPDATE_SIZE == 0);

        if ($this->tail !== '') {
            $buffer = $this->tail . $buffer;
            $length += mb_strlen($this->tail, '8bit');
        }

        $aligned = $length - ($length % self::ROUND_SIZE);
        $this->round(substr($buffer, 0, $aligned));

        $this->tail = (string) substr($buffer, $aligned);
        $this->lastSixteen = $this->tail !== '' ? null : substr($buffer, -16);
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

            if (!isset($a, $b) && $length < 16) {
                $tmp = $this->lastSixteen . $buffer;
                $a = $this->readBytes(8, $tmp, -16);
                $b = $this->readBytes(8, $tmp, -8);
            }
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
     * Handles a 48-byte aligned buffer. Applies to payloads exceeding 48 bytes (ROUND_SIZE).
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

    private function readBytes(int $bytes, string &$buffer, int $offset = 0): GMP
    {
        return gmp_import(substr($buffer, $offset, $bytes), $bytes, GMP_LITTLE_ENDIAN);
    }

    private function wyr3(string &$buffer, int $length): GMP
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
