#!/usr/bin/php
<?php
/*
	worker file - indexes resources - local and samba, searches via emule web interface and requests downloads

	# changelog
	2011-07-04			- eMule Kad reconnector, tries to reconnect Kad on host when needed
	2011-07-07			- improvements, store session
	2011-07-12			- Converting syntax to XML
	2011-08-09			-
	2011-08-10			- id_searches, random timeout, status, started using
	2012-06-21			- reading code, trying to cleanup, move rules added
	2012-09-22			- small bugfixes
	2012-11-05			- adding marking possibility of dumped files and deletion possibility
	2013-07-01			- trying to improve
	2013-07-02 02:19:05
	2013-09-25 21:15:58	- PHP5.5, correcting for mysqli, base1
	2013-09-27 01:31:30	- correcting for base1, mysql_result_as_array
	2013-10-18 23:27	- separating options, stopping search action from moving and marking files
	2013-10-22			- adding pingtest
	2014-04-02			- trying to shut up curl error 28 operation timed out
	2014-07-12 17:03:08 - v2, heavy update with indexing from mediaarchive, mounting from transfer, base2 replaces base
	2014-07-22 			- multi-client with clientpumps, search with name+size check
    2015-03-10 22:59:48 - replacing filesize and ed2khash with _custom editions for 32-bit 2GB PHP file size limit
	2015-07-24 23:27:00 - textual fixes
	2015-07-27 18:40:14 - updating indexer, adding fakecheck and more detailed debug messages and deep debug level for SQL and execs
	2015-07-28 00:49:48 - bugfix for indexer where size was not updated on hash update
	2015-08-18 00:10:42 - adding log messages for discovery, download and relocations
	2015-08-21 11:47:39 - cleanup
	2015-08-21 21:52:00 - adding file and line to errors
	2016-03-05 22:24:02 - cleanup
	2016-09-22 23:04:19 - base 2 to base 3
	2016-11-09 11:14:27 - bugfix, base 2 to base 3
	2017-09-10 23:46:00 - preview added
	2017-09-12 21:53:00 - dropping project name in file
	2017-09-19 19:25:00 - editing message handling
	2017-09-19 22:31:00 - using stderr for diagnostic output
	2017-09-21 00:21:00 - separating unfinished files listing and preview generation
	2017-09-22 23:49:00 - cleanup
	2017-09-28 23:14:00 - adding fake check command

	command search - searches and trigger downloads, could be put in a cronjob every 3:th, 6:th hour or so
	command download - checks result and trigger downloads - could be put about 5 min after the search has been triggered
	command index - indexes file collections

	developed for eMule xTreme 8.0
	developed for mldonkey-server
*/

require_once('include/functions.php');

$dryrun = false;
$force = false;
$unmount = true;

# get arguments
$arguments = getopt("c:dfhj:pPuv::", array(
	'download::',
	'dryrun',
	'dry-run',
	'fakecheck:',
	'find',
	'force',
	'help',
	'index::',
	'list::',
	'move',
	'preview',
	'previewscan',
	'results',
	'scan::',
	'search::',
	'test',
	'verbose::'
));

# walk arguments to set modes
foreach ($arguments as $k => $v) {

	# what argument is this?
	switch ($k) {
		case 'v': # be verbose
		case 'verbose':
			# determine and set level of verbosity
			switch ($v) {
				default:
					$verbose = VERBOSE_ERROR;
					break;
				case 'v':
					$verbose = VERBOSE_INFO;
					cl('Verbosity level set to info', VERBOSE_INFO, false);
					break;
				case 'vv':
					$verbose = VERBOSE_DEBUG;
					cl('Verbosity level set to debug', VERBOSE_INFO, false);
					break;
				case 'vvv':
					$verbose = VERBOSE_DEBUG_DEEP;
					cl('Verbosity level set to debug deep', VERBOSE_INFO, false);
					break;
			}

			break;
		case 'd': # only dry-run (do not run command)
		case 'dryrun':
		case 'dry-run':
			$dryrun = true;
			cl('Dryrun mode activated', VERBOSE_DEBUG);
			break;

		case 'f': # force - do not care about intervals and such
		case 'force':
			$force = true;
			cl('Force mode activated', VERBOSE_DEBUG);
			break;
		case 'u': # avoid unmounting
			cl('Avoiding unmount mode activated', VERBOSE_DEBUG);
			$unmount = false;
			break;
	}
}

