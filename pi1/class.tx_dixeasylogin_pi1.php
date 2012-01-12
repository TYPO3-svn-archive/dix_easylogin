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

// @requires cUrl, extbase, fluid

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/dope/class.dopeopenid.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/oauth/OAuth.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/Yadis.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/HTTPFetcher.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/PlainHTTPFetcher.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/ParanoidHTTPFetcher.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/ParseHTML.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/XML.php");
require_once(t3lib_extMgm::extPath("dix_easylogin")."res/yadis/XRDS.php");

/**
 * Plugin 'Easy Login' for the 'dix_easylogin' extension.
 *
 * @author	Markus Kappe <markus.kappe@dix.at>
 * @package	TYPO3
 * @subpackage	tx_dixeasylogin
 */
class tx_dixeasylogin_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_dixeasylogin_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_dixeasylogin_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'dix_easylogin';	// The extension key.
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		if (!function_exists('curl_exec')) { return ('Error: easylogin requires the PHP cURL extension.'); }
		$GLOBALS['piObj'] = &$this;

		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;    // Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		if ($this->piVars['action'] == 'xrds') {
			$content = tx_dixeasylogin_div::renderFluidTemplate('xrds.tmpl', t3lib_div::locationHeaderUrl('index.php?id='.$GLOBALS['TSFE']->id.'&tx_dixeasylogin_pi1[action]=verify'));
			echo $content; exit();
		}
		$this->providers = $this->getProvider();
		
		if ($loginType = $this->piVars['loginType']) {
			$GLOBALS["TSFE"]->fe_user->setKey("ses", "easylogin_loginType", $loginType);
		} else {
			$loginType = $GLOBALS["TSFE"]->fe_user->getKey("ses", "easylogin_loginType");
			if (!$this->providers[$loginType]) {  $loginType = null; }
		}
		if ($loginType) {
			$provider = $this->providers[$loginType];
			switch ($provider['type']) {
				case 'FACEBOOK':
					$obj = t3lib_div::makeInstance('tx_dixeasylogin_facebook');
					break;
				case 'OAUTH1':
					$obj = t3lib_div::makeInstance('tx_dixeasylogin_oauth1');
					break;
				case 'OPENID':
					$obj = t3lib_div::makeInstance('tx_dixeasylogin_openid');
					break;
				default:
					return "undefined authentication method &quot;".$provider['type']."&quot;, please check TypoScript";
			}
			$obj->init($provider, $this->piVars);
			$error = $obj->main();
		}
		
		$values = array(
			'provider' => $this->providers,
			'formaction' => $this->pi_getPageLink($GLOBALS['TSFE']->id),
			'prefix' => $this->prefixId,
			'user' => $GLOBALS['TSFE']->fe_user->user,
			'error' => $error,
			'constants' => array('CONTENTELEMENT' => 'CONTENTELEMENT'),
		);

		$content = tx_dixeasylogin_div::renderFluidTemplate('login.tmpl', $values);
		return $this->pi_wrapInBaseClass($content);
	}

	function getProvider() {
		$result = array();
		foreach ($this->conf['provider.'] as $key=>$type) {
			if (!(int)$key || strstr($key, '.')) { continue; } // just continue for the numeric ones, e.g. '10' but not '10.'
			$conf = $this->conf['provider.'][$key.'.'];
			$conf['type'] = trim(strtoupper($type));
			$conf['key'] = $key;
			$conf['icon'] = $conf['icon'] ? tx_dixeasylogin_div::getFileRelFileName($conf['icon']) : '';
			$conf['showMe'] = (!(bool)$GLOBALS['TSFE']->fe_user->user['uid'] || $conf['showWhenLoggedIn']);
			switch ($conf['type']) {
				case 'CONTENTELEMENT':
					$conf['content'] = tx_dixeasylogin_div::render_ttContent($conf['uid']);
					break;
				case 'OAUTH':
					break;
				case 'OPENID':
					$conf['withUsername'] = (bool) (strstr($conf['url'], '###NAME###'));
					break;
			} 
			$result[$key] = $conf;
		}
		return $result;
	}

	function sendXrdsHeader($content, $conf) { // called as USER_INT from TypoScript (page.2 = USER_INT)
		$xrdsLocation = t3lib_div::locationHeaderUrl('index.php?id='.$conf['pid'].'&tx_dixeasylogin_pi1[action]=xrds');
		header('X-XRDS-Location:'.$xrdsLocation);
	}
}

class tx_dixeasylogin_div {
	static function validateUrl($url) {
		if(function_exists('filter_var')) {
			return filter_var($url, FILTER_VALIDATE_URL);
		} else { 
			return eregi("^((https?)://)?(((www\.)?[^ ]+\.[com|org|net|edu|gov|us]))([^ ]+)?$", $url); 
		}
	}

