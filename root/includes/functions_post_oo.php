<?php
/** 
*
* @package functions_post_oo (object-oriented posting api)
* @copyright (c) 2006-2007 Pyramide (Frank Dreyer)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (!function_exists('generate_smilies'))
{
	include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
}
if (!function_exists('display_forums'))
{
	include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
}
if (!function_exists('make_forum_select'))
{
	include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
}
if (!function_exists('get_folder'))
{
	include($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
}

//This file might be included inside a function where $user is not set locally:
global $user;

if (isset($user))
{
	$user->add_lang('posting');
}

class topic
{
	var $topic_id;
	var $forum_id;

	var $posts = array();

	var $icon_id = 0;
	var $topic_attachment = 0;
	var $topic_approved = 1;
	var $topic_reported = 0;

	var $topic_views = 0;
	var $topic_replies = 0;
	var $topic_replies_real = 0;

	var $topic_status = ITEM_UNLOCKED;
	var $topic_moved_id = 0;
	var $topic_type = POST_NORMAL;
	var $topic_time_limit = 0;

	var $topic_title = '';
	var $topic_time;
	var $topic_poster;

	var $topic_first_post_id;
	var $topic_first_poster_name;
	var $topic_first_poster_colour;

	var $topic_last_post_id;
	var $topic_last_poster_name;
	var $topic_last_poster_colour;
	var $topic_last_post_subject;
	var $topic_last_post_time;
	var $topic_last_view_time;

	var $topic_bumped = 0;
	var $topic_bumper = 0;

	var $poll_title = '';
	var $poll_start = 0;
	var $poll_length = 0;
	var $poll_max_options = 1;
	var $poll_last_vote = 0;
	var $poll_vote_change = 0;
	var $poll_options = array();

	function topic($forum_id = 0)
	{
		$this->forum_id = $forum_id;
	}

	function get($topic_id, $load_posts = false)
	{
		global $db;
		$sql = 'SELECT *
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . intval($topic_id);
		$result = $db->sql_query($sql);
		$topic_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$topic_data)
		{
			//topic does not exist, return false
			return false;
		}

		//create object and fill in data
		$topic = new topic();

		$topic->topic_id = $topic_data['topic_id'];
		$topic->forum_id = $topic_data['forum_id'];

		$topic->icon_id = $topic_data['icon_id'];
		$topic->topic_attachment = $topic_data['topic_attachment'];
		$topic->topic_approved = $topic_data['topic_approved'];
		$topic->topic_reported = $topic_data['topic_reported'];

		$topic->topic_views = $topic_data['topic_views'];
		$topic->topic_replies = $topic_data['topic_replies'];
		$topic->topic_replies_real = $topic_data['topic_replies_real'];

		$topic->topic_status = $topic_data['topic_status'];
		$topic->topic_moved_id = $topic_data['topic_moved_id'];
		$topic->topic_type = $topic_data['topic_type'];
		$topic->topic_time_limit = $topic_data['topic_time_limit'];

		$topic->topic_title = $topic_data['topic_title'];
		$topic->topic_time = $topic_data['topic_time'];
		$topic->topic_poster = $topic_data['topic_poster'];

		$topic->topic_first_post_id = $topic_data['topic_first_post_id'];
		$topic->topic_first_poster_name = $topic_data['topic_first_poster_name'];
		$topic->topic_first_poster_colour = $topic_data['topic_first_poster_colour'];

		$topic->topic_last_post_id = $topic_data['topic_last_post_id'];
		$topic->topic_last_poster_name = $topic_data['topic_last_poster_name'];
		$topic->topic_last_poster_colour = $topic_data['topic_last_poster_colour'];
		$topic->topic_last_post_subject = $topic_data['topic_last_post_subject'];
		$topic->topic_last_post_time = $topic_data['topic_last_post_time'];
		$topic->topic_last_view_time = $topic_data['topic_last_view_time'];

		$topic->topic_bumped = $topic_data['topic_bumped'];
		$topic->topic_bumper = $topic_data['topic_bumper'];

		$topic->poll_title = $topic_data['poll_title'];
		$topic->poll_start = $topic_data['poll_start'];
		$topic->poll_length = $topic_data['poll_length'];
		$topic->poll_max_options = $topic_data['poll_max_options'];
		$topic->poll_last_vote = $topic_data['poll_last_vote'];
		$topic->poll_vote_change = $topic_data['poll_vote_change'];

		if ($load_posts)
		{
			$sql = 'SELECT *
				FROM ' . POSTS_TABLE . '
				WHERE topic_id = ' . intval($topic_id) . '
				ORDER BY post_time ASC';
			$result = $db->sql_query($sql);
			while ($post_data = $db->sql_fetchrow($result))
			{
				$topic->posts[] = post::from_array($post_data);
			}
			$db->sql_freeresult($result);
		}

		return $topic;
	}

	function from_post($post)
	{
		$topic = new topic();
		$topic->topic_id = $post->topic_id;
		$topic->forum_id = $post->forum_id;
		$topic->topic_title = $post->post_subject;
		$topic->topic_poster = $post->poster_id;
		$topic->topic_time = $post->post_time;
		$topic->icon_id = $post->icon_id;
		$topic->topic_attachment = $post->post_attachment;
		$topic->topic_approved = $post->post_approved;
		$topic->topic_reported = $post->post_reported;
		$topic->posts[] = &$post;
		return $topic;
	}

	function submit($submit_posts = true)
	{
		global $config, $db, $auth, $user;

		if (!$this->topic_id && count($this->posts) == 0)
		{
			trigger_error('cannot create a topic without posts', E_USER_ERROR);
		}

		if (!$this->topic_id)
		{
			//new post, set some default values if not set yet
			if(!$this->topic_poster) $this->topic_poster = $user->data['user_id'];
			if(!$this->topic_time) $this->topic_time = time();
			$this->posts[0]->post_subject = $this->topic_title;
		}

		if ($this->forum_id == 0)
		{
			//no forum id known, can only insert as global announcement
			$this->topic_type = POST_GLOBAL;
		}

		$this->topic_title = truncate_string($this->topic_title);

		$sql_data = array(
			'icon_id'			=> $this->icon_id,
			'topic_attachment'	=> $this->topic_attachment ? 1 : 0,
			'topic_approved'	=> $this->topic_approved ? 1 : 0,
			'topic_reported'	=> $this->topic_reported ? 1 : 0,
			//'topic_views'		=> $this->topic_views,
			'topic_replies'		=> $this->topic_replies,
			'topic_replies_real'=> $this->topic_replies_real,
			'topic_status'		=> $this->topic_status,
			'topic_moved_id'	=> $this->topic_moved_id,
			'topic_type'		=> $this->topic_type,
			'topic_time_limit'	=> $this->topic_time_limit,
			'topic_title'		=> $this->topic_title,
			'topic_time'		=> $this->topic_time,
			'topic_poster'		=> $this->topic_poster,
			'topic_bumped'		=> $this->topic_bumped ? 1 : 0,
			'topic_bumper'		=> $this->topic_bumper,
			'poll_title'		=> $this->poll_title,
			'poll_start'		=> $this->poll_start,
			'poll_length'		=> $this->poll_length,
			'poll_max_options'	=> $this->poll_max_options,
			//'poll_last_vote'	=> $this->poll_last_vote,
			'poll_vote_change'	=> $this->poll_vote_change ? 1 : 0
		);

		$sync = new syncer();

		if ($this->topic_id)
		{
			//edit
			$sql = 'SELECT *
					FROM ' . TOPICS_TABLE . '
					WHERE topic_id = ' . intval($this->topic_id);
			$result = $db->sql_query($sql);
			$topic_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$topic_data)
			{
				trigger_error("topic_id={$this->topic_id}, but that topic does not exist", E_USER_ERROR);
			}

			$db->sql_transaction('begin');

			$sql = 'UPDATE ' . TOPICS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' WHERE topic_id = ' . $this->topic_id;
			$db->sql_query($sql);

			//move to another forum -> also move posts and update statistics
			if ($this->forum_id != $topic_data['forum_id'])
			{
				$sql = 'UPDATE ' . POSTS_TABLE . '
					SET forum_id = ' . $this->forum_id . '
					WHERE topic_id = ' . $this->topic_id;
				$db->sql_query($sql);

				//old forum
				if ($topic_data['forum_id'] != 0)
				{
					$sync->add('forum', $topic_data['forum_id'], 'forum_topics', $topic_data['topic_approved'] ? -1 : 0);
					$sync->add('forum', $topic_data['forum_id'], 'forum_topics_real', -1);
					$sync->add('forum', $topic_data['forum_id'], 'forum_posts', -($topic_data['topic_replies'] + 1));
					$sync->forum_last_post($topic_data['forum_id']);
				}

				//new forum
				if ($this->forum_id != 0)
				{
					$sync->add('forum', $this->forum_id, 'forum_topics', $this->topic_approved ? 1 : 0);
					$sync->add('forum', $this->forum_id, 'forum_topics_real', 1);
					$sync->add('forum', $this->forum_id, 'forum_posts', ($this->topic_replies + 1));
				}
			}

			if ($this->topic_approved != $topic_data['topic_approved'])
			{
				//if forum_id was changed, we've already updated forum_topics above 
				if (($this->forum_id == $topic_data['forum_id']) && ($this->forum_id != 0))
				{
					$sync->add('forum', $this->forum_id, 'forum_topics', $this->topic_approved ? 1 : -1);
					$sync->add('forum', $this->forum_id, 'forum_posts', $this->topic_approved ? (1 + $this->topic_replies) : -(1 + $this->topic_replies));
					$sync->forum_last_post($this->forum_id);
				}

				//same with total topics+posts
				set_config('num_topics', $this->topic_approved ? $config['num_topics'] + 1 : $config['num_topics'] - 1, true);
				set_config('num_posts', $this->topic_approved ? $config['num_posts'] + (1 + $this->topic_replies) : $config['num_posts'] - (1 + $this->topic_replies), true);
			}

			$db->sql_transaction('commit');
		}
		else
		{
			//new topic
			$sql_data['forum_id'] = $this->forum_id;

			$sql = 'INSERT INTO ' . TOPICS_TABLE . ' ' .
				$db->sql_build_array('INSERT', $sql_data);

			$db->sql_query($sql);

			$this->topic_id = $db->sql_nextid();

			if ($this->forum_id != 0)
			{
				$sync->add('forum', $this->forum_id, 'forum_topics', $this->topic_approved ? 1 : 0);
				$sync->add('forum', $this->forum_id, 'forum_topics_real', 1);
			}
			//total topics
			if ($this->topic_approved)
			{
				set_config('num_topics', $config['num_topics'] + 1, true);
			}

			$sync->new_topic_flag = true;
		}


		// insert or update poll
		if (isset($this->poll_options) && !empty($this->poll_options))
		{
			$cur_poll_options = array();

			if ($this->poll_start && isset($topic_data))
			{
				$sql = 'SELECT * FROM ' . POLL_OPTIONS_TABLE . '
					WHERE topic_id = ' . $this->topic_id . '
					ORDER BY poll_option_id';
				$result = $db->sql_query($sql);

				$cur_poll_options = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$cur_poll_options[] = $row;
				}
				$db->sql_freeresult($result);
			}

			$sql_insert_ary = array();
			for ($i = 0, $size = sizeof($this->poll_options); $i < $size; $i++)
			{
				if (trim($this->poll_options[$i]))
				{
					if (empty($cur_poll_options[$i]))
					{
						$sql_insert_ary[] = array(
							'poll_option_id'	=> (int) $i,
							'topic_id'			=> (int) $this->topic_id,
							'poll_option_text'	=> (string) $this->poll_options[$i]
						);
					}
					else if ($this->poll_options[$i] != $cur_poll_options[$i])
					{
						$sql = 'UPDATE ' . POLL_OPTIONS_TABLE . "
							SET poll_option_text = '" . $db->sql_escape($this->poll_options[$i]) . "'
							WHERE poll_option_id = " . $cur_poll_options[$i]['poll_option_id'] . '
								AND topic_id = ' . $this->topic_id;
						$db->sql_query($sql);
					}
				}
			}

			$db->sql_multi_insert(POLL_OPTIONS_TABLE, $sql_insert_ary);

			if (sizeof($this->poll_options) < sizeof($cur_poll_options))
			{
				$sql = 'DELETE FROM ' . POLL_OPTIONS_TABLE . '
					WHERE poll_option_id >= ' . sizeof($this->poll_options) . '
						AND topic_id = ' . $this->topic_id;
				$db->sql_query($sql);
			}
		}

		//delete poll if we had one and poll_start is 0 now
		if (isset($topic_data) && $topic_data['poll_start'] && $this->poll_start == 0)
		{
			$sql = 'DELETE FROM ' . POLL_OPTIONS_TABLE . '
				WHERE topic_id = ' . $this->topic_id;
			$db->sql_query($sql);

			$sql = 'DELETE FROM ' . POLL_VOTES_TABLE . '
				WHERE topic_id = ' . $this->topic_id;
			$db->sql_query($sql);
		}
		//End poll


		if ($submit_posts && count($this->posts))
		{
			//find and sync first post
			if ($sync->new_topic_flag)
			{
				//test if var was not set in post
				$first_post = $this->posts[0];
				if ($first_post->post_subject == '')
				{
					$first_post->post_subject = $this->topic_title;
				}
				if (!$first_post->poster_id)
				{
					$first_post->poster_id = $this->topic_poster;
				}
				if (!$this->topic_approved)
				{
					$first_post->post_approved = 0;
				}
			}
			elseif ($topic_data && $this->topic_first_post_id != 0)
			{
				foreach ($this->posts as $post)
				{
					if ($post->post_id == $this->topic_first_post_id)
					{
						//test if var has been changed in topic. this is like the
						//else($submit_posts) below, but the user might have changed the
						//post object but not the topic, so we can't just overwrite them
						$first_post = $post;
						if ($this->topic_title != $topic_data['topic_title'])
						{
							$first_post->post_subject = $this->topic_title;
						}
						if ($this->topic_time != $topic_data['topic_time'])
						{
							$first_post->post_time = $this->topic_time;
						}
						if ($this->topic_poster != $topic_data['topic_poster'])
						{
							$first_post->poster_id = $this->topic_poster;
							$first_post->post_username = ($this->topic_poster == ANONYMOUS) ? $this->topic_first_poster_name : '';
						}
						if ($this->topic_approved != $topic_data['topic_approved'])
						{
							$first_post->post_approved = $this->topic_approved;
						}
						break;
					}
				}
			}

			//TODO sort by post_time in case user messed with it
			foreach ($this->posts as $post)
			{
				$post->_topic = $this;
				$post->topic_id = $this->topic_id;
				$post->forum_id = $this->forum_id;

				//if(!$post->poster_id) $post->poster_id = $this->topic_poster;

				$post->_submit($sync);
			}
		}
		else
		{
			//sync first post if user edited topic only
			$sync->set('post', $this->topic_first_post_id, array(
				'post_subject'	=> $this->topic_title,
				'post_time'		=> $this->topic_time,
				'poster_id'		=> $this->topic_poster,
				'post_username'	=> ($this->topic_poster == ANONYMOUS) ? $this->topic_first_poster_name : '',
				'post_approved'	=> $this->topic_approved,
				'post_reported'	=> $this->topic_reported
			));
		}

		$sync->execute();

		//refresh $this->topic_foo variables...
		$this->refresh_statvars();
	}

	/**
	* synchronizes topic and forum via sync() and updates member variables
	* ($this->topic_last_post_id etc.)
	*/
	function sync()
	{
		global $db;

		if (!$this->topic_id)
		{
			//topic does not exist yet
			return;
		}

		sync('topic', 'topic_id', $this->topic_id, false, true);
		if ($this->forum_id > 0)
		{
			sync('forum', 'forum_id', $this->forum_id);
		}

		$this->refresh_statvars();
	}

	function refresh_statvars()
	{
		global $db;

		$sql = 'SELECT *
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . $this->topic_id;
		$result = $db->sql_query($sql);
		$topic_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$topic_data)
		{
			//topic does not exist although we have a topic_id?
			trigger_error("topic id set ({$this->topic_id}) but topic does not exist?", E_USER_ERROR);
		}

		$this->topic_attachment = $topic_data['topic_attachment'];
		$this->topic_approved = $topic_data['topic_approved'];
		$this->topic_reported = $topic_data['topic_reported'];

		$this->topic_views = $topic_data['topic_views'];
		$this->topic_replies = $topic_data['topic_replies'];
		$this->topic_replies_real = $topic_data['topic_replies_real'];

		$this->topic_first_post_id = $topic_data['topic_first_post_id'];
		$this->topic_first_poster_name = $topic_data['topic_first_poster_name'];
		$this->topic_first_poster_colour = $topic_data['topic_first_poster_colour'];

		$this->topic_last_post_id = $topic_data['topic_last_post_id'];
		$this->topic_last_poster_name = $topic_data['topic_last_poster_name'];
		$this->topic_last_poster_colour = $topic_data['topic_last_poster_colour'];
		$this->topic_last_post_subject = $topic_data['topic_last_post_subject'];
		$this->topic_last_post_time = $topic_data['topic_last_post_time'];
		$this->topic_last_view_time = $topic_data['topic_last_view_time'];
	}

	/***/
	function add_poll($title, $poll_options, $max_options = 1)
	{
		$this->poll_start = time();
		$this->poll_title = $title;
		$this->poll_options = $poll_options;
		$this->poll_max_options = $max_options;
	}

	/***/
	function delete_poll()
	{
		$this->poll_title = '';
		$this->poll_start = 0;
		$this->poll_length = 0;
		$this->poll_last_vote = 0;
		$this->poll_max_options = 0;
		$this->poll_vote_change = 0;

		//POLL_OPTIONS_TABLE and POLL_VOTES_TABLE will be cleared in submit()
	}

	/**
	* clears all votes (restarts poll)
	*/
	function reset_poll()
	{
		global $db;

		$db->sql_transaction('begin');
		$sql = 'UPDATE ' . POLL_OPTIONS_TABLE . '
			SET poll_option_total = 0
			WHERE topic_id = ' . $this->topic_id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . POLL_VOTES_TABLE . '
			WHERE topic_id = ' . $this->topic_id;
		$db->sql_query($sql);

		$this->poll_start = time();
		$this->poll_last_vote = 0;
		$sql = 'UPDATE ' . TOPICS_TABLE . '
			SET poll_start = ' . $this->poll_start . ',
				poll_last_vote = 0
			WHERE topic_id = ' . $this->topic_id;
		$db->sql_query($sql);

		$db->sql_transaction('commit');
	}

	/**
	* bumps the topic
	* @param
	*/
	function bump($user_id = 0)
	{
		global $db, $user;

		$current_time = time();
		if($user_id == 0) $user_id = $user->data['user_id'];

		$db->sql_transaction('begin');

		$sql = 'UPDATE ' . POSTS_TABLE . "
			SET post_time = $current_time
			WHERE post_id = {$this->topic_last_post_id}
				AND topic_id = {$this->topic_id}";
		$db->sql_query($sql);

		$this->topic_bumped = 1;
		$this->topic_bumper = $user_id;
		$this->topic_last_post_time = $current_time;
		$sql = 'UPDATE ' . TOPICS_TABLE . "
			SET topic_last_post_time = $current_time,
				topic_bumped = 1,
				topic_bumper = $user_id
			WHERE topic_id = $topic_id";
		$db->sql_query($sql);

		update_post_information('forum', $this->forum_id);

		$sql = 'UPDATE ' . USERS_TABLE . "
			SET user_lastpost_time = $current_time
			WHERE user_id = $user_id";
		$db->sql_query($sql);

		$db->sql_transaction('commit');

		markread('post', $this->forum_id, $this->topic_id, $current_time, $user_id);

		add_log('mod', $this->forum_id, $this->topic_id, 'LOG_BUMP_TOPIC', $this->topic_title);
	}

	function move($forum_id)
	{
		move_topics($this->topic_id, $forum_id);

		$this->forum_id = $forum_id;
		foreach ($this->posts as $post)
		{
			$post->forum_id = $forum_id;
		}
	}

	function delete()
	{
		if (!$this->topic_id)
		{
			trigger_error('NO_TOPIC', E_USER_ERROR);
		}

		$ret = delete_topics('topic_id', $this->topic_id);

		//remove references to the deleted topic so calls to submit() will create a
		//new topic instead of trying to update the topich which does not exist anymore
		$this->topic_id = NULL;
		foreach ($this->posts as $post)
		{
			$post->topic_id = NULL;
			$post->post_id = NULL;
		}

		return $ret;
	}
}



