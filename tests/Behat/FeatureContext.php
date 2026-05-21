<?php
declare(strict_types=1);

namespace App\Tests\Behat;

use App\Repository\CoffeeBeanRepositoryInterface;
use App\Repository\InMemoryCoffeeBeanRepository;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class FeatureContext implements Context
{
    private ?CommandTester $commandTester = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly CoffeeBeanRepositoryInterface $repository
    ) {
    }

    /**
     * @Given the in-memory database tables are prepared
     */
    public function theInMemoryDatabaseTablesArePrepared(): void
    {

    }

    /**
     * @When I execute the command :commandName with fixture dataset:
     */
    public function iExecuteTheCommandWithFixtureDataset(string $commandName, PyStringNode $stringDataset): void
    {
        $fixturePath = sys_get_temp_dir() . '/behat_feed.jsonl';
        file_put_contents($fixturePath, $stringDataset->getRaw());

        $application = new Application($this->kernel);
        $command = $application->find($commandName);

        $this->commandTester = new CommandTester($command);
        $this->commandTester->execute([
            'filepath' => $fixturePath
        ]);

        unlink($fixturePath);
    }

    /**
     * @Then the command exit code should be :expectedCode
     */
    public function theCommandExitCodeShouldBe(int $expectedCode): void
    {
        if ($this->commandTester->getStatusCode() !== $expectedCode) {
            throw new \RuntimeException(sprintf('Expected code %d, got %d', $expectedCode, $this->commandTester->getStatusCode()));
        }
    }

    /**
     * @Then the in-memory database table :tableName should contain :expectedCount records
     */
    public function theInMemoryDatabaseTableShouldContainRecords(string $tableName, int $expectedCount): void
    {
        if ($this->repository->countAll() !== $expectedCount) {
            throw new \Exception("Expected {$expectedCount} records, but found " . $this->repository->countAll());
        }
    }
}
