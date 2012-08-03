<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Helge Funk <helge.funk@e-net.info>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Move localconf.php to LocalConfiguration.php
 *
 * @package TYPO3
 * @subpackage install
 * @author Helge Funk <helge.funk@e-net.info>
 */
class tx_coreupdates_localConfiguration extends Tx_Install_Updates_Base {

	/**
	 * @var string The title
	 */
	protected $title = 'Update LocalConfiguration';

	/**
	 * Checks if localconf.php is available. If so, the update should be done
	 *
	 * @param string &$description: The description for the update
	 * @return boolean TRUE if update should be done
	 */
	public function checkForUpdate(&$description) {
		$description = 'The localconfiguration file typo3conf/localconf.php is deprecated and ' .
			' unused since TYPO3 6.0. This wizard migrates the content of the file to the new ' .
			' format.';
		$description .= '<br /><strong>It is strongly recommended to run this wizard now.</strong><br />';
		$description .= 'The old localconf.php file is renamed to localconf.obsolete.php and can' .
			' be manually removed if everything works.';
		$result = FALSE;
		if (@is_file(PATH_typo3conf . 'localconf.php')) {
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Performs the update action.
	 *
	 * The methods reads localconf.php line by line and classifies every line
	 * to be either part of LocalConfiguration (everything that starts with TYPO3_CONF_VARS),
	 * belongs to the database settings (those will be merged to TYPO3_CONF_VARS),
	 * and everything else (those will be moved to the AdditionalConfiguration file.
	 *
	 * @param array &$dbQueries: Queries done in this update
	 * @param mixed &$customMessages: Custom messages
	 * @return boolean TRUE if everything went well
	 */
	public function performUpdate(array &$dbQueries, &$customMessages) {
		$result = FALSE;

		try {
			$localConfigurationContent = file(PATH_typo3conf . 'localconf.php');

				// Line array for the three categories: localConfiguration, db settings, additionalConfiguration
			$typo3ConfigurationVariables = array();
			$typo3DatabaseVariables = array();
			$additionalConfiguration = array();

			foreach ($localConfigurationContent as $line) {
				$line = trim($line);
				$matches = array();

					// Convert extList to array
				if (preg_match('/^\$TYPO3_CONF_VARS\[\'EXT\'\]\[\'extList\'\] *={1} *\'(.+)\';{1}/', $line, $matches) === 1) {
					$extListAsArray = t3lib_div::trimExplode(',', $matches[1], TRUE);
					$typo3ConfigurationVariables[] = '$TYPO3_CONF_VARS[\'EXT\'][\'extListArray\'] = ' . var_export($extListAsArray, TRUE) . ';';
					$typo3ConfigurationVariables[] = '$TYPO3_CONF_VARS[\'EXT\'][\'extList\'] = \'' . $matches[1] . '\';';
					// Match all other TYPO3_CONF_VARS
				} elseif (preg_match('/^\$TYPO3_CONF_VARS.+;{1}/', $line, $matches) === 1) {
					$typo3ConfigurationVariables[] = $matches[0];
					// Match variables beginning with '$typo_db'
				} elseif (preg_match('/^\$typo_db.+;{1}/', $line, $matches) === 1) {
					eval($matches[0]);
					if (isset($typo_db_host)) {
						$typo3DatabaseVariables['host'] = $typo_db_host;
					} elseif (isset($typo_db)) {
						$typo3DatabaseVariables['database'] = $typo_db;
					} elseif (isset($typo_db_username)) {
						$typo3DatabaseVariables['username'] = $typo_db_username;
					} elseif (isset($typo_db_password)) {
						$typo3DatabaseVariables['password'] = $typo_db_password;
					} elseif (isset($typo_db_extTableDef_script)) {
						$typo3DatabaseVariables['extTablesDefinitionScript'] = $typo_db_extTableDef_script;
					}
					unset($typo_db_host,$typo_db,$typo_db_username,$typo_db_password,$typo_db_extTableDef_script);
					// Else if the line is no comment or start / stop php tag -> add it to additional configuration
				} elseif (strlen($line) > 0 && preg_match('/^\/\/.+|^#.+|^<\?php$|^<\?$|^\?>$/', $line, $matches) === 0) {
					$additionalConfiguration[] = $line;
				}
			}

				// Build new TYPO3_CONF_VARS array
			$TYPO3_CONF_VARS = NULL;
			eval(implode(LF, $typo3ConfigurationVariables));

				// Add db settings to array
			$TYPO3_CONF_VARS['DB'] = $typo3DatabaseVariables;
			$TYPO3_CONF_VARS = t3lib_utility_Array::sortByKeyRecursive($TYPO3_CONF_VARS);

				// Write out new LocalConfiguration file
			t3lib_div::writeFile(
				PATH_site . t3lib_Configuration::LOCAL_CONFIGURATION_FILE,
				'<?php' . LF . 'return ' . t3lib_utility_Array::arrayExport($TYPO3_CONF_VARS) . ';' . LF . '?>'
			);

				// Write out new AdditionalConfiguration file
			if (sizeof($additionalConfiguration) > 0) {
				t3lib_div::writeFile(
					PATH_site . t3lib_Configuration::ADDITIONAL_CONFIGURATION_FILE,
					'<?php' . LF . implode(LF, $additionalConfiguration) . LF . '?>'
				);
			} else {
				@unlink(PATH_site . t3lib_Configuration::ADDITIONAL_CONFIGURATION_FILE);
			}

			rename(PATH_site . 'typo3conf/localconf.php', PATH_site . 'typo3conf/localconf.obsolete.php');

			$result = TRUE;
		} catch (Exception $e) {
		}

		return $result;
	}
}
?>