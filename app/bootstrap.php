<?php

namespace Platovies;

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use App\Core\ExtensionManager;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

define('ROOT_PATH', dirname(__DIR__));
define('THEMES_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'themes');
define('CONFIGS_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'configs');
define('RESOURCES_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'resources');
define('STORAGES_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
define('EXTENSIONS_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'extensions');

final class Bootstrap
{
    /**
     * The Slim application
     *
     * @var \Slim\App
     */
    protected $app;

    /**
     * @var \DI\Container
     */
    protected $container;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    protected function init()
    {
        //
    }

    protected function loadComposer()
    {
        $composerAutoloader = implode(DIRECTORY_SEPARATOR, [ROOT_PATH, 'vendor', 'autoload.php']);
        require_once $composerAutoloader;
    }

    protected function loadSetting($settingName)
    {
        $settingFile = implode(DIRECTORY_SEPARATOR, [CONFIGS_DIR, strtolower($settingName) . '.php']);
        if (file_exists($settingFile)) {
            return require $settingFile;
        }
    }

    protected function setup()
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();
        if (defined('ENV_MODE') && constant('ENV_MODE')) { // Should be set to true in production
            $containerBuilder->enableCompilation(STORAGES_DIR . DIRECTORY_SEPARATOR . 'caches');
        }
        // Set up settings
        $settings = $this->loadSetting('settings');
        $settings($containerBuilder);

        // Set up dependencies
        $dependencies = $this->loadSetting('dependencies');
        $dependencies($containerBuilder);

        // Set up repositories
        $repositories = $this->loadSetting('repositories');
        $repositories($containerBuilder);

        // Instantiate the app
        AppFactory::setContainer(($this->container = $containerBuilder->build()));

        $this->app = AppFactory::create();

        // Register middleware
        $middleware = $this->loadSetting('middleware');
        $middleware($this->app);

        // Register routes
        $routes = $this->loadSetting('routes');
        $routes($this->app);

        // Extension system
        ExtensionManager::loadExtensions($this->app, $this->container);
    }

    public function boot()
    {
        $this->init();
        $this->loadComposer();
        $this->setup();
        $this->run();
    }

    protected function run()
    {
        $this->writeErrorLogs(
            $this->setupHttpErrorHandle()
        );

        // Run App & Emit Response
        $response = $this->app->handle($this->request);
        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($response);
    }

    // Create Error Handler
    protected function setupHttpErrorHandle()
    {
        /** @var SettingsInterface $settings */
        $settings = $this->container->get(SettingsInterface::class);

        $displayErrorDetails = $settings->get('displayErrorDetails');

        // Create Request object from globals
        $serverRequestCreator = ServerRequestCreatorFactory::create();
        $this->request = $serverRequestCreator->createServerRequestFromGlobals();

        $responseFactory = $this->app->getResponseFactory();
        $callableResolver = $this->app->getCallableResolver();
        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

        // Create Shutdown Handler
        $shutdownHandler = new ShutdownHandler($this->request, $errorHandler, $displayErrorDetails);
        register_shutdown_function($shutdownHandler);

        return $errorHandler;
    }

    protected function writeErrorLogs(HttpErrorHandler $errorHandler)
    {
        $settings = $this->container->get(SettingsInterface::class);

        $displayErrorDetails = $settings->get('displayErrorDetails');
        $logError = $settings->get('logError');
        $logErrorDetails = $settings->get('logErrorDetails');

        // Add Error Middleware
        $errorMiddleware = $this->app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }

    public function getApp(): App
    {
        return $this->app;
    }
}
