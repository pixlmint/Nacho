<?php

namespace Nacho;

use DI\Container;
use DI\ContainerBuilder;
use Nacho\Contracts\DataHandlerInterface;
use Nacho\Contracts\NachoCoreInterface;
use Nacho\Contracts\PageManagerInterface;
use Nacho\Contracts\RequestInterface;
use Nacho\Contracts\Response;
use Nacho\Contracts\RouteFinderInterface;
use Nacho\Contracts\RouteInterface;
use Nacho\Contracts\UserHandlerInterface;
use Nacho\Helpers\ConfigurationContainer;
use Nacho\Helpers\DataHandler;
use Nacho\Helpers\FileHelper;
use Nacho\Helpers\HookHandler;
use Nacho\Helpers\MetaHelper;
use Nacho\Helpers\NachoContainerBuilder;
use Nacho\Helpers\PageManager;
use Nacho\Helpers\PageSecurityHelper;
use Nacho\Helpers\RouteFinder;
use Nacho\Hooks\NachoAnchors\PostCallActionAnchor;
use Nacho\Hooks\NachoAnchors\PreCallActionAnchor;
use Nacho\Hooks\NachoAnchors\PrePrintResponseAnchor;
use Nacho\Models\ContainerDefinitionsHolder;
use Nacho\Models\HttpResponse;
use Nacho\Models\Request;
use Nacho\ORM\RepositoryManager;
use Nacho\ORM\RepositoryManagerInterface;
use Nacho\Security\JsonUserHandler;
use Nacho\Hooks\NachoAnchors\PostFindRouteAnchor;
use Nacho\Security\UserRepository;
use function DI\create;
use function DI\factory;
use function DI\get;

class Nacho implements NachoCoreInterface
{
    public static Container $container;

    public function init(array|NachoContainerBuilder $containerConfig = []): void
    {
        if (is_array($containerConfig)) {
            $builder = $this->getContainerBuilder();
        } elseif ($containerConfig instanceof NachoContainerBuilder) {
            $builder = $containerConfig;
        } else {
            throw new \Exception('Invalid container config, must be array or ' . NachoContainerBuilder::class . ' instance');
        }
        $builder->addDefinitions($this->getContainerConfig());
        self::$container = $builder->build();

        /** @var HookHandler $hookHandler */
        $hookHandler = self::$container->get(HookHandler::class);

        $hookHandler->registerAnchor(PostFindRouteAnchor::getName(), new PostFindRouteAnchor());
        $hookHandler->registerAnchor(PreCallActionAnchor::getName(), new PreCallActionAnchor());
        $hookHandler->registerAnchor(PostCallActionAnchor::getName(), new PostCallActionAnchor());
        $hookHandler->registerAnchor(PrePrintResponseAnchor::getName(), new PrePrintResponseAnchor());
    }

    public function getContainerBuilder(): NachoContainerBuilder
    {
        $builder = new NachoContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions($this->getContainerConfig());
        return $builder;
    }

    public function run(array $config = []): void
    {
        $this->loadConfig($config);
        $path = $this->getPath();

        $configuration = self::$container->get(ConfigurationContainer::class);

        $hookHandler = self::$container->get(HookHandler::class);
        $hookHandler->registerConfigHooks($configuration->getHooks());

        $routeFinder = self::$container->get(RouteFinderInterface::class);
        $route = $routeFinder->getRoute($path);
        $route = $hookHandler->executeHook(PostFindRouteAnchor::getName(), ['route' => $route]);

        self::$container->get(RequestInterface::class)->setRoute($route);

        $hookHandler->executeHook(PreCallActionAnchor::getName(), []);
        $content = $this->getContent();
        $content = $hookHandler->executeHook(PostCallActionAnchor::getName(), ['returnedResponse' => $content]);

        $content = $hookHandler->executeHook(PrePrintResponseAnchor::getName(), ['response' => $content]);
        self::$container->get(RepositoryManagerInterface::class)->close();
        $this->printContent($content);
    }

    private function loadConfig(array $config = []): void
    {
        if (!$config) {
            $config = include_once($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        }
        $configContainer = self::$container->get(ConfigurationContainer::class);
        $configContainer->init($config);
    }

    private function printContent(Response $content): void
    {
        $content->send();
    }

    private function getContent(): Response
    {
        /** @var RouteInterface $route */
        $route = self::$container->get(RouteInterface::class);
        $userHandler = self::$container->get(UserHandlerInterface::class);
        if (!$userHandler->isGranted($route->getMinRole())) {
            header('Http/1.1 401');
            die();
        }
        $controllerClass = $route->getController();
        $controller = self::$container->get($controllerClass);
        $function = $route->getFunction();
        if (!method_exists($controller, $function)) {
            return new HttpResponse("{$function} does not exist in {$controllerClass}", 404);
        }

        return self::$container->call([$controller, $function]);
    }

    public function getPath(): string
    {
        $path = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'];

        if (str_ends_with($path, '/')) {
            $path = substr($path, 0, strlen($path) - 1);
        }

        return $path;
    }

    private function getContainerConfig(): ContainerDefinitionsHolder
    {
        return new ContainerDefinitionsHolder(-1, [
            'path' => factory([self::class, 'getPath']),
            DataHandlerInterface::class => create(DataHandler::class),
//            UserHandlerInterface::class => factory(function (ConfigurationContainer $config) {
//                $securityConfig = $config->getSecurity();
//                if (key_exists('userHandler', $securityConfig)) {
//                    return $securityConfig['userHandler'];
//                } else {
//                    return JsonUserHandler::class;
//                }
//            }),
            UserHandlerInterface::class => create(JsonUserHandler::class),
            PageManagerInterface::class => create(PageManager::class)->constructor(
                get(MetaHelper::class),
                get(PageSecurityHelper::class),
                get(FileHelper::class),
                get(UserHandlerInterface::class),
            ),
            RepositoryManagerInterface::class => create(RepositoryManager::class)->constructor(
                get(DataHandlerInterface::class)
            ),
            RouteInterface::class => factory(function (Container $c) {
                $finder = $c->get(RouteFinderInterface::class);
                return $finder->getRoute($c->get('path'));
            }),
            RequestInterface::class => factory(function (Container $c) {
                $request = $c->get(Request::class);
                $route = $c->get(RouteInterface::class);
                $request->setRoute($route);
                return $request;
            }),
            ConfigurationContainer::class => create(ConfigurationContainer::class),
            RouteFinderInterface::class => create(RouteFinder::class),
            UserRepository::class => create(UserRepository::class),
            MetaHelper::class => create(MetaHelper::class),
        ]);
    }
}