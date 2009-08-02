<?php
/**
* @package      fwolflib
* @subpackage	class
* @copyright    Copyright 2009, Fwolf
* @author       Fwolf <fwolf.aide+fwolflib.class@gmail.com>
*/

require_once('fwolflib/func/validate.php');

/**
 * Form operate class
 *
 * Generate html of form,
 * plus operate like validate, data recieve etc.
 *
 * Reference:
 * <http://pear.php.net/package/HTML_QuickForm/docs>
 * Form format:
 * <http://www.52css.com/article.asp?id=238>
 *
 * @package		fwolflib
 * @subpackage	class
 * @copyright	Copyright 2009, Fwolf
 * @author		Fwolf <fwolf.aide+fwolflib.class@gmail.com>
 * @since		2009-07-26
 * @version		$Id$
 */
class Form
{
	/**
	 * Configuration
	 * @var	array
	 */
	protected $aConfig = array(
		'action'	=> '',
		// enctype default 'application/x-www-form-urlencoded'
		'enctype'	=> '',
		// Default id=name, so only define one
		//'id'		=> 'fwolflib_form',
		'method'	=> 'POST',
		'name'		=> 'fl_form',
	);

	/**
	 * Form element define, raw order
	 *
	 * First value of attrib is DEFAULT value.
	 * array(
	 * 	name => array(
	 * 		name,
	 * 		type,
	 * 		label,
	 * 		attrib = array(
	 * 		),
	 * 	)
	 * )
	 * @var	array
	 * @see	$aElementAttribDefault
	 */
	protected $aElement = array();

	/**
	 * Default value of element attrib, use if not defined
	 * @var	array
	 */
	public $aElementAttribDefault = array(
		// Additional html define ?
		'html_add'	=> '',
		// Will following element stay in same row ?
		'keep_div'	=> false,
		// Label is before input or after it ?
		'label_pos'	=> 'before',
		// Value normally has no default value
		'value'		=> null,
	);

	/**
	 * Flag control <div> generate when doing element
	 * 0 not setuped
	 * 1 cur element will not end <div>
	 * can be recursive.
	 * @var	boolean
	 */
	protected $iFlagKeepDiv = false;


	/**
	 * contruct
	 */
	public function __construct() {

	} // end of func __construct


	/**
	 * Add an element define
	 * @param	string	$type
	 * @param	string	$name	Must not be empty or duplicate
	 * @param	string	$label
	 * @param	array	$attrib	Additional html attributes.
	 * @see		$aElement
	 */
	public function AddElement($type, $name, $label = '', $attrib = array()) {
		$this->aElement[$name] = array(
			'name'		=> $name,
			'type'		=> $type,
			'label'		=> $label,
			'attrib'	=> $attrib,
		);
		if ('file' == $type)
			$this->SetConfigEnctype(1);
	} // end of func AddElement


	/**
	 * Add element attribe define
	 * @param	string	$name
	 * @param	string	$key
	 * @param	mixed	$val
	 * @see		$aElement
	 */
	public function AddElementAttrib($name, $key, $val = null) {
		if (isset($this->aElement[$name]))
			$this->aElement[$name]['attrib'][$key] = $val;
	} // end of func AddElementAttrib


	/**
	 * Add element value attrib
	 *
	 * If $name is an array, it's a name/value array,
	 * or only assign $v to single element $name.
	 * @param	mixed	$name
	 * @param	mixed	$v
	 */
	public function AddElementValue($name, $v = null) {
		if (is_array($name)) {
			foreach ($name as $key => $val)
				$this->AddElementValue($key, $val);
		}
		else {
			if (!empty($v) && isset($this->aElement[$name]))
				$this->aElement[$name]['attrib']['value'] = $v;
		}
	} // end of func AddElementValue


	/**
	 * Get html of an element
	 * @param	array	$v
	 * @return	string
	 * @see AddElement()
	 */
	public function GetElement($elt) {
		$s_html = '';

		if (isset($elt['attrib']['label_align'])
			&& ('after' == $elt['attrib']['label_align']))
			$s_div = 'fl_elt_div_lr';
		else
			$s_div = 'fl_elt_div_ll';

		if (false == $this->iFlagKeepDiv)
			$s_html .= '<div class="' . $s_div . '" id="fl_elt_div_'
				. $elt['name'] . '">' . "\n";

		switch ($elt['type']) {
			case 'checkbox':
			case 'file':
			case 'image':
			case 'password':
			case 'radio':
			case 'text':
				$s_html .= $this->GetElementInput($elt);
				break;
			case 'button':
			case 'reset':
			case 'submit':
				$s_html .= $this->GetElementButton($elt);
				break;
			case 'hidden':
				// Do not need outer div, so use return directly.
				return $this->GetElementHidden($elt);
				break;
/*
				$s_html .= $this->GetElementFile($elt);
				break;
*/
		}

		if (isset($elt['attrib']['keep_div'])
			&& (true == $elt['attrib']['keep_div']))
			$this->iFlagKeepDiv = true;
		else
			$this->iFlagKeepDiv = false;
		if (false == $this->iFlagKeepDiv)
			$s_html .= '</div>' . "\n";

		return $s_html;
	} // end of func GetElement


