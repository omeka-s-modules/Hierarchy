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
                    foreach($formData['hierarchy'] as $hierarchyData) {
                        // Check if hierarchy already exists before adding/removing/updating
                        $hierarchyID = $hierarchyData['id'] ?: 0;
                        $content = $this->api()->searchOne('hierarchy', ['id' => $hierarchyID])->getContent();
                        if (!empty($hierarchyData['delete'])) {
                            if (!empty($content)) {
                                $response = $this->api($form)->delete('hierarchy', $hierarchyData['id']);
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
                $groupingID = $grouping['data']['groupingID'] ?: null;
                $groupingData['item_set'] = $grouping['data']['itemSet'] ?: null;
                $groupingData['hierarchy'] = $hierarchyID;
                $groupingData['parent_grouping'] = $parentGrouping ?: '';
                $groupingData['label'] = $grouping['data']['label'];
                $groupingData['position'] = $grouping['data']['position'] ?: '';
                if ($groupingID) {
                    // Update existing grouping metadata
                    $response = $this->api()->update('hierarchy_grouping', $groupingID, $groupingData);
                } else {
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
