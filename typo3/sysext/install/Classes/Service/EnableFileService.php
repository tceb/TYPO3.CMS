<?php
namespace TYPO3\CMS\Install\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2013 Steffen Ritter, Benjamin Mack <benjamin.mack@typo3.org>
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
 * Basic Service to check and create install tool files
 */
class EnableFileService {

	/**
	 * @constant Relative path to ENABLE_INSTALL_TOOL file
	 */
	const INSTALL_TOOL_ENABLE_FILE_PATH = 'typo3conf/ENABLE_INSTALL_TOOL';

	/**
	 * Creates the INSTALL_TOOL_ENABLE file
	 *
	 * @return boolean
	 */
	static public function createInstallToolEnableFile() {
		$installEnableFilePath = self::getInstallToolEnableFilePath();
		if (!is_file($installEnableFilePath)) {
			$result = touch($installEnableFilePath);
		} else {
			$result = TRUE;
			self::extendInstallToolEnableFileLifetime();
		}
		\TYPO3\CMS\Core\Utility\GeneralUtility::fixPermissions($installEnableFilePath);
		return $result;
	}

	/**
	 * Removes the INSTALL_TOOL_ENABLE file
	 *
	 * @return boolean
	 */
	static public function removeInstallToolEnableFile() {
		return unlink(self::getInstallToolEnableFilePath());
	}

	/**
	 * Checks if the install tool file exists
	 *
	 * @return boolean
	 */
	static public function checkInstallToolEnableFile() {
		$enableFile = self::getInstallToolEnableFilePath();
		if (@file_exists($enableFile)) {
			$content = @file_get_contents($enableFile);
			// if the file contains the pattern "KEEP_FILE", it will not be removed
			if (strpos($content, 'KEEP_FILE') === FALSE) {
				// maximum age of a valid INSTALL_TOOL_ENABLE file is 1 hour
				if (time() - @filemtime($enableFile) > 3600) {
					self::removeInstallToolEnableFile();
					return FALSE;
				}
			}
		} else {
			return FALSE;
		}
		self::extendInstallToolEnableFileLifetime();
		return TRUE;
	}

	/**
	 * Updates the last modification of the ENABLE_INSTALL_TOOL file
	 *
	 * @return void
	 */
	static public function extendInstallToolEnableFileLifetime() {
		$enableFile = self::getInstallToolEnableFilePath();
		// Extend the age of the ENABLE_INSTALL_TOOL file by one hour
		if (is_file($enableFile)) {
			$couldTouch = @touch($enableFile);
			if (!$couldTouch) {
				// If we can't remove the creation method will call us again.
				if (self::removeInstallToolEnableFile()) {
					self::createInstallToolEnableFile();
				}
			}
		}
	}

	/**
	 * Returns the path to the INSTALL_TOOL_ENABLE file
	 *
	 * @return string
	 */
	static protected function getInstallToolEnableFilePath() {
		return PATH_site . self::INSTALL_TOOL_ENABLE_FILE_PATH;
	}
}
