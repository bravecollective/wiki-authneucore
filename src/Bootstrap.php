<?php

namespace Brave\CoreConnector;

use Pimple\Container;
use Psr\Container\ContainerInterface;

class Bootstrap
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Bootstrap constructor
     */
    public function __construct()
    {
        $this->container = new \Pimple\Psr11\Container(new Container(include(ROOT_DIR . '/config/container.php')));
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }
}
