<?php
/**
 * JSON model for Conditions
 *
 * @var string $condition	valid arguments are <blank>, if, elseif, else and endif
 * @var array $data			content html to be shown if this condition is valid
 * @var array $builds		min and max build for the condition
 * @var array $children		child conditions if nesting is enabled
*/
class Condition {
	public $condition;
	public $data;
	public $builds;
	public $children;

	function __construct() {
		$this->condition = '';
		$this->data = array();
		$this->children = array();
		$this->builds = array(
			'min_build' => '',
			'max_build' => ''
		);
	}
}