<?php declare(strict_types=1);

namespace N189900\Wyhash;

/**
 * @coversDefaultClass \N189900\Wyhash\Hasher
 */
class HasherTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstructor(): void
    {
        $instance = new Hasher();

        $this->assertNotEmpty($instance->final());
    }

    /**
     * @covers ::__construct
     */
    public function testNonDefaultSeed(): void
    {
        $default = new Hasher();
        $custom = new Hasher('123');

        $this->assertNotSame($default->final(), $custom->final());
    }

    /**
     * @dataProvider dataProviderTestVectors
     * @dataProvider dataProviderBoundries
     * @covers ::hash
     */
    public function testVectorsHash(string $message, string $seed, string $expected): void
    {
        $this->assertSame($expected, Hasher::hash($message, $seed));
    }

    /**
     * @dataProvider dataProviderTestVectors
     * @dataProvider dataProviderBoundries
     * @covers ::final
     */
    public function testVectorsFinal(string $message, string $seed, string $expected): void
    {
        $hasher = new Hasher($seed);

        $this->assertSame($expected, $hasher->final($message));
    }

    public function dataProviderTestVectors(): array
    {
        return [
            ['', '0', '42bc986dc5eec4d3'],
            ['a', '1', '84508dc903c31551'],
            ['abc', '2', '0bc54887cfc9ecb1'],
            ['message digest', '3', '6e2ff3298208a67c'],
            ['abcdefghijklmnopqrstuvwxyz', '4', '9a64e42e897195b9'],
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', '5', '9199383239c32554'],
            ['12345678901234567890123456789012345678901234567890123456789012345678901234567890', '6', '7c1ccf6bba30f5a5'],
        ];
    }

    public function dataProviderBoundries(): array
    {
        return [
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
        ];
    }

    /**
     * @covers ::update
     */
    public function testStreamUpdate8k(): void
    {
        $message = str_repeat('aa0bb1cc2dd3ee4ff5gg6hh7ii8jj9kk', 256);
        $length = strlen($message);
        $hasher = new Hasher('0xdeadbeef');
        $offset = 0;
        while (true) {
            for ($i = 0; $i < 128; $i++) {
                $hasher->update(substr($message, $offset, $i));

                if (($offset += $i) >= $length) {
                    break 2;
                }
            }
        }

        $this->assertSame('11c4b58ff0f5ae88', $hasher->final());
    }
}