	static function loginFromIdentifier($identifier, $userinfo) {
		$user = tx_dixeasylogin_div::fetchUser($identifier);
		if (!$user['uid'] && $GLOBALS['piObj']->conf['allowCreate']) { // config
			$user = self::createUser($identifier, $userinfo);
		}
		if ($user['uid']) {
			self::login($user);
			self::redirectToSelf();
		} else {
			return sprintf($GLOBALS['piObj']->pi_getLL('nouser'), $identifier); // User not found. Please contact the admin of the website to request access to this site. Tell the admin this identifier: %s
		}
	}

	static function login($user) {
		$GLOBALS['TSFE']->fe_user->checkPid=0; //do not use a particular pid
		$GLOBALS['TSFE']->fe_user->createUserSession($user);
		$GLOBALS['TSFE']->fe_user->user = $user;
	}

	static function redirectToSelf() {
		#$url =  t3lib_div::locationHeaderUrl($GLOBALS['piObj']->pi_getPageLink($GLOBALS['TSFE']->id));
		$url = $GLOBALS['piObj']->cObj->getTypoLink_URL($GLOBALS['TSFE']->id, array('logintype' => 'login'));
		t3lib_utility_Http::redirect($url);
	}

	static function fetchUser($identifier) {
		$table = 'fe_users';
		$where = sprintf('tx_dixeasylogin_openid = %s %s', $GLOBALS['TYPO3_DB']->fullQuoteStr($identifier, $table), $GLOBALS['piObj']->cObj->enableFields($table));
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, $where);
		return $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	}

	function createUser($identifier, $userinfo) {
		# debugster ($userinfo); debugster ($_GET); exit();
		// possible keys in $userinfo: nickname,email,fullname,dob,gender,postcode,country,language,timezone,prefix,firstname,lastname,suffix
		// @see http://openid.net/specs/openid-simple-registration-extension-1_0.html#response_format
		$table = 'fe_users';
		$values = array(
			'email' => $userinfo['email'],
			'username' => ($userinfo['nickname'] ? $userinfo['nickname'] : $userinfo['email']), // TODO: check if username is unique
			'tx_dixeasylogin_openid' => $identifier,
			'pid' => $GLOBALS['piObj']->conf['user_pid'],
			'crdate' => time(),
			'tstamp' => time(),
			'password' => md5(microtime(1)),
			'usergroup' => $GLOBALS['piObj']->conf['usergroup'],
			'name' => $userinfo['fullname'] ? $userinfo['fullname'] : trim($userinfo['firstname'].' '.$userinfo['lastname'].' '.$userinfo['suffix']),
			'title' => $userinfo['prefix'],
			'first_name' => $userinfo['firstname'],
			'last_name' => $userinfo['lastname'],
			'zip' => $userinfo['postcode'],
			'country' => $userinfo['country'], // incoming format: http://www.iso.org/iso/country_codes/iso_3166_code_lists/country_names_and_code_elements.htm (e.g. DE or AT)
			# no field like "date of birth" in fe_users. incoming format: YYYY-MM-DD
			# no field like "gender" in fe_users. incoming format: "M" or "F"
			# no field like "language" in fe_users. incoming format: http://www.loc.gov/standards/iso639-2/php/code_list.php (e.g. de or de-DE)
			# no field like "timezone" in fe_users. incoming format: http://www.twinsun.com/tz/tz-link.htm (e.g.  "Europe/Paris" or "America/Los_Angeles")
		);
		$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $values);
		$user = tx_dixeasylogin_div::fetchUser($identifier);
		return $user;
	}

	static function makeCURLRequest($url, $method="GET", $params = "") {
		if (is_array($params)) {
			$params = http_build_query($params);
		}
		$curl = curl_init($url . ($method == "GET" && $params != "" ? "?" . $params : ""));
		
		//curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_HTTPGET, ($method == "GET"));
		curl_setopt($curl, CURLOPT_POST, ($method == "POST"));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		if ($method == "POST") {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		}
		
		$response = curl_exec($curl);
		return $response;
	}
	
	static function render_ttContent($uid) {
		$conf = array(
			'tables' => 'tt_content',
			'source' => $uid
		);
		return $GLOBALS['TSFE']->cObj->RECORDS($conf);
	}

	// @credits go out to http://modi.de/2010/02/12/fluid-without-extbase/
	static function renderFluidTemplate($filename, $values) {
		$renderer = t3lib_div::makeInstance('Tx_Fluid_View_StandaloneView');
		$path = $GLOBALS['piObj']->conf['template_path'];
		if (substr($path, -1) != '/') { $path .= '/'; }

		$controllerContext = t3lib_div::makeInstance('Tx_Extbase_MVC_Controller_ControllerContext');
		$controllerContext->setRequest(t3lib_div::makeInstance('Tx_Extbase_MVC_Request'));
		$renderer->setControllerContext($controllerContext);
		$renderer->setTemplatePathAndFilename(t3lib_div::getFileAbsFileName($path . $filename));
		$renderer->assign('values', $values);

		return $renderer->render();
	}
	
	static function getFileRelFileName($filename) {
		if (substr($filename, 0, 4) == 'EXT:') { // extension
			list($extKey, $local) = explode('/', substr($filename, 4), 2);
			$filename = '';
			if (strcmp($extKey, '') && t3lib_extMgm::isLoaded($extKey) && strcmp($local, '')) {
				$filename = t3lib_extMgm::siteRelPath($extKey) . $local;
			}
		}
		return $filename;
	}

}


