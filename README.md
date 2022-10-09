# PHP Wyhash (fast non-cryptographic hash)

[![Latest Version](https://img.shields.io/github/release/189900/wyhash.svg?style=flat-square)](https://github.com/189900/wyhash/releases)
[![Build Status](https://img.shields.io/github/workflow/status/189900/wyhash/CI?label=ci%20build&style=flat-square)](https://github.com/189900/wyhash/actions?query=workflow%3ACI)

`189900/wyhash` is a PHP implementation of the [wyhash algorithm by Wang Yi](https://github.com/wangyi-fudan/wyhash).

Generated hashes are compatible with [version final 3](https://github.com/wangyi-fudan/wyhash/tree/a5995b98ebfa7bd38bfadc0919326d2e7aabb805).

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require 189900/wyhash
```

## Usage
With a static call:
```php
use N189900\Wyhash\Hasher;

$hash = Hasher::hash('payload bytes');
```

With a hasher instance:
```php
use N189900\Wyhash\Hasher;

$hasher = new Hasher('123'); // optional custom seed
$hash = $hasher->final('payload bytes');
```

With a series of updates (to support streaming data, optimize memory usage):
```php
use N189900\Wyhash\Hasher;

$hasher = new Hasher();
$hasher->update('first chunk');
$hasher->update('second chunk');
$hash = $hasher->final('optional closing chunk');
```
## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
