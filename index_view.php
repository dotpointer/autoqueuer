<?php

# view preparations for control panel

# changelog
# 2013-07-17 22:12:05
# 2013-09-27 00:00:00 - correcting for base1, mysql_result_as_array
# 2014-07-12 16:42:21 - updating for v2
# 2014-07-22 00:00:00 - multi-client with clientpumps, search with name+size check
# 2014-07-23 11:35:18- adding pumps view
# 2015-03-10 22:22:09 - updating casting for int limitations
# 2015-06-05 17:37:07 - updating find
# 2015-07-23 00:45:28 - checking mlnet text bugs
# 2015-07-27 00:02:00 - adding ed2khash to find fields
# 2015-08-21 12:05:29 - cleanup
# 2016-02-23 12:51:15 - parameters
# 2016-03-05 21:50:56 - cleanup
# 2016-09-22 22:50:35 - base 2 to base 3
# 2017-09-10 23:45:00 - adding preview
# 2017-09-12 22:07:00 - dropping project name in file
# 2017-09-13 01:41:00 - adding cancel
# 2017-09-19 19:25:00 - editing message handling

# make sure there is something above this file
if (!isset($view)) exit;

switch ($view) {
	case 'preview':
		if (!is_numeric($id_clientpumps) || !strlen($filehash)) {
			cl('Missing parameters id_clientpumps or filehash.', VERBOSE_ERROR);
			fwrite(STDERR, messages(true));
			die(1);
		}

		$filehash = preg_replace("/[^a-zA-Z0-9]+/i", "", $filehash);;
		$id_clientpumps = (int)$id_clientpumps;
		$previewfile = PREVIEW_DIR.$id_clientpumps.'/'.$filehash.'.preview.jpg';

		if (!file_exists($previewfile)) {
			cl('File does not exist. Make sure subfolder below the pump id folders is readable by web server.', VERBOSE_ERROR);
			fwrite(STDERR, messages(true));
			die(1);
		}

		header('Content-Disposition: inline; filename='.$filehash.'.preview.jpg');
		header('Content-Type: image/jpeg');
		header('Content-Length: '.filesize($previewfile));
		readfile($previewfile);
		die();
}

