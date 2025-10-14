<?php
namespace Hierarchy;

return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => OMEKA_PATH . '/modules/Hierarchy/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'hierarchy' => 'Hierarchy\Api\Adapter\HierarchyAdapter',
            'hierarchy_grouping' => 'Hierarchy\Api\Adapter\HierarchyGroupingAdapter',
        ],
    ],
    'controllers' => [
        'factories' => [
            'Hierarchy\Controller\Index' => 'Hierarchy\Service\Controller\IndexControllerFactory',
        ],
        'invokables' => [
            'Hierarchy\Controller\SiteAdmin\Index' => 'Hierarchy\Controller\SiteAdmin\IndexController',
            'Hierarchy\Controller\Site\Index' => 'Hierarchy\Controller\Site\IndexController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            \Hierarchy\Service\HierarchyUpdater\HierarchyUpdater::class => \Hierarchy\Service\HierarchyUpdater\HierarchyUpdaterFactory::class,
        ],
        'aliases' => [
            \Hierarchy\Service\HierarchyUpdater::class => \Hierarchy\Service\HierarchyUpdater\HierarchyUpdater::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/Hierarchy/view',
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'Hierarchy' => Site\BlockLayout\Hierarchy::class,
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'hierarchyHelper' => View\Helper\HierarchyHelper::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/Hierarchy/src/Entity',
        ],
        'proxy_paths' => [
            OMEKA_PATH . '/modules/Hierarchy/data/doctrine-proxies',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'hierarchy' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/hierarchy',
                            'defaults' => [
                                '__NAMESPACE__' => 'Hierarchy\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'action' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'hierarchy' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/hierarchy',
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Hierarchy\Controller\SiteAdmin',
                                                'controller' => 'index',
                                                'action' => 'index',
                                            ],
                                        ],
                                        'may_terminate' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'child_routes' => [
                    'hierarchy' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/hierarchy/:grouping-id',
                            'defaults' => [
                                '__NAMESPACE__' => 'Hierarchy\Controller\Site',
                                'controller' => 'Index',
                                'action' => 'hierarchy',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Hierarchy', // @translate
                'route' => 'admin/hierarchy',
                'resource' => 'Hierarchy\Controller\Index',
            ],
        ],
        'site' => [
            [
                'label' => 'Hierarchy', // @translate
                'route' => 'admin/site/slug/hierarchy',
                'action' => 'index',
                'useRouteMatch' => true,
                'resource' => 'Hierarchy\Controller\SiteAdmin\Index',
                'pages' => [
                    [
                        'route' => 'admin/site/slug/hierarchy',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'resource_page_block_layouts' => [
         'invokables' => [
             'hierarchy' => Site\ResourcePageBlockLayout\Hierarchy::class,
         ],
     ],
     'resource_page_blocks_default' => [
         'items' => [
             'main' => ['hierarchy'],
         ],
         'item_sets' => [
             'main' => ['hierarchy'],
        ],
     ],
];
