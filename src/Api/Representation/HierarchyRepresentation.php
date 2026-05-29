<?php
namespace Hierarchy\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class HierarchyRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-hierarchy:Hierarchy';
    }

    public function getJsonLd()
    {
        return [
            'o-module-hierarchy:label' => $this->getLabel(),
            'o-module-hierarchy:position' => $this->getPosition(),
        ];
    }

    public function getLabel()
    {
        return $this->resource->getLabel();
    }

    public function getPosition()
    {
        return $this->resource->getPosition();
    }
}
