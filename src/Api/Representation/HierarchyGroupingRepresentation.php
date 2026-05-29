<?php
namespace Hierarchy\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class HierarchyGroupingRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-hierarchy:HierarchyGrouping';
    }

    public function getJsonLd()
    {
        return [
            'o-module-hierarchy:parent_grouping' => $this->getParentGrouping(),
            'o-module-hierarchy:label' => $this->getLabel(),
            'o:item_set' => $this->getItemSet(),
            'o-module-hierarchy:hierarchy' => $this->getAdapter('hierarchy')->getRepresentation($this->resource->getHierarchy()),
            'o-module-hierarchy:position' => $this->getPosition(),
        ];
    }

    public function getParentGrouping()
    {
        return $this->resource->getParentGrouping();
    }

    public function getLabel()
    {
        return $this->resource->getLabel();
    }

    public function getItemSet()
    {
        return $this->getAdapter('item_sets')->getRepresentation($this->resource->getItemSet());
    }

    // Returns the raw Hierarchy entity — used internally by view helpers for
    // identity comparison and direct API queries.
    public function getHierarchy()
    {
        return $this->resource->getHierarchy();
    }

    public function getPosition()
    {
        return $this->resource->getPosition();
    }
}
