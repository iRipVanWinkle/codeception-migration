<?php
namespace Codeception\Extension;

use Codeception\Exception\ExtensionException;
use Codeception\Lib\Connector\Yii2 as Yii2Connector;
use yii\base\InvalidConfigException;
use yii\console\controllers\MigrateController;

class block_stdout_filter extends \php_user_filter {
    function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = '';
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}

class Migration extends \Codeception\Extension
{

    /** @inheritdoc */
    public static $events = array(
        'suite.before' => 'beforeSuite',
        'suite.after' => 'afterSuite'
    );

    /** @inheritdoc */
    protected $config = [
        'cleanup' => true,
        'entryScript' => '',
        'entryUrl' => 'http://localhost/index-test.php',
    ];

    /**
     * @var \Codeception\Lib\Connector\Yii2
     */
    private $client;

    /** @inheritdoc */
    public function _initialize()
    {
        parent::_initialize();

        stream_filter_register("block_stdout", block_stdout_filter::class);
    }

    /**
     *  Event suite.before
     */
    public function beforeSuite()
    {
        $this->run('up');
    }

    /**
     * Event suite.after
     */
    public function afterSuite()
    {
        $this->run('down');
    }

    /**
     * Run command
     * @param string $command either `up` or `down`
     */
    protected function run($command)
    {
        if (array_key_exists('migrationPath', $this->config)) {
            $migrationPath = $this->config['migrationPath'];

            if (is_string($migrationPath)) {
                $migrationPath = [$migrationPath];
            }

            array_walk($migrationPath, [$this, $command === 'up' ? 'runMigrationUp' : 'runMigrationDown']);
        } else {
            $defaultMigrationPath = '@src/migrations';
            try {
                $this->runMigration($defaultMigrationPath, $command);
            } catch (ExtensionException $e) {
                $this->writeln("Maybe, you forgot set migrationPath.");
            }
        }
    }

    /**
     * Run migrate
     * @param string $migrationPath
     * @param string $command either `up` or `down`
     * @throws ExtensionException
     */
    protected function runMigration($migrationPath, $command)
    {
        $app = $this->mockApplication();

        $path = \Yii::getAlias($migrationPath, false);
        if ($path === false) {
            throw new ExtensionException(__CLASS__, "Invalid path alias: " . $migrationPath);
        }

        if (!file_exists($path)) {
            throw new ExtensionException(
                __CLASS__,
                "The migration path does not exist: " . realpath($this->getRootDir() . $path)
            );
        }

        $migrateController = new MigrateController('migrate', $app);
        $migrateController->migrationPath = $migrationPath;
        $migrateController->interactive = false;

        $filter = stream_filter_prepend(STDOUT, "block_stdout");
        ob_start();
        ob_implicit_flush();

        $migrateController->runAction($command);

        ob_clean();
        stream_filter_remove($filter);

        $this->destroyApplication();
    }

    /**
     * Run migration up
     * @param string $migrationPath
     */
    public function runMigrationUp($migrationPath){
        $this->runMigration($migrationPath, 'up');
    }

    /**
     * Run migration up
     * @param string $migrationPath
     */
    public function runMigrationDown($migrationPath){
        $this->runMigration($migrationPath, 'down');
    }

    /**
     * Mocks up the application instance.
     * @return \yii\web\Application|\yii\console\Application the application instance
     * @throws InvalidConfigException if the application configuration is invalid
     */
    protected function mockApplication()
    {
        $entryUrl = $this->config['entryUrl'];
        $entryFile = $this->config['entryScript'] ?: basename($entryUrl);
        $entryScript = $this->config['entryScript'] ?: parse_url($entryUrl, PHP_URL_PATH);
        $this->client = new Yii2Connector();
        $this->client->defaultServerVars = [
            'SCRIPT_FILENAME' => $entryFile,
            'SCRIPT_NAME' => $entryScript,
            'SERVER_NAME' => parse_url($entryUrl, PHP_URL_HOST),
            'SERVER_PORT' => parse_url($entryUrl, PHP_URL_PORT) ?: '80',
        ];
        $this->client->defaultServerVars['HTTPS'] = parse_url($entryUrl, PHP_URL_SCHEME) === 'https';
        $this->client->restoreServerVars();
        $this->client->configFile = $this->getRootDir() . $this->config['appConfig'];

        return $this->client->getApplication();
    }

    /**
     * Destroys the application instance created by [[mockApplication]].
     */
    protected function destroyApplication()
    {
        $this->client->resetPersistentVars();
        if (isset(\Yii::$app) && \Yii::$app->has('session', true)) {
            \Yii::$app->session->close();
        }
        // Close connections if exists
        if (isset(\Yii::$app) && \Yii::$app->has('db', true)) {
            \Yii::$app->db->close();
        }
    }
}
