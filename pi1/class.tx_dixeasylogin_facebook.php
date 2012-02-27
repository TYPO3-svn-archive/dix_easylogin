<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Markus Kappe <markus.kappe@dix.at>
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

class tx_dixeasylogin_facebook { // uses oauth 2.0
	function init($provider, $piVars) {
		$this->provider = $provider;
		$this->piVars = $piVars;
	}
	function main() {
		if ($this->piVars['process']) {
			$error = $this->redirToProvider();
		} elseif ($this->piVars['action']=="verify") {
			$error = $this->verifyLogin();
		}
		return $error;
	}

	function redirToProvider() {
		$verifyUrl = t3lib_div::locationHeaderUrl($GLOBALS['piObj']->pi_linkTP_keepPIvars_url(array('action'=>'verify'),0,1));
		if (strpos($verifyUrl, '?')) {
			return sprintf($GLOBALS['piObj']->pi_getLL('qmark_in_url'), $verifyUrl);
		}
		$requiredInfo = 'email';
		$location = sprintf('https://www.facebook.com/dialog/oauth?client_id=%s&redirect_uri=%s&scope=%s', $this->provider['appId'], urlencode($verifyUrl), $requiredInfo);
		header('Location: '.$location);
	}

	function verifyLogin() {
		$error = '';
		$token = $this->getToken(t3lib_div::_GET('code'), $error);
		if ($error) { return $error; }
		$userinfo = $this->getUserInfo($token, $error);
		if ($error) { return $error; }
		return tx_dixeasylogin_div::loginFromIdentifier($userinfo['id'], $userinfo);
	}

	function getToken($code, &$error) {
		$response = tx_dixeasylogin_div::makeCURLRequest('https://graph.facebook.com/oauth/access_token', 'GET', array(
			'client_id' => $this->provider['appId'],
			'redirect_uri' => t3lib_div::locationHeaderUrl($GLOBALS['piObj']->pi_linkTP_keepPIvars_url(array('action'=>'verify'),0,1)), // must not contain a question mark "?"
			'client_secret' => $this->provider['appSecret'],
			'code' => $code,
		));
		if (!$response) {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_validate_fb_nocode'), $code); // Error while validating facebook code-parameter '%s' (no answer)
			return false;
		}
		$decoded = json_decode($response, true);
		if ($decoded['error']) {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_validate_fb'), $decoded['error']['type'], $decoded['error']['message']); // Error while validating facebook code-parameter (%s: %s)
			return false;
		}
		$result = array();
		parse_str($response, $result);
		if (!$result['access_token']) {
			$error = $GLOBALS['piObj']->pi_getLL('error_fb_token'); // Error: could not retrieve access_token
		}
		return $result['access_token'];
	}

	function getUserInfo($token, &$error) {
		$response = tx_dixeasylogin_div::makeCURLRequest('https://graph.facebook.com/me', 'GET', array('access_token' => $token));
		$decoded = json_decode($response, true);
		if ($decoded['error']) {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_validate_fb_token'), $decoded['error']['type'], $decoded['error']['message']); // Error while validating facebook token-parameter (%s: %s)
			return;
		}
		$userinfo = array(
			'id' => 'facebook-'.$decoded['id'],
			'nickname' => $decoded['username'],
			'fullname' => $decoded['name'],
			'firstname' => $decoded['first_name'],
			'lastname' => $decoded['last_name'],
			'email' => $decoded['email'],
			'language' => $decoded['locale'],
			// not populated: dob,gender,postcode,country,timezone,prefix,suffix
		);
		return $userinfo;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dix_easylogin/pi1/class.tx_dixeasylogin_facebook.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dix_easylogin/pi1/class.tx_dixeasylogin_facebook.php']);
}

?>