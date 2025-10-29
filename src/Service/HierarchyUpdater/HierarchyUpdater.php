<?php
namespace Hierarchy\Service\HierarchyUpdater;

use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;

class HierarchyUpdater
{
    /** @var ApiManager */
    protected $api;

    /** @var Settings */
    protected $siteSettings;

    protected $response;

    public function __construct(ApiManager $api, Settings $siteSettings)
    {
        $this->api = $api;
        $this->siteSettings = $siteSettings;
    }

    public function updateHierarchy(array $hierarchyData)
    {
        $hierarchyID = isset($hierarchyData['id']) ? (int) $hierarchyData['id'] : 0;
        $content = $this->api->search('hierarchy', ['id' => $hierarchyID])->getContent();

        if (!empty($hierarchyData['delete'])) {
            if (!empty($content)) {
                $this->api->delete('hierarchy', $hierarchyData['id']);
                // Remove hierarchy from site settings
                $sites = $this->api->search('sites')->getContent();
                foreach ($sites as $site) {
                    $new = [];
                    $siteHierarchyArray = $this->siteSettings->get('site_hierarchies', [], $site->id());
                    foreach ($siteHierarchyArray as $siteHierarchy) {
                        if ($siteHierarchy['id'] != $hierarchyData['id']) {
                            $new[] = $siteHierarchy;
                        }
                    }
                    $this->siteSettings->set('site_hierarchies', $new, $site->id());
                }
            }
        } elseif (empty($content)) {
            $hierarchyResponse = $this->api->create('hierarchy', $hierarchyData);
            $hierarchyID = $hierarchyResponse ? $hierarchyResponse->getContent()->id() : null;
            $hierarchyData['id'] = $hierarchyID;
            $this->updateTreeData($hierarchyData);
        } else {
            $this->api->update('hierarchy', $hierarchyID, $hierarchyData);
            $this->updateTreeData($hierarchyData);
        }
        return $hierarchyID;
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
                        $this->response = $this->api->delete('hierarchy_grouping', $groupingID);
                    } else {
                        // Ignore if newly created grouping marked for deletion
                        continue;
                    }
                } else if (isset($groupingID)) {
                    // Update existing grouping metadata
                    $this->response = $this->api->update('hierarchy_grouping', $groupingID, $groupingData);
                } else {
                    // Create new grouping
                    $this->response = $this->api->create('hierarchy_grouping', $groupingData);
                }
                if (count($grouping['children']) > 0) {
                    // Handle multidimensional hierarchies by saving/retrieving previous state
                    $prevGrouping = $parentGrouping ?: null;
                    $childCount = count($grouping['children']);
                    // Store ID of parent with each child
                    $parentGrouping = $this->response ? $this->response->getContent()->id() : '';
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
