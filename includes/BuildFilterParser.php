<?php
/**
 * Filter Parser built for the parsing of the templates
 * to generate JSON conditions
 *
 * @var array $builds			list of specific builds
 * @var array $content			parsed html content of the element
 * @var bool $hascondition
*/
class BuildFilterParser {

	public $builds;
	public $content;
	public $hascondition;

	function __construct() {
		$this->builds = array();
	}

	/**
	 * @param string $input
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return array Conditions
	 *
	 * Iterates through all content of the html element rendering and parsing it
	 * any conditional templates are converted to Conditions and returned
	*/
	public function parseContent( $input, Parser $parser, PPFrame $frame ) {	
		global $wgBuildFilterTemplate, $wgBuildFilterNested;
	
		$templatename = '{{' . $wgBuildFilterTemplate;
	
		// split by newline
		$this->content = preg_split('/\r\n|\r|\n/', $input);
	
		// Condition list
		$conditions = array( new Condition() );
		for( $i = 0; $i < count( $this->content ); $i++ ) {
		
			// extract the line, parse the content and store the expanded result
			$line = $this->content[$i];
			$template = $parser->replaceVariables( $line, $frame );			$template = $parser->doDoubleUnderscore( $template );			$template = $parser->doHeadings( $template );			$template = $parser->replaceInternalLinks( $template );			$template = $parser->doAllQuotes( $template );			$template = $parser->replaceExternalLinks( $template );			$template = $parser->doMagicLinks( $template );			
			$this->content[$i] = $template;
		
			// if the template exists and is valid
			// extract the property information and create a Condition
			if( strpos( $line, $templatename ) !== false && strpos( $template, 'ERROR:' ) === false )  {
			
				$condition = $this->parseCondition( $line ); // attempt to parse the Condition
				if( $condition !== null ) {
				
					$this->hascondition = true;
				
					// set the Condition inner html and store
					array_push( $condition->data, $template );
					array_push( $conditions, $condition );
				
					// endifs start a new condition
					if( $condition->condition == 'endif' )
						array_push( $conditions, new Condition() );
				
					// success
					continue;
				}
			}
		
			// condition template missing or has errors
			$condition = end ( $conditions );
			array_push( $condition->data, $template );
		}
	
		// remove Conditions without inner html
		$filtered = array_filter( $conditions, function( $c ) { return !empty( $c->data); });
	
		// apply grouping based on the $wgBuildFilterNested setting
		$grouped = array();
		$grouping_func = ( $wgBuildFilterNested ? 'nestedGroup' : 'simpleGroup' );
	
		while( !empty( $filtered ) )
			array_push( $grouped, $this->$grouping_func( $filtered ) );
	
		return $grouped;
	}

	/**
	 * @param string $line
	 * @return Condition?
	 *
	 * Attempts to parse the Condition template to produce a Condition object
	 * also performs basic validation and corrects/applies build information
	*/
	function parseCondition( $line ) {
	
		$attrregex = '/([a-z_]+)=([a-z0-9.]+)/i';
		$buildregex = '/^((\d{1,2}\.){3}\d{4,6})$/';
		$valid_conditions = array( 'if', 'elseif', 'else', 'endif' );
	
		if( preg_match_all( $attrregex, $line, $matches) ) {
		
			$condition = new Condition();
		
			// attributes that require processing
			$attributes = array(
				'min_exclusive' => false,
				'max_exclusive' => false,
				'min_expansionlevel' => -1,
				'max_expansionlevel' => 99,
			);
			// extract each attribute and it's value
			$attrcount = count( $matches[0] );
			for( $i = 0; $i < $attrcount; $i++ ) {
		
				$attr = strtolower( $matches[1][$i] );
				$val = $matches[2][$i];
				switch( $attr ) {
					// condition
					case 'condition':
						$condition->condition = $val;
						break;
					
					// expansionlevels must be integers
					case 'min_expansionlevel':
					case 'max_expansionlevel':
						if( is_numeric( $val ) )
							$attributes[$attr] = (int)$val;
						break;
					
					// check builds are formatted correctly
					case 'min_build':
					case 'max_build':
						if( preg_match( $buildregex, $val ) )
							$condition->builds[$attr] = $val;
						break;
					
					// validate exclusives as booleans
					case 'min_exclusive':
					case 'max_exclusive':
						$attributes[$attr] = ( $val === '1' );
						break;
					
					// ignore anything unnecessary
					default:
						continue;
				}
			}
		
			// validate condition
			if( !in_array( $condition->condition, $valid_conditions ) )
				return null;
		
			// no validation required
			if( $condition->condition === 'else' ||
				$condition->condition === 'endif' )
				return $condition;
		
			// ensure a valid version argument exists
			if( $condition->builds['min_build'] === '' &&
				$condition->builds['max_build'] === '' &&
				$attributes['min_expansionlevel'] === -1 &&
				$attributes['max_expansionlevel'] === 99 )
				return null;
			// exclude exclusive-build combination
			if( ( $condition->builds['min_build'] !== '' && $attributes['min_exclusive'] ) ||
				( $condition->builds['max_build'] !== '' && $attributes['max_exclusive'] ) )
				return null;
			// append specified builds to the build list				// and convert expansions to build numbers			// Note: explicit builds take priority
			$this->updateBuildList( $condition );
			$this->convertExpansions( $condition, $attributes );
					
			return $condition;
		}
	
		return null;
	}

