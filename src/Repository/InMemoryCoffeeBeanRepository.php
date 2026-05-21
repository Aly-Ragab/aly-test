<?php
declare(strict_types=1);

namespace App\Repository;

class InMemoryCoffeeBeanRepository implements CoffeeBeanRepositoryInterface
{
    // A completely volatile array sitting purely in RAM
    private array $storage = [];

    public function countAll(): int
    {
        return count($this->storage);
    }

    public function bulkInsert(array $chunk): void
    {
        foreach ($chunk as $bean) {
           $this->storage[] = $bean;
        }
    }
}
