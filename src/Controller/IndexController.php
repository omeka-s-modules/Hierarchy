<?php
namespace Hierarchy\Controller;

use Hierarchy\Form\ConfigForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Laminas\Form\Form;
use Omeka\Api\Exception as ApiException;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{

    public function indexAction()
    {
        $form = $this->getForm(Form::class)->setAttribute('id', 'hierarchy-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            if (isset($formData['layout']) && $formData['layout'] == 'hierarchy') {
                $content = $this->viewHelpers()->get('hierarchyHelper')->hierarchyFormElement($form);
                $response = $this->getResponse();
                $response->setContent($content);
                return $response;
            } else {
                $form->setData($formData);
                if ($form->isValid()) {
                    unset($formData['form_csrf']);
                    foreach ($formData['hierarchy'] as $hierarchyData) {
                        // Check if hierarchy already exists before adding/removing/updating
                        $hierarchyID = $hierarchyData['id'] ?: 0;
                        $content = $this->api()->searchOne('hierarchy', ['id' => $hierarchyID])->getContent();
                        if (!empty($hierarchyData['delete'])) {
                            if (!empty($content)) {
                                $response = $this->api($form)->delete('hierarchy', $hierarchyData['id']);

                                // Remove hierarchy from any sites it is assigned to
                                $siteSettings = $this->siteSettings();
                                $sites = $this->api()->search('sites')->getContent();
                                foreach ($sites as $site) {
                                    $newSiteHierarchies = [];
                                    $siteHierarchyArray = $siteSettings->get('site_hierarchies', '', $site->id());
                                    foreach ($siteHierarchyArray as $siteHierarchy) {
                                        if ($siteHierarchy['id'] != $hierarchyData['id']) {
                                            $newSiteHierarchies[] = $siteHierarchy;
                                        }
                                    }
                                    $siteSettings->set('site_hierarchies', $newSiteHierarchies, $site->id());
                                }
                            } else {
                                continue;
                            }
                        } else if (empty($content)) {
                            $hierarchyResponse = $this->api($form)->create('hierarchy', $hierarchyData);
                            $hierarchyData['id'] = $hierarchyResponse ? $hierarchyResponse->getContent()->id() : '';
                            $response = $this->updateTreeData($hierarchyData);
                        } else {
                            $hierarchyResponse = $this->api($form)->update('hierarchy', $hierarchyID, $hierarchyData);
                            $response = $this->updateTreeData($hierarchyData);
                        }
                    }
                    if ($response) {
                        $this->messenger()->addSuccess('Hierarchy successfully updated'); // @translate
                        return $this->redirect()->refresh();
                    }
                } else {
                    $this->messenger()->addFormErrors($form);
                }
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function groupingFormAction()
    {
        $view = new ViewModel;
        $view->setTerminal(true);

        $itemSetArray = [];
        $itemSets = $this->api()->search('item_sets')->getContent();
        foreach ($itemSets as $itemSet) {
            if ($itemSet->title() != '')
            $itemSetArray[$itemSet->id()] = $itemSet->title();
        }

        $view->setVariable('itemSetArray', $itemSetArray);
        $view->setVariable('data', $this->params()->fromPost());
        return $view;
    }

    public function updateTreeData($hierarchyData)
    {
        $hierarchyID = $hierarchyData['id'];
        $iterate = function ($groupings) use (&$iterate, $hierarchyID, &$parentGrouping, &$childCount) {
            foreach ($groupings as $grouping) {
                $groupingID = isset($grouping['data']['groupingID']) ? $grouping['data']['groupingID'] : null;
                $groupingDelete = isset($grouping['state']['disabled']) ? $grouping['state']['disabled'] : false;
                // Ignore private item sets if returned
                $groupingData['item_set'] = (!isset($grouping['data']['itemSet']) || $grouping['data']['itemSet'] == 'privateHGset') ? null : $grouping['data']['itemSet'];
                $groupingData['hierarchy'] = $hierarchyID;
                $groupingData['parent_grouping'] = $parentGrouping ?: '';
                $groupingData['label'] = isset($grouping['data']['label']) ? $grouping['data']['label'] : '';
                $groupingData['position'] = isset($grouping['data']['position']) ? $grouping['data']['position'] : '';
                if ($groupingDelete) {
                    // Delete groupings with disabled flag
                    if (isset($groupingID)) {
                        $response = $this->api()->delete('hierarchy_grouping', $groupingID);
                    } else {
                        // Ignore if newly created grouping marked for deletion
                        continue;
                    }
                } else if (isset($groupingID)) {
                    // Update existing grouping metadata
                    $response = $this->api()->update('hierarchy_grouping', $groupingID, $groupingData);
                } else {
                    // Create new grouping
                    $response = $this->api()->create('hierarchy_grouping', $groupingData);
                }
                if (count($grouping['children']) > 0) {
                    // Handle multidimensional hierarchies by saving/retrieving previous state
                    $prevGrouping = $parentGrouping ?: null;
                    $childCount = count($grouping['children']);
                    // Store ID of parent with each child
                    $parentGrouping = $response ? $response->getContent()->id() : '';
                    $iterate($grouping['children']);
                    $parentGrouping = $prevGrouping;
                } elseif ($childCount >= 1) {
                    // Keep $parentGrouping the same if iterating 'sibling'
                    continue;
                } else {
                    $parentGrouping = '';
                }
            }
        };
        $iterate(json_decode($hierarchyData['data'], true));
        return true;
    }
}