class post
{
	var $post_id;
	var $topic_id;
	var $forum_id;

	var $poster_id;
	var $post_username = '';
	var $poster_ip;

	var $icon_id = 0;
	var $post_time;
	var $post_postcount = 1;
	var $post_approved = 1;
	var $post_reported = 0;

	var $enable_bbcode = 1;
	var $enable_smilies = 1;
	var $enable_magic_url = 1;
	var $enable_sig = 1;

	var $post_subject = '';
	var $post_text = '';

	var $post_edit_time = 0;
	var $post_edit_reason = '';
	var $post_edit_user = 0;
	var $post_edit_count = 0;
	var $post_edit_locked = 0;

	var $_topic;

	var $post_attachment = 0;
	var $attachments = array();

	function post($topic_id = NULL, $post_text = '')
	{
		$this->topic_id = $topic_id;
		$this->post_text = $post_text;
	}

	/** 
	* static method, loads the post with a given post_id from database.
	* returns false if the post does not exist
	*/
	function get($post_id)
	{
		global $db;
		//$sql = "SELECT p.*, t.topic_first_post_id, t.topic_last_post_id
		//		FROM " . POSTS_TABLE . " p, " . TOPICS_TABLE . " t
		//		WHERE p.post_id=" . intval($this->post_id) . " AND t.topic_id = p.topic_id";
		$sql = 'SELECT *
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . intval($post_id);
		$result = $db->sql_query($sql);
		$post_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$post_data)
		{
			//post does not exist, return false
			return false;
		}


