<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace APIF\Core;

use APIF\Core\Controller\Factory\DatasetManagementControllerFactory;
use APIF\Core\Controller\Factory\ObjectControllerFactory;
use APIF\Core\Controller\Factory\PermissionsManagementControllerFactory;
use APIF\Core\Controller\Factory\QueryControllerFactory;
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
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\IndexController::class => InvokableFactory::class,
            Controller\QueryController::class  => QueryControllerFactory::class,
            Controller\ObjectController::class  => ObjectControllerFactory::class,
            Controller\DatasetManagementController::class => DatasetManagementControllerFactory::class,
            Controller\PermissionsManagementController::class => PermissionsManagementControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'aliases' => [
            Repository\APIFCoreRepositoryInterface::class => Repository\APIFCoreRepository::class
        ],
        'factories' => [
            Repository\APIFCoreRepository::class => Repository\Factory\APIFCoreRepositoryFactory::class
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
];