	/**
	 * Get html of element input/submit button
	 * @param	array	$elt
	 * @return	string
	 * @see	AddElement()
	 */
	protected function GetElementButton($elt) {
		$s_html = $this->GetHtmlInput($elt);
		// Label set as value
		$s_html = str_replace('/>', 'value="' . $elt['label'] . '" />'
			, $s_html);
		return $s_html;
	} // end of func GetElementButton


	/**
	 * Get html of element hidden
	 * @param	array	$elt
	 * @return	string
	 * @see AddElement()
	 */
	protected function GetElementHidden($elt) {
		$s_html = $this->GetHtmlInput($elt);
		if (isset($elt['attrib']['value']))
			$s_html = str_replace('/>'
				, 'value="' . $elt['attrib']['value'] . '" />'
				, $s_html);
		return $s_html;
	} // end of func GetElementHidden


	/**
	 * Get html of element common input
	 * @param	array	$elt
	 * @return	string
	 * @see AddElement()
	 */
	protected function GetElementInput($elt) {
		$s_label = $this->GetHtmlLabel($elt);
		// Plus str without label
		$s_input = $this->GetElementHidden($elt);

		if (isset($elt['attrib']['label_align'])
			&& ('after' == $elt['attrib']['label_align']))
			$s_html = $s_input . $s_label;
		else
			$s_html = $s_label . $s_input;

		return $s_html;
	} // end of func GetElementInput


	/**
	 * Get form html
	 * @return	string
	 */
	public function GetHtml() {
		$s_html = '';
		// Form style, for typeset only
		// ll = label left, lr = label right
		$s_html .= '
		<style type="text/css" media="screen, print">
		<!--
		#' . $this->aConfig['name'] . ' .fl_elt_div_ll {
			clear: left;
			margin-top: 0.2em;
		}
		#' . $this->aConfig['name'] . ' .fl_elt_div_ll label {
			float: left;
			text-align: right;
			margin-right: 0.3em;
			padding-top: 0.2em;
		}
		#' . $this->aConfig['name'] . ' .fl_elt_div_lr {
			/*clear: right;*/
			margin-top: 0.2em;
		}
		#' . $this->aConfig['name'] . ' .fl_elt_div_lr label {
			/*float: right;*/
			text-align: left;
			margin-left: 0.3em;
			padding-top: 0.2em;
		}
		-->
		</style>
		';

		// Form head
		$s_html .= '<form ';
		foreach ($this->aConfig as $k => $v) {
			if (!empty($v))
				$s_html .= $k . '="' . $v . '" ';
		}
		if (!empty($this->aConfig['name']))
			$s_html .= 'id="' . $this->aConfig['name'] . '" ';
		$s_html .= " >\n";

		// Form body
		foreach ($this->aElement as $v) {
			$s_html .= $this->GetElement($v);
		}

		// Form footer
		$s_html .= "</form>\n";
		return $s_html;
	} // end of func GetHtml


	/**
	 * Get html of element's label part
	 * @param	array	$elt
	 * @return	string
	 * @see GetElement()
	 */
	protected function GetHtmlLabel($elt) {
		$s_label = '';
		if (!empty($elt['label'])) {
			$s_label .= '<label for="' . $elt['name'] . '">';
			$s_label .= $elt['label'] . '</label>' . "\n";
		}
		return $s_label;
	} // end of func GetHtmlLabel


	/**
	 * Get html of element's input part
	 * @param	array	$elt
	 * @return	string
	 * @see AddElement()
	 */
	protected function GetHtmlInput($elt) {
		$s_input = '';
		$s_input .= '<input ';
		$s_input .= 'type="' . $elt['type'] . '" ';
		$s_input .= 'name="' . $elt['name'] . '" ';
		$s_input .= 'id="' . $elt['name'] . '" ';
		if (isset($elt['attrib']['html_add'])
			&& (true == $elt['attrib']['html_add']))
			$s_input .= $elt['attrib']['html_add'];
		$s_input .= '/>' . "\n";

		return $s_input;
	} // end of func GetHtmlInput


	/**
	 * Set configuration
	 * @param	array|string	$c	Config array or name/value pair.
	 * @param	string			$v	Config value
	 * @see	$aConfig
	 */
	public function SetConfig($c, $v = '') {
		if (is_array($c)) {
			if (!empty($c))
				foreach ($c as $idx => $val)
					$this->SetConfig($idx, $val);
		}
		else
			$this->aConfig[$c] = $v;
	} // end of func SetConfig


	/**
	 * Set configuration enctype
	 * @param	int	$type	0:application/x-www-form-urlencoded
	 * 						1:multipart/form-data
	 * 						other value will empty the setting
	 */
	public function SetConfigEnctype($type = 0) {
		if (0 == $type)
			$this->aConfig['enctype'] = 'application/x-www-form-urlencoded';
		else if (1 == $type)
			$this->aConfig['enctype'] = 'multipart/form-data';
		else
			$this->aConfig['enctype'] = '';
	} // end of func SetConfigEnctype
} // end of class Form
?>