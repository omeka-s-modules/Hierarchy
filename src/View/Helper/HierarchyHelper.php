<?php
namespace Hierarchy\View\Helper;

use Laminas\Form\View\Helper\AbstractHelper;
use Omeka\Mvc\Exception;
use Omeka\Form\Element as OmekaElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

class HierarchyHelper extends AbstractHelper
{
    /**
     * Return the HTML necessary to render all hierarchy form elements.
     *
     */
    public function hierarchyFormElements($form)
    {
        $hierarchies = $this->getView()->api()->search('hierarchy', ['sort_by' => 'position'])->getContent();

        $html = '<div id="hierarchies">';
        foreach ($hierarchies as $hierarchy) {
            $html .= $this->hierarchyFormElement($form, $hierarchy);
        }
        $html .= '</div>';
        return $html;
    }

    public function hierarchyFormElement($form, $hierarchy = null) {
        $view = $this->getView();
        return $view->partial('hierarchy/common/hierarchy', [
            'label' => $hierarchy ? $hierarchy->getLabel() : null,
            'jsTreeData' => $hierarchy ? $this->toJstree($hierarchy) : '',
            'hierarchyContent' => $this->formElement($form, $hierarchy),
        ]);
    }

    public function formElement($form, $hierarchy = null) {
        $defaults = [
            'id' => '',
            'label' => '',
            'data' => '',
            'position' => '',
            'delete' => 0,
        ];
        $data = $hierarchy ? $hierarchy->getJsonLd() + $defaults : $defaults;
        
        $form->add([
            'name' => 'hierarchy[__hierarchyIndex__][label]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Hierarchy Label', // @translate
            ],
        ]);

        $form->add([
            'name' => 'hierarchy[__hierarchyIndex__][id]',
            'type' => 'hidden',
        ]);

        $form->add([
            'name' => 'hierarchy[__hierarchyIndex__][data]',
            'type' => 'hidden',
        ]);

        $form->add([
            'name' => 'hierarchy[__hierarchyIndex__][position]',
            'type' => 'hidden',
        ]);

        $form->add([
            'name' => 'hierarchy[__hierarchyIndex__][delete]',
            'type' => 'hidden',
        ]);

        $form->setData([
            'hierarchy[__hierarchyIndex__][label]' => $data['label'],
            'hierarchy[__hierarchyIndex__][id]' => $data['id'],
            'hierarchy[__hierarchyIndex__][data]' => $data['data'],
            'hierarchy[__hierarchyIndex__][position]' => $data['position'],
            'hierarchy[__hierarchyIndex__][delete]' => $data['delete'],
        ]);

