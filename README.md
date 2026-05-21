# Coffee Feed Processor

A Symfony 7 CLI application for importing and processing coffee feed data using asynchronous and synchronous messaging based on user preferences.

## Quick Start

Get the app running in 3 commands:

```bash
docker compose up -d                                                    # Start app and background worker
docker compose exec app php bin/console app:import-coffee-feed coffee_feed.jsonl --async -vvv  # Import data
docker compose exec app sqlite3 var/data.db "SELECT COUNT(*) FROM coffee_beans;"  # Verify import
```

## Overview

This project processes coffee feed data from a local JSONL file and stores it in a SQLite database. It uses Symfony's Messenger component for asynchronous message handling and includes test coverage with PHPUnit and Behat.

**Purpose**: The application efficiently ingests large coffee product feeds by decoupling the read (JSONL parsing) from write (database insertion) operations. Data is chunked into 20-item batches and processed either synchronously for immediate results or asynchronously via a background queue worker for better resource utilization.

## Tech Stack

- **Framework**: Symfony 7
- **PHP Version**: 8.4+
- **Database**: SQLite
- **Messaging**: Symfony Messenger with Doctrine transport
- **Testing**: PHPUnit 13, Behat (BDD)
- **Containerization**: Docker (Alpine-based multi-stage build)

## Project Structure

```
├── src/
│   ├── Command/              # CLI commands
│   ├── Entity/               # Database entities (CoffeeBean, etc.)
│   ├── Message/              # Message classes
│   ├── MessageHandler/       # Message handlers
│   ├── Repository/           # Doctrine repositories
│   └── Kernel.php
├── tests/                    # Test suites
├── config/                   # Configuration files
├── docker-compose.yaml       # Docker Compose setup
├── Dockerfile                # Multi-stage Docker build
├── coffee_feed.jsonl         # Input data file
└── composer.json
```

## Installation

### Prerequisites

- Docker

### Docker Setup

Build and run with Docker Compose:

```bash
docker compose up -d
```

This will:

- Build the multi-stage Docker image
- Set up the environment
- Automatically create the SQLite database via `scripts/entrypoint.sh`
- Start the **app** container (main application)
- Start the **queue_worker** container (dedicated background async worker that runs automatically)

## Docker Services

The docker-compose.yaml includes two services:

- **app**: Main application container for running commands, tests, and direct queries. Keep it running in the background to exec into.
- **queue_worker**: Dedicated background worker container that continuously processes async messages. Runs the `messenger:consume async` command automatically with memory limits (128MB) and batch limits (10 messages) to prevent resource exhaustion.

Both services share the same SQLite database via a named volume (`data_storage`).

## Input Data Format

The `coffee_feed.jsonl` file contains one JSON object per line, each representing a coffee product:

```jsonl
{"sku":"ARABICA-001","name":"Ethiopian Yirgacheffe","in_stock":true,"origin":"Ethiopia","roast":"Medium","roast_date":"2024-01-15"}
{"sku":"ROBUSTA-042","name":"Vietnamese Robusta","in_stock":false,"origin":"Vietnam","roast":"Dark","roast_date":"2024-01-10"}
{"sku":"BLEND-007","name":"Dawn Espresso Blend","in_stock":true,"origin":"Brazil/Colombia","roast":"Medium","roast_date":"2024-01-20"}
```

**Expected fields**: `sku`, `name`, `in_stock`, `origin`, `roast`, `roast_date`

The import command processes these records in 20-item chunks to maintain consistent memory usage regardless of file size.

## Usage

### Import Coffee Feed Data

Run the import command to process the coffee feed:

**Asynchronous (queued for background processing):**

```bash
docker compose exec app php bin/console app:import-coffee-feed coffee_feed.jsonl --async -vvv
```

**Synchronous (immediate processing):**

```bash
docker compose exec app php bin/console app:import-coffee-feed coffee_feed.jsonl -vvv
```

This command:

1. Ensures the `coffee_beans` table exists (creates it if needed)
2. Reads the `coffee_feed.jsonl` file in 20-item chunks
3. Dispatches messages for each chunk (async or sync based on `--async` flag)
4. Stores data in the database via the message handlers

### Monitoring Async Processing

When using the `--async` flag, messages are automatically processed by the `queue_worker` container. To monitor progress:

```bash
docker compose logs queue_worker -f  # View worker logs in real-time
docker compose ps                    # Check if worker is running
```

The worker will continue processing messages in the background until the queue is empty. No additional command is needed.

### Verify Import Results

After importing, verify the data was stored correctly:

**Direct SQLite access (recommended):**

Count total records:
```bash
docker compose exec app sqlite3 var/data.db "SELECT COUNT(*) as total FROM coffee_beans;"
```

Query specific records:
```bash
docker compose exec app sqlite3 var/data.db "SELECT sku, name, in_stock FROM coffee_beans LIMIT 10;"
```

