<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoffeeBean;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoffeeBeanRepository extends ServiceEntityRepository implements CoffeeBeanRepositoryInterface
{
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, CoffeeBean::class);
    }

    public function bulkInsert(array $chunk): void
    {

        try {
            $this->getEntityManager()->beginTransaction();

            $sql = 'INSERT INTO coffee_beans (sku, name, in_stock, description, origin, roast, tasting_score, flavor_notes, tags, variants) VALUES ';
            $valuesQueries = [];
            $parameters = [];

            foreach ($chunk as $index => $beanData) {
                $valuesQueries[] = "(:sku_$index, :name_$index, :in_stock_$index, :desc_$index, :origin_$index, :roast_$index, :score_$index, :notes_$index, :tags_$index, :variants_$index)";
                $parameters["sku_$index"] = $beanData['sku'];
                $parameters["name_$index"] = $beanData['name'];
                $parameters["in_stock_$index"] = (int)($beanData['in_stock'] ?? 0);
                $parameters["desc_$index"] = $beanData['description'] ?? null;
                $parameters["origin_$index"] = json_encode($beanData['origin'] ?? []);
                $parameters["roast_$index"] = json_encode($beanData['roast'] ?? []);
                $parameters["score_$index"] = json_encode($beanData['tasting_score'] ?? []);
                $parameters["notes_$index"] = json_encode($beanData['flavor_notes'] ?? []);
                $parameters["tags_$index"] = json_encode($beanData['tags'] ?? []);
                $parameters["variants_$index"] = json_encode($beanData['variants'] ?? []);
            }

            $sql .= implode(', ', $valuesQueries);
            $sql .= ' ON CONFLICT(sku) DO UPDATE SET name = excluded.name, in_stock = excluded.in_stock, description = excluded.description, origin = excluded.origin, roast = excluded.roast, tasting_score = excluded.tasting_score, flavor_notes = excluded.flavor_notes, tags = excluded.tags, variants = excluded.variants';

            $this->getEntityManager()->getConnection()->executeStatement($sql, $parameters);
            $this->getEntityManager()->getConnection()->commit();
        } catch (\Exception $e) {
            $this->getEntityManager()->getConnection()->rollBack();
            throw $e;
        }

    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
