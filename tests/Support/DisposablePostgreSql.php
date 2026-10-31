<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\ConfigurationUrlParser;
use PDO;
use RuntimeException;

final class DisposablePostgreSql
{
    public const string BOOTSTRAP = 'intelledger_ci_bootstrap';

    public const string ROLE = 'intelledger_ci';

    public const string PORT = '65432';

    public const string OPT_IN = 'disposable-postgresql-only';

    private ?PDO $admin = null;

    private ?string $database = null;

    private ?int $oid = null;

    private string $nonce;

    private bool $worker = false;

    private ?int $parentPid = null;

    /** @param array<string, mixed> $connection */
    public function __construct(private readonly array $connection)
    {
        $this->nonce = bin2hex(random_bytes(16));
    }

    /** @return array<string, string> */
    public static function environment(): array
    {
        $sources = [getenv(), $_ENV, $_SERVER];
        $values = [];
        foreach (array_unique(array_merge(...array_map(array_keys(...), $sources))) as $name) {
            if (! preg_match('/\A(?:APP_ENV|APP_.*_CACHE|INTELLEDGER_PG_INTEGRATION|DB_.*|DATABASE_URL|PG.*)\z/', $name)) {
                continue;
            }
            $candidates = [];
            foreach ($sources as $source) {
                if (array_key_exists($name, $source)) {
                    self::require(is_string($source[$name]));
                    $candidates[] = $source[$name];
                }
            }
            self::require(count(array_unique($candidates)) === 1);
            $values[$name] = $candidates[0];
        }

        return $values;
    }

    /**
     * Connection-free validation before Laravel or PDO can load development settings.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    public static function configuration(array $values): array
    {
        $expected = ['APP_ENV' => 'testing', 'INTELLEDGER_PG_INTEGRATION' => self::OPT_IN,
            'PG_TEST_HOST' => '127.0.0.1', 'PG_TEST_PORT' => self::PORT,
            'PG_TEST_DATABASE' => self::BOOTSTRAP, 'PG_TEST_USERNAME' => self::ROLE];
        foreach ($expected as $key => $value) {
            self::require(($values[$key] ?? null) === $value);
        }
        self::require(isset($values['PG_TEST_PASSWORD']) && $values['PG_TEST_PASSWORD'] !== ''
            && strlen($values['PG_TEST_PASSWORD']) <= 128 && ! str_contains($values['PG_TEST_PASSWORD'], "\0"));
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $expected) && $key !== 'PG_TEST_PASSWORD') {
                self::require($value === '');
            }
        }

        return ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => self::PORT,
            'database' => self::BOOTSTRAP, 'username' => self::ROLE, 'password' => $values['PG_TEST_PASSWORD'],
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'disable'];
    }

    /** @param array<string, mixed> $expected */
    public static function assertConfiguration(Application $app, array $expected): void
    {
        $config = $app->make('config');
        $connection = $config->get('database.connections.pgsql');
        self::require($app->environment('testing') && $config->get('app.env') === 'testing'
            && ! $app->configurationIsCached() && $config->get('database.default') === 'pgsql' && is_array($connection));
        self::require(array_diff(array_keys($connection), [...array_keys($expected), 'url']) === []);
        self::require(($connection['url'] ?? '') === '' || $connection['url'] === null);
        $resolved = (new ConfigurationUrlParser)->parseConfiguration($connection);
        foreach ($expected as $key => $value) {
            self::require(($resolved[$key] ?? null) === $value);
        }
    }