# check if this is running, if process id
exec('ps a|grep -v grep|grep "'.basename(__FILE__).'"|grep -v \'^ *'.getmypid().'\'', $output, $retval);
if (count($output)) {
	cl('Already running: '.var_export($output, true), VERBOSE_ERROR);
	fwrite(STDERR, messages(true));
	die(1);
}

# make sure the mounting root path exists - here we mount all samba shares and so on
if (!file_exists(MOUNT_ROOTPATH) || !is_dir(MOUNT_ROOTPATH)) {
	cl('Mounting root path ('.MOUNT_ROOTPATH.') does not exist'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
	fwrite(STDERR, messages(true));
	die(1);
}

# walk arguments again
foreach ($arguments as $k => $v) {

	# what argument is this?
	switch ($k) {

		# --- download scan, check for files in search results to download ---
		case 'download':
		case 'scan':

			cl('Action: scan results for downloads', VERBOSE_DEBUG);
			# scan existing response for results

			$id_searches = $v;

			scan_results_for_downloads($conn['c'], $link, $conn['ses'], $blacklist, $id_searches, $force);
			break;
		case 'fakecheck':
			$result = fakecheck_file($v);
			cl('Fake checked "'.$v.'", result: '.($result ? 'ok' : 'fake or incomplete'), VERBOSE_INFO);
			fwrite(STDERR, messages(true));
			break;
		# --- find, to run scheduled searches ---
		case 'find':
		case 'search':
			cl('Action: run scheduled search', VERBOSE_DEBUG);

			$id_searches = $v;

			# first scan any existing result for results
			scan_results_for_downloads($conn['c'], $link, $conn['ses'], $blacklist, $id_searches, $force);

			# get searches and order by how long ago the search has been done, plus timeout
			if ($id_searches === false) {
				$sql = 'SELECT
							*
						FROM
							searches
						WHERE
							status='.SEARCHES_STATUS_ACTIVE.'
						ORDER BY (UNIX_TIMESTAMP(executed) + executiontimeout)
						';
			# or if id is specified
			} else {
				$sql = 'SELECT
							*
						FROM
							searches
						WHERE
							id = "'.dbres($link, $id_searches).'"
						';
			}
			$searches = db_query($link, $sql);
			if ($searches === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			if (!count($searches)) {
				cl('No searches found'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			# check that the timeout has passed
			if (!$force && strtotime($searches[0]['executed']) + (int)$searches[0]['executiontimeout'] > time()) {
				cl('No suitable searches found', VERBOSE_DEBUG);
				fwrite(STDERR, messages(true));
				die();
			}
			cl('Found search to run: "'.$searches[0]['search'].'"', VERBOSE_DEBUG);

			# is there any demands which client this search must run on?
			if ((int)$searches[0]['id_clientpumps'] === 0) {
				# find a suitable client to run the search on based on when it was searched last
				$sql = 'SELECT * from clientpumps WHERE status=1 ORDER BY searched ASC LIMIT 1';
			} else {
				$sql = 'SELECT * from clientpumps WHERE status=1 AND id="'.(int)dbres($link, $searches[0]['id_clientpumps']).'" LIMIT 1';
			}
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			};

			# no one found?
			if(!count($result)) {
				cl('No suitable active client pumps found.'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			# construct a matching pump name
			$pumpname = $result[0]['type'].'@'.$result[0]['host'];

			# make sure we have a client pump
			if (!isset($clientpumps[$pumpname])) {
				cl('Could not find '.$pumpname.' in list of client pumps.'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			# update the timeout and the time when the search was executed and reset the resultscans counter
			$executiontimeout = (int)$searches[0]['executiontimeoutbase'] + rand(0, (int)$searches[0]['executiontimeoutrandbase']);
			$sql = 'UPDATE
						searches
					SET
						executed="'.date('Y-m-d H:i:s').'",
						executions=executions+1,
						executiontimeout='.$executiontimeout.',
						resultscans=0
					WHERE id="'.$searches[0]['id'].'"
					';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			};

			# this is important
			cl('Running search "'.$searches[0]['search'].'" (#'.$searches[0]['id'].') on '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].')', VERBOSE_INFO);

			# try to run the search
			$r = $clientpumps[$pumpname]['pump']->search(
				$searches[0]['search'],
				array(
					# 'avail' 	=> '',
					# 'ext' 		=> $searches[0]['extension'],
					'max' 		=> $searches[0]['sizemax'],
					# 'method' 	=> $searches[0]['method'], # server, global, kademlia
					'min' 		=> $searches[0]['sizemin'],
					# 'ses' 		=> $conn['ses'],
					# 'tosearch' 	=> $searches[0]['search'],
					'type' 		=> $searches[0]['type'], #empty=all,
					# 'unicode' 	=> 'on',
					# 'w' 		=> 'search'
				)
			);

			if ($r === false) {
				cl('Failed requesting search on '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].', '.__FILE__.':'.__LINE__.').', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			# update client pump stats
			$sql = 'UPDATE clientpumps SET searched="'.date('Y-m-d H:i:s').'", searches=searches+1 WHERE id="'.$clientpumps[ $pumpname ]['data']['id'].'"';
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			};

			# scan existing response for results
			scan_results_for_downloads($conn['c'], $link, $conn['ses'], $blacklist);

			break;

		# --- index collections ---
		case 'index':

			cl('Action: run indexer', VERBOSE_DEBUG);

			logmessage($link, LOGMESSAGE_TYPE_INDEXING_BEGIN);

			$id_collections = $v;

			# get all enabled collections
			if ($id_collections !== false) {
				$sql = 'SELECT * FROM collections WHERE id="'.dbres($link, $id_collections).'"';
			} else {
				$sql = 'SELECT * FROM collections WHERE enabled=1';
			}
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$collections = db_query($link, $sql);
			if ($collections === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			if (count($collections) < 1) {
				cl('Could not find any collections in database'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				fwrite(STDERR, messages(true));
				die(1);
			}

			# stop limit
			set_time_limit(0);

			# walk the endings and prepare find items
			$file_endings = array();
			foreach ($indexable_file_endings as $ending) {
				$file_endings[] =  '"*.'.strtoupper($ending).'"';
			}

			$rootpath = false;

			# walk collections
			foreach ($collections as $collection) {

				cl('Working on collection: '.$collection['name'], VERBOSE_DEBUG);

				cl('Checking url: '.$collection['url'], VERBOSE_DEBUG);

				# as we don't want to do any unneccessary things we first check for basic availability here first

				# parse the URL from where to get the files
				if (!($collection['url'] = parse_url($collection['url']))) {
					cl('Malformed URL format of destination '.$collection['url'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					continue 2;
				}

				# find out what type of source this is
				switch (strtolower($collection['url']['scheme'])) {
					# samba connection
					case 'smb':

						# job rootpath - eg /mnt/project/share
						if (!($rootpath = make_dir(MOUNT_ROOTPATH.$collection['name']))) {
							cl('Path '.MOUNT_ROOTPATH.$collection['name'].' could not be created, or is not a directory'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
							continue 3;
						}

						# are there options for this supplied?
						$options = isset($collection['mountoptions']) ? $collection['mountoptions'] : array();

						# try to mount
						if (!($collection['fullpath'] = mountcifs($collection['url'], $rootpath, $options))) {
							cl('Could not mount '.$collection['name'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
							continue 3;
						}

						$rootpath = $collection['fullpath'];

						# mark this as mounted so we may unmount it later on
						$collection['mountpath'] = substr($rootpath, -1) !== '/' ? $rootpath.'/' : $rootpath; # this is the base mountpath
						$collection['mounted'] = true;

						break;
					# local path
					case 'file':
						$collection['fullpath'] = substr($collection['url']['path'], -1) !== '/' ? $collection['url']['path'].'/' : $collection['url']['path'];
						if (!is_dir($collection['fullpath'])) {
							cl('Local path '.$collection['fullpath'].' not found'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
							continue 3;
						}

						$rootpath = $collection['fullpath'];
						break;
					default:
						cl('Unknown scheme: '.strtolower($collection['url']['scheme']).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						continue 3;
				}

				if (!file_exists($rootpath) || !is_dir($rootpath)) {
					cl('Bad mount path root dir: '.$rootpath.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					fwrite(STDERR, messages(true));
					die(1);
				}

				cl('Indexing files in '.$rootpath, VERBOSE_DEBUG);

				# find-indexing code from media archive, mounting code from transfer, some indexing parts from kreosot

				# set working dir to the root dir
				chdir($rootpath);

				# get a list of files
				# $cmd = 'find '.escapeshellarg($rootpath).' -type f \( -iname "*.JPG" -o -iname "*.JPEG" -o -iname "*.TIF" -o -iname "*.TIFF" \)';
				$cmd = 'find '.escapeshellarg($rootpath).' -type f \( -iname '.implode(' -o -iname ', $file_endings).' \)';
				cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);

				$files = shell_exec($cmd);
				if ($files === false) {
					cl('Could not do find'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				}

				$files = explode("\n", $files);

				if (!$dryrun) {
					# reset verified column in db
					#if ($id_collections !== false) {
					#	$sql = 'UPDATE files SET verified=0 WHERE id_collections < 0 OR id_collections="'.dbres($link, $id_collections).'"';
					#} else {
						$sql = 'UPDATE files SET verified=0';
					#}
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}
				}

				# simple stats collector
				$stats = array(
					'inserted' => 0,
					'nonexistant' => 0,
					'updated' => 0,
					'verified' => 0,
					'started' => time()
				);

				$total = count($files);
				cl('Files found: '.$total, VERBOSE_DEBUG);

				# walk files found
				foreach ($files as $k => $file) {

					# cut out mount path
					$file = substr($file, strlen($rootpath), strlen($file));

					# extract basename and path
					$basename = basename($file);
					$path = substr($file, 0, strrpos($file, '/') + 1);

					# intro text
					$intro = ($k > 0 ? round(($k/$total)*100) : 0).'% ('.($k+1).'/'.$total.'): ';

					# print info
					cl($intro.'File name: '.$file, VERBOSE_DEBUG);

					# TODO: how to deal with nonexistant files?
					if (!file_exists($file) || filesize_custom($file) < 1) {
					 	cl($intro.'Nonexistant / 0-size', VERBOSE_DEBUG);
							$stats['nonexistant']++;
							continue;
					}

					cl($intro.'Trying to identify name, path and size', VERBOSE_DEBUG);

					$filesize = filesize_custom($file);

					# try to get this file based on path+name
					$sql = 'SELECT
								*
							FROM
								files
							WHERE
								name="'.dbres($link, $basename).'" AND
								path="'.dbres($link, $path).'" AND
								size='.$filesize.' AND
								verified=0
							LIMIT 1';
					cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}

					# file found
					if (count($r)) {

						cl($intro.'Identified by name, path and size', VERBOSE_DEBUG);

						# get current fakecheck value
						$fakecheck = (int)$r[0]['fakecheck'];
						# has this file not been fake checked?
						if ($fakecheck === 0) {
							# run fake check
							$fakecheck = fakecheck_file($file);
							cl($intro.'Fake check '.($fakecheck > 0 ? 'PASSED' : 'FAILED'), VERBOSE_DEBUG);
						}

						cl($intro.'Updating meta data, '.($stats['verified']+1).' verified, '.($stats['updated']).' updated, '.($stats['inserted']).' inserted', VERBOSE_DEBUG);

						if (!$dryrun) {

							# does it exist according to db?
							if ((int)$r[0]['existing']) {
								# set it as verfied and fakechecked
								$sql = 'UPDATE
											files
										SET
											verified=1,
											fakecheck='.$fakecheck.'
										WHERE
											id="'.dbres($link, $r[0]['id']).'"
										';
							# or does it not exist already according to db?
							} else {
								# set it as verified, also update existence and fakecheck
								$sql = 'UPDATE
											files
										SET
											existing=1,
											fakecheck='.$fakecheck.',
											updated="'.date('Y-m-d H:i:s').'",
											verified=1
										WHERE
											id="'.dbres($link, $r[0]['id']).'"
										';
							}
							cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);

							$r = db_query($link, $sql);
							if ($r === false) {
								cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
								fwrite(STDERR, messages(true));
								die(1);
							}
						}
						$stats['verified']++;
						# go next file
						continue;
					}
					cl($intro.'Identification by name, path and size failed', VERBOSE_DEBUG);

					cl($intro.'Trying to identify by name, root path and size', VERBOSE_DEBUG);
					# try to get this file based on rootpath+path+name+size
					$sql = 'SELECT
								*
							FROM
								files
							WHERE
								name="'.dbres($link, $basename).'" AND
								path="'.dbres($link, $rootpath.$path).'" AND
								size='.$filesize.'  AND
								verified=0
							LIMIT 1
							';
					cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}

					# file found
					if (count($r)) {

						cl($intro.'Identified by root path, name and size', VERBOSE_DEBUG);

						# get current fakecheck value
						$fakecheck = (int)$r[0]['fakecheck'];
						# has this file not been fake checked?
						if ($fakecheck === 0) {
							# run fake check
							$fakecheck = fakecheck_file($file);
							cl($intro.'Fake check '.($fakecheck > 0 ? 'PASSED' : 'FAILED'), VERBOSE_DEBUG);
						}

						cl($intro.'Updating meta data, '.($stats['verified']+1).' verified, '.($stats['updated']).' updated, '.($stats['inserted']).' inserted', VERBOSE_DEBUG);

						if (!$dryrun) {
							# set it as verfied
							cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);
							$sql = 'UPDATE
										files
									SET
										existing=1,
										fakecheck='.$fakecheck.',
										path="'.dbres($link, $path).'",
										updated="'.date('Y-m-d H:i:s').'",
										verified=1
									WHERE id="'.dbres($link, $r[0]['id']).'"
									';
							$r = db_query($link, $sql);
							if ($r === false) {
								cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
								fwrite(STDERR, messages(true));
								die(1);
							}
						}
						$stats['verified']++;
						# go next file
						continue;
					}
					cl($intro.'Identification by name, root path and size failed', VERBOSE_DEBUG);

					cl($intro.'Trying to identify by ed2k hash', VERBOSE_DEBUG);

					cl($intro.'Calculating ed2k hash please standby', VERBOSE_DEBUG);

					# compute ed2khash
					$hash = ed2khash_custom($file);
					if (!$hash) {
						cl('Failed ed2k hashing: '.$file.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						continue;
					}

					cl($intro.'ed2k hash: '.$hash, VERBOSE_DEBUG);

					# try to get by ed2khash
					$sql = 'SELECT
								*
							FROM
								files
							WHERE
								ed2khash="'.dbres($link, $hash).'" AND
								verified=0
							LIMIT 1';
					cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}

					$found = count($r);
					# file found by hash
					if ($found) {
						cl($intro.'Identified by ed2khash', VERBOSE_DEBUG);

						# get current fakecheck value
						$fakecheck = (int)$r[0]['fakecheck'];
						# has this file not been fake checked?
						if ($fakecheck === 0) {
							# run fake check
							$fakecheck = fakecheck_file($file);
							cl($intro.'Fake check '.($fakecheck > 0 ? 'PASSED' : 'FAILED'), VERBOSE_DEBUG);
						}

						cl($intro.'Updating meta data, '.($stats['verified']).' verified, '.($stats['updated']+1).' updated, '.($stats['inserted']).' inserted', VERBOSE_DEBUG);

						if (!$dryrun) {
							# set it as verfied
							$sql = 'UPDATE
										files
									SET
										existing=1,
										fakecheck='.$fakecheck.',
										name="'.dbres($link, $basename).'",
										path="'.dbres($link, $path).'",
										size="'.$filesize.'",
										updated="'.date('Y-m-d H:i:s').'",
										verified=1
									WHERE id="'.dbres($link, $r[0]['id']).'"
									';
							$rtmp = db_query($link, $sql);
							if ($rtmp === false) {
								cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
								fwrite(STDERR, messages(true));
								die(1);
							}

							# if there is a move then make logmessage about it
							if ($r[0]['path'].$r[0]['name'] !== $path.$basename) {
								logmessage(
									$link,
									LOGMESSAGE_TYPE_RELOCATION,
									array(
										'from' => $r[0]['path'].$r[0]['name'],
										'to' => $path.$basename
									),
									false,
									(int)$r[0]['id']
								);
							}
						}
						$stats['updated']++;

						# cl($stats['updated'].' - Updating', VERBOSE_DEBUG);

						# go next file
						continue;
					}
					cl($intro.'Identification by ed2k hash failed', VERBOSE_DEBUG);

					cl($intro.'New file, preparing insert', VERBOSE_DEBUG);

					# prepare an insert array
					$ui = dbpia($link, array(
						'created' => date('Y-m-d H:i:s'),
						'ed2khash' => $hash,
						'existing' => 1,
						'id_collections' => $collection['id'],
						'modified' => date('Y-m-d H:i:s', filemtime($path.$basename)),
						'name' => $basename,
						'path' => $path,
						'size' => $filesize,
						'updated' => date('Y-m-d H:i:s'),
						'verified' => 1
					));

					# run fake check
					$fakecheck = fakecheck_file($file);
					cl($intro.'Fake check '.($fakecheck > 0 ? 'PASSED' : 'FAILED'), VERBOSE_DEBUG);
					$ui['fakecheck'] = $fakecheck;

					cl($intro.'Inserting meta data, '.($stats['verified']).' verified, '.($stats['updated']).' updated, '.($stats['inserted']+1).' inserted', VERBOSE_DEBUG);

					if (!$dryrun) {
						# insert it into db
						$sql = 'INSERT INTO files ('.implode(',', array_keys($ui)).') VALUES('.implode(',', $ui).')';
						cl($intro.'SQL: '.$sql, VERBOSE_DEBUG_DEEP);
						$r = db_query($link, $sql);
						if ($r === false) {
							cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
							fwrite(STDERR, messages(true));
							die(1);
						}

						# new file found - make a log message about it
						logmessage(
							$link,
							LOGMESSAGE_TYPE_SOURCE_DISCOVERED,
							array(
								'ed2khash' => $hash,
								'path' => $path.$basename, #$r[0]['path'].$r[0]['name'],
								'size' => $filesize
							),
							false,
							db_insert_id($link)
						);
					}
					$stats['inserted']++;

					# cl($stats['inserted'].' - Inserting', VERBOSE_DEBUG);
				}

				cl($intro.'Updating meta data, setting the rest of the files to nonexistant', VERBOSE_DEBUG);
				if (!$dryrun && $id_collections === false) {
					$sql = 'UPDATE files SET existing=0,updated="'.date('Y-m-d H:i:s').'" WHERE verified=0';
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}
				}

				$stats['duration'] = time() - $stats['started'];
				$stats['collection'] = $collection['name'];

				# cl('Indexed collection "'.$collection['name'].'": '.var_export($stats, true), VERBOSE_INFO);

				logmessage($link, LOGMESSAGE_TYPE_INDEXED_COLLECTION, $stats);

				# change dir to the rootpath, as we cannot stand inside what we are unmounting
				chdir(MOUNT_ROOTPATH);

				# do we have paths to unmount?
				if ($unmount) {
					if (isset($collection['mounted'], $collection['mountpath']) && is_mountpoint($collection['mountpath'])) {
						# try to unmount it
						#if (
						umount($collection['mountpath']);
						#) {
							# can we remove it too?
							# cl('Removing '.$collection['mountpath'], VERBOSE_DEBUG);
							# rmdir($collection['mountpath']);
						# }
					}

					# is the job root a dir?
					# if (is_dir($rootpath)) {
						# then try to remove it
						# cl('Removing '.$rootpath, VERBOSE_DEBUG);
						# rmdir($rootpath);
					# }
				}
			} #while-collections

			logmessage($link, LOGMESSAGE_TYPE_INDEXING_END);

			break;

		# --- list collections printout ---
		case 'list':

			cl('Action: list', VERBOSE_DEBUG);

			# find out what parameter that is supplied
			switch ($v) {
				case 'collections':
					cl('Listing collections', VERBOSE_DEBUG);

					$sql = 'SELECT * FROM collections';
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}

					# walk collections and echo them
					foreach ($r as $v) {
						cl($v['id'].': "'.$v['name'].' - '.$v['url'], VERBOSE_DEBUG);
					}

					break;

				case 'searches':
					cl('Listing searches', VERBOSE_DEBUG);

					$sql = 'SELECT * FROM searches';
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$r = db_query($link, $sql);
					if ($r === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						fwrite(STDERR, messages(true));
						die(1);
					}

					foreach ($r as $v) {
						cl($v['id'].': "'.$v['search'].'", '.$v['status'], VERBOSE_DEBUG);
					}

					break;
				case 'incoming':
					cl('Listing incoming downloaded files', VERBOSE_DEBUG);

					$files = get_filelist();

					foreach ($files as $v) {
						echo $v."\n";
					}

					break;
				default:
					cl('Unknown list or nothing specified to list', VERBOSE_DEBUG);
					fwrite(STDERR, messages(true));
					die(1);
			}

			break;

		# --- move downloaded files ---
		case 'move':

			cl('Action: move downloaded files', VERBOSE_DEBUG);

			move_downloaded_files($link);
			break;

		case 'p':
		case 'preview':
			cl('Action: generate previews', VERBOSE_DEBUG);
			foreach ($clientpumps as $pumpname => $pump) {
				if (method_exists($pump['pump'], 'generatePreviews')) {
					$pump['pump']->generatePreviews();
				}
			}
			break;

		case 'P':
		case 'previewscan':
			cl('Action: scan for previewable files', VERBOSE_DEBUG);
			foreach ($clientpumps as $pumpname => $pump) {
				if (method_exists($pump['pump'], 'previewScanUnfinished')) {
					$pump['pump']->previewScanUnfinished();
				}
			}
			break;

		# --- results printout ---
		case 'results': # get search results
			cl('Action: get search results', VERBOSE_DEBUG);
			foreach ($clientpumps as $pumpname => $pump) {

				# is this pump not active?
				if (!(int)$pump['data']['status']) {
					cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') is inactive, skipping', VERBOSE_DEBUG);
					continue;
				}

				echo $pumpname.':'."\n";
				if ($pump['pump']->results() !== false) {
					foreach ($pump['pump']->results() as $row) {
						echo implode(',', $row)."\n";
					}
				}
			}

			break;
	}
}

# close cURL resource, and free up system resources
if (isset($conn['c']) && $conn['c']) {
	curl_close($conn['c']);
}
?>
