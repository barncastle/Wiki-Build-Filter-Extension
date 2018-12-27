<?php
include 'Condition.php';
include 'BuildFilterParser.php';
/**
 * Extension hooks
*/
class BuildFilterHooks {

	/**
	 * @param Parser $parser
	 * @return boolean true
	 *
	 *  MW hook
	 * overrides tag parsing and injects the css/js modules
	*/
	static function onParserInit( Parser $parser ) {
		global $wgOut, $wgBuildFilterTag;
	
		// apply customer render hook to the tag
		$parser->setHook( $wgBuildFilterTag, array( __CLASS__, 'customRender' ) );
	
		// apply css/js module
		$wgOut->addModules( 'ext.buildFilter' );
	
		return true;
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string html
	 *
	 * Custom render implementation
	 * validates if the filter parser is required
	 * if so; builds the custom control html, content and JSON
	*/
	static function customRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgBuildFilterTemplate;
	
		// no templates to parse
		if( strpos( $input, $wgBuildFilterTemplate ) === false )
			return $input;
	
		// remove whitespace bar the first
		$input = ' ' . trim($input);
			
		// parse templates and generate Conditions	
		$filterparser = new BuildFilterParser();
		$conditions = $filterparser->parseContent( $input, $parser, $frame );
	
		// no conditions
		// return the already rendered content
		if( !$filterparser->hascondition )
			return join("\n", $filterparser->content);
			
		// build html output
		$html  = '<div class="build-filter">';
		$html .= '	<script class="build-filter-data" type="application/json">' . json_encode( $conditions, JSON_UNESCAPED_UNICODE ) . '</script>';
		$html .= '	<div class="build-filter-panel">';
		$html .= '		<select class="build-filter-select">';
		$html .= 			self::buildOptions( $filterparser->builds );
		$html .= '		</select>';
		$html .= '		<img class="build-filter-image">';
		$html .= '	</div>';
		$html .= '	<pre>';
		$html .=		join("\n", $filterparser->content);
		$html .= '	</pre>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param &array $builds
	 * @return string html
	 *
	 * Generates the options for the filter dropdown based on
	 * the known final expansion builds and any Condition specific builds
	*/
	static function buildOptions( &$builds ) {
		global $wgBuildFilterExpansions, $wgBuildFilterDefaultBuild;
	
		// apply known final exp builds
		$expkeys = array_keys( $wgBuildFilterExpansions );

		for( $i = 0; $i < count( $expkeys ); $i++ ) {
		
			$exp = $expkeys[$i];
			$build = $wgBuildFilterExpansions[$expkeys[$i]];
					
			// override build to LIVE for the final exp
			$key = "{$exp} " . ( $build === 99999 ? '(LIVE)' : "({$build})" );		
			$builds[$key] = "{$i}.{$build}";
		}
	
		// sort by value		// TODO will need changing when the 10th exp is released
		asort($builds);
		// prefix an All option and build the options html
		$options = "<option value='-1'>All</option>\n";
		foreach( $builds as $k=>$v ) {						$selected = ( substr( $v, -strlen( $wgBuildFilterDefaultBuild )) == $wgBuildFilterDefaultBuild ) ? 'selected' : '';					$options .= "<option value='{$v}' {$selected}>{$k}</option>\n";		}
			
		
		return $options;
	}
}