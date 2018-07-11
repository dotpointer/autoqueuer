<?php

# actions for control panel

# changelog
# 2013-07-17 22:15:11
# 2013-09-25 - correcting for base1
# 2014-07-12 16:39:15 - updating for v2
# 2014-07-23 13:19:25 - multi client
# 2015-03-10 22:16:57
# 2015-07-27 02:29:17 - adding email
# 2015-08-21 12:02:14 - small cleanup
# 2016-02-23 13:43:31 - removing email parameter, adding email parameters
# 2016-03-05 22:09:02 - cleanup
# 2016-09-22 22:53:42 - base 2 to base 3
# 2017-07-31 14:22:37 - adding nickname
# 2017-09-10 23:43:00 - newline removed
# 2017-09-12 21:52:00 - dropping project name in file
# 2017-09-13 01:43:00 - adding cancel
# 2017-09-19 19:25:00 - editing message handling
# 2017-09-22 00:08:00 - adding redownload
# 2018-07-11 18:37:00 - adding login

if (!isset($action)) exit;

# is the editusers setup array set
if (isset($editusers)) {

	if (strlen($password_salt) > 15) {
		# walk this array
		foreach ($editusers as $user) {
			if (!isset($user['username']) || !isset($user['password'])) {
				continue;
			}

			if (!validate_user($user['username'])) {
				die('Username in editusers array is too short, too long or contain invalid characters.');
				break;
			}

			if (!validate_pass($user['password'])) {
				die('Password in editusers array is too short or does not contain letters or digits.');
				break;
			}

			$sql = '
				SELECT
					*
				FROM
					users
				WHERE
					username="'.dbres($link, $user['username']).'" OR
					nickname="'.dbres($link, $user['username']).'"
				';
			$result = db_query($link, $sql);

			$iu = array(
				'username' => $user['username'],
				'updated' => date('Y-m-d H:i:s')
			);

			if (!count($result)) {
				$iu['created'] = date('Y-m-d H:i:s');
				$iu = dbpia($link, 	$iu);
				# set password separately
				$iu['password'] = 'ENCRYPT("'.dbres($link, $user['password']).'", "'.dbres($link, $password_salt).'")';
				$sql = '
					INSERT INTO users (
						'.implode(',', array_keys($iu)).'
					) VALUES(
						'.implode(',', $iu).'
					)';
				db_query($link, $sql);
			} else {
				# make sure visum users are not tampered with
				if ($result[0]['id_visum'] !== '0') {
					die('A username in editusers array matches a Visum user. Cannot edit Visum users with the editusers array.');
					break;
				}
				$iu['updated'] = date('Y-m-d H:i:s');
				$iu = dbpua($link, $iu);
				$iu['password'] = 'password=ENCRYPT("'.dbres($link, $user['password']).'", "'.dbres($link, $password_salt).'")';
				$sql = '
					UPDATE
						users
					SET
						'.implode($iu, ',').'
					WHERE
						id="'.dbres($link, $result[0]['id']).'"
					';
				db_query($link, $sql);
			}
			# echo $sql."\n";
		}
		die('Users noted in the user editing array has been created and updated. Please comment out the array in the setup file when done with it to continue.');
	} else {
		die('The password salt text is too short, please set a longer one in the setup file.');
	}
}

