<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;


use DI\Container;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Extensions\Composer\Utils;
use EnjoysCMS\Core\Modules\Module;
use EnjoysCMS\Core\Setting\Setting;
use EnjoysCMS\Module\Catalog\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\TwigFunction;

abstract class PublicController extends AbstractController
{
    protected Module $module;
    protected Config $config;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->config = $container->get(Config::class);

        $this->module = new Module(
            Utils::parseComposerJson(
                __DIR__ . '/../../composer.json'
            )
        );
        $this->twig->addGlobal('module', $this->module);
        $this->twig->addGlobal('config', $this->config);
        $this->twig->addFunction(new TwigFunction('callstatic', 'forward_static_call_array'));
    }

}
