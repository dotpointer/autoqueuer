<?php
	# eMuleXtremeMod Client Pump Class - manages the communication between the project and the client

	# changelog
	# 2013-12-20 00:00:00
	# 2013-12-21 00:00:00 - improving, connecting to kreosot
	# 2013-12-22 00:00:00 - improving again
	# 2014-07-19 18:41:34 - converting it to pump class
	# 2014-07-22 00:00:00 - multi-client with clientpumps, search with name+size check
	# 2014-07-24 16:30:41
	# 2014-09-03 22:44:28 - bugfix kadReconnect missed old connection code
	# 2015-03-10 22:17:51 - making filesize a float instead of an int
	# 2015-08-21 11:31:09 - cleanup
	# 2017-09-10 22:46:00 - adding id
	# 2017-09-12 22:18:00 - dropping project name in file
	# 2017-09-19 19:25:00 - editing message handling

	class eMuleXtremeModPump {

		# where to store session tempfile
		private $c = false;
		private $host = 'localhost';
		private $id = 0;
		private $password = '';
		private $port = 4711;
		private $ses = false;
		private $sessionfile = '/tmp/'.PROJECT_FILENAME.'.session';
		private $url = '';

		# eMule-defined search types
		/*
		$types = array(
			'Arc'	=> 'Archive (.zip .rar .ace...)',
			'Audio'	=> 'Audio (.mp3 .ogg .wav...)',
			'Iso'	=> 'CDImage (.iso .bin .nrg...)',
			'Doc'	=> 'Document (.doc .txt .pdf...)',
			'Image'	=> 'Image (.jpg .gif .png...)',
			'Pro'	=> 'Program (.exe .zip .rar...)',
			'Video'	=> 'Video (.avi .mpg .ogm...)'
		);
		*/

		# eMule-defined search types - shortened, notice that they must have first letter uppercased, key is used
		public	$types = array(
			# 'Arc'	=> 'Archive',
			'Audio'	=> 'Audio',
			'Doc'	=> 'Document',
			'Image'	=> 'Image',
			# 'Iso'	=> 'Disk image',
			'Pro'	=> 'Program',
			'Video'	=> 'Video'
		);

		# eMule-defined server methods, key is used
		public	$methods = array(
			'global'	=> 'Global',
			'server'	=> 'Server',
			'kademlia'	=> 'Kademlia'
		);

		# when object is made
		public function __construct($config=array()) {

			# invalid config - then get out now
			if (!is_array($config)) {
				return false;
			}

			# set defaults
			$this->id			= isset($config['id']) 			!== false	? $config['id']				: $this->id;
			$this->host			= isset($config['host']) 		!== false	? $config['host']			: $this->host;
			$this->password		= isset($config['password']) 	!== false	? $config['password']		: $this->password;
			$this->port			= isset($config['port'])		!== false	? $config['port']			: $this->port;
			$this->sessionfile	= isset($config['sessionfile'])	!== false	? $config['sessionfile']	: $this->sessionfile;
			$this->url			= isset($config['url'])			!== false 	? $config['url']			: 'http://'.$this->host.':'.$this->port.'/';

			# connection should not be made here, as emh creates object during loading

			return true;
		}

		# when object is destroyed
		public function __destruct() {
			# is there a curl connection?
			if ($this->c) {
				# then close it
				curl_close($this->c);
			}
		}

		# as PHP:s built-in array_merge does not do a real merge with overwriting of int-keys, we do it ourself
		private function arrayMergeKeepKeys(/* dynamic */) {
			$result = array();
			foreach (func_get_args() as $arg) {
				if (!is_array($arg)) continue;
				foreach ($arg as $k => $v) {
					$result[$k] = $v;
				}
			}
			return $result;
		}

		# to cancel a download, based on file hash
		public function cancel($filehash) {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			# contact client and make the request
			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array(
				'file' => strtoupper($filehash),
				'op' => 'cancel',
				'ses' => $this->ses,
				'w' => 'transfer'
			))));
			$r = $this->parseResponse($r); # convert to XML
			if (!isset($r['status']) || $r['status'] != 'ok') {
				cl('Download request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}
			return true;
		}

		# to make an eMule cURL connection
		private function connect() {

			# already connected?
			if ($this->c) {
				# no point of being here
				return true;
			}

			# make a new cURL object
			$this->c = curl_init();
			$m = array();

			# try to get session data from cache file
			$this->ses = file_exists($this->sessionfile) && is_readable($this->sessionfile) ? file_get_contents($this->sessionfile) : false;

			# a previous session was found
			if ($this->ses !== false) {
				# try to contact eMule with the session
				cl('Trying previous session id: '.$this->ses, VERBOSE_INFO);

				$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses))));
				$r = $this->parseResponse($r); # convert to XML

				# if it failed, make session invalid
				if (!isset($r['status']) || $r['status'] != 'ok') {
					cl('Login with previous session id failed', VERBOSE_INFO);
					$this->ses = false;
				}
			}

			# no previous session
			if ($this->ses === false) {

				cl('No previous session, trying to login', VERBOSE_INFO);

				# --- try to login
				$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('p' => $this->password, 'w' => 'password'))));
				$r = $this->parseResponse($r); # convert to XML

				if (!isset($r['status']) || $r['status'] != 'ok') {
					cl('Login failed, response was: '.var_export($r, true), VERBOSE_ERROR);
					return false;
				}

				$this->ses = $r['ses'];

				cl('Session code which will be stored: '.$this->ses, VERBOSE_INFO);

				# if (file_existsis_readable($this->sessionfile)) {
				file_put_contents($this->sessionfile, $this->ses);
			}

			# make sure kad is connected
			$this->kadReconnect();

			return true;
		}

		# simple curling function to send and receive data
		private function curl($unused, $opt=array()) {

			# default setup
			$defopt = array(
				CURLOPT_COOKIESESSION	=> true,
				CURLOPT_URL				=> '',
				CURLOPT_RETURNTRANSFER 	=> true,	# return web page
				CURLOPT_HEADER         	=> false,	# don't return headers
				CURLOPT_FOLLOWLOCATION 	=> true,	# follow redirects
				CURLOPT_ENCODING       	=> '',		# handle all encodings
				CURLOPT_USERAGENT      	=> 'idfc',	# who am i
				CURLOPT_AUTOREFERER    	=> true,	# set referer on redirect
				CURLOPT_CONNECTTIMEOUT 	=> 120,		# timeout on connect
				CURLOPT_TIMEOUT        	=> 120,		# timeout on response
				CURLOPT_MAXREDIRS      	=> 10,		# stop after 10 redirects
				CURLOPT_POST            => false,	# i am sending post data
				CURLOPT_SSL_VERIFYHOST	=> 0,		# don't verify ssl
				CURLOPT_SSL_VERIFYPEER	=> false,
				CURLOPT_VERBOSE			=> false,   # set to true to echo headers
			);

			# merge defaults and provided parameters to get the best mix
			$opt = $this->arrayMergeKeepKeys($defopt, $opt);

			curl_setopt_array($this->c, $opt);
			$r = curl_exec($this->c);
			if (curl_errno($this->c)) {
				# could not connect to host?  then get out silently
				# if (curl_errno($this->c) === 7) return false;

				cl('cURL-error: '.curl_error($this->c).' ('.curl_errno($this->c).')', VERBOSE_ERROR);
				return false;
			}
			return $r;
		}

		# to request download
		public function download($filehash) {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			# try to download
			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses, 'w' => 'search', 'downloads' => strtoupper($filehash)))));
			$r = $this->parseResponse($r); # convert to XML
			if (!isset($r['status']) || $r['status'] != 'ok') {
				cl('Download request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}

			return true;
		}

		# to extract ed2klist link to an array
		public function fileToArray($file) {

			$fileinfo = explode('|', $file['ed2klink']);

			/*
			 0: protocol (crap)
			 1: type
			 2: filename
			 3: size?
			 4: ed2khash
			 5: endslash (crap)
			*/

			# incomplete response?
			if (!isset($fileinfo[2], $fileinfo[3], $fileinfo[4])) {
				cl('Invalid response - ED2k-link is invalid, contents of it: '.var_export($file['ed2klink'], true), VERBOSE_ERROR);
				return array();
			}

			return array(
				'ed2k' => strtoupper($fileinfo[4]),
				'id' => strtoupper($fileinfo[4]),
				'link' => $file['ed2klink'],
				'name' => stripslashes($fileinfo[2]), # dunno why, but here it spitted out slashes...
				'size' => (float)$fileinfo[3],
				'type' => strtolower($file['type'])
			);
		}

		# to get search results - filter may be min, max, type
		public function results($filter=false) {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			# fetch search results from eMule
			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses, 'w' => 'search', 'sort' => 1, 'sortAsc' => 0))));
			$r = $this->parseResponse($r); # convert to XML
			if (!isset($r['status']) || $r['status'] != 'ok') {
				cl('Search results request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}

			return $this->responseToFilelist($r, 'searchresultlist', $filter);
		}

		# to get transfer list - filter may be min, max, type
		public function transfers($filter=false) {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			# request transfer list from eMule
			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses, 'w' => 'transfer', 'sort' => 1, 'sortAsc' => 0))));
			$r = $this->parseResponse($r); # convert to XML

			# if it failed, end here
			if ($r['status'] != 'ok' || !isset($r['filelist'], $r['filelist']['file'])) {
				cl('Transfers request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}

			return $this->responseToFilelist($r, 'transferlist', $filter);;
		}

		# to reconnect kad
		private function kadReconnect() {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses, 'w' => 'kad'))));
			$r = $this->parseResponse($r); # convert to XML

			if (!in_array(strtolower($r['kadstatus']), array('ansluten', 'connected'))) {
				cl('Kad disconnected, trying to connect', VERBOSE_INFO);
				$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query(array('ses' => $this->ses, 'w' => 'kad', 'c' => 'connect'))));
			} else {
				cl('Kad connected, nothing needed todo', VERBOSE_INFO);
			}

			return true;
		}

		# convert object to array - aaron at tekserve dot com 25-Oct-2009 01:16
		private function objectToArray($mixed) {
			if (is_object($mixed)) $mixed = (array) $mixed;
			if (is_array($mixed)) {
				$new = array();
				foreach($mixed as $key => $val) {
					$key = preg_replace("/^\\0(.*)\\0/","",$key);
					$new[$key] =  $this->objectToArray($val);
				}
			}
			else $new = $mixed;
			return $new;
		}

		# parse an xml response and return a plain array
		private function parseResponse($xmlstr) {
			# TODO: better way to secure the filenames, possible search for <ed2klink>*</ed2klink> and fix between
			$xmlstr = str_replace('&', '&amp;', $xmlstr);

			# make sure we remove unneccessary tags
			$xmlstr = preg_replace("/<!--.*?-->/ms","",$xmlstr);

			$xml = new SimpleXMLElement($xmlstr);

			$xml = is_object($xml) ?  $this->objectToArray($xml) : $xml;
			return $xml;
		}

		# to convert a response array to a simplified file list
		public function responseToFilelist($r, $listtype='searchresultlist', $filter=false) {

			if (!isset($r['status']) || $r['status'] != 'ok') {
				cl('Response is invalid, cannot convert to file list: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}

			# no files in list?
			if (!isset($r['filelist'], $r['filelist']['file'])) {
				return array();
			}

			$files = array();
			foreach ($r['filelist']['file'] as $file) {
				# what type of list are we working with here
				switch ($listtype) {
					case 'searchresultlist':

						# invalid response?
						if (!isset($file['ed2klink'])||!isset($file['type'])) {
							cl('Invalid filelist response - no ED2k-link or type, file structure was: '.var_export($file, true), VERBOSE_ERROR);
							continue;
						}

						# extract file info from ed2k link
						$fileinfo = $this->fileToArray($file);

						# no file info found?
						if (!count($fileinfo)) {
							# logmessage('Invalid response - ED2k-link is invalid, contents of it: '.$file['ed2klink'], $link);
							cl('Invalid response - ED2k-link is invalid, contents of it: '.var_export($file['ed2klink'], true), VERBOSE_ERROR);
							continue;
						}

						cl('Checking "'.$fileinfo['name'].'" ('.$fileinfo['size'].' b, '.$fileinfo['ed2k'].')', VERBOSE_DEBUG);

						# is filter enabled?
						if (is_array($filter)) {
							if (isset($filter['min']) && $filter['min'] > $fileinfo['size']) continue;
							if (isset($filter['max']) && $filter['max'] < $fileinfo['size']) continue;
							if (isset($filter['type']) && $filter['type'] != $fileinfo['type']) continue;
						}

						# add it to list of files
						$files[] = $fileinfo;

						break;
					case 'transferlist':

						# is filter enabled?
						if (is_array($filter)) {
							if (isset($filter['min']) && $filter['min'] > $file['fsize']) continue;
							if (isset($filter['max']) && $filter['max'] < $file['fsize']) continue;
							if (isset($filter['type']) && $filter['type'] != $file['filetype']) continue;
						}

						# cleanup the mess
						$file['ed2k'] = $file['ed2khash'];
						$file['id'] = $file['ed2khash'];
						$file['name'] = $file['fname'];
						$file['size'] = (float)$file['fsize'];

						# try to extract "(n.n%)" from the finfo section, if not there, check if the transfer is complete by comparing "x MB" (sizecompleted) with "y MB" (sizetotal)
						$file['completed'] = preg_match('/\((\d+.\d+)\%\)/', $file['finfo'], $m) ? (float)$m[1] : ($file['sizecompleted'] === $file['sizetotal'] ? 100 : false);

						$file['sizecompleted'] = $file['sizecompleted'];

						unset($file['fname'], $file['fsize'], $file['filetype'], $file['ed2khash'], $file['finfo'], /*$file['sizecompleted'],*/ $file['sizetotal'], $file['type']);

						$files[] = $file;
						break;
				}
			}

			return $files;
		}

		# to perform a search - searchtext, options and filter of output can be supplied
		public function search($search, $options = array(), $filter=false) {

			# make sure client is connected
			if (!$this->connect()) {
				return false;
			}

			# default parameters - note the order, may be important
			$defaults = array(
				'tosearch' 	=> $search,
				'unicode' 	=> 'on',
				'type' 		=> '', #empty=all,
				'min' 		=> 0,
				'max' 		=> 0,
				'avail' 	=> '',
				'ext' 		=> '',
				'method' 	=> 'global', # server, global, kademlia
				'w' 		=> 'search',
				'ses' 		=> $this->ses
			);

			# merge defaults with the search options
			$search = array_merge($defaults, $options);

			# is type defined?
			if (strlen($search['type'])) {
				# awkward formatting to suit emule - make sure first letter is uppercase
				$search['type'] = ucfirst($search['type']);
				# still not matching?
			 	if (!array_key_exists($search['type'], $this->types)) {
					cl('Search request failed, unknown type: '.var_export($search['type'], true), VERBOSE_ERROR);
					return false;
				}
			}

			$r = $this->curl($this->c, array(CURLOPT_URL => $this->url.'?'.http_build_query($search)));
			$r = $this->parseResponse($r); # convert to XML

			if (!isset($r['status']) || $r['status'] != 'ok') {
				cl('Search request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
				return false;
			}
			return $this->responseToFilelist($r, 'searchresultlist', $filter);
		}
	}
?>
