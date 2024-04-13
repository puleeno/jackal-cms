<?php

namespace App\Http;

use Quagga\Contracts\ApplicationConstract;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

class Kernel
{
    /**
     * @var \Quagga\Contracts\ApplicationConstract
     */
    private $app;

    /**
     * Constructor.
     *
     * @param ApplicationConstract $app
     */
    public function __construct(ApplicationConstract $app)
    {
        $this->app = $app;
    }

    /**
     * Configure the application.
     */
    public function configure()
    {
        $this->app->booted();
    }


    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Psr\Http\Message\RequestInterface  $request
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return void
     */
    public function terminate(RequestInterface $request, ResponseInterface $response)
    {
        $this->terminateMiddleware($request, $response);

        $this->app->terminate();
    }



    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Psr\Http\Message\RequestInterface  $request
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return void
     */
    protected function terminateMiddleware($request, $response)
    {
        // placeholder to terminal middlewares
    }
}
