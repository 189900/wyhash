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
            ['', '0x00', '0409638ee2bde459'],
            ['a', '0x01', 'a8412d091b5fe0a9'],
            ['abc', '0x02', '32dd92e4b2915153'],
            ['message digest', '0x03', '8619124089a3a16b'],
            ['abcdefghijklmnopqrstuvwxyz', '0x04', '7a43afb61d7f5f40'],
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', '0x05', 'ff42329b90e50d58'],
            ['12345678901234567890123456789012345678901234567890123456789012345678901234567890', '0x06', 'c39cab13b115aad3'],
        ];
    }

    public function dataProviderBoundries(): array
    {
        return [
            ['a', '0x01', 'a8412d091b5fe0a9'],
            ['ab', '0x02', '8b7217c061d20083'],
            ['abc', '0x03', 'd48aa7d78e3836b1'],
            ['abcd', '0x04', '7fd76d4558a8929d'],
            ['abcde', '0x05', 'cb83330ef9ef6822'],
            ['abcdef', '0x06', '61b232c4f3585759'],
            ['abcdefg', '0x07', '9655db40456cb53d'],
            ['abcdefgh', '0x08', '5638cd0ca81dafe2'],
            ['abcdefghi', '0x09', '76f018efc6022e79'],
            ['abcdefghij', '0x0a', '0702332e4dd0c546'],
            ['abcdefghijk', '0x0b', '714b9c2a0402c881'],
            ['abcdefghijkl', '0x0c', '4c966cdd06015416'],
            ['abcdefghijklm', '0x0d', 'a770e6fb8d028e9e'],
            ['abcdefghijklmn', '0x0e', '4ebc6ad5cf396d19'],
            ['abcdefghijklmno', '0x0f', '63665326d6688ddf'],
            ['abcdefghijklmnop', '0x10', 'e4689174fc7dea98'],
            ['abcdefghijklmnopq', '0x11', '53bacb246c11c41b'],
            ['abcdefghijklmnopqr', '0x12', '1c422affc8f0f447'],
            ['abcdefghijklmnopqrs', '0x13', 'c7b082d58a3c7863'],
            ['abcdefghijklmnopqrst', '0x14', '7409af2dfb671007'],
            ['abcdefghijklmnopqrstu', '0x15', '0ff8f6c74d1d45c7'],
            ['abcdefghijklmnopqrstuv', '0x16', '2c8b87e29e108062'],
            ['abcdefghijklmnopqrstuvw', '0x17', 'df69ee21ce7efa5f'],
            ['abcdefghijklmnopqrstuvwx', '0x18', '451982a1c147f43f'],
            ['abcdefghijklmnopqrstuvwxy', '0x19', '42dac569bb7d64cd'],
            ['abcdefghijklmnopqrstuvwxyz', '0x1a', '19d12a45ac41d86d'],
            ['abcdefghijklmnopqrstuvwxyz1234567890123456789012', '0x1b', '41d8853646b7e361'],
            ['abcdefghijklmnopqrstuvwxyz12345678901234567890123', '0x1c', 'ec8078b9111be37b'],
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

        $this->assertSame('099a2c9bf44c34b9', $hasher->final());
    }
}