		return post::from_array($post_data);
	}

	function from_array($post_data)
	{
		global $db;

		if (!is_array($post_data))
		{
			trigger_error('post::from_array - $post_data not an array');
		}

		//create object and fill in data
		$post = new post();
		$post->post_id = $post_data['post_id'];
		$post->topic_id = $post_data['topic_id'];
		$post->forum_id = $post_data['forum_id'];

		$post->poster_id = $post_data['poster_id'];
		$post->post_username = $post_data['post_username'];
		$post->poster_ip = $post_data['poster_ip'];

		$post->icon_id = $post_data['icon_id'];
		$post->post_time = $post_data['post_time'];
		$post->post_postcount = $post_data['post_postcount'];
		$post->post_approved = $post_data['post_approved'];
		$post->post_reported = $post_data['post_reported'];

		$post->enable_bbcode = $post_data['enable_bbcode'];
		$post->enable_smilies = $post_data['enable_smilies'];
		$post->enable_magic_url = $post_data['enable_magic_url'];
		$post->enable_sig = $post_data['enable_sig'];

		$post->post_subject = $post_data['post_subject'];
		$post->post_attachment = $post_data['post_attachment'];

		$post->post_edit_time = $post_data['post_edit_time'];
		$post->post_edit_reason = $post_data['post_edit_reason'];
		$post->post_edit_user = $post_data['post_edit_user'];
		$post->post_edit_count = $post_data['post_edit_count'];

		//check first/last post
		//$this->_is_first_post = ($post_data['post_id'] == $post_data['topic_first_post_id']);
		//$this->_is_last_post = ($post_data['post_id'] == $post_data['topic_last_post_id']);

		//parse message
		decode_message($post_data['post_text'], $post_data['bbcode_uid']);
		$post_data['post_text'] = str_replace(array('&#58;', '&#46;'), array(':', '.'), $post_data['post_text']);
		$post->post_text = $post_data['post_text'];

		//attachments
		if ($post->post_attachment)
		{
			$sql = 'SELECT *
				FROM ' . ATTACHMENTS_TABLE . '
				WHERE post_msg_id = ' . $post->post_id;
			$result = $db->sql_query($sql);
			while ($attach_row = $db->sql_fetchrow($result))
			{
				$post->attachments[] = attachment::from_array($attach_row);
			}
		}

		return $post;
	}

	/**
	*loads and returns the topic for this post
	* @param all_posts whether to load all other posts of the topic into topic->posts
	*/
	function get_topic($all_posts = false)
	{
		if (!$this->_topic)
		{
			if ($this->post_id)
			{
				//existing post, load existing topic
				$this->_topic = topic::get($this->topic_id, $all_posts);

				//insert $this into topic->posts array
				if ($all_posts)
				{
					//this post was also loaded from database, replace it with $this
					for ($i=0; $i<sizeof($this->_topic->posts); $i++)
					{
						if ($this->_topic->posts[$i]->post_id == $this->post_id)
						{
							//found it
							$this->_topic->posts[$i] = &$this;
							break;
						}
					}
				}
				else
				{
					//no posts were loaded in topic::get(), add our post to topic->posts
					$this->_topic->posts[] = &$this;
				}
			}
			else
			{
				//new post, generate topic
				$this->_topic = topic::from_post($this);
			}
		}
		return $this->_topic;
	}

	/**
	* sets the following variables based on the permissions of $this->poster_id:
	* post_postcount, post_approved
	* enable_bbcode, enable_smilies, enable_magic_url, enable_sig
	* img_status, flash_status, quote_status
	* by default (if you never call this function) all variables are set to 1 (allowed)
	* note that this does not check whether the user can post at all - use validate() for that.
	* @todo
	*/
	function apply_permissions()
	{
		//TODO
	}

	/**
	* checks if $this->poster_id has the permissions required to submit this post.
	* note that calling this does not change the behaviour of submit()
	*/
	function validate()
	{
		// ?? $this->apply_permissions();
		//TODO
	}

	/**
	* returns the html representation of this post
	*/
	function display_format()
	{
		//TODO
	}

	/**
	* moves this post to a different topic. If you want to merge multiple post
	* into one topic, calling move_posts() directly is recommended.
	*/
	function merge_into($topic_id)
	{
		move_posts($this->post_id, $topic_id);
		$this->topic_id = $topic_id;
		$this->_topic = null;
	}

	/**
	* Submit dummy function for PHP4 compatibility
	*/
	function submit()
	{
		$dummy_var = false;
		$this->_submit($dummy_var);
	}

	/**
	* create/update this post in the database
	* @param $sync used internally by topic->submit()
	*/
	function _submit(&$sync)
	{
		global $config, $db, $auth, $user;

		if ($sync === false)
		{
			//submit() was called directly so we need to sync after it
			$sync = new syncer();
			$exec_sync = true;
		}
		else
		{
			//submit() was called by topic->submit(), sync there when everything is done
			$exec_sync = false;
		}

		if (!$this->post_id)
		{
			//new post, set some default values if not set yet
			if(!$this->poster_id) $this->poster_id = $user->data['user_id'];
			if(!$this->poster_ip) $this->poster_ip = $user->ip;
			if(!$this->post_time) $this->post_time = time();
		}

		$this->post_subject = truncate_string($this->post_subject);


		$sql_data = array(
			'poster_id' 		=> $this->poster_id,
			'poster_ip' 		=> $this->poster_ip,
			'post_username'		=> $this->post_username,
			'icon_id'			=> $this->icon_id,
			'post_time'			=> $this->post_time,
			'post_postcount'	=> $this->post_postcount ? 1 : 0,
			'post_approved'		=> $this->post_approved ? 1 : 0,
			'post_reported'		=> $this->post_reported ? 1 : 0,
			'enable_bbcode'		=> $this->enable_bbcode ? 1 : 0,
			'enable_smilies'	=> $this->enable_smilies ? 1 : 0,
			'enable_magic_url'	=> $this->enable_magic_url ? 1 : 0,
			'enable_sig'		=> $this->enable_sig ? 1 : 0,
			'post_subject'		=> $this->post_subject,
			'bbcode_bitfield'	=> 0,
			'bbcode_uid'		=> '',
			'post_text'			=> $this->post_text,
			'post_checksum'		=> md5($this->post_text),
			//'post_attachment'	=> $this->post_attachment ? 1 : 0,
			'post_edit_time'	=> $this->post_edit_time,
			'post_edit_reason'	=> $this->post_edit_reason,
			'post_edit_user'	=> $this->post_edit_user,
			'post_edit_count'	=> $this->post_edit_count,
			'post_edit_locked'	=> $this->post_edit_locked
		);

		$flags = '';
		generate_text_for_storage($sql_data['post_text'], $sql_data['bbcode_uid'], $sql_data['bbcode_bitfield'], $flags, $this->enable_bbcode, $this->enable_magic_url, $this->enable_smilies);

		if ($this->post_id && $this->topic_id)
		{
			//edit
			$sql = 'SELECT p.*, t.topic_first_post_id, t.topic_last_post_id, t.topic_approved, t.topic_replies
					FROM ' . POSTS_TABLE . ' p
					LEFT JOIN ' . TOPICS_TABLE . ' t
						ON (t.topic_id = p.topic_id)
					WHERE p.post_id = ' . intval($this->post_id);
			//$sql = "SELECT * FROM " . POSTS_TABLE . " WHERE post_id=" . intval($this->post_id);
			$result = $db->sql_query($sql);
			$post_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$post_data)
			{
				trigger_error("post_id={$this->post_id}, but that post does not exist", E_USER_ERROR);
			}

			//check first/last post
			$is_first_post = ($post_data['post_id'] == $post_data['topic_first_post_id']);
			$is_last_post = ($post_data['post_id'] == $post_data['topic_last_post_id']);

			$db->sql_transaction('begin');

			$sql = 'UPDATE ' . POSTS_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $sql_data) . '
				WHERE post_id=' . $this->post_id;
			$db->sql_query($sql);

			if ($this->topic_id != $post_data['topic_id'])
			{
				//merge into new topic
				//get new topic's forum id and first/last post time
				$sql = 'SELECT forum_id, topic_time, topic_last_post_time
						FROM ' . TOPICS_TABLE . "
						WHERE topic_id = {$this->topic_id}";
				$result = $db->sql_query($sql);
				$new_topic_data = $db->sql_fetchrow($result);
				if (!$new_topic_data)
				{
					trigger_error("attempted to merge post {$this->post_id} into topic {$this->topic_id}, but that topic does not exist", E_USER_ERROR);
				}

				//sync forum_posts
				if ($new_topic_data['forum_id'] != $post_data['forum_id'])
				{
					$sync->add('forum', $post_data['forum_id'], 'forum_posts', $this->post_approved ? -1 : 0);
					$sync->add('forum', $new_topic_data['forum_id'], 'forum_posts', $this->post_approved ? 1 : 0);
					if ($this->forum_id != $new_topic_data['forum_id'])
					{
						//user changed topic_id but not forum_id, so we saved the wrong one above. correct it via sync
						$this->forum_id = $new_topic_data['forum_id'];
						$sync->set('post', $this->post_id, 'forum_id', $this->forum_id);
					}
				}

				//sync old topic
				$sync->add('topic', $post_data['topic_id'], 'topic_replies', $this->post_approved ? -1 : 0);
				$sync->add('topic', $post_data['topic_id'], 'topic_replies_real', -1);

				//sync new topic
				$sync->add('topic', $this->topic_id, 'topic_replies', $this->post_approved ? 1 : 0);
				$sync->add('topic', $this->topic_id, 'topic_replies_real', 1);

				if ($is_first_post)
				{
					//this was the first post in the old topic, sync it
					$sync->topic_first_post($post_data['topic_id']);
					$is_first_post = false; //unset since we dont know status for new topic yet
				}

				if ($is_last_post)
				{
					//this was the last post in the old topic, sync it
					$sync->topic_last_post($post_data['topic_id']);
					$sync->forum_last_post($post_data['forum_id']);
					$is_last_post = false; //unset since we dont know status for new topic yet
				}

				if ($this->post_time <= $new_topic_data['topic_time'])
				{
					//this will be the first post in the new topic, sync it
					$sync->topic_first_post($this->topic_id);
					$is_first_post = true;
				}
				if ($this->post_time >= $new_topic_data['topic_last_post_time'])
				{
					//this will be the last post in the new topic, sync it
					$sync->topic_last_post($this->topic_id);
					$sync->forum_last_post($this->topic_id);
					$is_last_post = true;
				}
			}
			elseif ($is_first_post)
			{
				$sync->set('topic', $this->topic_id, array(
					'icon_id'			=> $this->icon_id,
					'topic_approved'	=> $this->post_approved,
					'topic_title'		=> $this->post_subject,
					'topic_poster'		=> $this->poster_id,
					'topic_time'		=> $this->post_time
				));
			}


			//check if some statistics relevant flags have been changed
			if ($this->post_approved != $post_data['post_approved'])
			{
				//if topic_id was changed, we've already updated it above.
				if ($this->topic_id == $post_data['topic_id'])
				{
					if ($is_first_post)
					{
						//first post -> approve/disapprove whole topic if not yet done (should only happen when directly storing the post)
						if ($this->post_approved != $post_data['topic_approved'])
						{
							$sync->add('forum', $this->forum_id, 'forum_topics', $this->post_approved ? 1 : -1);
							$sync->add('forum', $this->forum_id, 'forum_posts', $this->post_approved ? (1+$post_data['topic_replies']) : -(1+$post_data['topic_replies']));
							$sync->forum_last_post($this->forum_id);

							//and the total topics+posts
							set_config('num_topics', $this->post_approved ? $config['num_topics'] + 1 : $config['num_topics'] - 1, true);
							set_config('num_posts', $this->post_approved ? $config['num_posts'] + (1+$post_data['topic_replies']) : $config['num_posts'] - (1+$post_data['topic_replies']), true);
						}
					}
					else
					{
						//reply
						$sync->add('topic', $this->topic_id, 'topic_replies', $this->post_approved ? 1 : -1);
						$sync->add('forum', $this->forum_id, 'forum_posts', $this->post_approved ? 1 : -1);
					}
				}

				//update total posts
				if (!$is_first_post)
				{
					set_config('num_posts', $this->post_approved ? $config['num_posts'] + 1 : $config['num_posts'] - 1, true);
				}
			}
			/*if($this->post_postcount != $post_data['post_postcount'] && $this->poster_id != ANONYMOUS)
			{
				//increase or decrease user_posts
				$sync->add('user', $this->poster_id, 'user_posts', $this->post_approved ? 1 : -1);
			}*/
			if ($this->poster_id != $post_data['poster_id'] || $this->post_postcount != $post_data['post_postcount'])
			{
				if ($post_data['post_postcount'] && $post_data['poster_id'] != ANONYMOUS)
				{
					$sync->add('user', $post_data['poster_id'], 'user_posts', -1);
				}
				if ($this->post_postcount && $this->poster_id != ANONYMOUS)
				{
					$sync->add('user', $this->poster_id, 'user_posts', 1);
				}
			}

			if ($is_first_post)
			{
				$sync->topic_first_post($this->topic_id);
			}
			if ($is_last_post)
			{
				$sync->topic_last_post($this->topic_id);
				$sync->forum_last_post($this->forum_id);
			}

			reindex('edit', $this->post_id, $sql_data['post_text'], $this->post_subject, $this->poster_id, $this->forum_id);

			$db->sql_transaction('commit');
		}
		elseif ($this->topic_id)
		{
			//reply
			$sql = 'SELECT t.*, f.forum_name
					FROM ' . TOPICS_TABLE . ' t
					LEFT JOIN ' . FORUMS_TABLE . ' f
						ON (f.forum_id = t.forum_id)
					WHERE t.topic_id = ' . intval($this->topic_id);
			$result = $db->sql_query($sql);
			$topic_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$topic_data)
			{
				trigger_error("topic_id={$this->topic_id}, but that topic does not exist", E_USER_ERROR);
			}

			//we need topic_id and forum_id 
			$this->forum_id = $topic_data['forum_id'];
			$sql_data['forum_id'] = $this->forum_id;
			$sql_data['topic_id'] = $this->topic_id;

			//make sure we have a post_subject (empty subjects are bad for e.g. approving)
			if ($this->post_subject == '')
			{
				$this->post_subject = 'Re: ' . $topic_data['topic_title'];
			}

			$db->sql_transaction('begin');

			//insert post
			$sql = 'INSERT INTO ' . POSTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data);
			$db->sql_query($sql);
			$this->post_id = $db->sql_nextid();

			//update topic
			if (!$sync->new_topic_flag)
			{
				$sync->add('topic', $this->topic_id, 'topic_replies', $this->post_approved ? 1 : 0);
				$sync->add('topic', $this->topic_id, 'topic_replies_real', 1);
				$sync->set('topic', $this->topic_id, 'topic_bumped', 0);
				$sync->set('topic', $this->topic_id, 'topic_bumper', 0);
			}
			else
			{
				$sync->topic_first_post($this->topic_id);
				$sync->new_topic_flag = false;
			}
			$sync->topic_last_post($this->topic_id);

			//update forum
			if ($this->forum_id != 0)
			{
				$sync->add('forum', $this->forum_id, 'forum_posts', $this->post_approved ? 1 : 0);
				$sync->forum_last_post($this->forum_id);
			}


			if ($this->post_postcount)
			{
				//increase user_posts...
				$sync->add('user', $this->poster_id, 'user_posts', 1);
			}
			if ($this->post_approved)
			{
				//...and total posts
				set_config('num_posts', $config['num_posts'] + 1, true);
			}

			reindex('reply', $this->post_id, $sql_data['post_text'], $this->post_subject, $this->poster_id, $this->forum_id);

			$db->sql_transaction('commit');

			// Mark this topic as posted to
			markread('post', $this->forum_id, $this->topic_id, $this->post_time, $this->poster_id);

			// Mark this topic as read
			// We do not use post_time here, this is intended (post_time can have a date in the past if editing a message)
			markread('topic', $this->forum_id, $this->topic_id, time());

			//
			if ($config['load_db_lastread'] && $user->data['is_registered'])
			{
				$sql = 'SELECT mark_time
					FROM ' . FORUMS_TRACK_TABLE . '
					WHERE user_id = ' . $user->data['user_id'] . '
						AND forum_id = ' . $this->forum_id;
				$result = $db->sql_query($sql);
				$f_mark_time = (int) $db->sql_fetchfield('mark_time');
				$db->sql_freeresult($result);
			}
			else if ($config['load_anon_lastread'] || $user->data['is_registered'])
			{
				$f_mark_time = false;
			}

			if (($config['load_db_lastread'] && $user->data['is_registered']) || $config['load_anon_lastread'] || $user->data['is_registered'])
			{
				// Update forum info
				$sql = 'SELECT forum_last_post_time
					FROM ' . FORUMS_TABLE . '
					WHERE forum_id = ' . $this->forum_id;
				$result = $db->sql_query($sql);
				$forum_last_post_time = (int) $db->sql_fetchfield('forum_last_post_time');
				$db->sql_freeresult($result);

				update_forum_tracking_info($this->forum_id, $forum_last_post_time, $f_mark_time, false);
			}

			// Send Notifications
			user_notification('reply', $this->post_subject, $topic_data['topic_title'], $topic_data['forum_name'], $this->forum_id, $this->topic_id, $this->post_id);
		}
		else
		{
			//new topic
			$this->_topic = topic::from_post($this);
			$this->_topic->submit(true);

			//PHP4 Compatibility:
			if(version_compare(PHP_VERSION, '5.0.0', '<'))
			{
				$this->topic_id = $this->_topic->topic_id;
				$this->post_id = $this->_topic->topic_first_post_id;
			}
			$exec_sync = false;
		}

		foreach ($this->attachments as $attachment)
		{
			$attachment->post_msg_id = $this->post_id;
			$attachment->topic_id = $this->topic_id;
			$attachment->poster_id = $this->poster_id;
			$attachment->in_message = 0;
			$attachment->is_orphan = 0;
			$attachment->submit();
		}

		if ($exec_sync)
		{
			$sync->execute();
		}

		/*if ($sync_topic)
		{
			if ($this->_topic)
			{
				$this->_topic->sync();
			}
			else
			{
				sync('topic', 'topic_id', $this->topic_id);
			}
		}*/
	}

	/**
	* delete this post (and if it was the last one in the topic, also delete the topic)
	*/
	function delete()
	{
		if (!$this->post_id)
		{
			trigger_error('NO_POST', E_USER_ERROR);
		}

		$ret = delete_posts('post_id', $this->post_id);

		//remove references to the deleted post so calls to submit() will create a
		//new post instead of trying to update the post which does not exist anymore
		$this->post_id = NULL;

		return $ret;
	}

	/**
	* mark this post as edited (modify post_edit_* fields).
	* currently logged in user will be used if user_id = 0
	*/
	function mark_edited($user_id = 0, $reason = '')
	{
		if ($user_id = 0)
		{
			global $user;
			$user_id = $user->data['user_id'];
		}
		$this->post_edit_count++;
		$this->post_edit_time = time();
		$this->post_edit_user = $user_id;
		$this->post_edit_reason = $reason;
	}

	function mark_read()
	{
		if ($this->post_id)
		{
			//only when post already stored
			markread('topic', $this->forum_id, $this->topic_id, time());
		}
	}
}


