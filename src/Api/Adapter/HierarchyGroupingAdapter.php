<?php
namespace Hierarchy\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class HierarchyGroupingAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'label' => 'label',
        'position' => 'position',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'label' => 'label',
        'position' => 'position',
        'hierarchy' => 'hierarchy',
        'parent_grouping' => 'parent_grouping',
        'item_set' => 'item_set',
    ];

    public function getEntityClass()
    {
        return 'Hierarchy\Entity\HierarchyGrouping';
    }

    public function getResourceName()
    {
        return 'hierarchy_grouping';
    }

    public function getRepresentationClass()
    {
        return 'Hierarchy\Api\Representation\HierarchyGroupingRepresentation';
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['item_set'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.item_set',
                $this->createNamedParameter($qb, $query['item_set']))
            );
        }
        if (isset($query['parent_grouping'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.parent_grouping',
                $this->createNamedParameter($qb, $query['parent_grouping']))
            );
        }
        if (isset($query['label'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.label',
                $this->createNamedParameter($qb, $query['label']))
            );
        }
        if (isset($query['hierarchy'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.hierarchy',
                $this->createNamedParameter($qb, $query['hierarchy']))
            );
        }
        if (isset($query['position'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.position',
                $this->createNamedParameter($qb, $query['position']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'item_set')) {
            // If no itemSet, save to DB as null
            $itemSet = null;
            if ($request->getValue('item_set')) {
                $itemSet = $this->getAdapter('item_sets')->findEntity($request->getValue('item_set'));
            }
            $entity->setItemSet($itemSet);
        }
        if ($this->shouldHydrate($request, 'parent_grouping')) {
            $entity->setParentGrouping($request->getValue('parent_grouping'));
        }
        if ($this->shouldHydrate($request, 'label')) {
            $entity->setLabel($request->getValue('label'));
        }
        if ($this->shouldHydrate($request, 'hierarchy')) {
            $hierarchy = $this->getAdapter('hierarchy')->findEntity($request->getValue('hierarchy'));
            $entity->setHierarchy($hierarchy);
        }
        // (Re-)order groupings by their order in the input
        static $position = 1;
        if ($this->shouldHydrate($request, 'position')) {
            $entity->setPosition($position++);
        }
    }
}
