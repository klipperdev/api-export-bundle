<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiExportBundle\Metadata\Guess;

use Klipper\Component\Metadata\ActionMetadataBuilderInterface;
use Klipper\Component\Metadata\Guess\GuessActionConfigInterface;
use Klipper\Component\Metadata\ObjectMetadataBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class GuessActionMetadata implements GuessActionConfigInterface
{
    public const ACTIONS = [
        'export' => 'guessExport',
    ];

    public function guessActionConfig(ActionMetadataBuilderInterface $builder): void
    {
        $actionName = $builder->getName();

        if (isset(static::ACTIONS[$actionName])) {
            $this->{static::ACTIONS[$actionName]}($builder, $builder->getParent());
        }
    }

    /**
     * Guess the export action.
     *
     * @param ActionMetadataBuilderInterface $builder The action metadata builder
     */
    public function guessExport(ActionMetadataBuilderInterface $builder): void
    {
        $builder->addDefaults([
            '_action' => 'export',
            '_action_class' => $builder->getParent()->getClass(),
        ]);
        $builder->addRequirements([
            'ext' => 'csv|html|ods|xls|xlsx',
        ]);

        if (empty($builder->getMethods())) {
            $builder->setMethods([Request::METHOD_GET]);
        }

        if (null === $builder->getPath()) {
            $builder->setPath($this->getBasePath($builder->getParent()));
        }

        if (null === $builder->getController()) {
            $builder->setController('Klipper\Bundle\ApiExportBundle\Controller\StandardController::exportAction');
        }
    }

    /**
     * Get the base path of route.
     *
     * @param ObjectMetadataBuilderInterface $builder The object metadata builder
     * @param string                         $prefix  The path prefix
     */
    private function getBasePath(ObjectMetadataBuilderInterface $builder, string $prefix = ''): string
    {
        return '/{organization}/'.('' !== $prefix ? $prefix.'/' : '').$builder->getPluralName().'.{ext}';
    }
}