## Testing

### Unit Tests (PHPUnit)

```bash
docker compose exec app vendor/bin/phpunit
```

### Behavior-Driven Tests (Behat)

```bash
docker compose exec app vendor/bin/behat
```

## Development

### Database Initialization

The database is created with a two-tier approach for safety and flexibility:

**1. Docker startup (entrypoint):**
When `docker compose up` runs, `scripts/entrypoint.sh` executes `scripts/init.sql` to create the initial SQLite database if it doesn't exist. This ensures the database file and base structure are ready.

**2. Runtime safeguard (import command):**
Each time you run the import command, `ImportCoffeeFeedCommand` calls `ensureSchemaExists()` which verifies the `coffee_beans` table exists. If it's missing (e.g., fresh database or corrupted state), it's recreated automatically.

**Result**: The `coffee_beans` table is guaranteed to exist before any data import, preventing import failures due to missing schemas.

### Environment Variables

Application configuration via `.env` files:

- `.env` - Shared environment configuration
- `.env.dev` - Development-specific settings
- `.env.test` - Test environment settings

**Key variables**:

```bash
APP_ENV=dev                                                                    # Environment: dev or test
APP_DEBUG=true                                                                 # Enable debug mode
DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db?timeout=5&journal_mode=wal  # SQLite with WAL mode
MESSENGER_TRANSPORT_DSN=doctrine://default                                     # Use Doctrine for message queue
```

**WAL mode** (`journal_mode=wal`) improves SQLite concurrency - critical for async worker + main app writing simultaneously.

### Database

The application uses SQLite. Database files are stored in the `var/` directory.

## Architecture

The application uses a **CQRS (Command Query Responsibility Segregation)** pattern combined with **Event-Driven architecture**:

**Query Side (Read):**

- `ImportCoffeeFeedCommand`: Reads JSONL file via streaming generator, chunks data, dispatches events

**Command Side (Write):**

- `ImportCoffeeChunkHandler`: Subscribes to chunk events and persists data to the database

**Separation & Decoupling:**

- File reading (query) is completely isolated from database writing (command)
- Communication flows through Symfony Messenger as the event bus
- Enables independent scaling of read and write operations

### Event-Driven Flow

1. **ImportCoffeeFeedCommand**: Streams JSONL and dispatches `ImportCoffeeChunkMessage` events
2. **ImportCoffeeChunkMessage**: Event payload containing a batch of coffee bean data
3. **ImportCoffeeChunkHandler**: Event handler that subscribes and processes writes

### Processing Modes

- **Synchronous** (default): Events processed immediately within the same command via the `sync` transport
- **Asynchronous** (`--async` flag): Events dispatched to a queue and processed by background workers

### Memory Efficiency Considerations

The implementation prioritizes memory efficiency for handling large datasets:

1. **Streaming Generator Pattern**: The `streamJsonInChunks()` method uses PHP generators to read the JSONL file line-by-line, keeping only one line in memory at a time instead of loading the entire file

2. **Chunked Processing**: Data is processed in fixed 20-item batches. This prevents memory from accumulating when processing large feeds

3. **Async Queue Offload**: With the `--async` flag, messages are immediately dispatched to the queue (database transport) rather than held in memory waiting for processing

4. **Bulk Inserts**: The handler performs bulk inserts via `bulkInsert()` instead of individual row inserts, reducing round-trips and memory overhead

5. **Decoupled Read/Write**: The command releases memory after dispatching each chunk's event - it doesn't wait for handler completion, allowing garbage collection between iterations

### Data Model

- **CoffeeBean Entity**: Represents a coffee product (SKU, name, stock status, origin, roast, etc.)

## Docker Build

The Dockerfile uses a multi-stage build strategy:

1. **Builder Stage**: Compiles PHP extensions and installs dependencies
2. **Runtime Stage**: Minimal image with only runtime requirements

This approach reduces the final image size while maintaining all necessary functionality.

## Troubleshooting

### Message Processing Issues

**Messages not being processed:**

- Check the worker container is running: `docker compose ps`
- View worker logs: `docker compose logs queue_worker -f`
- The async worker runs automatically in the background when you use `--async` flag

### Database Issues

**Database locked or corrupted:**

Reset by removing the volume (the database will be recreated on next startup):

```bash
docker compose down -v
docker compose up -d
```

### Docker Issues

**Container fails to start or stuck:**

```bash
docker compose down -v  # Remove volumes and containers
docker compose up --build  # Rebuild and start fresh
```

**Permission errors in var/ directory:**

```bash
docker compose exec app chown -R www-data:www-data var/
```

## License

Proprietary

## AI Assistance

This project was developed under human direction with AI assistance (Claude/Gemini) as a tool for code implementation, testing setup, and documentation. All architectural decisions and technical direction were made by the developer.

## Contributing

This is an assessment project. Refer to the project guidelines for contribution policies.
