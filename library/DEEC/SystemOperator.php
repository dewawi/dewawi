<?php

class DEEC_SystemOperator
{
    public static function allowed(): bool
    {
        $auth = Zend_Auth::getInstance();
        if (!$auth->hasIdentity() || !Zend_Registry::isRegistered('User')) return false;
        $user = Zend_Registry::get('User');
        $identity = $auth->getIdentity();
        if (!is_array($user) || !is_object($identity) || !in_array($user['admin'] ?? null, [1, '1', true], true)
            || !in_array($user['activated'] ?? null, [1, '1', true], true) || !in_array($user['deleted'] ?? null, [0, '0', false], true)) return false;
        $id = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id !== false && (string)$id === (string)($identity->id ?? '');
    }
}
