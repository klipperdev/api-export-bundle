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

use Klipper\Component\Metadata\ActionMetadataBuilder;
use Klipper\Component\Metadata\Guess\GuessObjectConfigInterface;
use Klipper\Component\Metadata\ObjectMetadataBuilderInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class GuessActionsMetadata implements GuessObjectConfigInterface
{
    public function guessObjectConfig(ObjectMetadataBuilderInterface $builder): void
    {
        if (false === $builder->getBuildDefaultActions()) {
            return;
        }

        $this->addAction($builder, 'export');
    }

    private function addAction(ObjectMetadataBuilderInterface $metadata, string $name): void
    {
        if (!\in_array($name, $metadata->getExcludedDefaultActions(), true)) {
            $metadata->addAction(new ActionMetadataBuilder($name));
        }
    }
}
