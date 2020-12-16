<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiExportBundle\DependencyInjection\Compiler;

use Klipper\Bundle\ApiBundle\View\Transformer\PrePaginateViewTransformerInterface;
use Klipper\Bundle\ApiExportBundle\Controller\StandardController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ViewTransformerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(StandardController::class)) {
            return;
        }

        $def = $container->getDefinition(StandardController::class);
        $transformers = $def->getArgument(0);

        foreach ($this->findAndSortTaggedServices('klipper_api.view_transformer', $container) as $service) {
            $serviceDef = $container->getDefinition((string) $service);
            $serviceClass = (string) $serviceDef->getClass();

            if (is_a($serviceClass, PrePaginateViewTransformerInterface::class, true)) {
                $transformers[] = $service;
            }
        }

        $def->setArgument(0, $transformers);
    }
}