class privmsg
{
	var $msg_id;
	var $root_level = 0;
	var $reply_from_msg_id = 0;

	var $message_time;
	var $author_id;
	var $author_ip;
	//var $to_address;
	//var $bcc_address;

	/**list of recipients in the form:
	 * array(
	 *   'u' => array(
	 *     12 => 'to',  //user_id 12 as to
	 *     34 => 'bcc'  //user_id 34 as bcc
	 * 	 ),
	 *   'g' => array(
	 *     56 => 'to',  //group_id 56 as to
	 *     78 => 'bcc'  //group_id 78 as bcc
	 *   )
	 * )*/
	var $address_list = array('u'=>array(), 'g'=> array());

	var $icon_id;
	var $enable_bbcode = 1;
	var $enable_smilies = 1;
	var $enable_magic_url = 1;
	var $enable_sig = 1;

	var $message_subject = '';
	var $message_text = '';
	var $message_edit_reason = '';
	var $message_edit_user = 0;
	var $message_edit_time = 0;
	var $message_edit_count = 0;

	//var $message_attachment = 0;

	function pm()
	{

	}

	/**
	* initializes and returns a new privmsg object as reply to the message with msg_id $msg_id
	*/
	function reply_to($msg_id, $quote = true)
	{
		global $db;

		$sql = 'SELECT p.msg_id, p.root_level, p.author_id, p.message_subject, p.message_text, p.bbcode_uid, p.to_address, p.bcc_address, u.username
				FROM ' . PRIVMSGS_TABLE . ' p
				LEFT JOIN ' . USERS_TABLE . ' u
					ON (p.author_id = u.user_id)
				WHERE msg_id = ' . intval($msg_id);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		if (!$row)
		{
			trigger_error('NO_PRIVMSG', E_USER_ERROR);
		}

		$privmsg = new privmsg();
		$privmsg->reply_from_msg_id = $row['msg_id'];
		$privmsg->root_level = ($row['root_level'] ? $row['root_level'] : $row['msg_id']);
		$privmsg->message_subject = ((!preg_match('/^Re:/', $row['message_subject'])) ? 'Re: ' : '') . censor_text($row['message_subject']);

		if ($quote)
		{
			decode_message($row['message_text'], $row['bbcode_uid']);
			//for some reason we need &quot; here instead of "
			$privmsg->message_text = '[quote=&quot;' . $row['username'] . '&quot;]' . censor_text(trim($row['message_text'])) . "[/quote]\n";
		}

		//add original sender as recipient
		$privmsg->to($row['author_id']);

		//if message had only a single recipient, use that as sender
		if ($row['to_address'] == '' || $row['bcc_address'] == '')
		{
			$to = ($row['to_address'] != '') ? $row['to_address'] : $row['bcc_address'];
			if (preg_match('#^u_(\\d+)$#', $to, $m))
			{
				$privmsg->author_id = $m[1];
			}
		}

		return $privmsg;
	}