# find out what action to take if any
switch ($action) {
	case 'delete_clientpump': # delete a clientpump

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		# make sure a clientpump id is supplied
		if (!$id_clientpumps) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Missing clientpump ID.'
				)
			)));
		}

		# do not delete, just hide
		$sql = 'UPDATE
					clientpumps
				SET
					status='.CLIENTPUMPS_STATUS_DELETED.'
				WHERE
					id="'.dbres($link, $id_clientpumps).'"
				';
		cl('SQL: '.$sql, VERBOSE_DEBUG);
		$result = db_query($link, $sql);
		if ($result === false) {
			cl(db_error($link), VERBOSE_ERROR);
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));

	case 'delete_dumped_files': # to delete selected files that have been dumped, eg are in collection -2

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		foreach ($id_files as $k => $v) {
			$id_files[$k] = '"'.dbres($link, $v).'"';
		}

		if (!count($id_files)) {
			break;
		}

		$sql = '
				DELETE FROM
					files
				WHERE
					id IN ('.implode($id_files, ',').') AND
					id_collections=-2
				';
		cl('SQL: '.$sql, VERBOSE_DEBUG);
		$result = db_query($link, $sql);
		if ($result === false) {
			cl(db_error($link), VERBOSE_ERROR);
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		break;

	case 'delete_search': # delete a search

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		if (!$id_searches) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Missing search ID.'
				)
			)));
		}

		# do not delete, just hide
		$sql = '
				UPDATE
					searches
				SET
					status='.SEARCHES_STATUS_DELETED.'
				WHERE
					id="'.dbres($link, $id_searches).'"
				';
		cl('SQL: '.$sql, VERBOSE_DEBUG);
		$result = db_query($link, $sql);
		if ($result === false) {
			cl(db_error($link), VERBOSE_ERROR);
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));

	case 'cancel':

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		if (!$id) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Missing file ID.'
				)
			)));
		}

		if (!$id_clientpumps) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Missing pump ID.'
				)
			)));
		}

		$output = array('status' => 'ok', 'data' => array());

		# which client to ask?
		$tmp = false;
		foreach ($clientpumps as $pumpname => $pump) {
			if ((int)$id_clientpumps === (int)$pump['data']['id'] && (int)$pump['data']['status'] === 1) {
				$tmp = $pumpname;
				break;
			}
		}
		$pumpname = $tmp;

		# no suitable pump found?
		if ($pumpname === false) {
			# then get out
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Pump is not active or found.'
				)
			)));
		}

		# try to run the call
		$r = $clientpumps[$pumpname]['pump']->cancel($id);

		# did it fail?
		if ($r === false) {

			cl('Failed cancel download on '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].').', VERBOSE_ERROR);
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		# is redownload with file hash specified?
		if ($redownload && $filehash) {
			$sql = '
				UPDATE
					files
				SET
					redownload=1
				WHERE
					ed2khash="'.dbres($link, $filehash).'" AND
					existing=0 AND
					id_collections="'.dbres($link, FILES_ID_COLLECTIONS_DOWNLOAD).'" AND
					redownload=0
				LIMIT 1';
			cl('SQL: '.$sql, VERBOSE_DEBUG);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link), VERBOSE_ERROR);
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => messages(false)
					)
				)));
			}
		}

		die(json_encode($output));

	case 'insert_or_update_clientpump': # make a new clientpump or update an existing one

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		# at least a clientpump term must be specified
		if (!strlen($type) || !strlen($host) || !is_numeric($port)) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Missing or invalid values.'
				)
			)));
		}

		$insert_update = array(
			'host' => $host,
			'path_incoming' => $path_incoming,
			'port' => $port,
			'status' => $status,
			'`type`' => $type,
			'username' => $username
		);

		# shall password be set?
		if ($password !== '********') {
			$insert_update['password'] = $password;
		}

		# updating
		if ($id_clientpumps) {
			$insert_update['updated'] = date('Y-m-d H:i:s');
			$insert_update = dbpua($link, $insert_update);
			$sql = '
					UPDATE
						clientpumps
					SET
						'.implode(',', $insert_update).'
					WHERE
						id="'.dbres($link, $id_clientpumps).'"
					';
			cl('SQL: '.$sql, VERBOSE_DEBUG);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link), VERBOSE_ERROR);
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => messages(false)
					)
				)));
			}
		# inserting
		} else {
			$insert_update['created'] = date('Y-m-d H:i:s');
			$insert_update = dbpia($link, $insert_update);
			$sql = '
					INSERT INTO
						clientpumps
					(
						'.implode(',', array_keys($insert_update)).'
					)
					VALUES(
						'.implode(',', $insert_update).'
					)
					';
			cl('SQL: '.$sql, VERBOSE_DEBUG);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link), VERBOSE_ERROR);
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => messages(false)
					)
				)));
			}
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));

	case 'insert_or_update_parameters': # insert or update update parameters

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		# at least a clientpump term must be specified
		# if (!strlen($email_enabled) || !strlen($email_address) || !is_numeric($email_timeout)) die(json_encode(array('status' => 'error', 'data' => array('message' => 'Missing or invalid values.'))));

		$parameters = array(
			'email_address' => $email_address,
			'email_enabled' => $email_enabled,
			'email_timeout' => is_numeric($email_timeout) ? $email_timeout : 0
		);

		foreach ($parameters as $parameter => $value) {

			# check if this parameter is in database
			$sql = '
					SELECT
						*
					FROM
						parameters
					WHERE
						parameter="'.dbres($link, $parameter).'"
					';
			$result = db_query($link, $sql);
			# is it in db?
			if (count($result)) {
				$insert_update = dbpua($link, array($parameter => $value));
				$sql = '
						UPDATE
							parameters
						SET
							value="'.dbres($link, $value).'"
						WHERE
							parameter="'.dbres($link, $parameter).'"
						';
				cl('SQL: '.$sql, VERBOSE_DEBUG);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_ERROR);
					die(json_encode(array(
						'status' => 'error',
						'data' => array(
							'message' => messages(false)
						)
					)));
				}

			# or is it not in db?
			} else {

				$insert_update = dbpia($link, array($parameter => $value));
				$sql = '
						INSERT INTO
							parameters
						(
							parameter,
							value
						)
						VALUES(
							'.implode(',', array( '"'.dbres($link, $parameter).'"', '"'.dbres($link, $value).'"' )).'
						)
						';
				cl('SQL: '.$sql, VERBOSE_DEBUG);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_ERROR);
					die(json_encode(array(
						'status' => 'error',
						'data' => array(
							'message' => messages(false)
						)
					)));
				}
			}
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));

	case 'insert_or_update_search': # make a new search or update an existing one

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		# at least a search term must be specified
		if (!strlen($search)) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'No search specified.'
				)
			)));
		}

		$insert_update = array(
			'executiontimeoutbase' => $executiontimeoutbase,
			'executiontimeout' => (int)$executiontimeoutbase + rand(0, (int)$executiontimeoutrandbase),
			'executiontimeoutrandbase' => $executiontimeoutrandbase,
			'extension' => $extension,
			'method' => $method,
			'movetopath' => $movetopath,
			'nickname' => $nickname,
			'search' => $search,
			'sizemax' => $sizemax,
			'sizemin' => $sizemin,
			'status' => $status,
			'type' => $type
		);

		# updating
		if ($id_searches) {
			$insert_update['updated'] = date('Y-m-d H:i:s');
			$insert_update = dbpua($link, $insert_update);
			$sql = '
					UPDATE
						searches
					SET
						'.implode(',', $insert_update).'
					WHERE
						id="'.dbres($link, $id_searches).'"
					';
			cl('SQL: '.$sql, VERBOSE_DEBUG);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link), VERBOSE_ERROR);
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => messages(false)
					)
				)));
			}
		# inserting
		} else {
			$insert_update['created'] = date('Y-m-d H:i:s');
			$insert_update = dbpia($link, $insert_update);
			$sql = '
					INSERT INTO
						searches
					(
						'.implode(',', array_keys($insert_update)).'
					)
					VALUES(
						'.implode(',', $insert_update).'
					)
					';
			cl('SQL: '.$sql, VERBOSE_DEBUG);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link), VERBOSE_ERROR);
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => messages(false)
					)
				)));
			}
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));
	case 'login': # login taken from mediaarchive->inventory

		if (is_logged_in()) {
			header('Location: ./');
		}

		if ($logintype === 'visum') {
			if (!file_exists(dirname(__FILE__).'/class-visum.php')) {
				# user will see this when returning from visum, therefore no json here
				die('Local Visum support is not available.');
			}
			# visum login begin
			if (!$ticket) {
				die('Missing'.' ticket.');
			}
			$method='http';
			if ($method === 'http') {
				# this is what is needed to get Visum login over HTTP
				require_once('class-visum.php');
				$visum = new Visum();
				# var_dump($visum->getUserByTicket($ticket));
			} else if ($method === 'direct') {
				# this is what is needed to get Visum login directly
				#define('DATABASE_NAME', 'visum'); # just because base wants this
				#require_once('base.php'); # needed because of connection functions and such
				# require_once('../include/functions.php'); # visum functionality used for direct communication
				require_once('class-visum.php'); # visum client class
				#file_get_contents('class-visum.php');
				#$link = get_database_connection();
				# mysql_set_charset('utf8', $link);
				$visum = new Visum(VISUM_METHOD_DIRECT, $link);
			}

			try {
				$visum_user = $visum->getUserByTicket($ticket);
			} catch(VisumException $e) {
				$t = $e->getResponseArray();
				die('Error'.': '.$t['error']);
			} catch(Exception $e) {
				die('Error'.':'.$e->getMessage());
			}

			if (!isset($visum_user['id_users'])) {
				die('Missing user id in Visum response.');
			}
			$id_visum = $visum_user['id_users'];

			# update local credentials with what we got from visum
			$iu = array();
			# scan visum response for credentials to update
			foreach (array('gender','nickname','birth') as $k => $v) {
				if (!isset($visum_user[$v])) continue;
				# put it into the update array
				$iu[$v] = $visum_user[$v];
			}

			# was there anything to update supplied?
			if (count($iu) > 0) {
				$iu['updated'] = date('Y-m-d H:i:s');
				$iu = dbpua($link, $iu);
				$sql = '
					UPDATE
						users
					SET
						'.implode(',',$iu).'
					WHERE
						id_visum="'.dbres($link, $id_visum).'"
					';
				$r = db_query($link, $sql);
			}

			# try to find the user, did it exist in local db?
			$sql = '
				SELECT
					*
				FROM
					users
				WHERE
					id_visum="'.dbres($link, $id_visum).'"
				';
			$r = db_query($link, $sql);

			# mysql_result_as_array($result, $users);
			if (count($r) < 1) {
				die('No such user found in local database.');
			}
			$user = reset($r);

			# this means user is logged in
			$_SESSION[SITE_SHORTNAME]['user'] = $user;

			# now we have a visum user id to match against our own database and then create a login, that's all that is needed

			header('Location: ./');
			# visum login end
		} else if ($logintype='local') {
			# try to find the user, did it exist in local db?
			$sql = '
				SELECT
					*
				FROM
					users
				WHERE
					username="'.dbres($link, $username).'" AND
					password=ENCRYPT("'.dbres($link, $password).'", "'.dbres($link, $password_salt).'")
				';
			$r = db_query($link, $sql);

			if (count($r) < 1) {
				die($format === 'json' ? json_encode(array(
					'status' => 'error',
					'data' => array(
					'message' => 'No such user found in local database.'
					)
				)) : 'No such user found in local database.'
				);
			}
			$user = reset($r);

			# this means user is logged in
			$_SESSION[SITE_SHORTNAME]['user'] = $user;
			if ($format === "json") {
				die(json_encode(array(
					'status' => 'ok',
					'data' => array()
				)));
			} else {
				header('Location: ./');
			}
			die();
		}

		break;

	case 'logout':
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'ok',
				'data' => array()
			)));
		}
		$_SESSION[SITE_SHORTNAME]['user'] = false;
		unset($_SESSION[SITE_SHORTNAME]['user']);
		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));

	case 'quickfind': # JSON - to request an eMule find directly

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}

		$output = array('status' => 'ok', 'data' => array('searchresultlist' => array()));

		# which client to ask?
		$tmp = false;
		foreach ($clientpumps as $pumpname => $pump) {
			if ((int)$id_clientpumps === (int)$pump['data']['id'] && (int)$pump['data']['status'] === 1) {
				$tmp = $pumpname;
				break;
			}
		}
		$pumpname = $tmp;

		# no suitable pump found?
		if ($pumpname === false) {
			# then get out
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Pump is not active or found.'
				)
			)));
		}

		# try to run the search
		$r = $clientpumps[$pumpname]['pump']->search($search, array(
			'max'	=> $sizemax,
			'min'	=> $sizemin,
			'type'	=> $type
		));

		# did it fail?
		if ($r === false) {
			cl('Failed requesting search on '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].').');
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		$output['data']['searchresultlist'] = web_check_results($conn, $r, $link, $show_download);

		die(json_encode($output));

	case 'quickfind_download': # JSON - to request a download, based on filehashes

		# make sure user is logged in
		if (!is_logged_in()) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Login required.'
				)
			)));
		}
		# this MUST be recorded in db!

		# which client to ask?
		$tmp = false;
		foreach ($clientpumps as $pumpname => $pump) {
			if ((int)$id_clientpumps === (int)$pump['data']['id'] && (int)$pump['data']['status'] === 1) {
				$tmp = $pumpname;
				break;
			}
		}
		$pumpname = $tmp;

		# no suitable pump found?
		if ($pumpname === false) {
			# then get out
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => 'Pump is not active or found.'
				)
			)));
		}

		if (!$clientpumps[ $pumpname ]['pump'] ->download($id)){
			# did it fail? why?
			cl('Failed requesting download from '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].').', VERBOSE_ERROR);
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => messages(false)
				)
			)));
		}

		die(json_encode(array(
			'status' => 'ok',
			'data' => array()
		)));
	}
?>
