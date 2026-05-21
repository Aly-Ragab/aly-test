<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoffeeBean;

interface CoffeeBeanRepositoryInterface
{
    public function countAll(): int;
    public function bulkInsert(array $chunk): void;
}