	/**
	* adds a recipient. arguments can be (in any order):
	* - 'to':  set type to 'to' (default)
	* - 'bcc': set type to 'bcc'
	* - integer: a user_id or group_id
	* - 'u' or 'user':	 the number is a user_id (default)
	* - 'g' or 'group': the number is a group_id
	* e.g. $pm->to('user', 'to', 123);
	*/
	function to()
	{
		$type = 'to';
		$ug_type = 'u';
		$id = 0;

		$args = func_get_args();
		$args = array_map('strtolower', $args);

		foreach ($args as $arg)
		{
			switch ($arg)
			{
				case 'to': $type = 'to'; break;
				case 'bcc': $type = 'bcc'; break;
				case 'user': case 'u': $ug_type = 'u'; break;
				case 'group': case 'g': $ug_type = 'g'; break;
			}
			if(is_numeric($arg)) $id = intval($arg);
		}

		if ($id == 0)
		{
			trigger_error('privmsg->to(): no id given', E_USER_ERROR);
		}
		$this->address_list[$ug_type][$id] = $type;
	}

	function get($id)
	{
		trigger_error('not yet implemented', E_USER_ERROR);
	}

	function submit()
	{
		global $user, $db;

		if (!$this->msg_id)
		{
			//new message, set some default values if not set yet
			if(!$this->author_id) $this->author_id = $user->data['user_id'];
			if(!$this->author_ip) $this->author_ip = $user->ip;
			if(!$this->message_time) $this->message_time = time();
		}

		$this->message_subject = truncate_string($this->message_subject);

		if ($user->data['user_id'] == $this->author_id)
		{
			$author_username = $user->data['username'];
		} 
		else
		{
			$sql = 'SELECT username 
				FROM ' . USERS_TABLE . '
				WHERE user_id=' . $this->author_id;
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			if (!$row)
			{
				trigger_error('NO_USER', E_USER_ERROR);
			}
			$author_username = $row['username'];
		}

		$message = $this->message_text;
		$bbcode_uid = $bbcode_bitfield = $options = '';
		generate_text_for_storage($message, $bbcode_uid, $bbcode_bitfield, $options, $this->enable_bbcode, $this->enable_magic_url, $this->enable_smilies);

		$data = array(
			'msg_id'				=> (int) $this->msg_id,
			'from_user_id'			=> (int) $this->author_id,
			'from_user_ip'			=> $this->author_ip,
			'from_username'			=> $author_username,
			'reply_from_root_level'	=> $this->root_level,
			'reply_from_msg_id'		=> $this->reply_from_msg_id,
			'icon_id'				=> (int) $this->icon_id,
			'enable_sig'			=> (bool) $this->enable_sig,
			'enable_bbcode'			=> (bool) $this->enable_bbcode,
			'enable_smilies'		=> (bool) $this->enable_smilies,
			'enable_urls'			=> (bool) $this->enable_magic_url,
			'bbcode_bitfield'		=> $bbcode_bitfield,
			'bbcode_uid'			=> $bbcode_uid,
			'message'				=> $message,
			'attachment_data'		=> false,
			'filename_data'			=> false,
			'address_list'			=> $this->address_list
		);

		$mode = ($this->msg_id) ? 'edit' : ($this->reply_from_msg_id ? 'reply' : 'post');
		submit_pm($mode, $this->message_subject, $data);
		$this->msg_id = $data['msg_id'];
	}

