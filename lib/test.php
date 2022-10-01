<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use N189900\Wyhash\Hasher;

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
    echo $msg . '=' . ($eh = Hasher::hash($msg, (string) $seed)) . "\n";
    assert($eh === $hash);
}

echo "\ndone\n";
