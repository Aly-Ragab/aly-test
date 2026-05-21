Feature: Import coffee feed datasets
    In order to update inventory listings
    As a system administrator
    I need to process raw JSONL files cleanly

    Scenario: Processing a valid dataset file cleanly
    #    Given the in-memory database tables are prepared
        When I execute the command "app:import-coffee-feed" with fixture dataset:
      """
      {"sku": "BHT-1", "name": "Behat Certified roast", "in_stock": true}
      {"sku": "BHT-2", "name": "Acceptance Espresso", "in_stock": false}
      """
        Then the command exit code should be 0
        And the in-memory database table "coffee_beans" should contain 2 records