class tx_dixeasylogin_openid {

	function init($provider, $piVars) {
		$this->provider = $provider;
		$this->piVars = $piVars;
	}

	function main() {
		if ($this->piVars['process']) {
			$error = $this->redirToProvider();
		} elseif ($this->piVars['action']=="verify" && t3lib_div::_GP('openid_mode') != "cancel") {
			$error = $this->verifyLogin();
		}
		return $error;
	}

	function redirToProvider() {
		$error = null;
		$openid_url = $this->getOpenidUrl(trim($this->piVars['userName']), $error);
		if ($error) { return $error; }

		$openid = t3lib_div::makeInstance('Dope_OpenID', $openid_url);
		$openid->setReturnURL(t3lib_div::locationHeaderUrl('index.php?id='.$GLOBALS['TSFE']->id.'&tx_dixeasylogin_pi1[action]=verify'));
		$trustRoot = t3lib_div::locationHeaderUrl('/');
		$openid->SetTrustRoot($trustRoot);
		if ($GLOBALS['piObj']->conf['optionalInfo']) {
			$openid->setOptionalInfo(t3lib_div::trimExplode(',', $GLOBALS['piObj']->conf['optionalInfo'])); // config
		}
		$openid->setRequiredInfo(t3lib_div::trimExplode(',', $GLOBALS['piObj']->conf['requiredInfo'])); // config
		//$openid->setPapePolicies('http://schemas.openid.net/pape/policies/2007/06/phishing-resistant '); // config
		//$openid->setPapeMaxAuthAge(120); // config
		
		/*
		* Attempt to discover the user's OpenID provider endpoint
		*/
		$endpoint_url = $openid->getOpenIDEndpoint();
		if($endpoint_url){
			$openid->redirect();
		} else {
			$the_error = $openid->getError();
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_endpoint'), $the_error['code'], $the_error['description']); // Error while getting OpenID endpoint (%s): %s
		}
		return $error;
	}

	function getOpenidUrl($name, &$error) {
		$url = str_replace('###NAME###', $name, $this->provider['url']);
		if (!tx_dixeasylogin_div::validateUrl($url)) {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('invalid_url'), htmlspecialchars($url)); // Error: OpenID Identifier is not in proper format (%s).
		}
		return $url;
	}

	function verifyLogin() {
		$openid_id = t3lib_div::_GP('openid_identity');
		$openid = t3lib_div::makeInstance('Dope_OpenID', $openid_id);
		$validate_result = $openid->validateWithServer();
		if ($validate_result === TRUE) {
			return tx_dixeasylogin_div::loginFromIdentifier($openid_id, $openid->filterUserInfo($_GET));
		} else if ($openid->isError() === TRUE){
			$the_error = $openid->getError();
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_validate'), $the_error['code'], $the_error['description']); // Error: Could not validate the OpenID
		} else {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_validate_nocode'), $openid_id); // Error: Could not validate the OpenID
		}
		return $error;
	}

}

class tx_dixeasylogin_oauth1 {
	function init($provider, $piVars) {
		$this->provider = $provider;
		$this->piVars = $piVars;

		$sigClass = 'OAuthSignatureMethod_'.trim(strtoupper($this->provider['sigMethod']));
		if (!class_exists($sigClass)) { $sigClass = 'OAuthSignatureMethod_HMAC_SHA1'; }
		$this->sigMethod = t3lib_div::makeInstance($sigClass);
		$this->consumer = new OAuthConsumer($this->provider['consumerKey'], $this->provider['consumerSecret'], NULL);
	}

	function main() {
		if ($this->piVars['process']) {
			$error = $this->getRequestToken();
			if (!$error) {
				$error = $this->redirToProvider();
			}
		} elseif ($this->piVars['action']=="verify") {
			$error = $this->verifyLogin();
		}
		return $error;
	}

