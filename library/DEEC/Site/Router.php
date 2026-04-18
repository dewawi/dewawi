<?php

class DEEC_Site_Router
{
    public function registerRoutes(DEEC_Site_Context $siteContext)
    {
        $frontController = Zend_Controller_Front::getInstance();
        $router = $frontController->getRouter();

        $site = $siteContext->getSite();

        $this->registerBaseRoutes($router, $siteContext);
        $this->registerFallbackRoute($router);
        $this->registerSlugRoutes($router, $site['id']);
    }

    protected function registerBaseRoutes(Zend_Controller_Router_Rewrite $router, DEEC_Site_Context $siteContext)
    {
        $router->addRoute('shop_home', new Zend_Controller_Router_Route(
            '/',
            array(
                'module' => 'shops',
                'controller' => 'index',
                'action' => 'index'
            )
        ));

        $router->addRoute('sitemap', new Zend_Controller_Router_Route(
            'sitemap.xml',
            array(
                'module' => 'shops',
                'controller' => 'sitemap',
                'action' => 'index'
            )
        ));

        $router->addRoute('contact_send', new Zend_Controller_Router_Route(
            'contact/send',
            array(
                'module' => 'shops',
                'controller' => 'contact',
                'action' => 'send'
            )
        ));

        $router->addRoute('contact_success', new Zend_Controller_Router_Route(
            'contact/success',
            array(
                'module' => 'shops',
                'controller' => 'contact',
                'action' => 'success'
            )
        ));

        $router->addRoute('contact_error', new Zend_Controller_Router_Route(
            'contact/error',
            array(
                'module' => 'shops',
                'controller' => 'contact',
                'action' => 'error'
            )
        ));

        if ($siteContext->hasFeature('inquiry')) {
            $router->addRoute('inquiry_send', new Zend_Controller_Router_Route(
                'inquiry/send',
                array(
                    'module' => 'shops',
                    'controller' => 'inquiry',
                    'action' => 'send'
                )
            ));

            $router->addRoute('inquiry_success', new Zend_Controller_Router_Route(
                'inquiry/success',
                array(
                    'module' => 'shops',
                    'controller' => 'inquiry',
                    'action' => 'success'
                )
            ));

            $router->addRoute('inquiry_error', new Zend_Controller_Router_Route(
                'inquiry/error',
                array(
                    'module' => 'shops',
                    'controller' => 'inquiry',
                    'action' => 'error'
                )
            ));
        }

        if ($siteContext->hasFeature('cart')) {
            $router->addRoute('cart', new Zend_Controller_Router_Route(
                'cart',
                array(
                    'module' => 'shops',
                    'controller' => 'cart',
                    'action' => 'index'
                )
            ));

            $router->addRoute('cart_add', new Zend_Controller_Router_Route(
                'cart/add',
                array(
                    'module' => 'shops',
                    'controller' => 'cart',
                    'action' => 'add'
                )
            ));

            $router->addRoute('cart_update', new Zend_Controller_Router_Route(
                'cart/update',
                array(
                    'module' => 'shops',
                    'controller' => 'cart',
                    'action' => 'update'
                )
            ));

            $router->addRoute('cart_remove', new Zend_Controller_Router_Route(
                'cart/remove',
                array(
                    'module' => 'shops',
                    'controller' => 'cart',
                    'action' => 'remove'
                )
            ));

            $router->addRoute('cart_clear', new Zend_Controller_Router_Route(
                'cart/clear',
                array(
                    'module' => 'shops',
                    'controller' => 'cart',
                    'action' => 'clear'
                )
            ));
        }

        if ($siteContext->hasFeature('checkout')) {
            $router->addRoute('checkout', new Zend_Controller_Router_Route(
                'checkout',
                array(
                    'module' => 'shops',
                    'controller' => 'checkout',
                    'action' => 'index'
                )
            ));

            $router->addRoute('checkout_send', new Zend_Controller_Router_Route(
                'checkout/send',
                array(
                    'module' => 'shops',
                    'controller' => 'checkout',
                    'action' => 'send'
                )
            ));

            $router->addRoute('checkout_success', new Zend_Controller_Router_Route(
                'checkout/success',
                array(
                    'module' => 'shops',
                    'controller' => 'checkout',
                    'action' => 'success'
                )
            ));
        }

        $router->addRoute('feed', new Zend_Controller_Router_Route(
            'products-de.xml',
            array(
                'module' => 'shops',
                'controller' => 'item',
                'action' => 'feed'
            )
        ));

        $router->addRoute('product', new Zend_Controller_Router_Route(
            'product/:id',
            array(
                'module' => 'shops',
                'controller' => 'item',
                'action' => 'index',
                'id' => null
            ),
            array(
                'id' => '\d+'
            )
        ));
    }

    protected function registerFallbackRoute(Zend_Controller_Router_Rewrite $router)
    {
        $router->addRoute('shop_fallback', new Zend_Controller_Router_Route(
            '*',
            array(
                'module' => 'shops',
                'controller' => 'index',
                'action' => 'index'
            )
        ));
    }

    protected function registerSlugRoutes(Zend_Controller_Router_Rewrite $router, $siteId)
    {
        $slugTable = new Zend_Db_Table('slug');
        $slugs = $slugTable->fetchAll(array('shopid = ?' => (int) $siteId));

        $slugDict = array();
        foreach ($slugs as $slug) {
            $slugData = $slug->toArray();
            $slugDict[$slugData['entityid']] = $slugData;
        }

        foreach ($slugs as $slug) {
            $slugData = $slug->toArray();

            if (empty($slugData['slug'])) {
                continue;
            }

            $fullSlug = $this->buildFullSlug($slugData, $slugDict);

            $router->addRoute(
                'slug_' . $slugData['controller'] . '_' . $slugData['entityid'],
                new Zend_Controller_Router_Route(
                    $fullSlug,
                    array(
                        'module' => $slugData['module'],
                        'controller' => $slugData['controller'],
                        'action' => 'index',
                        'id' => $slugData['entityid']
                    )
                )
            );
        }
    }

    protected function buildFullSlug(array $item, array $slugDict)
    {
        $slug = $item['slug'];

        while (!empty($item['parentid']) && isset($slugDict[$item['parentid']])) {
            $item = $slugDict[$item['parentid']];
            $slug = $item['slug'] . '/' . $slug;
        }

        return $slug;
    }
}
