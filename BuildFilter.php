<?php
include 'includes/BuildFilterHooks.php';
/**
 * User Defined Settings
*/
// mediawiki tag
$wgBuildFilterTag = 'conditional';
// conditional template
$wgBuildFilterTemplate = 'Template:Condition';
// allow recursive conditions
$wgBuildFilterNested = true;
// list of expansion names and their final builds
$wgBuildFilterExpansions = array(
	'PreVanilla' => 3988,
	'Vanilla'    => 6141,
	'TBC'        => 8606,
	'WotLK'      => 12340,
	'Cata'       => 15595,
	'MoP'        => 18414,
	'WoD'        => 21742,
	'Legion'     => 26972,
	'BfA'        => 99999
);
// default dropdown build (or -1 for the All view)
$wgBuildFilterDefaultBuild = -1;

/**
 * Extension Requirements
*/
// hooks
$wgHooks['ParserFirstCallInit'][] = 'BuildFilterHooks::onParserInit';
// styles and javascript
$wgResourceModules['ext.buildFilter'] = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'BuildFilter',
	'styles' => 'modules/ext.BuildFilter.css',
	'scripts' => 'modules/ext.BuildFilter.js'
);
// version info
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'BuildFilter',
	'description' => 'Apply build filtering to pre elements wrapped by <code>&lt;' . $wgBuildFilterTag . '&gt;</code> tags',
	'version' => '1.0.0',
	'author' => 'WoWDevWiki'
);