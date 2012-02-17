<?php

/**
 * This is the main web entry point for MediaWiki.
 *
 * If you are reading this in your web browser, your server is probably
 * not configured correctly to run PHP applications!
 *
 * See the README, INSTALL, and UPGRADE files for basic setup instructions
 * and pointers to the online documentation.
 *
 * http://www.mediawiki.org/
 *
 * ----------
 *
 * Copyright (C) 2001-2007 Magnus Manske, Brion Vibber, Lee Daniel Crocker,
 * Tim Starling, Erik Möller, Gabriel Wicke, Ævar Arnfjörð Bjarmason,
 * Niklas Laxström, Domas Mituzas, Rob Church and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */


# Initialise common code
require_once( './includes/WebStart.php' );

# Initialize MediaWiki base class
require_once( "includes/Wiki.php" );
$mediaWiki = new MediaWiki();

wfProfileIn( 'main-misc-setup' );
OutputPage::setEncodings(); # Not really used yet

$maxLag = $wgRequest->getVal( 'maxlag' );
if ( !is_null( $maxLag ) ) {
	if ( !$mediaWiki->checkMaxLag( $maxLag ) ) {
		exit;
	}
}

# Query string fields
$action = $wgRequest->getVal( 'action', 'view' );
$title = $wgRequest->getVal( 'title' );

$wgTitle = $mediaWiki->checkInitialQueries( $title,$action,$wgOut, $wgRequest, $wgContLang );
if ($wgTitle == NULL) {
	unset( $wgTitle );
}

#
# Send Ajax requests to the Ajax dispatcher.
#
if ( $wgUseAjax && $action == 'ajax' ) {
	require_once( $IP . '/includes/AjaxDispatcher.php' );

	$dispatcher = new AjaxDispatcher();
	$dispatcher->performAction();
	$mediaWiki->restInPeace( $wgLoadBalancer );
	exit;
}


wfProfileOut( 'main-misc-setup' );

# Setting global variables in mediaWiki
$mediaWiki->setVal( 'Server', $wgServer );
$mediaWiki->setVal( 'DisableInternalSearch', $wgDisableInternalSearch );
$mediaWiki->setVal( 'action', $action );
$mediaWiki->setVal( 'SquidMaxage', $wgSquidMaxage );
$mediaWiki->setVal( 'EnableDublinCoreRdf', $wgEnableDublinCoreRdf );
$mediaWiki->setVal( 'EnableCreativeCommonsRdf', $wgEnableCreativeCommonsRdf );
$mediaWiki->setVal( 'CommandLineMode', $wgCommandLineMode );
$mediaWiki->setVal( 'UseExternalEditor', $wgUseExternalEditor );
$mediaWiki->setVal( 'DisabledActions', $wgDisabledActions );

$wgArticle = $mediaWiki->initialize ( $wgTitle, $wgOut, $wgUser, $wgRequest );

// MindTouch (steveb): create parser and register custom extensions
$p = new Parser;

// add list of custom extensions
$p->mTagHooks = array(
	'breadcrumbs' => '', 
	'math' => '',
	'rss' => '',
	'title-override' => '',
	'abbr' => '',
	'kbd' => '',
	'math' => '',
	'object' => ''
);

// add languages
Title::AddCachedPrefix('ca');
Title::AddCachedPrefix('cs');
Title::AddCachedPrefix('de');
Title::AddCachedPrefix('en');
Title::AddCachedPrefix('es');
Title::AddCachedPrefix('fi');
Title::AddCachedPrefix('fr');
Title::AddCachedPrefix('hu');
Title::AddCachedPrefix('it');
Title::AddCachedPrefix('ja');
Title::AddCachedPrefix('ko');
Title::AddCachedPrefix('nl');
Title::AddCachedPrefix('pl');
Title::AddCachedPrefix('pt');
Title::AddCachedPrefix('ru');
Title::AddCachedPrefix('zh-cn');
Title::AddCachedPrefix('zh-tw');

// set article path
$wgArticlePath = '$1';

// read request text (or provide default)
$text = $wgRequest->getVal('text', "'''MindTouch MediaWiki Converter bridge is installed.'''");

foreach(explode(',', $wgRequest->getVal('interwiki', '')) as $prefix) {
	if($prefix != '') {
		$wgInterwikiList[$prefix] = $prefix;
	}
}

// parse request text
$result = $p->parse($text, $wgTitle, new ParserOptions);

// set content-type for response
header( "Content-type: text/html; charset=utf-8" );

// emit converted request text
echo('<html xmlns:mediawiki="#mediawiki">');
echo('<head>');
echo('<title>' . htmlspecialchars(($title != NULL) ? $title : '') . '</title>');
foreach($result->getCategories() as $category => $sort) {
	echo('<mediawiki:meta type="category">' . htmlspecialchars($category) . '</mediawiki:meta>');
}
global $wgLang;
foreach($result->getLanguageLinks() as $language) {
	$parts = split(':', $language, 2);
	
	// load language so we can convert titles to their canonicalform
	$wgContLang = Language::factory($parts[0]);
	$canonicalTitle = Title::newFromDBkey($parts[1]);
	if($canonicalTitle) {
		echo('<mediawiki:meta type="language" language="' . $parts[0] . '">' . $canonicalTitle->getNormalizedDbKey() . '</mediawiki:meta>');
	} else {
		echo('<mediawiki:meta type="language" language="' . $parts[0] . '">' . htmlspecialchars($parts[1]) . '</mediawiki:meta>');
	}
}
echo('</head>');
echo('<body>');
echo(strtr($result->mText, array( 
	'.7B' => '{',
	'.7C' => '|',
	'.7D' => '}',
	'.26' => '&'
)));
echo('</body>');
echo('</html>');
exit;
// MindTouch: --- end

$mediaWiki->finalCleanup ( $wgDeferredUpdateList, $wgLoadBalancer, $wgOut );

# Not sure when $wgPostCommitUpdateList gets set, so I keep this separate from finalCleanup
$mediaWiki->doUpdates( $wgPostCommitUpdateList );

$mediaWiki->restInPeace( $wgLoadBalancer );

