<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportCoffeeChunkMessage;
use App\Repository\CoffeeBeanRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportCoffeeChunkHandler
{
    public function __construct(
        private CoffeeBeanRepositoryInterface $coffeeBeanRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ImportCoffeeChunkMessage $message): void
    {
        $chunk = $message->getChunk();
        if (empty($chunk)) {
            return;
        }

        try {
            $this->logger->info(
                'Starting to import Chunk starts with ' . $chunk[0]['sku'] . ' and ends with ' . $chunk[count($chunk) - 1]['sku'] . ' and contains ' . count($chunk) . ' items'
                );
            $this->coffeeBeanRepository->bulkInsert($chunk);
        } catch (\Exception $e) {
            $this->logger->error('Error importing coffee beans: ' . $e->getMessage());
            throw $e;
        }
    }
}
