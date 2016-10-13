<?php
/**
*
* @package phpBB Extension - Find posts & topics by date
* @copyright (c) 2015 Sheer
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace sheer\find_by_date\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
/**
* Assign functions defined in this class to event listeners in the core
*
* @return array
* @static
* @access public
*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'						=> 'load_language_on_setup',
			'core.search_modify_url_parameters'		=> 'modify_url_parameters',
			'core.search_backend_search_after'		=> 'search_backend_search_after',
		);
	}

	/** @var \phpbb	emplate	emplate */
	protected $template;

	//** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\config\config */
	protected $config;

	/**
	* Constructor
	*/
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\request\request_interface $request,
		$phpbb_root_path,
		$php_ext,
		\phpbb\config\config $config
	)
	{
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->config = $config;
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'sheer/find_by_date',
			'lang_set' => 'search_by_date',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function search_backend_search_after($event)
	{
		if($date = $this->request->variable('d', ''))
		{
			$ex_fid_ary = $event['ex_fid_ary'];
			$total_match_count = $event['total_match_count'];
			$total_match_count = array('total_match_count' => $event['total_match_count']);
			$total_match_count = array_merge($total_match_count, $ex_fid_ary);
			$event['total_match_count'] = $total_match_count;
		}
	}

	public function modify_url_parameters($event)
	{
		if($date = $this->request->variable('d', ''))
		{
			$add_keywords		= $this->request->variable('add_keywords', '', true);
			$author				= $this->request->variable('author', '', true);
			$keywords			= $this->request->variable('keywords', '', true);

			$sql_where = $event['sql_where'];

			$time = explode('.', $date);
			$time = mktime(0, 0, 0, (int)$time[1], (int)$time[0], $time[2]);
			$next = $time + 3600 * 24;

			if(!$sql_where && ($add_keywords || $add_keywords || $keywords))
			{
				 return;
			}

			$total = $event['total_match_count']['total_match_count'];

			$this->template->assign_vars(array(
				'SEARCH_DATE'	=> $this->user->format_date($time, 'j F Y'),
				'AUTHOR'		=> $author,
			));

			$diff = array('total_match_count' => $total);
			$ex_fid_ary = array_diff($total = $event['total_match_count'], $diff);

			if ($event['show_results'] == 'posts')
			{
				if ($sql_where)
				{
					$sql_where .= ' AND';
					$sql_where = str_replace('f.forum_id', 'p.forum_id', $sql_where);
				}
				$sql_where .= ' p.post_time > ' . $time . ' AND p.post_time < ' . $next . ' AND p.post_visibility = 1';
				if (sizeof($ex_fid_ary))
				{
					$sql_where .= ' AND ' . $this->db->sql_in_set('p.forum_id', $ex_fid_ary, true) . '';
				}

				$sql = 'SELECT COUNT(p.post_id) AS total
					FROM ' . POSTS_TABLE . ' p
						WHERE ' . $sql_where . '';
				$result = $this->db->sql_query($sql);
				$total = (int) $this->db->sql_fetchfield('total');
				$this->db->sql_freeresult($result);

				$total = $total;
				$event['sql_where'] = $sql_where;
			}
			else
			{
				$ids = array();
				if($sql_where)
				{
					$sql_where = '' . str_replace('topic_visibility', 'post_visibility', $sql_where . ' AND ');
					$sql_where = str_replace('f.forum_id', 't.forum_id', $sql_where);
				}
				else
				{
					$sql_where = ' post_visibility = 1 AND ';
				}

				$sql_where_ex_fid = (sizeof($ex_fid_ary)) ? ' AND ' . $this->db->sql_in_set('t.forum_id', $ex_fid_ary, true) . '' : '';

				$sql = 'SELECT DISTINCT t.topic_id
					FROM ' . POSTS_TABLE . ' t
					WHERE ' . $sql_where . '
					t.post_time > ' . $time . ' AND t.post_time < ' . $next . ' ' . $sql_where_ex_fid . '';
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$ids[] = $row['topic_id'];
				}
				$this->db->sql_freeresult($result);

				if (sizeof($ids))
				{
					$total = sizeof($ids);
					$event['sql_where'] = $this->db->sql_in_set('t.topic_id', $ids);
				}
				else
				{
					$total = 0;
					$event['sql_where'] = $this->db->sql_in_set('t.topic_id', 0);
				}
			}

			$event['total_match_count'] = $total;
			$event['u_search'] = append_sid("{$this->phpbb_root_path}search.$this->php_ext", 'd=' . $date . '&add_keywords=' . urlencode(htmlspecialchars_decode($add_keywords)) . '&submit=true');
		}
	}
}
