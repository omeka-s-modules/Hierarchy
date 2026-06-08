<?php
namespace Hierarchy\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function hierarchyAction()
    {
        $view = new ViewModel;
        $site = $this->currentSite();
        $this->setBrowseDefaults('');

        // Retrieve grouping given in URL
        $grouping = $this->params('grouping-id') ? $this->api()->read('hierarchy_grouping', $this->params('grouping-id'))->getContent() : '';
        $groupingItemSet = $grouping->getItemSet();

        // Retrieve items assigned to groupings, including 'child' groupings if hierarchy_group_resources checked in config
        $allGroupings = $this->api()->search('hierarchy_grouping', ['hierarchy' => $grouping->getHierarchy(), 'sort_by' => 'position'])->getContent();
        $itemSetArray = $this->viewHelpers()->get('hierarchyHelper')->getChildItemsets($grouping, $allGroupings);

        foreach ($itemSetArray as $itemSet) {
            $itemSetIDArray[] = $itemSet ? $itemSet->id() : 0;
        }

        if (!empty($itemSetIDArray)) {
            $query = $this->params()->fromQuery();
            $query['item_set_id'] = $itemSetIDArray;
            $response = $this->api()->search('items', $query);
            $page = $this->params()->fromQuery('page', 1);
            $this->paginator($response->getTotalResults(), $page);
            $items = $response->getContent();
        } else {
            $items = [];
        }

        $siteSlug = $this->params('site-slug');

        $view->setVariable('hierarchyGrouping', $grouping);
        $view->setVariable('itemSet', $groupingItemSet);
        $view->setVariable('items', $items);
        $view->setVariable('siteSlug', $siteSlug);
        return $view;
    }
}
