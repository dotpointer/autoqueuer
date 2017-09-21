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

if (!isset($action)) exit;

# find out what action to take if any
switch ($action) {
	case 'delete_clientpump': # delete a clientpump

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

	case 'quickfind': # JSON - to request an eMule find directly

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