	function delete()
	{
		trigger_error('not yet implemented', E_USER_ERROR);
	}
}

class attachment
{
	//TODO
	var $attach_id;
	var $post_msg_id = 0;
	var $topic_id = 0;
	var $in_message = 0;
	var $poster_id = 0;
	var $is_orphan = 1;
	var $physical_filename = '';
	var $real_filename = '';
	var $download_count = 0;
	var $attach_comment = '';
	var $extension = '';
	var $mimetype = '';
	var $filesize = 0;
	var $filetime = 0;
	var $thumbnail = 0;

	function attachment()
	{
		//dont call new attachment(), call attachment::create() or attachment::create_checked()
	}

	/**
	* directly creats an attachment (bypassing checks like allowed extensione etc.)
	*/
	function create($file, $filename)
	{
		//TODO
		/*global $user;

		$attachment = new attachment();
		if ($user)
		{
			$attachment->poster_id = $user->data['user_id'];
		}
		$upload = new fileupload();
		$upload->local_upload($file);

		$attachment->real_filename = $filename;
		copy($file, $attachment->get_file());

		return $attachment;*/
	}

	/**
	* creates an attachment through the phpBB function upload_attachment. i.e.
	* quota, allowed extensions etc. will be checked.
	* returns an attachment object on success or an array of error messages on failure
	* submit() is automatically called so that this attachment appears in the acp
	* "orphaned attachments" list if you dont assign it to a post.
	* WARNING: $file will be moved to the attachment storage
	*/
	function create_checked($file, $forum_id, $mimetype = 'application/octetstream')
	{
		global $user;

		if (!file_exists($file))
		{
			trigger_error('FILE_NOT_FOUND', E_USER_ERROR);
		}

		$filedata = array(
			'realname'	=> basename($file),
			'size'		=> filesize($file),
			'type'		=> $mimetype
		);
		$filedata = upload_attachment(false, $forum_id, true, $file, false, $filedata);
		if ($filedata['post_attach'] && !sizeof($filedata['error']))
		{
			$attachment = new attachment();
			$attachment->poster_id = $user->data['user_id'];
			$attachment->physical_filename = $filedata['physical_filename'];
			$attachment->real_filename = $filedata['real_filename'];
			$attachment->extension = $filedata['extension'];
			$attachment->mimetype = $filedata['mimetype'];
			$attachment->filesize = $filedata['filesize'];
			$attachment->filetime = $filedata['filetime'];
			$attachment->thumbnail = $filedata['thumbnail'];
			$attachment->submit();
			return $attachment;
		}
		else
		{
			trigger_error(implode('<br/>', $filedata['error']), E_USER_ERROR);
		}
	}

