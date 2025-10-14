<?php
namespace Hierarchy\Controller;

use Hierarchy\Form\ConfigForm;
use Hierarchy\Service\HierarchyUpdater\HierarchyUpdater;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Laminas\Form\Form;
use Omeka\Api\Exception as ApiException;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    protected $form;

    protected $hierarchyUpdater;

    public function __construct(HierarchyUpdater $hierarchyUpdater)
    {
        $this->hierarchyUpdater = $hierarchyUpdater;
    }

    public function indexAction()
    {
        $this->form = $this->getForm(Form::class)->setAttribute('id', 'hierarchy-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            if (isset($formData['layout']) && $formData['layout'] == 'hierarchy') {
                $content = $this->viewHelpers()->get('hierarchyHelper')->hierarchyFormElement($this->form);
                $this->response = $this->getResponse();
                $this->response->setContent($content);
                return $this->response;
            } else {
                $this->form->setData($formData);
                if ($this->form->isValid()) {
                    unset($formData['form_csrf']);
                    foreach ($formData['hierarchy'] as $hierarchyData) {
                        $this->hierarchyUpdater->updateHierarchy($hierarchyData);
                    }
                    if ($this->response) {
                        $this->messenger()->addSuccess('Hierarchy successfully updated'); // @translate
                        return $this->redirect()->refresh();
                    }
                } else {
                    $this->messenger()->addFormErrors($this->form);
                }
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $this->form);
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
}
