<?php declare(strict_types=1);

namespace N189900\Wyhash;

use GMP;
use N189900\Wyhash\Exception\AlreadyFinalizedException;

class Hasher
{
    private State $state;
    private string $tail = '';

    public function __construct(string $seed = null)
    {
        $this->state = new State($seed);
    }

    public static function hash(string $buffer, string $seed = null): string
    {
        return (new self($seed))->final($buffer);
    }

    /**
     * @throws AlreadyFinalizedException
     */
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

    /**
     * @throws AlreadyFinalizedException
     */
    public function final(string $buffer = ''): string
    {
        if (isset($buffer[State::UPDATE_SIZE - 1])) {
            $this->update($buffer);
            $buffer = '';
        }

        return $this->state->final($this->tail . $buffer);
    }
}
