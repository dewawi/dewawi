<?php

class Application_Plugin_Acl extends Zend_Controller_Plugin_Abstract
{
	public function routeShutdown(
		Zend_Controller_Request_Abstract $request
	): void {
		$auth = Zend_Auth::getInstance();

		if (!$auth->hasIdentity()) {
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

		if ($controller === 'error') {
			$this->logPermit($request);
			return;
		}

		if ($module === 'admin') {
			return;
		}

		if ($controller === 'downloads') {
			return;
		}

		$user = Zend_Registry::get('User');
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
		if ($request->getModuleName() === 'shops') {
			return;
		}

		if ($this->isLoginRequest($request)) {
			return;
		}

		$this->redirectToLogin($request);
	}

	private function isLoginRequest(
		Zend_Controller_Request_Abstract $request
	): bool {
		return $request->getModuleName() === 'users'
			&& $request->getControllerName() === 'user'
			&& $request->getActionName() === 'login';
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

		if ($canViewController && $resolvedController !== null) {
			$redirector->gotoSimple(
				'index',
				$resolvedController,
				$request->getModuleName()
			);

			return;
		}

		$redirector->gotoSimple(
			'index',
			'index',
			'default'
		);
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
