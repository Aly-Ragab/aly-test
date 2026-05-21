<?php

namespace App\Command;

use App\Message\ImportCoffeeChunkMessage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AsCommand(name: 'app:import-coffee-feed')]
class ImportCoffeeFeedCommand extends Command
{
    private Connection $connection;
    private MessageBusInterface $messageBus;

    public function __construct(Connection $connection, MessageBusInterface $messageBus)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->messageBus = $messageBus;
    }

    protected function configure(): void
    {
        $this->addArgument('filepath', InputArgument::REQUIRED)
            ->addOption('async', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filepath = $input->getArgument('filepath');
        $isAsync = $input->getOption('async');

        if (!file_exists($filepath) || !is_readable($filepath)) {
            $io->error('File not found or not readable.');
            return Command::FAILURE;
        }

        $this->ensureSchemaExists();
        $io->title(sprintf('Starting Ingestion Feed Pipeline (%s Mode)...', $isAsync ? 'Asynchronous' : 'Synchronous'));

        $chunkSize = 20;
        $dispatchedBatchesCount = 0;

        try {
            foreach ($this->streamJsonInChunks($filepath, $chunkSize) as $chunk) {
                $message = new ImportCoffeeChunkMessage($chunk);
                $stamps = $isAsync ? [] : [new TransportNamesStamp('sync')];
                $this->messageBus->dispatch($message, $stamps);
                $dispatchedBatchesCount++;
            }

            if ($isAsync) {
                $io->success(
                    sprintf(
                        'Successfully chunked and dispatched %d message payloads to the background broker.',
                        $dispatchedBatchesCount
                    )
                );
            } else {
                $io->success(
                    sprintf(
                        'Successfully executed %d synchronous chunk insert transactions inline.',
                        $dispatchedBatchesCount
                    )
                );
            }
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ingestion pipeline halted: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function ensureSchemaExists(): void
    {
        try {
            $this->connection->executeStatement(
                "
            CREATE TABLE IF NOT EXISTS coffee_beans (
                sku TEXT PRIMARY KEY, name TEXT NOT NULL, in_stock INTEGER NOT NULL, description TEXT NULL,
                origin TEXT, roast TEXT, tasting_score TEXT, flavor_notes TEXT, tags TEXT, variants TEXT
            )
        "
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create database schema: ' . $e->getMessage());
        }
    }

    private function streamJsonInChunks(string $filepath, int $chunkSize): \Generator {
        $handle = fopen($filepath, 'r');
        $chunk = [];
        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                $data = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $chunk[] = $data;
                }
                if (count($chunk) === $chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }
            if (!empty($chunk)) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }
}
