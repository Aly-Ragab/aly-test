<?php

declare(strict_types=1);

namespace App\Message;

/**
 *
 * @author Aly
 */
final readonly class ImportCoffeeChunkMessage
{
    public function __construct(
        private array $chunk
    ) {
    }

    public function getChunk(): array {
        return $this->chunk;
    }
}
