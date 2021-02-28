<?php


namespace Ramsterhad\DeepDanbooruTagAssist\Application\Router\Config;


use Ramsterhad\DeepDanbooruTagAssist\Application\Router\Config\Exception\UnknownRouteException;
use Symfony\Component\Yaml\Yaml;

class RouterConfig
{
    private array $yamlConfig;

    /** @var Route[] */
    private array $routes;

    private Route $defaultRoute;

    public function loadConfig()
    {
        $this->yamlConfig = Yaml::parseFile(__DIR__ . '/routes.yaml');
    }

    public function parseConfigFileToRoutes()
    {
        foreach ($this->yamlConfig as $alias => $routeConfig) {

            $route = new Route();
            $route->setAlias($alias);
            $route->setFullQualifiedNamespacePath($routeConfig['namespace']);

            if ($alias === '_default') {
                $route->addMethod($routeConfig['action']);
                $this->defaultRoute = $route;
            } else {
                foreach ($routeConfig['actions'] as $action) {
                    $route->addMethod($action);
                }
            }

            $this->routes[$alias] = $route;
        }
    }

    public function getYamlConfig(): array
    {
        return $this->yamlConfig;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function hasAlias(string $alias): bool
    {
        if (array_key_exists($alias, $this->routes)) {
            return true;
        }
        return false;
    }

    public function getRouteByAlias(string $alias): Route
    {
        if (!$this->hasAlias($alias)) {
            throw new UnknownRouteException();
        }

        return $this->routes[$alias];
    }

    public function getDefaultRoute(): Route
    {
        return $this->defaultRoute;
    }
}