	function getRequestToken() {
	  $req_req = OAuthRequest::from_consumer_and_token($this->consumer, NULL, "GET", $this->provider['requestTokenUrl'], array());
	  $req_req->sign_request($this->sigMethod, $this->consumer, NULL);

		$response = tx_dixeasylogin_div::makeCURLRequest((string)$req_req, 'GET', array());

	  $params = array();
	  parse_str($response, $params);
	  $this->oauth_token = $params['oauth_token'];
	  $this->oauth_token_secret = $params['oauth_token_secret'];
	  if (!$this->oauth_token || !$this->oauth_token_secret) { return sprintf($GLOBALS['piObj']->pi_getLL('error_reqToken'), $response); }
		$GLOBALS["TSFE"]->fe_user->setKey("ses", "easylogin_oauth_token", $this->oauth_token);
		$GLOBALS["TSFE"]->fe_user->setKey("ses", "easylogin_oauth_token_secret", $this->oauth_token_secret);
	}

	function redirToProvider() { // authorize
		$callback_url = t3lib_div::locationHeaderUrl($GLOBALS['piObj']->pi_linkTP_keepPIvars_url(array('action' => 'verify'),0,1));
  	$auth_url = $this->provider['authorizeUrl'] . '?oauth_token='.$this->oauth_token.'&oauth_callback='.urlencode($callback_url);
	  header("Location: $auth_url");
	}

	function verifyLogin() { // get access token
		$error = '';
		$this->oauth_token = $GLOBALS["TSFE"]->fe_user->getKey("ses", "easylogin_oauth_token");
		$this->oauth_token_secret = $GLOBALS["TSFE"]->fe_user->getKey("ses", "easylogin_oauth_token_secret");

		$tokenObj = t3lib_div::makeInstance('OAuthConsumer', $this->oauth_token, $this->oauth_token_secret);
	  $acc_req = OAuthRequest::from_consumer_and_token($this->consumer, $tokenObj, "GET", $this->provider['accessTokenUrl'], array());
	  $acc_req->sign_request($this->sigMethod, $this->consumer, $tokenObj);

		$response = tx_dixeasylogin_div::makeCURLRequest((string)$acc_req, 'GET', array());

	  $params = array();
	  parse_str($response, $params);
	  // problem here: according to oauth specs there is no need for a response parameter identifing the user. 
		// twitter uses "user_id" but other oauth providers may use "userid", "uid", "user", "id" or worst: nothing at all
		if (!$params['oauth_token']) {
			$error = sprintf($GLOBALS['piObj']->pi_getLL('error_getting_accesstoken'), $request); // Error: Could not get access token (%s)
			return $error; 
		}
		
		$userinfo = $this->getUserInfo($params, $error);
		if ($error) { return $error; }
		return tx_dixeasylogin_div::loginFromIdentifier($userinfo['id'], $userinfo);
	}

	function getUserInfo($accessTokenParams, &$error) {
		$endpoint = $this->provider['requestProfileUrl'];
		$markerNames = $this->extractMarker($endpoint);
		foreach ($markerNames as $v) {
			$endpoint = str_replace('###'.$v.'###', $accessTokenParams[$v], $endpoint);
		}
		$tokenObj = t3lib_div::makeInstance('OAuthConsumer', $this->oauth_token, $this->oauth_token_secret);
	  $req = OAuthRequest::from_consumer_and_token($this->consumer, $tokenObj, "GET", $endpoint, array());
	  $req->sign_request($this->sigMethod, $this->consumer, $tokenObj);

		$response = tx_dixeasylogin_div::makeCURLRequest((string)$req, 'GET', array());
		$details = json_decode($response, true);
		if ($details[0]) { $details = $details[0]; } // when the details are stored in an object capsulated in an array (twitter)
		$userinfo = array();
		foreach ($this->provider['profileMap.'] as $dbField => $detailsField) {
			$userinfo[$dbField] = $details[$detailsField];
		}
		if (!$userinfo['id']) {
			$error = $GLOBALS['piObj']->pi_getLL('error_getting_userinfo'); // Error: While retrieving user details, the user id was empty
		}
		$userinfo['id'] = 'oauth1-'.$this->provider['key'].'-'.$userinfo['id'];
		return $userinfo;
	}
	
	function extractMarker($str) {
		$result = array();
		while (strpos($str, '###') !== false) {
			$start = strpos($str, '###') + 3;
			$stop = strpos($str, '###', $start);
			$result[] = substr($str, $start, $stop-strlen($str));
			$str = substr($str, $stop+3);
		}
		return $result;
	}
}

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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dix_easylogin/pi1/class.tx_dixeasylogin_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dix_easylogin/pi1/class.tx_dixeasylogin_pi1.php']);
}

?>