        $view = $this->getView();
        return $view->formCollection($form);
    }

    public function toJstree($hierarchy)
    {
        $hierarchyID = $hierarchy->id();
        $allGroupings = $this->getView()->api()->search('hierarchy_grouping', ['hierarchy' => $hierarchyID, 'sort_by' => 'position'])->getContent();

        $iterate = function ($groupings) use (&$iterate, &$allGroupings, &$childNode, &$childCount, &$prevCount) {
            $jstreeNodes = [];
            foreach ($groupings as $key => $grouping) {
                // Skip groupings with parent unless on 'children' subarray iteration
                if ($grouping->getParentGrouping() != 0 && !$childNode) {
                    continue;
                }
                // Show itemSet count in jstree node label if hierarchy_show_count checked in config
                $itemSetCount = $this->getView()->setting('hierarchy_show_count') ? $this->itemSetCount($grouping, $allGroupings) : '';
                if ($grouping->getItemSet()) {
                    try {
                        // If no grouping label, show itemSet title as grouping heading
                        $nodeText = $grouping->getLabel() ? $grouping->getLabel() . $itemSetCount : $grouping->getItemSet()->title() . $itemSetCount;
                        $groupingItemSet = $grouping->getItemSet()->id();
                    } catch (\Exception $e) {
                        // Catch and ignore private itemSets
                        $nodeText = $grouping->getLabel() ? $grouping->getLabel() . $itemSetCount : $itemSetCount;
                        $groupingItemSet = 'privateHGset';
                    }
                } else {
                    $nodeText = $grouping->getLabel() ? $grouping->getLabel() . $itemSetCount : $itemSetCount;
                    $groupingItemSet = '';
                }
                $jstreeNodes[$key] = [
                    'text' => $nodeText,
                    'data' => [
                        'label' => $grouping->getLabel() ?: '',
                        'itemSet' => $groupingItemSet,
                        'groupingID' => $grouping->id(),
                        'position' => $grouping->getPosition(),
                    ],
                ];
                // Return any groupings with current grouping ID as parent
                $childArray = array_filter($allGroupings, function($child) use($grouping) {
                    return $child->getParentGrouping() == $grouping->id();
                });
                if (count($childArray) > 0) {
                    // Handle multidimensional hierarchies by saving/retrieving previous state
                    $prevNode = $childNode;
                    $childNode = true;
                    $childCount = count($childArray);
                    $jstreeNodes[$key]['children'] = $iterate($childArray);
                    $childNode = $prevNode;
                } elseif ($childCount >= 1) {
                    // Keep $childNode the same if iterating 'sibling'
                    continue;
                } else {
                    $childNode = false;
                }
            }
            return array_values($jstreeNodes);
        };

        return $iterate($allGroupings);
    }

    public function itemSetCount($currentGrouping, $allGroupings)
    {
        $view = $this->getView();

        $itemSetArray = $this->getChildItemsets($currentGrouping, $allGroupings);

        $itemCount = 0;

        if (!$itemSetArray) {
            $resourceArray = array();
        } else {
            $resourceArray = array();
            foreach ($itemSetArray as $itemSet) {
                $response = $view->api()
                ->search('items', [
                    'item_set_id' => $itemSet->id(),
                ]);
                foreach ($response->getContent() as $itemSetResource) {
                    $resourceArray[] = $itemSetResource->id();
                }
            }
        }

        $resourceArray = array_unique($resourceArray);
        $itemCount = count($resourceArray);

        if ($itemCount == 0) {
            // Hide count if 0 items returned
            return null;
        } else if ($itemCount > 1) {
            return ' (' . $itemCount . ' items)';
        } else {
            return ' (' . $itemCount . ' item)';
        }
    }

    public function getChildItemsets($currentGrouping, $allGroupings)
    {
        $view = $this->getView();
        $itemSetArray = array();
        $this->siteItemSetArray = array();

        if ($view->currentSite()) {
            $siteItemSets = $view->currentSite()->siteItemSets();
            $siteItemSetArray = array();
            foreach ($siteItemSets as $siteItemSet) {
                $this->siteItemSetArray[] = $siteItemSet->itemSet()->id();
            }
        }

        // Gather all 'child' itemSets if hierarchy_group_resources checked in site config OR if called from admin side
        if ($view->status()->isAdminRequest() || $view->siteSetting('hierarchy_group_resources')) {
            $iterate = function ($currentGrouping) use ($view, $allGroupings, &$iterate, &$itemSetArray) {
                if ($currentGrouping->getItemSet()) {
                    try {
                        // Ignore item sets not assigned to site
                        if ($this->siteItemSetArray && !in_array($currentGrouping->getItemSet()->id(), $this->siteItemSetArray)) {
                            throw new Exception\NotFoundException;
                        }
                        $itemSet = $currentGrouping->getItemSet() ? $view->api()->read('item_sets', $currentGrouping->getItemSet()->id())->getContent() : null;
                        $itemSetArray[] = $itemSet;
                    } catch (\Exception $e) {
                        // Move on to children -- itemSet not found or private
                    }
                }
                // Return any groupings with current grouping as parent
                $childArray = array_filter($allGroupings, function($child) use($currentGrouping) {
                    return $child->getParentGrouping() == $currentGrouping->id();
                });
                foreach ($childArray as $childGrouping) {
                    $iterate($childGrouping);
                }
            };
            $iterate($currentGrouping);
        } else {
            if ($currentGrouping->getItemSet()) {
                try {
                    // Ignore item sets not assigned to site
                    if ($this->siteItemSetArray && !in_array($currentGrouping->getItemSet()->id(), $this->siteItemSetArray)) {
                        throw new Exception\NotFoundException;
                    }
                    $itemSet = $currentGrouping->getItemSet() ? $view->api()->read('item_sets', $currentGrouping->getItemSet()->id())->getContent() : null;
                    $itemSetArray[] = $itemSet;
                } catch (\Exception $e) {
                    // Move on -- itemSet not found or private
                }
            }
        }

        // Remove duplicate and empty item sets
        $itemSetArray = array_filter(array_unique($itemSetArray, SORT_REGULAR));

        return $itemSetArray;
    }

    public function buildNestedList(array $groupings, $currentItemSet, $item = null, $public = false)
    {
        $view = $this->getView();
        $view->headLink()->appendStylesheet($view->assetUrl('css/hierarchy.css', 'Hierarchy'));
        $filterLocale = (bool) $view->siteSetting('filter_locale_values');
        $lang = $view->lang();
        $valueLang = $filterLocale ? [$lang, ''] : null;
        static $printedGroupings = [];
        static $itemSetCounter = 0;
        $itemSetCounter++;
        $iterate = function ($groupings) use ($view, $currentItemSet, $item, $public, $valueLang, &$itemSetCounter, &$iterate, &$allGroupings, &$printedGroupings, &$currentHierarchy, &$childCount) {
            foreach ($groupings as $key => $grouping) {
                // Continue if grouping has already been printed
                if (isset($printedGroupings) && in_array($grouping, $printedGroupings)) {
                    continue;
                }

                if ($currentHierarchy != $grouping->getHierarchy()) {
                    // Close HTML list and value if previous hierarchy or itemSet iteration
                    if (isset($currentHierarchy) || $itemSetCounter > 1) {
                        echo '</ul>';
                    }
                    $currentHierarchy = $grouping->getHierarchy();
                    if ($view->status()->isAdminRequest() || $view->siteSetting('hierarchy_show_label')) {
                        echo '<span class="hierarchy-label">' . $currentHierarchy->getLabel() . '</span>';
                    }
                    echo '<ul class="hierarchy-list">';
                    // Show label if hierarchy_show_label checked in site config OR if called from admin side
                    $allGroupings = $this->getView()->api()->search('hierarchy_grouping', ['hierarchy' => $currentHierarchy, 'sort_by' => 'position'])->getContent();
                    $iterate($allGroupings, $currentItemSet);
                    continue;
                }

                if ($grouping->getParentGrouping() != 0) {
                    // $iterate through any groupings with current grouping as child
                    $parentArray = array_filter($allGroupings, function($parent) use($grouping) {
                        return $parent->id() == $grouping->getParentGrouping();
                    });
                    if (count($parentArray) > 0) {
                        $iterate($parentArray, $currentItemSet);
                        continue;
                    }
                }

                if ($grouping->getItemSet()) {
                    try {
                        // If no grouping label, show itemSet title as grouping heading
                        $displayTitle = $grouping->getItemSet()->displayTitle(null, $valueLang);
                        $groupingLabel = $grouping->getLabel() ?: $displayTitle;
                    } catch (\Exception $e) {
                        // itemSet not found or private
                        $groupingLabel = $grouping->getLabel() ? $grouping->getLabel() . $view->translate(' (Private)') : $view->translate('[Untitled] (Private)');
                    }
                } else {
                    $groupingLabel = $grouping->getLabel() ? $grouping->getLabel() : $view->translate('[Untitled]');
                }

                try {
                    $setID = $grouping->getItemSet() ? $grouping->getItemSet()->id() : '';
                    $itemSet = $this->getView()->api()->read('item_sets', $setID)->getContent();
                } catch (\Exception $e) {
                    // Print groupings without assigned itemSet
                    $itemSet = null;
                    // Show (combined child) itemSet count if called from admin side
                    if ($view->status()->isAdminRequest() || $view->siteSetting('hierarchy_show_count')) {
                        $itemSetCount = $this->itemSetCount($grouping, $allGroupings);
                    } else {
                        $itemSetCount = '';
                    }
                    // Show itemSet count if hierarchy_show_count checked in config
                    $itemSetShow = $view->siteSetting('hierarchy_show_count') ? $itemSetCount : '';

                    if ($itemSetCount != null && (strpos($groupingLabel, '(Private)') === false)) {
                        if ($public) {
                            echo '<li>' . $view->hyperlink($groupingLabel, $view->url('site/hierarchy', ['site-slug' => $view->currentSite()->slug(), 'grouping-id' => $grouping->id()])) . $itemSetShow;
                        } else {
                            // Don't link to hierarchies without items to display (private etc.)
                            echo '<li>' . $groupingLabel . $itemSetShow;
                        }
                    } else if (!empty($groupingLabel)) {
                         echo '<li>' . $groupingLabel;
                    }
                }

                if (!is_null($itemSet)) {
                    $itemSetArray = isset($item) ? $item->itemSets() : array($currentItemSet);
                    foreach ($itemSetArray as $itemItemSet) {
                        $itemSetIDArray[] = $itemItemSet->id();
                    }

                    // Show (combined child) itemSet count if hierarchy_show_count checked in site config OR if called from admin side
                    if ($view->status()->isAdminRequest() || $view->siteSetting('hierarchy_show_count')) {
                        $itemSetCount = $this->itemSetCount($grouping, $allGroupings);
                    } else {
                        $itemSetCount = '';
                    }

                    if ($itemSetCount != null && (strpos($groupingLabel, '(Private)') === false)) {
                        if (in_array($grouping->getItemSet()->id(), $itemSetIDArray)) {
                            // Bold groupings with current itemSet assigned
                            if ($public) {
                                echo '<li><b>' . $view->hyperlink($groupingLabel, $view->url('site/hierarchy', ['site-slug' => $view->currentSite()->slug(), 'grouping-id' => $grouping->id()])) . '</b>' . $itemSetCount;
                            } else {
                                echo '<li><b>' . $itemSet->link($groupingLabel) . '</b>' . $itemSetCount;
                            }
                        } else {
                            if ($public) {
                                echo '<li>' . $view->hyperlink($groupingLabel, $view->url('site/hierarchy', ['site-slug' => $view->currentSite()->slug(), 'grouping-id' => $grouping->id()])) . $itemSetCount;
                            } else {
                                echo '<li>' . $itemSet->link($groupingLabel) . $itemSetCount;
                            }
                        }
                    } else {
                        // Don't link to groupings with item sets not assigned to site
                        echo '<li>' . $groupingLabel;
                    }
                }

                // Return any groupings with current grouping as parent
                $childArray = array_filter($allGroupings, function($child) use($grouping) {
                    return $child->getParentGrouping() == $grouping->id();
                });

                // Remove already printed groupings from $allGroupings array
                $allGroupings = array_filter($allGroupings, function($child) use($grouping) {
                    return $child->id() != $grouping->id();
                });

                $printedGroupings[] = $grouping;

                if (count($childArray) > 0) {
                    // Handle multidimensional hierarchies by saving/retrieving previous state
                    $prevChildArray = $childArray ?: [];
                    $childCount = count($childArray);
                    echo '<ul>';
                    $iterate($childArray, $currentItemSet);
                    echo '</ul></li>';
                    $childArray = $prevChildArray;
                    continue;
                } elseif ($childCount >= 1) {
                    echo '</li>';
                    // Keep other variables the same if iterating 'sibling'
                    $childCount--;
                    continue;
                } else {
                    echo '</li>';
                }
            }
        };
        $iterate($groupings, $currentItemSet);
        $printedGroupings = [];
    }
}
