<?php

/**
 * @file classes/plugins/GenericPlugin.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for generic plugins
 */


import('lib.pkp.classes.plugins.LazyLoadPlugin');

class GenericPlugin extends LazyLoadPlugin {
	/**
	 * Constructor
	 */
	function GenericPlugin() {
		parent::LazyLoadPlugin();
	}

	/*
	 * Override protected methods from PKPPlugin
	 */

	/**
	 * Generic plug-ins implement the enabled/disabled logic
	 * by default. This is necessary so that we can make sure
	 * that disabled plug-ins will not have to be instantiated.
	 *
	 * Call this method with a list of management verbs (if any)
	 * generated by the custom plug-in.
	 *
	 * @see PKPPlugin::getManagementVerbs()
	 */
	function getManagementVerbs() {
		// Site plug-ins can only be administered by site admins
		if ($this->isSitePlugin() && !Validation::isSiteAdmin()) return array();

		$verbs = parent::getManagementVerbs();

		if ($this->getEnabled()) {
			$verbs[] = array('disable', __('common.disable'));
		} else {
			$verbs[] = array('enable', __('common.enable'));
		}
		return $verbs;
	}

	/**
	 * @see PKPPlugin::manage()
	 */
	function manage($verb, $args, &$message, &$messageParams, &$pluginModalContent = null) {
		if ($verb != 'enable' && !$this->getEnabled()) fatalError('Invalid management action on disabled plug-in!');

		switch ($verb) {
			case 'enable':
				$this->setEnabled(true);
				$message = NOTIFICATION_TYPE_PLUGIN_ENABLED;
				$messageParams = array('pluginName' => $this->getDisplayName());
				return false;
			case 'disable':
				$this->setEnabled(false);
				$message = NOTIFICATION_TYPE_PLUGIN_DISABLED;
				$messageParams = array('pluginName' => $this->getDisplayName());
				return false;
		}

		return true;
	}
}
?>
