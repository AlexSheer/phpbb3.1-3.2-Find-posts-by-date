<?php
/**
*
* @package phpBB Extension - Find posts & topics by date  English
* @copyright (c) 2016 Sheer
* @license GNU General Public License, version 2 (GPL-2.0)
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

$lang = array_merge($lang, array(
	'SERCH_DATE'			=> 'Date',
	'SERCH_DATE_EXPLAIN'	=> 'Укажите здесь дату, чтобы найти сообщения за определенный день.',
));
