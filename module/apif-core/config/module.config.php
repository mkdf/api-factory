<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace APIF\Core;

use APIF\Core\Controller\Factory\BrowseControllerFactory;
use APIF\Core\Controller\Factory\DatasetManagementControllerFactory;
use APIF\Core\Controller\Factory\ObjectControllerFactory;
use APIF\Core\Controller\Factory\PermissionsManagementControllerFactory;
use APIF\Core\Controller\Factory\QueryControllerFactory;
use APIF\Core\Controller\Factory\SchemaManagementControllerFactory;
use APIF\Core\Controller\Factory\SchemaRetrievalControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'router' => [
        'routes' => [
            'home' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'management' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/management',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'management',
                    ],
                ],
            ],
            'swaggerMain' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/swagger-config-main.json',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'swaggerConfigMain',
                    ],
                ],
            ],
            'swaggerManagement' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/swagger-config-management.json',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'swaggerConfigManagement',
                    ],
                ],
            ],
            'query' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/query/:id',
                    'defaults' => [
                        'controller' => Controller\QueryController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'object' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/object/:id[/:doc-id]',
                    'defaults' => [
                        'controller' => Controller\ObjectController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'browse' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/browse/:id',
                    'defaults' => [
                        'controller' => Controller\BrowseController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'datasetmanagement' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/management/datasets[/:id]',
                    'defaults' => [
                        'controller' => Controller\DatasetManagementController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'permissionsmanagement' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/management/permissions[/:id]',
                    'defaults' => [
                        'controller' => Controller\PermissionsManagementController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'schemamanagement' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/management/schemas[/:id]',
                    'defaults' => [
                        'controller' => Controller\SchemaManagementController::class,
                        //'action'     => 'query',
                    ],
                ],
            ],
            'schemaassignment' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/management/datasets/:datasetid/schemas/:id',
                    'defaults' => [
                        'controller' => Controller\SchemaManagementController::class,
                        'action'     => 'assignment',
                    ],
                ],
            ],
            'schemaretrieval' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/schemas[/:id]',
                    'defaults' => [
                        'controller' => Controller\SchemaRetrievalController::class,
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\IndexController::class => InvokableFactory::class,
            Controller\QueryController::class  => QueryControllerFactory::class,
            Controller\ObjectController::class  => ObjectControllerFactory::class,
            Controller\BrowseController::class => BrowseControllerFactory::class,
            Controller\DatasetManagementController::class => DatasetManagementControllerFactory::class,
            Controller\PermissionsManagementController::class => PermissionsManagementControllerFactory::class,
            Controller\SchemaRetrievalController::class => SchemaRetrievalControllerFactory::class,
            Controller\SchemaManagementController::class => SchemaManagementControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'aliases' => [
            Repository\APIFCoreRepositoryInterface::class => Repository\APIFCoreRepository::class,
            Repository\SchemaRepositoryInterface::class => Repository\SchemaRepository::class,
            Service\ActivityLogManagerInterface::class => Service\ActivityLogManager::class,
            Service\SchemaValidatorInterface::class => Service\SchemaValidator::class,
        ],
        'factories' => [
            Repository\APIFCoreRepository::class => Repository\Factory\APIFCoreRepositoryFactory::class,
            Repository\SchemaRepository::class => Repository\Factory\SchemaRepositoryFactory::class,
            Service\ActivityLogManager::class => Service\Factory\ActivityLogManagerFactory::class,
            Service\SchemaValidator::class => Service\Factory\SchemaValidatorFactory::class,
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'log' => [
        'apifReadLogger' => [
            'writers' => [
                'stream' => [
                    'name' => 'stream',
                    'priority' => 1,
                    'options' => [
                        'stream' => 'log/readLog',
                        'formatter' => [
                            'name' => \Laminas\Log\Formatter\Simple::class,
                            'options' => [
                                'format' => '%timestamp% %priorityName% (%priority%): %message% %extra%',
                                'dateTimeFormat' => 'c',
                            ],
                        ],
                        'filters' => [
                            'priority' => [
                                'name' => 'priority',
                                'options' => [
                                    'operator' => '<=',
                                    'priority' => \Laminas\Log\Logger::INFO,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'processors' => [
                'requestid' => [
                    'name' => \Laminas\Log\Processor\RequestId::class,
                ],
            ],
        ],
    ],
];
