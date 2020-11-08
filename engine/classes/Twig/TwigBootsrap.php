<?php

namespace NG\Twig;

use NG\Core\Container;

class TwigBootsrap
{
    public static function boot(Container $container)
    {
        global $twigLoader, $twig;

        $twigLoader = new NGTwigLoader(root);

        $twig = new NGTwigEnvironment($twigLoader, [
            'cache'       => root.'cache/twig/',
            'auto_reload' => true,
            'autoescape'  => false,
            'charset'     => 'UTF-8',
        ]);

        // Register twig into container
        $container->set('twigLoader', $twigLoader);
        $container->set('twig', $twig);
    }
}