<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Entry point to the install tool and step installer.
 *
 * There are two main controllers: "step" and "tool".
 * The step controller is always called first, and redirects to the tool controller
 * if the basic core functionality is given (instance configuration exists, database
 * connection works, ...)
 * The tool controller is the main "install tool" with all the main functionality.
 *
 * The step controller handles the basic installation.
 * During first installation it creates the basic file and folder structure, the
 * configuration files, the database connection and a basic configuration. Those steps
 * are cycled through and if some step returns TRUE on "needsExecution", an input
 * form of this step is rendered.
 * After initial installation, the step installer is still called if the install
 * tool is accessed, so it will automatically come up if some basic configuration fails.
 * If everything is ok, the step installer will redirect to the main install tool.
 * The step installer also has some "silent" update scripts, for example it migrates
 * a localconf.php to LocalConfiguration if needed.
 *
 * This ensures as soon as the tool controller is called, the basic configuration is ok.
 *
 * Whenever the bootstrap or other core elements figure the installation
 * needs an update that is handled within the step controller, it should just
 * redirect to the entry script and let the step controller do necessary work.
 *
 * The step installer initiates browser redirects if steps were executed. This simplifies
 * internal logic by separating the different bootstrap states needed during installation
 * from each other.
 *
 * There is also a backend module controller, that basically only shows a screen
 * with the "enable install tool" button and then redirects to the entry script. Other
 * than that, it does not interfere with step or tool controller and just sets a
 * context GET parameter to indicate that the install tool is called within backend context.
 *
 * To coordinate different tasks within step and install controller and actions, several
 * GET or POST parameters are used, all prefixed with "install".
 * Parameters allowed as GET and POST are preserved during redirects, POST parameters are
 * thrown away between redirects (HTTP status code 303).
 *
 * The following main GET and POST parameters are used:
 * - GET/POST "install[context]" Preserved
 *   Either empty, 'standalone' or 'backend', fallback to 'standalone'. Set to 'backend'
 *   if install tool is called form the backend main module (BackendModuleController). This
 *   changes the view a bit and shows the doc header in the install tool, changes background
 *   color and such.
 *
 * - GET/POST "install[controller]" Preserved
 *   Either empty, 'step' or 'tool', fallback to 'step'. This coordinates whether the step
 *   or tool controller is called. This parameter is never set externally, so the step
 *   controller is always called first. It itself sets the type to 'tool' and redirects to
 *   the tool controller if needed.
 *   This means you could (but shouldn't) directly call the tool controller, but it
 *   will still require a login and session, then.
 *
 * - GET/POST "install[action]" Preserved
 *   Determine step and tool controller main action sanitized by step / tool controller and
 *   only executed if user is logged in. Form protection API relies on this.
 *
 * - GET/POST "install[redirectCount]"
 *   The install tool initiates redirects to itself if configuration parameters were changed.
 *   This may lead to infinite redirect loops under rare circumstances. This parameter is
 *   incremented for each redirect to break a loop after some iterations.
 *
 * - POST "install[set]"
 *   Contains keys to determine which sub-action of the action is requested,
 *   eg. "change install tool password" in "important actions". Set to 'execute' if some
 *   step should be executed.
 *
 * - POST "install[values]"
 *   Data values for a specific "install[set]" action
 *
 * - POST "install[token]"
 *   A session and instance specific token created from the instance specific
 *   encryptionKey (taken care of in an early point in the step controller). This hash
 *   is used as form protection against CSRF for all POST data. Both the step and tool
 *   controller will logout a user if the token check fails. The only exception to this
 *   handling is the very first installation step where no session and no encryption key
 *   can exist yet.
 */

define('TYPO3_MODE', 'BE');
define('TYPO3_enterInstallScript', '1');

// Bootstrap bare minimum: class loader, LocalConfiguration, but no extensions and such
require __DIR__ . '/../../core/Classes/Core/Bootstrap.php';
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
	->baseSetup('typo3/sysext/install/Start/')
	->startOutputBuffering()
	->loadConfigurationAndInitialize(FALSE, 'TYPO3\\CMS\\Core\\Package\\FailsafePackageManager');

// Execute 'tool' or 'step' controller depending on install[controller] GET/POST parameter
$getPost = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('install');
if (isset($getPost['controller']) && $getPost['controller'] === 'tool') {
	$controller = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
		'TYPO3\\CMS\\Install\\Controller\\ToolController'
	);
} elseif ($getPost['controller'] === 'ajax') {
	$controller = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
		'TYPO3\\CMS\\Install\\Controller\\AjaxController'
	);
} else {
	$controller = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
		'TYPO3\\CMS\\Install\\Controller\\StepController'
	);
}
$controller->execute();
