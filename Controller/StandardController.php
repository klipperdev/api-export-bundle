<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiExportBundle\Controller;

use Doctrine\ORM\QueryBuilder;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiBundle\View\Transformer\PrePaginateViewTransformerInterface;
use Klipper\Component\Export\Exception\ExportNotFoundException;
use Klipper\Component\Export\Exception\InvalidFormatException;
use Klipper\Component\Export\ExportManagerInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Permission\PermVote;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Standard controller for API Export.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class StandardController
{
    /**
     * @var PrePaginateViewTransformerInterface[]
     */
    private array $viewTransformers = [];

    /**
     * @param PrePaginateViewTransformerInterface[] $viewTransformers
     */
    public function __construct(
        array $viewTransformers
    ) {
        foreach ($viewTransformers as $transformer) {
            if ($transformer instanceof PrePaginateViewTransformerInterface) {
                $this->viewTransformers[] = $transformer;
            }
        }
    }

    /**
     * Standard action to export the entities.
     */
    public function exportAction(
        Request $request,
        ControllerHelper $helper,
        MetadataManagerInterface $metadataManager,
        TranslatorInterface $translator,
        ExportManagerInterface $exportManager,
        string $ext
    ): Response {
        set_time_limit(0);

        $class = $request->attributes->get('_action_class');
        $repo = $helper->getRepository($class);
        $meta = $metadataManager->get($class);
        $defaultRepoMethod = method_exists($repo, 'createTranslatedQueryBuilder')
            ? 'createTranslatedQueryBuilder'
            : 'createQueryBuilder';
        $method = $request->attributes->get('_method_repository', $defaultRepoMethod);
        $alias = $request->attributes->get('_method_repository_alias', 'o');
        $indexBy = $request->attributes->get('_method_repository_index_by');
        $querySortable = $request->headers->has('x-sort')
            ? $request->headers->get('x-sort')
            : $request->query->get('sort');
        $fields = $request->headers->has('x-fields')
            ? $request->headers->get('x-fields')
            : $request->query->get('fields');
        $headerType = $request->headers->has('x-header-type')
            ? $request->headers->get('x-header-type')
            : $request->query->get('header-type');
        $headerLabels = !$headerType || 'label' === $headerType;

        if (!$helper->isGranted(new PermVote('view'), $class) || !$helper->isGranted(new PermVote('export'))) {
            throw $helper->createAccessDeniedException();
        }

        if (empty($querySortable) && !empty($meta->getDefaultSortable())) {
            $sort = [];

            foreach ($meta->getDefaultSortable() as $field => $direction) {
                $sort[] = $field.':'.$direction;
            }

            $request->headers->set('x-sort', implode(', ', $sort));
        }

        /** @var QueryBuilder $qb */
        $qb = $repo->{$method}($alias, $indexBy);
        $query = $qb->getQuery();

        foreach ($this->viewTransformers as $transformer) {
            $transformer->prePaginate($query);
        }

        $query->setFirstResult(null);
        $query->setMaxResults(null);

        try {
            $filename = $this->getExportFilename($translator->trans($meta->getPluralLabel(), [], 'entities'), $ext);
            $exportedData = $exportManager->exportQuery($meta, $query, $this->getFields($fields), $ext, $headerLabels);
            $writer = $exportedData->getWriter();

            $response = new StreamedResponse(
                static function () use ($writer): void {
                    $writer->save('php://output');
                }
            );

            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache', true);
            $response->headers->addCacheControlDirective('must-revalidate', true);
            $response->headers->set('Content-Type', $exportedData->getMimeType());
            $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');

            return $response;
        } catch (InvalidFormatException $e) {
            throw new BadRequestHttpException($translator->trans('klipper_api_export.invalid_format', [
                'format' => $ext,
            ], 'exceptions'), $e);
        } catch (ExportNotFoundException $e) {
            throw new NotFoundHttpException(null, $e);
        } catch (\Throwable $e) {
            throw new BadRequestHttpException($translator->trans('klipper_api_export.error', [], 'exceptions'), $e);
        }
    }

    private function getExportFilename(string $name, string $ext): string
    {
        $date = new \DateTime();

        return utf8_decode($name.' '.$date->format('Y-m-d H-i-s').'.'.$ext);
    }

    /**
     * @return string[]
     */
    private function getFields(?string $requestFields): array
    {
        $fields = array_map('trim', explode(',', $requestFields));

        return 1 === \count($fields) && empty($fields[0]) ? [] : $fields;
    }
}
