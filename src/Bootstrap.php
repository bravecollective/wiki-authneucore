<?php
namespace Brave\CoreConnector;

use Psr\Container\ContainerInterface;

/**
 *
 */
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
        $container = new \Pimple\Container(require_once(ROOT_DIR . '/config/container.php'));
        $this->container = $container;

    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }
}
