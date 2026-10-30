<?php

class Application_Plugin_Acl extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(Zend_Controller_Request_Abstract $request): void
	{
		if($this->isPublicRequest($request)) return;

		$auth = Zend_Auth::getInstance();
		if ($request->getModuleName() === 'default' && $request->getControllerName() === 'trash' && $request->getActionName() === 'add') {
			// The JSON endpoint checks the actual target module and delete permission.
			if (!$auth->hasIdentity()) $this->denyAccess($request);
			return;
		}
		if ($request->getModuleName() === 'default' && $request->getControllerName() === 'media' && $request->getActionName() === 'manager') {
			if (!$auth->hasIdentity()) $this->denyAccess($request);
			return;
		}

		if(!$auth->hasIdentity()) {
			$this->handleGuestRequest($request);
			return;
		}

		$this->handleAuthenticatedRequest($request);
	}

	private function handleAuthenticatedRequest(
		Zend_Controller_Request_Abstract $request
	): void {
		$module = $request->getModuleName();
		$controller = $request->getControllerName();

		if ($module === 'default' && $controller === 'error') {
			$this->logPermit($request);
			return;
		}

		$user = Zend_Registry::get('User');

		if ($module === 'admin' || (in_array($module, ['ebay', 'shops'], true) && $controller === 'log')) {
			if (!empty($user['admin'])) {
				return;
			}

			$this->denyAccess($request);
			return;
		}

		if ($controller === 'downloads') {
			return;
		}

		$permission = new DEEC_Permission((array)$user);

		/*
		 * Preserve the current behavior:
		 * modules without a permission column are not blocked.
		 */
		if (!$permission->hasModule($module)) {
			return;
		}

		$resolvedController = $permission->resolveController($request);

		if (
			!$permission->hasController(
				$module,
				$resolvedController
			)
		) {
			$this->denyAccess($request);
			return;
		}

		if ($permission->hasRoutePermission($request)) {
			return;
		}

		$this->denyAccess(
			$request,
			$permission->hasPermission(
				$module,
				$resolvedController,
				'view'
			),
			$resolvedController
		);
	}

    private function handleGuestRequest(
	    Zend_Controller_Request_Abstract $request
    ): void {
	    if ($request->getModuleName() === 'shops' && in_array($request->getControllerName(), ['index', 'item', 'category', 'page', 'tag', 'sitemap', 'cart', 'checkout', 'contact', 'inquiry'], true)) {
		    return;
	    }

	    if ($this->isLoginRequest($request)) {
		    return;
	    }

	    if (
		    $request instanceof Zend_Controller_Request_Http
		    && $request->isXmlHttpRequest()
	    ) {
		    $this->forwardToSessionExpired($request);
		    return;
	    }

	    $this->redirectToLogin($request);
    }

    private function forwardToSessionExpired(
	    Zend_Controller_Request_Abstract $request
    ): void {
	    $request
		    ->setModuleName('users')
		    ->setControllerName('user')
		    ->setActionName('session');
    }

	private function isLoginRequest(
		Zend_Controller_Request_Abstract $request
	): bool {
		return $request->getModuleName() === 'users'
			&& $request->getControllerName() === 'user'
			&& $request->getActionName() === 'login';
	}

	private function isPublicRequest(Zend_Controller_Request_Abstract $request): bool
	{
		if (
			$request instanceof Zend_Controller_Request_Http
			&& $request->getModuleName() === 'shops'
			&& $request->getControllerName() === 'search'
			&& $request->getActionName() === 'index'
			&& in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)
			&& Zend_Registry::isRegistered('SiteContext')
			&& Zend_Registry::get('SiteContext') instanceof DEEC_Site_Context
		) {
			return true;
		}

		// The file action authorizes private objects itself and also serves public shop assets.
		if ($request->getModuleName() === 'contacts' && $request->getControllerName() === 'attachment' && $request->getActionName() === 'file') return true;
		if(
			$request->getModuleName() === 'default'
			&& $request->getControllerName() === 'feedback'
			&& $request->getActionName() === 'index'
		) {
			return true;
		}

		if(
			$request->getModuleName() === 'campaigns'
			&& $request->getControllerName() === 'unsubscribe'
			&& $request->getActionName() === 'index'
		) {
			return true;
		}

		return $request->getModuleName() === 'campaigns'
			&& $request->getControllerName() === 'feedback'
			&& $request->getActionName() === 'index';
	}

	private function redirectToLogin(
		Zend_Controller_Request_Abstract $request
	): void {
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper(
			'redirector'
		);

		$params = [
			$request->getModuleName(),
			$request->getControllerName(),
			$request->getActionName(),
		];

		$id = $request->getParam('id');

		if ($id !== null && $id !== '') {
			$params[] = $id;
		}

		$redirector->gotoSimple(
			'login',
			'user',
			'users',
			[
				'url' => implode('|', $params),
			]
		);
	}

	private function denyAccess(
		Zend_Controller_Request_Abstract $request,
		bool $canViewController = false,
		?string $resolvedController = null
	): void {
		$this->logDenied($request);

		$flashMessenger =
			Zend_Controller_Action_HelperBroker::getStaticHelper(
				'FlashMessenger'
			);

		$flashMessenger->addMessage(
			'MESSAGES_ACCESS_DENIED'
		);

		$redirector =
			Zend_Controller_Action_HelperBroker::getStaticHelper(
				'redirector'
			);

		if ($canViewController && $resolvedController !== null && !$request->isPost() && !$request->isXmlHttpRequest()) {
			if (
				$request->getActionName() === 'edit'
				&& (int)$request->getParam('id', 0) > 0
			) {
				$redirector->gotoSimple(
					'view',
					$request->getControllerName(),
					$request->getModuleName(),
					[
						'id' => (int)$request->getParam('id'),
					]
				);

				return;
			}

			$redirector->gotoSimple(
				'index',
				$request->getControllerName(),
				$request->getModuleName()
			);

			return;
		}

		// Route to a response-only action so the denied controller cannot run.
		$this->getResponse()->setHttpResponseCode(403);
		$request
			->setModuleName('default')
			->setControllerName('error')
			->setActionName('forbidden');
	}

	private function logPermit(
		Zend_Controller_Request_Abstract $request
	): void {
		error_log(
			'PERMIT/'
			. $request->getModuleName()
			. '/'
			. $request->getControllerName()
			. '/'
			. $request->getActionName()
		);
	}

	private function logDenied(
		Zend_Controller_Request_Abstract $request
	): void {
		error_log(
			'NO_PERMIT/'
			. $request->getModuleName()
			. '/'
			. $request->getControllerName()
			. '/'
			. $request->getActionName()
		);

		error_log(
			'NO_PERMIT'
			. $request->getRequestUri()
		);
	}
}
