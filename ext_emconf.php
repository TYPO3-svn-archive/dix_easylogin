<?php

########################################################################
# Extension Manager/Repository config file for ext "dix_easylogin".
#
# Auto generated 27-02-2012 17:50
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Easy Login and Register with OpenID (FE)',
	'description' => 'Do you know facebook connect? Easylogin is even better because it works not only with facebook but also with Google, Yahoo, myOpenId, twitter, and all other providers that offer OpenID or OAuth. It also integrates the common felogin (Username/Password)',
	'category' => 'fe',
	'author' => 'Markus Kappe',
	'author_email' => 'markus.kappe@dix.at',
	'shy' => '',
	'dependencies' => 'fluid,extbase',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.2.5',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-4.6.99',
			'fluid' => '',
			'extbase' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:36:{s:9:"ChangeLog";s:4:"5343";s:10:"README.txt";s:4:"bfc8";s:12:"ext_icon.gif";s:4:"f9b3";s:17:"ext_localconf.php";s:4:"5098";s:14:"ext_tables.php";s:4:"66b1";s:14:"ext_tables.sql";s:4:"08e3";s:16:"locallang_db.xml";s:4:"ff17";s:14:"doc/manual.sxw";s:4:"4c00";s:33:"pi1/class.tx_dixeasylogin_div.php";s:4:"2819";s:38:"pi1/class.tx_dixeasylogin_facebook.php";s:4:"d34e";s:36:"pi1/class.tx_dixeasylogin_oauth1.php";s:4:"0c7c";s:36:"pi1/class.tx_dixeasylogin_openid.php";s:4:"4adf";s:33:"pi1/class.tx_dixeasylogin_pi1.php";s:4:"fb2b";s:17:"pi1/locallang.xml";s:4:"99f8";s:29:"res/dope/class.dopeopenid.php";s:4:"d654";s:21:"res/icons/blogger.ico";s:4:"6c92";s:22:"res/icons/facebook.jpg";s:4:"d707";s:20:"res/icons/flickr.ico";s:4:"9bac";s:20:"res/icons/google.gif";s:4:"ca68";s:22:"res/icons/myopenid.ico";s:4:"b22b";s:21:"res/icons/twitter.gif";s:4:"a8b3";s:23:"res/icons/wordpress.ico";s:4:"6cec";s:19:"res/icons/yahoo.ico";s:4:"1698";s:19:"res/oauth/OAuth.php";s:4:"ee05";s:25:"res/yadis/HTTPFetcher.php";s:4:"5138";s:21:"res/yadis/Manager.php";s:4:"5d0a";s:33:"res/yadis/ParanoidHTTPFetcher.php";s:4:"4489";s:23:"res/yadis/ParseHTML.php";s:4:"965f";s:30:"res/yadis/PlainHTTPFetcher.php";s:4:"09c9";s:17:"res/yadis/XML.php";s:4:"09c4";s:18:"res/yadis/XRDS.php";s:4:"bfe5";s:19:"res/yadis/Yadis.php";s:4:"7567";s:20:"static/constants.txt";s:4:"e9b9";s:16:"static/setup.txt";s:4:"cd00";s:20:"templates/login.tmpl";s:4:"b9f4";s:19:"templates/xrds.tmpl";s:4:"adfe";}',
	'suggests' => array(
	),
);

?>