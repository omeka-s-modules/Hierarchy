<?php

namespace Hierarchy\Controller\SiteAdmin;

use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Form\Form;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $site = $this->currentSite();
        $siteSettings = $this->siteSettings();
        $form = $this->getForm(Form::class);
        $hierarchies = $this->api()->search('hierarchy', ['sort_by' => 'position'])->getContent();
        
        $allHierarchies = [];
        foreach ($hierarchies as $hierarchy) {
            $allHierarchies[$hierarchy->id()] = $hierarchy->getLabel();
        }
        $siteHierarchies = $siteSettings->get('site_hierarchies') ?: [];
        
        // Filter out deleted hierarchies from $siteHierarchies
        $allHierarchyIds = array_keys($allHierarchies);
        $siteHierarchies = array_filter($siteHierarchies, function ($row) use ($allHierarchyIds) {
            return in_array($row['id'], $allHierarchyIds);
        });

        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            if (isset($params['site_hierarchies'])) {
                $siteHierarchies = [];
                foreach ($params['site_hierarchies'] as $siteHierarchyID) {
                    $siteHierarchies[] = ['id' => $siteHierarchyID];
                }
            } else {
                $siteHierarchies = [];
            }
        }
        $siteSettings->set('site_hierarchies', $siteHierarchies);

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('allHierarchies', $allHierarchies);
        $view->setVariable('siteHierarchies', $siteHierarchies);
        return $view;
    }
}
