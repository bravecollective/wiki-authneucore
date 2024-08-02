<?php

namespace Brave\CoreConnector;

use Pimple\Container;
use Psr\Container\ContainerInterface;

class Bootstrap
{
    protected ContainerInterface $container;

    public function __construct()
    {
        $this->container = new \Pimple\Psr11\Container(
            new Container(include(ROOT_DIR . '/config/container.php'))
        );
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