	/**
	 * @param &array $condition
	 *
	 * Validates the min_build and max_build values of the condition
	 * preventing overflows and stores specified builds
	 *
	*/
	function updateBuildList( &$condition ) {	
		global $wgBuildFilterExpansions;
	
		$expansions = array_keys( $wgBuildFilterExpansions );
		$maxbuilds = array_values( $wgBuildFilterExpansions );
		$maxexp = count( $expansions );	
		foreach( array( 'min_build', 'max_build' ) as $attr ) {
		
			if( $condition->builds[$attr] === '' )
				continue;
		
			// split build into an int array of [exp,maj,min,build]
			$parts = array_map('intval', explode('.', $condition->builds[$attr]) );
		
			// clamp expansion
			$exp = min( max( $parts[0], 0 ), $maxexp );
		
			// clamp build
			$build = ( $parts[0] < 0 ? 0 : ( $parts[0] > $maxexp ? max( $maxbuilds ) : end( $parts ) ) );
		
			// append to the build list
			$this->builds["{$expansions[$exp]} ({$build})"] = "{$exp}.{$build}";
		}
	}

	/**
	 * @param &array $condition
	 * @param &array $attributes
	 *
	 * Validates the min_expansionlevel and max_expansionlevel values of the Condition
	 * preventing overflows and applies build values taking into account exclusiveness
	*/
	function convertExpansions( &$condition, &$attributes ) {	
		global $wgBuildFilterExpansions;
			$maxexp = count( $wgBuildFilterExpansions ) - 1;
		$maxbuilds = array_values( $wgBuildFilterExpansions );
	
		foreach( array( 'min', 'max' ) as $prefix ) {
		
			// skip specified builds
			if( $condition->builds[ $prefix . '_build' ] !== '' )
				continue;
		
			$is_exclusive = $attributes[ $prefix . '_exclusive' ];
			$exp = $attributes[ $prefix . '_expansionlevel' ];
		
			// recalculate exclusive expansionlevels
			if( $is_exclusive )
				$exp += ( $prefix === 'min' ? 1 : -1 );
		
			// clamp build
			$build = ( $exp < 0 ? 0 : ( $exp > $maxexp ? max( $maxbuilds ) : $maxbuilds[$exp] ) );
		
			// clamp expansion
			$exp = min( max( $exp, 0 ), $maxexp );
		
			// set exp and build - major and minor are currently unused
			$condition->builds[ $prefix . '_build' ] = "{$exp}.0.0.{$build}";
		}
	}
	/**
	 * @param &array $conditions
	 * @return array $branch
	 *
	 * Conditions are grouped until an 'if' or <blank> condition is found
	 * it is assumed Conditions are logically ordered
	*/
	function simpleGroup( array &$conditions ) {
	
		$branch = array();
		$first = true;
	
		while( !empty( $conditions ) ) {

			// new group required
			if( !$first && ( $conditions[0]->condition === '' || $conditions[0]->condition === 'if' ) )
				return $branch;
		
			array_push( $branch, array_shift( $conditions ) );
			$first = false;
		}

		return $branch;
	}

	/**
	 * @param &array $conditions
	 * @param bool $ischild (optional)
	 * @return array $branch
	 *
	 * Creates nested Conditions
	*/
	function nestedGroup( array &$conditions, bool $ischild = false ) {
	
		$branch = array();
		$first = true;
	
		// child branches must start with an if condition
		if( !empty( $conditions ) && $ischild && $conditions[0]->condition !== 'if' )
			return $branch;
		while( !empty( $conditions ) ) {
		
			// <blank> starts a new branch
			if( !$first && $conditions[0]->condition === ''  )
				return $branch;
					
			// assign to the current branch
			$c = array_shift( $conditions );
			array_push( $branch, $c );
			$first = false;
		
			// endif and <blank> ends the current branch
			if( $c->condition === 'endif' || $c->condition === '' )
				return $branch;
		
			// apply nested conditions
			$c->children = $this->nestedGroup( $conditions, true );
		}
		return $branch;
	}

}