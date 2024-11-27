<?php
namespace Hierarchy\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Form\Element\Select;
use Laminas\View\Renderer\PhpRenderer;

class Hierarchy extends AbstractBlockLayout
{

    public function getLabel() {
        return 'Hierarchy'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        $allHierarchies = $view->api()->search('hierarchy', ['sort_by' => 'position'])->getContent();

        // Only show groupings/hierarchies assigned by Hierarchy option in site's context menu
        $siteHierarchiesArray = $view->siteSetting('site_hierarchies');

        $hierarchies = array();
        foreach ($allHierarchies as $hierarchy) {
            if ($siteHierarchiesArray) {
                foreach ($siteHierarchiesArray as $siteHierarchy) {
                    if ($hierarchy->id() == $siteHierarchy['id']) {
                        $hierarchies[] = $hierarchy;
                    }
                }
            }
        }

        $options = [];
        foreach ($hierarchies as $hierarchy) {
            $options[$hierarchy->id()] = $hierarchy->getLabel() ?: '[Untitled]';
        }

        if (count($hierarchies) === 0) {
            $options[] = $view->translate('(No site hierarchies assigned)');
        }

        $setHierarchy = $block ? $block->dataValue('Hierarchy') : '';

        $select = new Select('o:block[__blockIndex__][o:data][Hierarchy]');
        $select->setValueOptions($options)->setValue($setHierarchy);

        $html = '<div class="field">';
        $html .= '<div class="field-meta"><label>' . $view->translate('Hierarchy') . '</label></div>';
        $html .= '<div class="inputs">' . $view->formSelect($select) . '</div>';
        $html .= '</div>';
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {	
        $hierarchy = $view->api()->searchOne('hierarchy', ['id' => $block->dataValue('Hierarchy')])->getContent();
        if (!$hierarchy) {
            return '';
        }

        $hierarchyData = $view->hierarchyHelper()->toJstree($hierarchy);
        $allGroupings = $view->api()->search('hierarchy_grouping', ['hierarchy' => $hierarchy->id(), 'sort_by' => 'position'])->getContent();

        return $view->partial('hierarchy/common/block-layout/hierarchy-public', [
            'hierarchy' => $hierarchy,
            'hierarchyData' => $hierarchyData,
            'allGroupings' => $allGroupings,
        ]);
    }
}