# is format json?
if ($format === 'json') {

	# output json header
	header('Content-Type: application/json');

	# find out what to view if any
	switch ($view) {

		case 'clientpumps': # list of client pumps
			$output = array(
				'status' => 'ok',
				'data' => array()
			);

			# get all clientpumps
			$sql = 'SELECT
							*
					FROM
						clientpumps';
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

			foreach ($result as $k => $v) {
				unset($result[$k]['password']);
			}

			$output['data']['clientpumps'] = $result;

			# cast
			foreach ($output['data']['clientpumps'] as $key => $row) {
				$output['data']['clientpumps'][$key] = caster(
					$output['data']['clientpumps'][$key],
					array(
						'status',
						'id',
						'queuedfiles',
						'searches'
					)
				);
			}

			die(json_encode($output));

		case 'dumped': # list of dumped files
			$output = array(
						'status' => 'ok',
						'data' => array()
					);

			$sql = 'SELECT
						files.id,
						files.name,
						files.created
					FROM
						files
					WHERE
						files.id_collections=-2
					ORDER BY created DESC';
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

			# $output['data']['files_dumped'] = array();
			$output['data']['files_dumped'] = $result;

			# cast
			foreach ($output['data']['files_dumped'] as $key => $row) {
				$output['data']['files_dumped'][$key] = caster($output['data']['files_dumped'][$key], array('id'));
			}

			die(json_encode($output));

		case 'find': # to get results from db-findbox

			$output = array(
				'status' => 'ok',
				'data' => array(
					'searchresultlist' => array()
				)
			);

			# searches
			$sql = 'SELECT
						*
					FROM
						files
					 WHERE
					 	name LIKE "%'.dbres($link, $find).'%" OR
					 	ed2khash="'.dbres($link, $find).'"
					 ORDER BY
					 	created
					 DESC';
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

			$output['data']['findresult'] = $result;

			die(json_encode($output));

		case 'latest_queued': # to get latest queued files
			$output = array(
				'status' => 'ok',
				'data' => array()
			);

			# get latest queued files
			$sql = 'SELECT
						files.name,
						files.id_searches,
						files.created
					FROM
						files
					WHERE
						NOT files.id_searches = 0
					ORDER BY
						created DESC
					LIMIT 30';
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

			$output['data']['files_queued'] = $result;

			# cast
			foreach ($output['data']['files_queued'] as $key => $row) {
				$output['data']['files_queued'][$key] = caster(
					$output['data']['files_queued'][$key],
					array('id_searches')
				);
			}

			# searches
			$sql = 'SELECT
						id,
						search
					FROM
						searches
					 WHERE status > '.SEARCHES_STATUS_DELETED;
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
			$output['data']['searches'] = $result;

			# cast data
			foreach ($output['data']['searches'] as $key => $row) {
				$output['data']['searches'][$key] = caster(
					$output['data']['searches'][$key],
					array('id')
				);
			}

			# stats for graph
			$sql = '
				SELECT
					count(files.id) AS files_queued,
					searches.search
				FROM
					files,
					searches
				WHERE
					files.id_searches=searches.id AND NOT files.id_searches=0
				GROUP BY
					searches.id ORDER BY
				files.created DESC
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

			$output['data']['files_queued_stats'] = $result;

			# chart pie colors
			$colors = array(
				'#013a17',
				'#046e57',
				'#16777e',
				'#458e87',
				'#6fb6cc',
				'#49738b',
				'#59636f',
				'#2a7cb6',
				'#194d75',
				'#04326d'
			);

			# prepare it for highcharts
			foreach ($output['data']['files_queued_stats'] as $k => $v) {
				$output['data']['files_queued_stats'][$k] = array(
					'name' => $v['search'],
					'y' => (int)$v['files_queued'],
					'color' => array_key_exists($k, $colors) ? $colors[$k] : '#ffffff'
				);
			}

			die(json_encode($output));

		case 'log': # to get logmessages
			$output = array(
				'status' => 'ok',
				'data' => array()
			);

			# get logmessages
			$sql = 'SELECT
						type,
						data,
						created
					FROM
						logmessages
					ORDER BY
						id DESC, created DESC
					LIMIT 30';
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

			$output['data']['logmessages'] = $result;

			# cast
			foreach ($output['data']['logmessages'] as $key => $row) {
				$output['data']['logmessages'][$key] = caster(
					$output['data']['logmessages'][$key],
					array('id')
				);
			}
			die(json_encode($output));

		case 'parameters': # list of parameters
			$output = array(
				'status' => 'ok',
				'data' => array(
					'parameters' => get_parameters($link)
				)
			);

			die(json_encode($output));

		case 'quickfind_results': # to get results from quick find

			$output = array(
				'status' => 'ok',
				'data' => array(
					'searchresultlist' => array()
				)
			);

			$r = array();
			# walk client pumps
			foreach ($clientpumps as $pumpname => $pump) {

				# skip inactive pumps
				if (!(int)$pump['data']['status']) continue;

				# skip pumps that we are not interested in, if a pump id has been sent in
				if ($id_clientpumps !== false && (int)$id_clientpumps !== (int)$pump['data']['id']) {
					continue;
				}

				# request pump data
				$rtmp = $pump['pump']->results();

				# did it fail?
				if ($rtmp === false) {
					cl('Failed fetching data from pump '.$pumpname.' (#'.$pump['data']['id'].').', VERBOSE_ERROR);
					die(json_encode(array(
						'status' => 'error',
						'data' => array(
							'message' => messages(false)
						)
					)));
				}

				# enrichen data
				foreach ($rtmp as $k => $v) {
					$rtmp[$k]['pumpname'] = $pumpname;
					$rtmp[$k]['id_clientpumps'] = (int)$pump['data']['id'];
				}

				# merge together
				$r = array_merge($r, $rtmp);
			}

			$output['data']['searchresultlist'] = web_check_results($conn, $r, $link, $show_download);

			die(json_encode($output));

		case 'searches': # to get list of searches

			$output = array(
				'status' => 'ok',
				'data' => array()
			);

			# get stats
			$sql = 'SELECT
						collections.host,
						collections.hostpath,
						collections.rootpath,
						COUNT(files.id) AS fileamount
					FROM
						files,
						collections
					WHERE
						files.id_collections = collections.id
					GROUP BY
						files.id_collections';
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
			$output['data']['stats'] = $result;

			# get searches
			$sql = 'SELECT
						*
					FROM
						searches
					WHERE
						status > '.SEARCHES_STATUS_DELETED.'
					ORDER BY status DESC, search ASC
					'
					;
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
			$output['data']['searches'] = $result;

			# cast searches
			foreach ($output['data']['searches'] as $key => $row) {
				$output['data']['searches'][$key] = caster(
					$output['data']['searches'][$key],
					array(
						'executions',
						'executiontimeout',
						'executiontimeoutbase',
						'executiontimeoutrandbase',
						'id',
						'queuedfiles',
						'resultscans',
						'status'
					),
					array(
						'sizemin',
						'sizemax'
					)
				);
			}

			# cast stats
			foreach ($output['data']['stats'] as $key => $row) {
				$output['data']['stats'][$key] = caster(
					$output['data']['stats'][$key],
					array(
						'fileamount'
					)
				);
			}

			die(json_encode($output));

		case 'transfers': # to get transfer list

			$output = array(
				'status' => 'ok',
				'data' => array()
			);

			$r = array();
			# walk client pumps
			foreach ($clientpumps as $pumpname => $pump) {

				# skip inactive pumps
				if (!(int)$pump['data']['status']) continue;

				# request pump data
				$rtmp = $pump['pump']->transfers();

				# did it fail?
				if ($rtmp === false) {
					cl('Failed fetching data from pump '.$pumpname.' (#'.$pump['data']['id'].').', VERBOSE_ERROR);
					cl(db_error($link), VERBOSE_ERROR);
					die(json_encode(array(
						'status' => 'error',
						'data' => array(
							'message' => messages(false)
						)
					)));
				}

				$actions = array();

				if (method_exists($pump['pump'], 'cancel')) {
					$actions[] = 'cancel';
				}

				# enrichen data with preview information
				foreach ($rtmp as $k => $v) {
					$rtmp[$k]['id_clientpumps'] = (int)$pump['data']['id'];
					$rtmp[$k]['pumpname'] = $pumpname;
					if (isset($rtmp[$k]['ed2k'])) {
						$rtmp[$k]['preview'] = file_exists(PREVIEW_DIR.(int)$pump['data']['id'].'/'.$rtmp[$k]['ed2k'].'.preview.jpg');
					} else {
						$rtmp[$k]['preview'] = false;
					}
					$rtmp[$k]['actions'] = $actions;
				}

				# merge together
				$r = array_merge($r, $rtmp);
			}

			$output['data'] = $r;

			die(json_encode($output));
	/*
		case 'transfer_compare': # to get transfer list
		case 'transfers_compare':

			$output = array('status' => 'ok', 'data' => array());

			# downloading the file list takes too much time
			set_time_limit(0);

			# get emule connection
			$conn = curl_get_connection();
			if (!is_array($conn)) break;

			# fetch search results from eMule
			$r = curl_do($conn['c'], array(CURLOPT_URL => EMULEWEBURL.'?'.http_build_query(array(
				'ses' => $conn['ses'],
				'w' => 'transfer',
				'sort' => 1,
				'sortAsc' => 0
			))));

			$r = parse_response($r); # convert to XML
			if ($r['status'] != 'ok') {
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => 'Fetch search result request failed.'
					)
				)));
			}

			$transfers = isset($r['filelist']['file']) ? $r['filelist']['file'] : array();

			# walk files in transfer
			foreach ($transfers as $transferfile) {

				# echo $transferfile['fname']."\n";
				$name = strrpos($transferfile['fname'], '.') !== false ? substr($transferfile['fname'], 0, strrpos($transferfile['fname'], '.') + 1) : $transferfile['fname'];

				# find similar files not in download
				$sql = 'SELECT
							*
						FROM
							files
						 WHERE
						 		name LIKE "'.dbres($link, $name).'%"
						 	AND
						 		LENGTH(name) >= LENGTH("'.dbres($link, $transferfile['fname']).'") - 1
					 		AND
						 		LENGTH(name) <= LENGTH("'.dbres($link, $transferfile['fname']).'") + 1
						 	AND
						 		size >= '.(float)dbres($link, $transferfile['fsize']).'
						 	AND
						 		id_collections != "'.dbres($link, FILES_ID_COLLECTIONS_DOWNLOAD).'"
					';
				cl('SQL: '.$sql, VERBOSE_DEBUG);
				$result = db_query($link, $sql);
				if ($result === false) die(json_encode(array('status' => 'error', 'data' => array('message' => db_error($link)))));
				if (count($result)) {
					echo 'Possible double: '.$transferfile['fname'].' '.$transferfile['fsize']."\n";
					foreach ($result as $dbfile) {
						echo '- '.$dbfile['name'];
						echo ' '.$dbfile['size'];
						echo ' '.((int)$dbfile['existing']===1 ? ' EXISTERAR ' : 'BORTA');
						echo ' '.((int)$dbfile['id_collections'] ===FILES_ID_COLLECTIONS_DUMPED ? ' DUMPAD ' : $dbfile['id_collections']);
						echo "\n";
					}
				}
			}

			die (json_encode($output));
			*/
	}

	die();
}
?>
