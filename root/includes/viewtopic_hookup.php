<?php
/**
*
* @package phpBB Hookup MOD
* @copyright (c) 2007, 2008, 2009 Pyramide und gn#36 ?
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (isset($topic_data['hookup_enabled']) && $topic_data['hookup_enabled'])
{
	if (!function_exists('generate_smilies'))
	{
		include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
	}
	if (!function_exists('user_get_id_name'))
	{
		include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
	}
	if (!class_exists('messenger'))
	{
		include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
	}
	if (!class_exists('topic'))
	{
		include($phpbb_root_path . 'includes/functions_post_oo.' . $phpEx);
	}

	$user->add_lang('mods/hookup');


	$userlist = array();
	$userids = array();
	$comments = array();
	$datelist = array();
	$available_data = array();
	$available_sums = array();
	$hookup_errors = array();
	$is_hookup_owner = ($user->data['user_id'] == $topic_data['topic_poster']) || $auth->acl_get('m_edit', $forum_id);

	//POST-vars
	$delete_hookup = request_var('delete_hookup', 'no');
	$delete_user_ids = request_var('delete_user', array(0));
	$delete_date_ids = request_var('delete_date', array(0));
	$add_dates = trim(request_var('add_date', '', true));
	$invite_self = trim(request_var('invite_self', ''));
	$add_users = trim(request_var('usernames', '', true)); //can't use add_user because the javascript popup requires the fields name to be "usernames"
	$add_groups = request_var('add_groups', array(0));
	$set_active = request_var('set_active', -1);


	//load list of groups for "add groups" box
	$sql = 'SELECT group_id, group_name, group_type
		FROM ' . GROUPS_TABLE . '
		WHERE group_type <> ' . GROUP_HIDDEN;
	$result = $db->sql_query($sql);

	$s_group_list = '';
	while ($row = $db->sql_fetchrow($result))
	{
		$s_group_list .= '<option value="' . $row['group_id'] . '"' . ($row['group_type'] == GROUP_SPECIAL ? ' class="sep"' : '') .'>';
		$s_group_list .= ($row['group_type'] == GROUP_SPECIAL ? $user->lang['G_' . $row['group_name']] : $row['group_name']);
		$s_group_list .= '</option>';
	}
	$db->sql_freeresult($result);

	$template->assign_var('S_GROUP_LIST', $s_group_list);


	//load user_ids for this hookup
	$sql = 'SELECT user_id, notify_status, comment
		FROM ' . HOOKUP_MEMBERS_TABLE . '
		WHERE topic_id = ' . $topic_id;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
	{
		$userids[] = $row['user_id'];

		$comments[$row['user_id']] = $row['comment'];
		//reset the notify_status for the viewing user if nescessary
		if ($row['user_id'] == $user->data['user_id'] && $row['notify_status'])
		{
			$sql = 'UPDATE ' . HOOKUP_MEMBERS_TABLE . "
				SET notify_status = 0
				WHERE topic_id = $topic_id
					AND user_id = {$user->data['user_id']}";
			$db->sql_query($sql);
		}
	}
	$db->sql_freeresult($result);

	//invite self
	if (($topic_data['hookup_self_invite'] || $is_hookup_owner) && ($invite_self == 'join') && !in_array($user->data['user_id'], $userids))
	{
		//user or owner wants to join and is not a member yet.
		$sql_array = array(
			'topic_id'		=> $topic_id,
			'user_id'		=> $user->data['user_id'],
			'notify_status'	=> 0 //would be set to 0 on the next refresh anyway
		);
		$sql = 'INSERT INTO ' . HOOKUP_MEMBERS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_array);
		$db->sql_query($sql);
		$userids[] = $user->data['user_id'];
	}
	elseif ($topic_data['hookup_self_invite'] && ($invite_self == 'leave') && in_array($user->data['user_id'], $userids))
	{
		//user wants to leave and is a member, display confirmation box first
		if (confirm_box(true))
		{
			$where_sql = " WHERE topic_id = $topic_id AND user_id = {$user->data['user_id']}";
			$sql = 'DELETE FROM ' . HOOKUP_MEMBERS_TABLE . $where_sql;
			$db->sql_query($sql);
			$sql = 'DELETE FROM ' . HOOKUP_AVAILABLE_TABLE . $where_sql;
			$db->sql_query($sql);

			$userids = array_diff($userids, array($user->data['user_id']));
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				't'				=> $topic_id,
				'invite_self'	=> 'leave',
			));
			confirm_box(false, $user->lang['HOOKUP_INVITE_SELF_LEAVE_CONFIRM'], $s_hidden_fields);
		}
	}

	//add users
	if ($is_hookup_owner && !empty($add_users))
	{
		$username_array = array_unique(explode("\n", $add_users));
		$username_array = array_map('utf8_clean_string', $username_array);

		//get some userdata
		$sql = 'SELECT user_id, username, user_type, user_permissions, user_lang, user_email, user_jabber, user_notify_type
			FROM ' . USERS_TABLE . '
			WHERE ' . $db->sql_in_set('username_clean', $username_array);
		$result = $db->sql_query($sql);

		$new_users = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$new_users[$row['user_id']] = $row;
		}
		$db->sql_freeresult($result);

		$userids_to_add = array_diff(array_keys($new_users), $userids);
		$userids_already_added = array_intersect(array_keys($new_users), $userids);
	}

	//add group(s)
	else if ($is_hookup_owner && count($add_groups) > 0)
	{
		//get some userdata
		$sql = 'SELECT u.user_id, u.username, u.user_type, u.user_permissions, u.user_lang, u.user_email, u.user_jabber, u.user_notify_type
			FROM ' . USER_GROUP_TABLE . ' ug
			JOIN ' . USERS_TABLE . ' u
				ON (u.user_id = ug.user_id)
			WHERE ' . $db->sql_in_set('ug.group_id', $add_groups) . '
				AND ug.user_pending = 0';
		$result = $db->sql_query($sql);

		$new_users = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$new_users[$row['user_id']] = $row;
		}
		$db->sql_freeresult($result);

		$userids_to_add = array_diff(array_keys($new_users), $userids);
		//dont set $userids_already_added because it might generate a lot of warnings
	}

	//now that we have the user_ids and data, add the users
	if (isset($userids_to_add) && count($userids_to_add) > 0)
	{
		//check if users have read permission
		$user_auth = new auth();
		foreach($userids_to_add as $key => $user_id)
		{
			$user_auth->acl($new_users[$user_id]);
			if (!$user_auth->acl_get('f_read', $forum_id))
			{
				$hookup_errors[] = sprintf($user->lang['USER_CANNOT_READ_FORUM'], $new_users[$user_id]['username']);
				unset($userids_to_add[$key]);
			}
		}

		//insert users into database
		$sql_array = array();
		foreach($userids_to_add as $user_id)
		{
			$sql_array[] = array(
				'topic_id'		=> $topic_id,
				'user_id'		=> $user_id,
				'notify_status'	=> 1 //no need to notify the user about new dates when he hasn't visited the
									 //hookup yet and thus not even entered his available info for the first dates
			);
		}
		$db->sql_multi_insert(HOOKUP_MEMBERS_TABLE, $sql_array);

		//notify new users about invitation
		$messenger = new messenger();
		foreach($userids_to_add as $user_id)
		{
			$userdata = $new_users[$user_id];
			$messenger->template('hookup_added', $userdata['user_lang']);
			$messenger->to($userdata['user_email'], $userdata['username']);
			$messenger->im($userdata['user_jabber'], $userdata['username']);
			$messenger->assign_vars(array(
				'USERNAME'		=> $userdata['username'],
				'TOPIC_TITLE'	=> $topic_data['topic_title'],
				'U_TOPIC'		=> generate_board_url() . "/viewtopic.$phpEx?f=$forum_id&t=$topic_id",
			));
			$messenger->send($userdata['user_notify_type']);
		}
		$messenger->save_queue();

		//add userids to local array
		$userids = array_merge($userids, $userids_to_add);
	}

	//generate error messages for users that are already members
	if (isset($userids_already_added) && count($userids_already_added) > 0)
	{
		foreach($userids_already_added as $userid)
		{
			$hookup_errors[] = sprintf($user->lang['HOOKUP_USER_EXISTS'], $new_users[$userid]['username']);
		}
	}


	//disable/delete whole hookup
	if ($delete_hookup == 'disable' || $delete_hookup == 'delete')
	{
		if (confirm_box(true))
		{
			if ($delete_hookup == 'delete')
			{
				$sql = 'UPDATE ' . TOPICS_TABLE . "
					SET hookup_enabled = 0,
						hookup_active_date=0
					WHERE topic_id = $topic_id";
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . HOOKUP_DATES_TABLE . " WHERE topic_id = $topic_id";
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . HOOKUP_MEMBERS_TABLE . " WHERE topic_id = $topic_id";
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . HOOKUP_AVAILABLE_TABLE . " WHERE topic_id = $topic_id";
				$db->sql_query($sql);
			}
			else //disable
			{;
				$sql = 'UPDATE ' . TOPICS_TABLE . "
					SET hookup_enabled = 0
					WHERE topic_id = $topic_id";
				$db->sql_query($sql);
			}
			redirect($viewtopic_url);
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				't'				=> $topic_id,
				'delete_hookup'	=> $delete_hookup
			));
			confirm_box(false, $user->lang['DELETE_HOOKUP_' . strtoupper($delete_hookup) . '_CONFIRM'], $s_hidden_fields);
		}
	}


	//confirm box for user/date deletion (only one confirm box because you can delete both with one <form>)
	$delete_confirm = false;
	if ($is_hookup_owner && (count($delete_user_ids) > 0 || count($delete_date_ids) > 0))
	{
		if (confirm_box(true))
		{
			$delete_confirm = true;
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				't'				=> $topic_id,
				'delete_date'	=> $delete_date_ids,
				'delete_user'	=> $delete_user_ids,
				//'available'		=> $available,
			));
			confirm_box(false, sprintf($user->lang['HOOKUP_DELETE_CONFIRM'], count($delete_date_ids), count($delete_user_ids)), $s_hidden_fields);
		}
	}

	//delete users
	if ($is_hookup_owner && count($delete_user_ids) > 0 && $delete_confirm)
	{
		//make sure we only have ints.
		$delete_ids = array_map('intval', $_POST['delete_user']);
		$where_sql = " WHERE topic_id = $topic_id AND " . $db->sql_in_set('user_id', $delete_ids);
		$sql = 'DELETE FROM ' . HOOKUP_MEMBERS_TABLE  . $where_sql;
		$db->sql_query($sql);
		$sql = 'DELETE FROM ' . HOOKUP_AVAILABLE_TABLE . $where_sql;
		$db->sql_query($sql);

		$userids = array_diff($userids, $delete_ids);
	}

	//delete date(s)
	if ($is_hookup_owner && count($delete_date_ids) > 0 && $delete_confirm)
	{
		//make sure we only have ints.
		$delete_ids = array_map('intval', $_POST['delete_date']);
		$where_sql = " WHERE topic_id = $topic_id AND " . $db->sql_in_set('date_id', $delete_ids);
		$sql = 'DELETE FROM ' . HOOKUP_DATES_TABLE . $where_sql;
		$db->sql_query($sql);
		$sql = 'DELETE FROM ' . HOOKUP_AVAILABLE_TABLE . $where_sql;
		$db->sql_query($sql);
	}


	//load list of invited users so we can check who is permitted to add dates 
	$sql = 'SELECT user_id, username, user_colour
		FROM ' . USERS_TABLE . '
		WHERE ' . $db->sql_in_set('user_id', $userids, false, true) . '
		ORDER BY username_clean ASC';
	$result = $db->sql_query($sql);
	$userlist = $db->sql_fetchrowset($result);

	//build list of user_ids
	foreach ($userlist as $ignore => $userrow)
	{
		$userids[] = $userrow['user_id'];
	}
	$db->sql_freeresult($result);
	$is_hookup_member = in_array($user->data['user_id'], $userids);

	//add a new date. this needs to be done after we loaded the userlist but before the datelist 
	if (($is_hookup_owner || $is_hookup_member) && strlen($add_dates) > 0)
	{
		$add_dates = array_map("trim", explode("\n", $add_dates));
		$sql_data = array();

		//replace german date format
		$add_dates = preg_replace('#(\\d{1,2})\\. ?(\\d{1,2})\\. ?(\\d{4})#', '$3-$2-$1', $add_dates);

		foreach($add_dates as $date)
		{
			//strtotime uses the local (server) timezone, so parse manually and use gmmktime to ignore any timezone
			if (!preg_match('#(\\d{4})-(\\d{1,2})-(\\d{1,2}) (\\d{1,2}):(\\d{2})#', $date, $m))
			{
				$hookup_errors[] = "$date: {$user->lang['INVALID_DATE']}";
			}
			else
			{
				$date_time = gmmktime($m[4], $m[5], 0, $m[2], $m[3], $m[1]);

				//manually subtract users timezone and dst
				$date_time -= ($user->timezone + $user->dst);

				if ($date_time < time())
				{
					$hookup_errors[] = "$date: {$user->lang['CANNOT_ADD_PAST']}";
				}
				else
				{
					//check for duplicate
					$sql = 'SELECT date_id
						FROM ' . HOOKUP_DATES_TABLE . "
						WHERE topic_id = $topic_id
							AND date_time = $date_time";
					$result = $db->sql_query($sql);
					if ($db->sql_fetchrow($result))
					{
						$hookup_errors[] = sprintf($user->lang['DATE_ALREADY_ADDED'], $user->format_date($date_time));
					}
					else
					{
						$sql_data[] = array(
							'topic_id'	=> $topic_id,
							'date_time'	=> $date_time
						);
					}
					$db->sql_freeresult($result);
				}
			}
		}
		if (count($sql_data) > 0)
		{
			$db->sql_multi_insert(HOOKUP_DATES_TABLE, $sql_data);

			//notify members about new dates
			$messenger = new messenger();
			$notified_userids = array();

			$sql = 'SELECT u.user_id, u.username, u.user_lang, u.user_email, u.user_jabber, u.user_notify_type
				FROM ' . HOOKUP_MEMBERS_TABLE . ' hm
				JOIN ' . USERS_TABLE . " u
					ON (u.user_id = hm.user_id)
				WHERE hm.topic_id = $topic_id
					AND hm.user_id <> {$user->data['user_id']}
					AND hm.notify_status = 0";
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$messenger->template('hookup_dates_added', $row['user_lang']);
				$messenger->to($row['user_email'], $row['username']);
				$messenger->im($row['user_jabber'], $row['username']);
				$messenger->assign_vars(array(
					'USERNAME' => $row['username'],
					'TOPIC_TITLE'	=> $topic_data['topic_title'],
					'U_TOPIC'	=> generate_board_url() . "/viewtopic.$phpEx?f=$forum_id&t=$topic_id"
				));
				$messenger->send($row['user_notify_type']);

				$notified_userids[] = $row['user_id'];
			}
			$db->sql_freeresult($result);

			$messenger->save_queue();

			//set notify status
			if (count($notified_userids))
			{
				$sql = 'UPDATE ' . HOOKUP_MEMBERS_TABLE . "
					SET notify_status = 1
					WHERE topic_id = $topic_id
						AND " . $db->sql_in_set('user_id', $notified_userids);
				$db->sql_query($sql);
			}
		}
	}


	//load dates for this hookup
	$sql = 'SELECT date_id, date_time
		FROM ' . HOOKUP_DATES_TABLE . '
		WHERE topic_id = ' . $topic_id . '
		ORDER BY date_time ASC';
	$result = $db->sql_query($sql);
	//associative array date_id => date_row
	while ($row = $db->sql_fetchrow($result))
	{
		$datelist[$row['date_id']] = $row;
	}
	$db->sql_freeresult($result);


	//load available info
	foreach($datelist as $date)
	{
		$available_sums[$date['date_id']] = array(HOOKUP_YES => 0, HOOKUP_MAYBE => 0, HOOKUP_NO => 0);
	}

	$sql = 'SELECT date_id, user_id, available 
		FROM ' . HOOKUP_AVAILABLE_TABLE . '
		WHERE topic_id = ' . $topic_id;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
	{
		$available_data[$row['user_id']][$row['date_id']] = $row['available'];
		$available_sums[$row['date_id']][$row['available']]++;
	}
	$db->sql_freeresult($result);

	//update this users available info
	if ($is_hookup_member && isset($_POST['available']) && is_array($_POST['available']))
	{
		foreach($_POST['available'] as $date_id => $available)
		{
			//ignore HOOKUP_UNSET and other invalid values
			if (!is_numeric($date_id) || !isset($datelist[$date_id]) || !in_array($available, array(HOOKUP_YES, HOOKUP_NO, HOOKUP_MAYBE)))
			{
				continue;
			}

			//update existing row
			if (isset($available_data[$user->data['user_id']][$date_id]))
			{
				$old_status = $available_data[$user->data['user_id']][$date_id];
				//ignore unchanged values
				if ($old_status == $available)
				{
					continue;
				}

				$sql = 'UPDATE ' . HOOKUP_AVAILABLE_TABLE . "
						SET available = $available
						WHERE user_id = {$user->data['user_id']}
							AND date_id = $date_id";
				$db->sql_query($sql);

				//update local stats
				$available_sums[$date_id][$old_status]--;
				$available_sums[$date_id][$available]++;
			}
			//insert new row
			else
			{
				$sql_ary = array(
					'topic_id'			=> $topic_id,
					'date_id'			=> $date_id,
					'user_id'			=> $user->data['user_id'],
					'available'			=> $available,
				);
				$sql = 'INSERT INTO ' . HOOKUP_AVAILABLE_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
				$db->sql_query($sql);

				//update local stats
				$available_sums[$date_id][$available]++;
			}


			//replace value in array
			$available_data[$user->data['user_id']][$date_id] = $available;
		}

		//Update Comment:
		$comment = request_var('comment', '', true);
		$sql = 'UPDATE ' . HOOKUP_MEMBERS_TABLE . "
			SET comment='" . $db->sql_escape($comment) . "' 
			WHERE user_id={$user->data['user_id']}
				AND topic_id = $topic_id";
		$db->sql_query($sql);
		$comments[$user->data['user_id']] = $comment;
	}

	//Set active date
	if ($is_hookup_owner && ($set_active > -1))
	{
		if ($set_active != 0 && !isset($datelist[$set_active]))
		{
			trigger_error('NO_DATE');
		}

		$active_date_formatted = $set_active != 0 ? $user->format_date($datelist[$set_active]['date_time']) : '-';

		if (confirm_box(true))
		{
			$title_prefix = request_var('title_prefix', false);
			$send_email = request_var('send_email', false);
			$post_reply = request_var('post_reply', false);

			//insert active date (short format) into topic title. this will use language
			//and timezone of the "active maker" but the alternative would be
			//to query the HOOKUP_DATES table every time we need the topic title
			if ($set_active == 0 || $title_prefix)
			{
				$new_title = preg_replace('#^(\\[.+?\\] )?#', ($set_active != 0 ? '[' . $user->format_date($datelist[$set_active]['date_time'], $user->lang['HOOKUP_DATEFORMAT_TITLE']) . '] ' : ''), $topic_data['topic_title']);

				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET hookup_active_date = ' . (int) $set_active . ",
						topic_title = '" . $db->sql_escape($new_title) . "'
					WHERE topic_id = $topic_id";
				$db->sql_query($sql);

				$sql = 'UPDATE ' . POSTS_TABLE . "
					SET post_subject='" . $db->sql_escape($new_title) . "'
					WHERE post_id = {$topic_data['topic_first_post_id']}"; 
				$db->sql_query($sql);
			}
			else
			{
				//only set hookup_active_date
				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET hookup_active_date = ' . (int) $set_active . "
					WHERE topic_id = $topic_id";
				$db->sql_query($sql);
			}


			//notify all members about active date
			if ($set_active != 0 && $send_email)
			{
				$messenger = new messenger();
				$title_without_date = preg_replace('#^(\\[.+?\\] )#', '', $topic_data['topic_title']);

				$sql = 'SELECT u.user_id, u.username, u.user_lang, u.user_dateformat, u.user_email, u.user_jabber, u.user_notify_type
					FROM ' . HOOKUP_MEMBERS_TABLE . ' hm
					JOIN ' . USERS_TABLE . " u
						ON (u.user_id = hm.user_id)
					WHERE hm.topic_id = $topic_id";
				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					$messenger->template('hookup_active_date', $row['user_lang']);
					$messenger->to($row['user_email'], $row['username']);
					$messenger->im($row['user_jabber'], $row['username']);
					$messenger->assign_vars(array(
						'USERNAME' 		=> $row['username'],
						'TOPIC_TITLE'	=> $title_without_date,
						'U_TOPIC'		=> generate_board_url() . "/viewtopic.$phpEx?f=$forum_id&t=$topic_id",
						//TODO use recipients language
						'ACTIVE_DATE'	=> $user->format_date($datelist[$set_active]['date_time'], $row['user_dateformat'], true),
						'ACTIVE_DATE_SHORT'=> $user->format_date($datelist[$set_active]['date_time'], $user->lang['HOOKUP_DATEFORMAT']),
					));
					$messenger->send($row['user_notify_type']);
				}
				$db->sql_freeresult($result);

				$messenger->save_queue();
			}

			//post reply to this topic. Again this can only be in the "active maker"s language
			if ($post_reply)
			{
				$message = $user->lang['SET_ACTIVE_POST_TEMPLATE'];
				$message = str_replace('{ACTIVE_DATE}', $user->format_date($datelist[$set_active]['date_time'], $user->lang['HOOKUP_DATEFORMAT_POST']), $message);

				$post = new post($topic_id);
				$post->post_text = $message;
				$post->post_subject = sprintf($user->lang['SET_ACTIVE_POST_TITLE'], $user->format_date($datelist[$set_active]['date_time'], $user->lang['HOOKUP_DATEFORMAT_POST']));
				$post->submit();
			}

			meta_refresh(3, $viewtopic_url);
			$message = ($set_active != 0 ? sprintf($user->lang['ACTIVE_DATE_SET'], $active_date_formatted) : $user->lang['ACTIVE_DATE_UNSET']) . '<br /><br />' . sprintf($user->lang['RETURN_TOPIC'], '<a href="' . $viewtopic_url . '">', '</a>');
			trigger_error($message);
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				't'			=> $topic_id,
				'set_active'=> $set_active
			));
			if ($set_active != 0)
			{
				confirm_box(false, sprintf($user->lang['SET_ACTIVE_CONFIRM'], $active_date_formatted), $s_hidden_fields, 'hookup_active_date_confirm.html');
			}
			else
			{
				confirm_box(false, 'UNSET_ACTIVE', $s_hidden_fields);
			}
		}
	}

	if (count($datelist) == 0)
	{
		$hookup_errors[] = $user->lang['HOOKUP_NO_DATES'];
	}
	if (count($userlist) == 0)
	{
		$hookup_errors[] = $user->lang['HOOKUP_NO_USERS'];
	}


	//template
	$template->assign_vars(array(
		'S_HAS_HOOKUP'		=> true,
		'S_IS_SELF_INVITE'	=> $topic_data['hookup_self_invite'],
		'S_IS_HOOKUP_OWNER'	=> $is_hookup_owner,
		'S_IS_HOOKUP_MEMBER'=> $is_hookup_member,
		'S_HOOKUP_ACTION'	=> $viewtopic_url,
		'S_LANG_PATH'		=> $user->lang_path . $user->lang_name,
		'S_LANG_NAME'		=> $user->lang_name,
		'S_NUM_DATES'		=> count($datelist),
		'S_NUM_DATES_PLUS_1'=> count($datelist)+1,
		'S_ACTIVE_DATE'		=> $topic_data['hookup_active_date'],
		'S_HAS_DATES'		=> (count($datelist) > 0),
		'S_HAS_USERS'		=> (count($userlist) > 0),
		'ACTIVE_DATE_DATE'	=> isset($datelist[$topic_data['hookup_active_date']]) ? $user->format_date($datelist[$topic_data['hookup_active_date']]['date_time']) : '-',
		'U_UNSET_ACTIVE'	=> $viewtopic_url . '&amp;set_active=0',
		'U_FIND_USERNAME'	=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=ucp&amp;field=usernames'),
		'UA_FIND_USERNAME'	=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&form=ucp&field=usernames', false),
		'HOOKUP_ERRORS'		=> (count($hookup_errors) > 0) ? implode('<br />', $hookup_errors) : false,
		'HOOKUP_YES'		=> HOOKUP_YES,
		'HOOKUP_MAYBE'		=> HOOKUP_MAYBE,
		'HOOKUP_NO'			=> HOOKUP_NO,
		'HOOKUP_UNSET'		=> HOOKUP_UNSET,
		'L_HOOKUP_YES'		=> $user->lang['HOOKUP_STATUS'][HOOKUP_YES],
		'L_HOOKUP_NO'		=> $user->lang['HOOKUP_STATUS'][HOOKUP_NO],
		'L_HOOKUP_MAYBE'	=> $user->lang['HOOKUP_STATUS'][HOOKUP_MAYBE],
		'L_HOOKUP_UNSET'	=> $user->lang['HOOKUP_STATUS'][HOOKUP_UNSET],
		//one letter versions for summaries
		'L_HOOKUP_Y'		=> $user->lang['HOOKUP_STATUS'][HOOKUP_YES]{0},
		'L_HOOKUP_N'		=> $user->lang['HOOKUP_STATUS'][HOOKUP_NO]{0},
		'L_HOOKUP_M'		=> $user->lang['HOOKUP_STATUS'][HOOKUP_MAYBE]{0},

	));

	foreach($datelist as $hookup_date)
	{
		$yes_count = $available_sums[$hookup_date['date_id']][HOOKUP_YES];
		$maybe_count = $available_sums[$hookup_date['date_id']][HOOKUP_MAYBE];
		$no_count = $available_sums[$hookup_date['date_id']][HOOKUP_NO];
		//$total_count = $yes_count + $maybe_count + $no_count; //unset_count?
		$total_count = count($userlist);
		$unset_count = $total_count - ($yes_count + $maybe_count + $no_count);

		$yes_percent = $total_count > 0 ? floor(($yes_count / $total_count) * 100) : 0;
		$maybe_percent = $total_count > 0 ? floor(($maybe_count / $total_count) * 100) : 0;
		$no_percent = $total_count > 0 ? floor(($no_count / $total_count) * 100) : 0;
		$unset_percent = ($unset_count) ? 100 - ($yes_percent + $maybe_percent + $no_percent) : 0;

		$template->assign_block_vars('date', array(
			'ID'			=> $hookup_date['date_id'],
			'DATE'			=> $user->format_date($hookup_date['date_time'], $user->lang['HOOKUP_DATEFORMAT']),
			'FULL_DATE'		=> $user->format_date($hookup_date['date_time']),
			//'ADDED_AT_BY'		=> sprintf($user->lang['ADDED_AT_BY'], $user->format_date($hookup_date['added_at']), $hookup_date['added_by_name']),
			'YES_COUNT'		=> $yes_count,
			'YES_PERCENT'	=> $yes_percent,
			'MAYBE_COUNT'	=> $maybe_count,
			'MAYBE_PERCENT'	=> $maybe_percent,
			'NO_COUNT'		=> $no_count,
			'NO_PERCENT'	=> $no_percent,
			'UNSET_COUNT'	=> $unset_count,
			'UNSET_PERCENT'	=> $unset_percent,
			'S_IS_ACTIVE'	=> ($hookup_date['date_id'] == $topic_data['hookup_active_date']) ? true : false,
			'U_SET_ACTIVE'	=> $viewtopic_url . '&amp;set_active=' . $hookup_date['date_id'],
		));
	}

	foreach($userlist as $hookup_user)
	{
		$is_self = ($hookup_user['user_id'] == $user->data['user_id']);

		$template->assign_block_vars('user', array(
			'ID'		=> $hookup_user['user_id'],
			'NAME'		=> $hookup_user['username'],
			'COMMENT'	=> isset($comments[$hookup_user['user_id']]) ? $comments[$hookup_user['user_id']] : '',
			'USERNAME_FULL'	=> get_username_string('full', $hookup_user['user_id'], $hookup_user['username'], $hookup_user['user_colour']),
			'IS_SELF'	=> $is_self
		));

		foreach($datelist as $hookup_date)
		{
			$available = isset($available_data[$hookup_user['user_id']][$hookup_date['date_id']])
					? $available_data[$hookup_user['user_id']][$hookup_date['date_id']]
					: HOOKUP_UNSET;

			$template->assign_block_vars('user.date', array(
				'ID'				=> $hookup_date['date_id'],
				'AVAILABLE'			=> $user->lang['HOOKUP_STATUS'][$available],
				'STATUS_YES'		=> ($available == HOOKUP_YES),
				'STATUS_NO'			=> ($available == HOOKUP_NO),
				'STATUS_MAYBE'		=> ($available == HOOKUP_MAYBE),
				'STATUS_UNSET'		=> ($available == HOOKUP_UNSET),
				'S_SELECT_NAME'		=> 'available['.$hookup_date['date_id'].']',
				'S_IS_ACTIVE'		=> $hookup_date['date_id'] == $topic_data['hookup_active_date'],
			));
		}
	}
}
?>