    /**
     * Empty environment/cache paths prevent development .env and cache loading.
     *
     * @param  array<string, mixed>|null  $receipt  A live parent capability for a non-owning worker only.
     */
    public function prepare(Application $app, string $directory, ?array $receipt = null): void
    {
        if ($receipt !== null) {
            self::validateWorkerReceipt($receipt);
            $this->worker = true;
            $this->database = $receipt['database'];
            $this->oid = $receipt['oid'];
            $this->nonce = $receipt['nonce'];
            $this->parentPid = $receipt['parent_pid'];
        }
        self::require(is_dir($directory) && scandir($directory) === ['.', '..']);
        $app->useEnvironmentPath($directory);
        if (preg_match('/\A[A-Za-z]:/', $directory, $drive) === 1) {
            $app->addAbsoluteCachePathPrefix($drive[0]);
        }
        $values = ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost', 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $this->connection['host'], 'DB_PORT' => $this->connection['port'],
            'DB_DATABASE' => self::BOOTSTRAP, 'DB_USERNAME' => self::ROLE,
            'DB_PASSWORD' => $this->connection['password'], 'DB_URL' => '', 'DB_SSLMODE' => 'disable',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4'];
        foreach (['CONFIG', 'ROUTES', 'EVENTS', 'SERVICES', 'PACKAGES'] as $cache) {
            $values['APP_'.$cache.'_CACHE'] = $directory.'/'.strtolower($cache).'.php';
        }
        foreach ($values as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        $app->bind('db', fn () => throw new RuntimeException('PostgreSQL preflight must precede database resolution.'));
        $app->beforeBootstrapping(RegisterProviders::class, function (Application $app): void {
            self::assertConfiguration($app, $this->connection);
            if ($this->worker) {
                $this->admin = $this->connect(self::BOOTSTRAP);
                $this->assertReceipt();
                $statement = $this->admin->prepare('SELECT count(*) FROM pg_stat_activity WHERE pid=? AND datname=? AND usename=? AND application_name=?');
                $statement->execute([$this->parentPid, self::BOOTSTRAP, self::ROLE, 'intelledger-lease-'.$this->nonce]);
                self::require((int) $statement->fetchColumn() === 1);
            } else {
                $this->provision();
            }
            $target = array_replace($this->connection, ['database' => $this->database]);
            $app->make('config')->set('database.connections', ['pgsql' => $target]);
            self::assertConfiguration($app, $target);
        });
    }

    private function connect(string $database): PDO
    {
        $pdo = new PDO('pgsql:host=127.0.0.1;port='.self::PORT.';dbname='.$database.';connect_timeout=5;sslmode=disable',
            self::ROLE, $this->connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("SET lock_timeout='3s'; SET statement_timeout='8s'");

        return $pdo;
    }

    private function provision(): void
    {
        $this->admin = $this->connect(self::BOOTSTRAP);
        $identity = $this->admin->query("SELECT current_database() AS database, current_user AS actor, current_setting('server_version_num')::int / 10000 AS major")->fetch(PDO::FETCH_ASSOC);
        self::require($identity['database'] === self::BOOTSTRAP && $identity['actor'] === self::ROLE && (int) $identity['major'] === 18);
        self::require((int) $this->admin->query("SELECT count(*) FROM pg_database WHERE NOT datistemplate AND datname NOT IN ('postgres', 'intelledger_ci_bootstrap')")->fetchColumn() === 0);
        self::require((int) $this->admin->query("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind IN ('r','v','m','p','S','f')")->fetchColumn() === 0);
        $database = 'intelledger_ci_'.bin2hex(random_bytes(12));
        $this->admin->exec('CREATE DATABASE "'.$database.'" TEMPLATE template0');
        $this->database = $database;
        $statement = $this->admin->prepare('SELECT oid FROM pg_database WHERE datname=?');
        $statement->execute([$database]);
        $this->oid = (int) $statement->fetchColumn();
        $this->admin->exec('COMMENT ON DATABASE "'.$database.'" IS '.$this->admin->quote($this->nonce));
        $statement = $this->admin->prepare("SELECT set_config('application_name', ?, false)");
        $statement->execute(['intelledger-lease-'.$this->nonce]);
        $this->parentPid = (int) $this->admin->query('SELECT pg_backend_pid()')->fetchColumn();
        $this->admin->query('SELECT pg_advisory_lock('.$this->oid.')');
        $pdo = $this->connect($database);
        self::require($pdo->query('SELECT current_database()')->fetchColumn() === $database);
        self::require((int) $pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn() === 0);
    }

    /** Check immediately before migrations and each group of financial mutations. */
    public function assertOwned(Application $app): void
    {
        self::require($this->database !== null && $this->oid !== null && $this->admin !== null);
        self::assertConfiguration($app, array_replace($this->connection, ['database' => $this->database]));
        $this->assertReceipt();
        $connection = $app->make('db')->connection('pgsql');
        $identity = $connection->selectOne('SELECT current_database() AS name, current_user AS actor');
        self::require($connection->getDriverName() === 'pgsql' && $identity->name === $this->database && $identity->actor === self::ROLE);
    }

    private function assertReceipt(): void
    {
        self::require($this->admin !== null && $this->database !== null && $this->oid !== null
            && preg_match('/\Aintelledger_ci_[a-f0-9]{24}\z/', $this->database) === 1);
        $statement = $this->admin->prepare("SELECT oid, pg_get_userbyid(datdba) AS owner, shobj_description(oid, 'pg_database') AS receipt FROM pg_database WHERE datname=?");
        $statement->execute([$this->database]);
        $identity = $statement->fetch(PDO::FETCH_ASSOC);
        self::require(is_array($identity) && (int) $identity['oid'] === $this->oid
            && $identity['owner'] === self::ROLE && hash_equals($this->nonce, $identity['receipt'] ?? ''));
    }

    /** @param array<string, mixed> $receipt */
    public static function validateWorkerReceipt(array $receipt): void
    {
        self::require(count($receipt) === 4 && is_string($receipt['database'] ?? null)
            && preg_match('/\Aintelledger_ci_[a-f0-9]{24}\z/', $receipt['database']) === 1
            && is_int($receipt['oid'] ?? null) && $receipt['oid'] > 0
            && is_int($receipt['parent_pid'] ?? null) && $receipt['parent_pid'] > 0
            && is_string($receipt['nonce'] ?? null) && preg_match('/\A[a-f0-9]{32}\z/', $receipt['nonce']) === 1);
    }

    /** A live parent's in-memory capability, never a recovery/adoption file.
     * @return array{database: string, oid: int, nonce: string, parent_pid: int}
     */
    public function workerReceipt(Application $app): array
    {
        self::require(! $this->worker && $this->parentPid !== null);
        $this->assertOwned($app);

        return ['database' => $this->database, 'oid' => $this->oid, 'nonce' => $this->nonce, 'parent_pid' => $this->parentPid];
    }

    /** Hold a staged worker on the parent's bootstrap-session barrier, not a timed sleep. */
    public function waitForParent(): void
    {
        self::require($this->worker && $this->admin !== null && $this->oid !== null);
        $this->assertReceipt();
        $this->admin->exec("SET lock_timeout='10s'; SET statement_timeout='12s'");
        $this->admin->query('SELECT pg_advisory_lock('.$this->oid.')');
        throw new RuntimeException('The live parent barrier was released unexpectedly.');
    }

    public static function clearEnvironmentDirectory(?string $directory): void
    {
        if ($directory === null) {
            return;
        }
        self::require(! is_link($directory));
        if (! is_dir($directory)) {
            return;
        }
        self::require(preg_match('/\Apg-environment-[a-f0-9]{24}\z/', basename($directory)) === 1
            && realpath(dirname($directory)) === realpath(dirname(__DIR__, 2).'/.phpunit.cache')
            && realpath($directory) === realpath(dirname($directory)).DIRECTORY_SEPARATOR.basename($directory));
        $entries = scandir($directory);
        self::require(is_array($entries));
        $names = array_diff($entries, ['.', '..']);
        foreach ($names as $name) {
            $file = $directory.'/'.$name;
            self::require(! is_link($file) && is_file($file)
                && preg_match('/\A(?:config|routes|events|services|packages)\.php\z/', $name) === 1);
        }
        foreach ($names as $name) {
            self::require(unlink($directory.'/'.$name));
        }
        self::require(rmdir($directory));
    }

    /** Drop only this invocation's database, without terminating other sessions. */
    public function cleanup(?Application $app): void
    {
        self::require(! $this->worker);
        if ($this->database === null) {
            return;
        }
        $this->assertReceipt();
        if ($app !== null && $app->resolved('db')) {
            $app->make('db')->purge('pgsql');
        }
        $database = $this->database;
        $statement = $this->admin->prepare('SELECT count(*) FROM pg_stat_activity WHERE datname=?');
        $statement->execute([$database]);
        self::require((int) $statement->fetchColumn() === 0);
        $this->admin->exec('DROP DATABASE "'.$database.'"');
        $statement = $this->admin->prepare('SELECT count(*) FROM pg_database WHERE datname=?');
        $statement->execute([$database]);
        self::require((int) $statement->fetchColumn() === 0);
        $this->database = null;
        $this->admin = null;
    }

    private static function require(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('PostgreSQL integration safety check failed; refusing database mutation.');
        }
    }
}
