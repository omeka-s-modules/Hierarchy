<?php
namespace Hierarchy\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use Laminas\View\Renderer\PhpRenderer;

class Hierarchy implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Hierarchy'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return ['items', 'item_sets'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        $api = $resource->getServiceLocator()->get('Omeka\ApiManager');
        return $view->partial('hierarchy/common/resource-page-block-layout/hierarchy', ['resource' => $resource, 'api' => $api]);
    }
}