	function get($attach_id)
	{
		global $db;
		$sql = "SELECT * FROM " . ATTACHMENTS_TABLE . " WHERE attach_id=" . intval($attach_id);
		$result = $db->sql_query($sql);
		$attach_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$attach_data)
		{
			//attachment does not exist, return false
			return false;
		}


		return attachment::from_array($attach_data);
	}

	function from_array($data)
	{
		$attachment = new attachment();
		$attachment->attach_id = $data['attach_id'];
		$attachment->post_msg_id = $data['post_msg_id'];
		$attachment->topic_id = $data['topic_id'];
		$attachment->in_message = $data['in_message'];
		$attachment->poster_id = $data['poster_id'];
		$attachment->is_orphan = $data['is_orphan'];
		$attachment->physical_filename = $data['physical_filename'];
		$attachment->real_filename = $data['real_filename'];
		$attachment->download_count = $data['download_count'];
		$attachment->attach_comment = $data['attach_comment'];
		$attachment->extension = $data['extension'];
		$attachment->mimetype = $data['mimetype'];
		$attachment->filesize = $data['filesize'];
		$attachment->filetime = $data['filetime'];
		$attachment->thumbnail = $data['thumbnail'];
		return $attachment;
	}

	function submit()
	{
		global $config, $db, $auth, $user;

		if (!$this->attach_id)
		{
			//new attachment, set some default values if not set yet
			if(!$this->poster_id) $this->poster_id = $user->data['user_id'];
			if(!$this->filetime) $this->filetime = time();
		}

		$sql_data = array(
			'post_msg_id'		=> $this->post_msg_id,
			'topic_id'			=> $this->topic_id,
			'in_message'		=> $this->in_message,
			'poster_id'			=> $this->poster_id,
			'is_orphan'			=> $this->is_orphan,
			'physical_filename'	=> $this->physical_filename,
			'real_filename'		=> $this->real_filename,
			//'download_count'	=> $this->download_count,
			'attach_comment'	=> $this->attach_comment,
			'extension'			=> $this->extension,
			'mimetype'			=> $this->mimetype,
			'filesize'			=> $this->filesize,
			'filetime'			=> $this->filetime,
			'thumbnail'			=> $this->thumbnail
		);

		$update_post_topic = false;

		if ($this->attach_id)
		{
			//edit
			$sql = 'SELECT *
				FROM ' . ATTACHMENTS_TABLE . '
				WHERE attach_id = ' . $this->attach_id;
			$result = $db->sql_query($sql);
			$attach_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$attach_data)
			{
				trigger_error("attach_id={$this->attach_id}, but that attachment does not exist", E_USER_ERROR);
			}

			$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
				SET ' . $db->sql_build_array('UPDATE', $sql_data) . '
				WHERE attach_id = ' . $this->attach_id;
			$db->sql_query($sql);

			if ($attach_data['post_msg_id'] != $this->post_msg_id || $attach_data['topic_id'] != $this->topic_id)
			{
				$update_post_topic = true;
			}
		}
		else
		{
			//insert attachment
			$sql = 'INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data);
			$db->sql_query($sql);
			$this->attach_id = $db->sql_nextid();

			$update_post_topic = true;
		}

		if ($update_post_topic)
		{
			//update post and topic tables
			if ($this->topic_id)
			{
				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET topic_attachment = 1
					WHERE topic_id = ' . $this->topic_id;
				$db->sql_query($sql);
			}
			if ($this->post_msg_id)
			{
				$sql = 'UPDATE ' . POSTS_TABLE . '
					SET post_attachment = 1
					WHERE post_id = ' . $this->post_msg_id;
				$db->sql_query($sql);
			}
		}
	}

	function delete()
	{
		delete_attachments('attach', $this->id);
	}

	function get_file()
	{
		global $phpbb_root_path, $config;
		return $phpbb_root_path . $config['upload_path'] . '/' . $this->physical_filename;
	}

	function get_thumbnail()
	{
		global $phpbb_root_path, $config;
		return $phpbb_root_path . $config['upload_path'] . '/thumb_' . $this->physical_filename;
	}
}

