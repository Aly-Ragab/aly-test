<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\CoffeeBeanRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportCoffeeFeedCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private CoffeeBeanRepositoryInterface $repository;
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Boot the kernel
        self::bootKernel();

        // 2. Get the test container (this now works)
        $container = self::getContainer();

        // 3. Create the application using the booted kernel
        $application = new Application(self::$kernel);
        $command = $application->find('app:import-coffee-feed');
        $this->commandTester = new CommandTester($command);

        // 4. Fetch the repository (test container allows non-public services)
        $this->repository = $container->get(CoffeeBeanRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->fixturePath)) {
            unlink($this->fixturePath);
        }
    }

    public function testExecuteSuccessfulSynchronousImport(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/test_feed.jsonl';
        file_put_contents(
            $this->fixturePath,
            json_encode([
                'sku' => 'TEST-SKU-1',
                'name' => 'In-Memory Espresso',
                'in_stock' => true
            ]) . "\n"
        );

        $this->commandTester->execute(['filepath' => $this->fixturePath]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Successfully executed', $this->commandTester->getDisplay());

        $this->assertEquals(1, $this->repository->countAll());
    }

}
