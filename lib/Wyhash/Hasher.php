<?php declare(strict_types=1);

namespace N189900\Wyhash;

use GMP;

class Hasher
{
    private State $state;
    private string $tail = '';

    public function __construct(string $seed = '0')
    {
        $this->state = new State($seed);
    }

    public static function hash(string $buffer, string $seed = '0'): string
    {
        return (new self($seed))->final($buffer);
    }

    public function update(string $buffer): self
    {
        $this->tail .= $buffer;
        $length = mb_strlen($this->tail, '8bit');

        if ($aligned = $length - ($length % State::UPDATE_SIZE)) {
            $this->state->update(substr($this->tail, 0, $aligned));
            $this->tail = (string) substr($this->tail, $aligned);
        }

        return $this;
    }

    public function final(string $buffer = ''): string
    {
        if (isset($buffer[State::UPDATE_SIZE - 1])) {
            $this->update($buffer);
            $buffer = '';
        }

        return $this->state->final($this->tail . $buffer);
    }
}