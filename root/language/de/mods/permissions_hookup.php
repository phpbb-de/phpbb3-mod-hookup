<?php
/**
*
* hookup mod [Deutsch - Du]
*
* @package language
* @copyright (c) 2006-2008 Pyramide (Frank Dreyer)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*
*/


/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// Define categories and permission types
$lang = array_merge($lang, array(
	'acl_f_hookup'		=> array('lang' => 'Kann Terminplaner hinzufügen', 'cat' => 'content')
));


?>