<?php
namespace Hierarchy\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\ItemSet;

/**
 * @Entity
 */
class HierarchyGrouping extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column(type="integer", nullable=true)
     */
    protected $parent_grouping;

    /**
     * @Column(type="string", nullable=true)
     */
    protected $label;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\ItemSet"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $item_set;

    /**
     * @ManyToOne(targetEntity="Hierarchy\Entity\Hierarchy")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $hierarchy;

    /**
     * @Column(type="integer")
     */
    protected $position;

    public function getId()
    {
        return $this->id;
    }

    public function setParentGrouping($parentGrouping)
    {
        $this->parent_grouping = $parentGrouping;
    }

    public function getParentGrouping()
    {
        return $this->parent_grouping;
    }

    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setItemSet(?ItemSet $itemSet = null)
    {
        $this->item_set = $itemSet;
    }

    public function getItemSet()
    {
        return $this->item_set;
    }

    public function setHierarchy(Hierarchy $hierarchy)
    {
        $this->hierarchy = $hierarchy;
    }

    public function getHierarchy()
    {
        return $this->hierarchy;
    }

    public function setPosition($position)
    {
        $this->position = $position;
    }

    public function getPosition()
    {
        return $this->position;
    }
}
