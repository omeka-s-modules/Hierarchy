<?php
namespace Hierarchy;

use Omeka\Module\AbstractModule;
use Omeka\Entity\Item;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Fieldset;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\Event;
use Hierarchy\Form\ConfigForm;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
    
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
    
        // Allow all users to access public hierarchies
        $acl->allow(
            null,
            ['Hierarchy\Entity\Hierarchy',
            'Hierarchy\Entity\HierarchyGrouping',
            'Hierarchy\Api\Adapter\HierarchyGroupingAdapter',
            'Hierarchy\Api\Adapter\HierarchyAdapter',
            ],
        );
        $acl->allow(null, 'Hierarchy\Controller\Site\Index', 'hierarchy');
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec('CREATE TABLE hierarchy_grouping (id INT AUTO_INCREMENT NOT NULL, item_set_id INT DEFAULT NULL, hierarchy_id INT NOT NULL, parent_grouping INT DEFAULT NULL, `label` VARCHAR(255) DEFAULT NULL, position INT NOT NULL, INDEX IDX_DCDE57FF960278D7 (item_set_id), INDEX IDX_DCDE57FF582A8328 (hierarchy_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
        $connection->exec('CREATE TABLE hierarchy (id INT AUTO_INCREMENT NOT NULL, `label` VARCHAR(255) DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
        $connection->exec('ALTER TABLE hierarchy_grouping ADD CONSTRAINT FK_DCDE57FF960278D7 FOREIGN KEY (item_set_id) REFERENCES item_set (id) ON DELETE SET NULL;');
        $connection->exec('ALTER TABLE hierarchy_grouping ADD CONSTRAINT FK_DCDE57FF582A8328 FOREIGN KEY (hierarchy_id) REFERENCES hierarchy (id) ON DELETE CASCADE;');

        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites', [])->getContent();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');

        // Turn all hierarchy site view settings on by default
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $siteSettings->set('hierarchy_show_label', '1');
            $siteSettings->set('hierarchy_show_count', '1');
            $siteSettings->set('hierarchy_group_resources', '1');
        }
    }
    
    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec('ALTER TABLE hierarchy_grouping DROP FOREIGN KEY FK_DCDE57FF960278D7');
        $connection->exec('ALTER TABLE hierarchy_grouping DROP FOREIGN KEY FK_DCDE57FF582A8328');
        $connection->exec('DROP TABLE hierarchy');
        $connection->exec('DROP TABLE hierarchy_grouping');
        
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites', [])->getContent();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');

        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $siteSettings->delete('hierarchy_show_label');
            $siteSettings->delete('hierarchy_show_count');
            $siteSettings->delete('hierarchy_group_resources');
            $siteSettings->delete('site_hierarchies');
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_elements',
            [$this, 'addSiteSettings']
        );

        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_input_filters',
            [$this, 'addSiteSettingsInputFilters']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'addItemAdminHierarchies']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.sidebar',
            [$this, 'addItemSetAdminHierarchies']
        );
    }

    /**
     * Add elements to the site settings form.
     *
     * @param Event $event
     */
    public function addSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $form = $event->getTarget();

        $groups = $form->getOption('element_groups');
        $groups['hierarchy'] = 'Hierarchy'; // @translate
        $form->setOption('element_groups', $groups);

        $form->add([
            'type' => 'checkbox',
            'name' => 'hierarchy_show_label',
            'options' => [
                        'element_group' => 'hierarchy',
                        'label' => 'Show hierarchy label', // @translate
                        'info' => 'If checked, assigned label will display as hierarchy header on public pages.', // @translate
                    ],
            'attributes' => [
                'id' => 'show-label',
                'value' => $siteSettings->get('hierarchy_show_label', true),
            ],
        ]);

        $form->add([
            'type' => 'checkbox',
            'name' => 'hierarchy_show_count',
            'options' => [
                        'element_group' => 'hierarchy',
                        'label' => 'Show hierarchy resource counts', // @translate
                        'info' => 'If checked, hierarchy groupings will show # of resources within currently assigned itemSet.', // @translate
                    ],
            'attributes' => [
                'id' => 'show-count',
                'value' => $siteSettings->get('hierarchy_show_count', true),
            ],
        ]);

        $form->add([
            'type' => 'checkbox',
            'name' => 'hierarchy_group_resources',
            'options' => [
                        'element_group' => 'hierarchy',
                        'label' => 'Combine hierarchy resources', // @translate
                        'info' => 'If checked, groupings will display resources of all child groupings in resource counts and on hierarchy grouping browse pages.', // @translate
                    ],
            'attributes' => [
                'id' => 'group-resources',
                'value' => $siteSettings->get('hierarchy_group_resources', true),
            ],
        ]);
    }

    /**
     * Add input filters to the site settings form.
     *
     * @param Event $event
     */
    public function addSiteSettingsInputFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->add([
            'name' => 'hierarchy_show_label',
            'required' => false,
            'allow_empty' => true,
        ]);
        $inputFilter->add([
            'name' => 'hierarchy_show_count',
            'required' => false,
            'allow_empty' => true,
        ]);
        $inputFilter->add([
            'name' => 'hierarchy_group_resources',
            'required' => false,
            'allow_empty' => true,
        ]);
    }

    // Add relevant hierarchy nested lists to item admin display sidebar
    public function addItemAdminHierarchies(Event $event)
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/hierarchy.css', 'Hierarchy'));
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $printedHierarchies = array();
        $itemGroupings = array();
        if ($view->item->itemSets()) {
            // Get order for printing item's sets from position on hierarchy page
            $itemSetOrder = array_filter($api->search('hierarchy_grouping', ['sort_by' => 'position'], ['returnScalar' => 'item_set'])->getContent());
            $itemSets = array_replace(array_flip($itemSetOrder), $view->item->itemSets());

            foreach ($itemSets as $currentItemSet) {
                if (is_numeric($currentItemSet)) {
                    continue;
                }
                $groupings = $api->search('hierarchy_grouping', ['item_set' => $currentItemSet->id(), 'sort_by' => 'position'])->getContent();
                foreach ($groupings as $grouping) {
                    if (!in_array($grouping->getHierarchy()->getId(), $printedHierarchies)) {
                        // Only print hierarchies with multiple item set groupings once
                        $itemGroupings[][] = $grouping;
                        $printedHierarchies[] = $grouping->getHierarchy()->getId();
                    }
                }
            }
            // Return if no groupings/hierarchies found
            if (!$itemGroupings) {
                return;
            }

            echo '<div class="meta-group">';
            echo '<h4>' . $view->translate('Hierarchies') . '</h4>';
            echo '<div class="hierarchies value">';

            foreach ($itemGroupings as $groupings) {
                $view->hierarchyHelper()->buildNestedList($groupings, $currentItemSet, $view->item);
            }
            echo '</div></div>';
        }
    }

    // Add relevant hierarchy nested lists to item set admin display sidebar
    public function addItemSetAdminHierarchies(Event $event)
    {
        $view = $event->getTarget();
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $groupings = $api->search('hierarchy_grouping', ['item_set' => $view->resource->id(), 'sort_by' => 'position'])->getContent();

        // Return if no groupings/hierarchies found
        if (!$groupings) {
            return;
        }

        echo '<div class="meta-group">';
        echo '<h4>' . $view->translate('Hierarchies') . '</h4>';
        echo '<div class="hierarchies value">';
        $view->hierarchyHelper()->buildNestedList($groupings, $view->resource, $view->item);
        echo '</div></div>';
    }
}