//helper methods
function reindex($mode, $post_id, $message, $subject, $poster_id, $forum_id)
{
	global $config, $phpbb_root_path, $phpEx;
	// Select the search method and do some additional checks to ensure it can actually be utilised
	$search_type = basename($config['search_type']);

	if (!file_exists($phpbb_root_path . 'includes/search/' . $search_type . '.' . $phpEx))
	{
		trigger_error('NO_SUCH_SEARCH_MODULE', E_USER_ERROR);
	}

	require_once("{$phpbb_root_path}includes/search/$search_type.$phpEx");

	$error = false;
	$search = new $search_type($error);

	if ($error)
	{
		trigger_error($error);
	}

	$search->index($mode, $post_id, $message, $subject, $poster_id, $forum_id);
}

/**class that collects updates to the topics/posts/forums/users tables and executes
 * them with as few sql queries as possible (e.g. instead of executing "posts=posts+1"
 * 2 times, it executes "posts=posts+2" once)*/
class syncer
{
	var $data = array();
	var $topic_first_post = array();
	var $topic_last_post = array();
	var $forum_last_post = array();
	var $new_topic_flag = false;

	/**@access private*/
	function init($type, $id)
	{
		if (!isset($this->data[$type]))
		{
			$this->data[$type] = array();
		}
		if (!isset($this->data[$type][$id]))
		{
			$this->data[$type][$id]['set'] = array();
			$this->data[$type][$id]['add'] = array();
			$this->data[$type][$id]['sql'] = array();
		}
	}

	/**increments or decrements a field.
	 * @param $type which table (topic, user, forum or post)
	 * @param $id topic_id, user_id etc.
	 * @param $field field name (e.g. topic_replies)
	 * @param $amount how much to add/subtract (default 1)
	 * example: $sync->add('topic', 123, 'topic_replies', 1)
	 * -> UPDATE phpbb_topics SET topic_replies = topic_replies + 1 WHERE topic_id = 123*/
	function add($type, $id, $field, $amount = 1)
	{
		$this->init($type, $id);
		if (!isset($this->data[$type][$id]['add'][$field]))
		{
			$this->data[$type][$id]['add'][$field] = 0;
		}
		$this->data[$type][$id]['add'][$field] += $amount;
	}

	function set($type, $id, $field, $value = false)
	{
		$this->init($type, $id);
		if (is_array($field))
		{
			$this->data[$type][$id]['set'] += $field;
		}
		else
		{
			$this->data[$type][$id]['set'][$field] = $value;
		}
	}

	function topic_first_post($topic_id)
	{
		$this->topic_first_post[] = $topic_id;
	}

	function topic_last_post($topic_id)
	{
		$this->topic_last_post[] = $topic_id;
	}

	function forum_last_post($forum_id)
	{
		$this->forum_last_post[] = $forum_id;
	}

	function update_first_last_post()
	{
		global $db;

		//topic_first_post
		$this->topic_first_post = array_unique($this->topic_first_post);
		foreach ($this->topic_first_post as $topic_id)
		{
			$sql = 'SELECT p.post_id, p.post_approved, p.poster_id, p.post_subject, p.post_username, p.post_time, u.username, u.user_colour
				FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
				WHERE p.topic_id=' . $topic_id . '
					AND u.user_id = p.poster_id
				ORDER BY post_time ASC';
			$result = $db->sql_query_limit($sql, 1);
			if ($row = $db->sql_fetchrow($result))
			{
				$this->set('topic', $topic_id, array(
					'topic_time'				=> $row['post_time'],
					'topic_poster'				=> $row['poster_id'],
					'topic_approved'			=> $row['post_approved'],
					'topic_first_post_id'		=> $row['post_id'],
					'topic_first_poster_name'	=> ($row['poster_id'] == ANONYMOUS) ? $row['post_username'] : $row['username'],
					'topic_first_poster_colour'	=> $row['user_colour']
				));
			}
		}

		//topic_last_post
		if (count($this->topic_last_post))
		{
			$update_sql = update_post_information('topic', $this->topic_last_post, true);
			foreach ($update_sql as $topic_id => $sql)
			{
				$this->init('topic', $topic_id);
				$this->data['topic'][$topic_id]['sql'] += $sql;
			}
		}

		//forum_last_post
		if (count($this->forum_last_post))
		{
			$update_sql = update_post_information('forum', $this->forum_last_post, true);
			foreach ($update_sql as $forum_id => $sql)
			{
				$this->init('forum', $forum_id);
				$this->data['forum'][$forum_id]['sql'] += $sql;
			}
		}
	}

	function execute()
	{
		global $db;

		$this->update_first_last_post();

		$sql_array = array();

		$tables = array(
			'topic'	=> array('table' => TOPICS_TABLE, 'key' => 'topic_id'),
			'user'	=> array('table' => USERS_TABLE, 'key' => 'user_id'),
			'forum'	=> array('table' => FORUMS_TABLE, 'key' => 'forum_id'),
			'post'	=> array('table' => POSTS_TABLE, 'key' => 'post_id')
		);

		foreach ($this->data as $type => $items)
		{
			foreach ($items as $id => $item)
			{
				$sql = 'UPDATE ' . $tables[$type]['table'] . ' SET ';
				if (count($item['set']))
				{
					$sql .= $db->sql_build_array('UPDATE', $item['set']) . ', ';
				}
				if (count($item['add']))
				{
					//build ', field = field + 1' style queries
					foreach($item['add'] as $field => $value)
					{
						$value = intval($value);
						/*if($value == 0)
						{
							continue;
						}*/
						$sql .= "$field = $field " . ($value < 0 ? '-' : '+') . abs($value) . ', ';
					}
				}
				if (count($item['sql']))
				{
					$sql .= implode(', ', $item['sql']) . ', ';
				}
				$sql = substr($sql, 0, -2);
				$sql .= ' WHERE ' . $tables[$type]['key'] . ' = ' . $id;
				$sql_array[] = $sql;
			}
		}

		foreach ($sql_array as $sql)
		{
			$db->sql_query($sql);
		}
	}
}

?>