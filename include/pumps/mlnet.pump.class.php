<?php
  # MLDonkey-Server Client Pump Class - manages the communication between the project and the client

  # changelog
  # 2013-12-20 00:00:00
  # 2013-12-21 00:00:00 - improving, connecting to kreosot
  # 2013-12-22 00:00:00 - improving again
  # 2014-07-16 15:42:54 - taking emule class
  # 2014-07-19 20:05:43 - making it a pump
  # 2014-07-22 00:00:00 - multi-client with clientpumps, search with name+size check
  # 2014-07-23 18:37:10
  # 2015-07-23 01:19:56 - bugfixes for mlnet and utf8 - mlnet sends utf8 text
  # 2015-07-24 23:18:00 - bugfix for 0 in min/max values which were sent to client and stopped searches
  # 2015-08-21 11:24:56 - cleanup
  # 2015-08-22 10:11:50
  # 2016-11-08 23:24:04 - bugfix min max
  # 2017-09-10 17:33:00 - sorting functions
  # 2017-09-10 17:53:00 - adding generate preview
  # 2017-09-10 23:46:00 - adding preview to transfers, adding id, putting preview into production
  # 2017-09-12 22:10:00 - dropping project name in file
  # 2017-09-13 00:08:00 - adding chunk weights export
  # 2017-09-13 01:42:00 - updating cancel, putting it into production
  # 2017-09-19 19:25:00 - editing message handling
  # 2017-09-19 22:31:00 - using stderr for diagnostic output
  # 2017-09-21 00:21:00 - separating unfinished files listing and preview generation
  # 2017-09-21 23:12:00 - adding last modified to transfers
  # 2017-09-22 12:15:00 - bugfix, remove thumbnail only if it exists
  # 2017-09-28 00:07:00 - sorting transfer list descending on modify date
  # 2018-07-13 19:31:26 - indentation change, tab to 2 spaces
  # 2018-07-28 14:59:00 - updating videosheet location
  # 2019-09-15 17:25:00 - bugfix, error message

  # general notice: data from mlnet already is in UTF-8!

  class mlnetPump {

    private $c 			= false;
    private $host 		= 'localhost';
    private $id			= 0;
    private $password 	= '';
    private $port 		= 4080;
    private $ses 		= false;
    private $url 		= '';
    private $username	= '';

    # mlnet-defined search types, key is used
    public	$types = array(
      # arc is missing
      'Audio'		=> 'Audio',
      # 'Col'		=> 'Collection', # this is unique for mlnet
      'Doc'		=> 'Document',
      'Image'		=> 'Image',
      'Pro'		=> 'Program',
      'Video'		=> 'Video'
    );

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

    # to cancel a download, tested
    public function cancel($id) {

      # example: http://host:4080/files?cancel=9&selectPriority9=%3D0
      $data = $this->curl(array(
        CURLOPT_URL => $this->getUrl().'files?'.http_build_query(array('cancel' => $id))
      ));
      if ($data === false) return false;

      return true;
    }

    # when object is made
    public function __construct($config=array()) {

      # invalid config - then get out now
      if (!is_array($config)) {
        return false;
      }

      $this->id				= isset($config['id']) 				!== false	? $config['id']				: $this->id;
      $this->host				= isset($config['host']) 			!== false	? $config['host']			: $this->host;
      $this->password			= isset($config['password']) 		!== false	? $config['password']		: $this->password;
      $this->port				= isset($config['port'])			!== false	? $config['port']			: $this->port;
      $this->username			= isset($config['username']) 		!== false	? $config['username']		: $this->username;

      # connection should not be made here, as emh creates object during loading

      return true;
    }

    # simple curling function to send and receive data
    private function curl($opt=array()) {

      # no curl object?
      if (!$this->c) {
        # then make one
        $this->c = curl_init();
      }

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
        CURLOPT_VERBOSE			=> false,       # set to true to echo headers
      );

      # merge defaults and provided parameters to get the best mix
      $opt = $this->arrayMergeKeepKeys($defopt, $opt);

      curl_setopt_array($this->c, $opt);
      $r = curl_exec($this->c);
      if (curl_errno($this->c)) {
        cl('cURL-error: '.curl_error($this->c).' ('.curl_errno($this->c).')', VERBOSE_ERROR);
        return false;
      }
      return $r;
    }

    # when object is destroyed
    public function __destruct() {
      # is there a curl connection?
      if ($this->c) {
        # then close it
        curl_close($this->c);
      }
    }

    # to request download
    public function download($ed2klink) {

      $data = $this->curl(array(
        CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'dllink '.$ed2klink))
      ));
      if ($data === false) return false;

      # no suitable response texts?
      if (strpos($data, 'Added link') === false && strpos($data, 'File is already in download queue') === false) {
        # then raise error
        cl('Download request failed, invalid response: '.var_export($r, true), VERBOSE_ERROR);
        return false;
      }

      # otherwise all ok
      return true;
    }

    private function getUnfinishedFiles() {

      # get settings page with file paths
      $data = $this->curl(array(
        CURLOPT_URL => $this->getUrl().'submit?q=voo+5' # .http_build_query(array('q' => 'voo+5'))
      ));
      if ($data === false) {
        # then raise error
        cl('Settings page request failed, invalid response: '.var_export($data, true), VERBOSE_ERROR);
        return false;
      }

      # extract temporary directory path
      # value=temp_directory><input style="font-family: verdana; font-size: 10px;"
            # type=text name=value onchange="track_changed(this)" size=20 value="/temporary/folder/path"></td></form>
      preg_match_all('/value\=temp_directory\>\<input\s*style\=\"font\-family\:\s*verdana\;\s*font\-size\:\s*10px\;\"\s*type\=text\s*name\=value\s*onchange\=\"track_changed\(this\)\"\s*size\=20\s*value=\"(.*?)\"\>/m', $data, $m);
      if (!isset($m[1], $m[1][0])) {
        # then raise error
        cl('Failed extracting temporary directory.', VERBOSE_ERROR);
        return false;
      }

      $originalpath = $m[1][0];
      if (!is_dir($originalpath)) {
        # then raise error
        cl('Extracted temporary directory "'.$originalpath.'" is not a directory.', VERBOSE_ERROR);
        return false;
      }

      # do a real path of it
      $originalpath = realpath($m[1][0]);
      if (!is_dir($originalpath)) {
        # then raise error
        cl('Extracted temporary directory "'.$originalpath.'" is not a directory.', VERBOSE_ERROR);
        return false;
      }

      # make sure it's long enough
      if (strlen($originalpath) < 2) {
        # then raise error
        cl('Extracted temporary directory "'.$originalpath.'" is too short.', VERBOSE_ERROR);
        return false;
      }

      # make sure it ends with a slash
      $originalpath = substr($originalpath, -1) === '/' ? $originalpath : $originalpath.'/';

      # search for files
      exec('find '.escapeshellarg($originalpath).' -type f', $o, $r);
      if ($r !== 0) {
        # then raise error
        cl('Failed searching incoming '.$originalpath.' folder for files: '.var_export($o, true).' ('.$r.')', VERBOSE_ERROR);
        return false;
      }

      $files = array();

      # filter unwanted files
      foreach ($o as $file) {
        if (strpos(basename($file), 'urn_ed2k') !== 0) {
          continue;
        }

        $files[] = $file;
      }

      return $files;
    }

    public function previewScanUnfinished() {
      # get file list
      $originalfiles = $this->getUnfinishedFiles();
      if ($originalfiles === false) {
        return false;
      }

      # get db files
      $db_files =  pump_get_unfinished($this->id);

      # delete files only in db
      $db_files_kept = array();
      $db_id_files_remove = array();
      foreach ($db_files as $db_file) {
        $found = false;
        foreach ($originalfiles as $file) {
          if (basename($file) === $db_file['name']) {
            $found = true;
            break;
          }
        }
        if (!$found) {
          $db_id_files_remove[] = $db_file['id'];
        } else {
          $db_files_kept[] = $db_file;
        }
      }

      if (count($db_id_files_remove)) {
        pump_delete_unfinished($this->id, $db_id_files_remove);
      }

      $db_files = $db_files_kept;

      $db_files_update = array();

      # walk files from dir
      foreach ($originalfiles as $file) {

        # walk db files
        $found = false;
        $filename = basename($file);
        $filesize = filesize($file);
        $filemodified = date('Y-m-d H:i:s', filemtime($file));

        foreach ($db_files as $db_file) {

          # existing file
          if ($db_file['name'] === $filename) {
            $found = true;
            # existing file size or modification date differ
            if ((int)$filesize !== (int)$db_file['size'] || $filemodified !== $db_file['modified']) {
              # prepare for update
              $db_files_update[] = array(
                'id' => $db_file['id'],
                'name' => $filename,
                'size' => $filesize,
                'modified' => $filemodified
              );

            }
          }
        }

        if (!$found) {
          # new file, prepare for insert
          $db_files_update[] = array(
            'name' => $filename,
            'size' => $filesize,
            'modified' => $filemodified
          );
        }
      }

      # was there files to update
      if (count($db_files_update)) {
        # then call for update in db
        pump_update_unfinished($this->id, $db_files_update);
      }
    }

    # to generate preview files
    public function generatePreviews() {

      $originalfiles = $this->getUnfinishedFiles();
      if ($originalfiles === false) {
        return false;
      }

      # ends with slash
      $previewpath = PREVIEW_DIR.$this->id.'/';
      if (!is_dir($previewpath)) {
        if (!mkdir($previewpath, 0777, true)) {
          cl('Failed creating preview directory: '.$previewpath, VERBOSE_ERROR);
          return false;
        }
      }

      $db_files =  pump_get_unfinished($this->id, true);

      # walk files
      foreach ($originalfiles as $origfile) {

        # must be a renewable file, otherwise skip it
        $renewable = false;
        foreach ($db_files as $db_file) {
          if ($db_file['name'] === basename($origfile)) {
            $renewable = true;
            break;
          }
        }

        if (!$renewable) {
          continue;
        }

        $modifytime = filemtime($origfile);
        $ed2k = substr(basename($origfile), 9);
        $normalthumbpath = $previewpath.$ed2k.'.preview.jpg';

        # thumb exists and we got modify time for it
        #if (file_exists($normalthumbpath) && $modifytime !== false) {
        #	$previewtime = filemtime($normalthumbpath);
          # is the thumb time before the file time
        #	if ($previewtime < $modifytime) {
            //fwrite(STDERR, 'Thumbnail outdated [T: '.date('Y-m-d H:i:s', $previewtime).' / F: '.date('Y-m-d H:i:s', $modifytime).'] on '.$normalthumbpath."\n");
            # then remove the thumbnail
            # unlink($normalthumbpath);
        #	}
        #}

        if (file_exists($normalthumbpath)) {
          unlink($normalthumbpath);
        }

        # no main thumb?
        #if (!file_exists($normalthumbpath)) {
          unset($c, $r);
          # run video sheet to make it
          $c = 'php '.dirname(__FILE__).'/../videosheet --filename='.escapeshellarg($origfile).' --format=jpeg --number=4 --column=2 --quality=75 --thumbsize=512,-1 --gridonly --skipnoduration --output='.escapeshellarg($normalthumbpath);
          passthru($c, $r);

          if ($r === 0 && file_exists($normalthumbpath)) {
            # if it succeeded, set the date of the file to the same as the file
            touch($normalthumbpath, $modifytime);
          }
        #}

        # tell that this file has been renewed now
        pump_update_renewed_file($this->id, $db_file['id'], filesize($origfile), date('Y-m-d H:i:s', $modifytime));
      }

      # remove thumbnails without parent
      exec('find '.escapeshellarg($previewpath).' -type f', $o, $r);
      if ($r !== 0) {
        # then raise error
        cl('Failed searching preview folder '.$previewpath.' for files: '.var_export($o, true).' ('.$r.')', VERBOSE_ERROR);
        return false;
      }

      $previews = $o;
      foreach ($previews as $previewfile) {
        $preview_filename = basename($previewfile);

        # make sure it ends with .preview.jpg
        if (substr($preview_filename, - strlen('.preview.jpg')) !== '.preview.jpg') {
          continue;
        }

        # get original filename out of the filename - urn_ed2k_<something>.preview.jpg
        $origname = 'urn_ed2k_'.substr($preview_filename, 0, strlen($preview_filename) - strlen('.preview.jpg'));
        $found = false;

        # check if it exists in file list
        foreach ($originalfiles as $origfile) {
          if (basename($origfile) === $origname) {
            $found = true;
            break;
          }
        }

        # was not found, wants to delete it then
        if (!$found) {
          # fwrite(STDERR, 'Removing leftover thumbnail: '.$previewfile."\n");
          unlink($previewfile);
        }
      }

      return true;
    }

    # to get an url
    private function getUrl() {
      return 'http://'.$this->username.':'.$this->password.'@'.$this->host.':'.$this->port.'/';
    }

    # to get search results
    public function results() {
      $data = $this->curl(array(
        CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vr'))
      ));
      if ($data === false) return false;

      # example data to match:
      # </tr>
      # <tr class="dl-1"><td class="sr"><a href="ed2k://|file|SomeFile.ext|3244353555|ABCDEFGHIJKLMNOPQRSTUVXYZ1234567|/">Donkey</a></td><td onMouseOver="setTimeout('popLayer(\'Sing Star - ABBA (pw=MamaMia).rar<BR>completesources: 1&lt;br&gt;availability: 1\')',0);setTimeout('hideLayer()',100000);return true;"  onMouseOut="hideLayer();setTimeout('hideLayer()',0);return true;" class="sr"><a href=results?d=555 target="fstatus">SomeFile.ext
      # </a></td><td class="sr ar">3.23G</td>
      #			<td class="sr ar">1</td>
      #			<td class="sr ar">1</td>
      #			<td class="sr"><a href="http://bitzi.com/lookup/ed2k:ABCDEFGHIJKLMNOPQRSTUVXYZ1234567">BI</a></td>
      #			<td class="sr"><a href="http://www.filedonkey.com/url/ABCDEFGHIJKLMNOPQRSTUVXYZ1234567">FD</a></td>
      #			<td class="sr ar"></td>
      #			<td class="sr ar"></td>
      #			<td class="sr ar"></td><td class="sr"></td></tr>

      preg_match_all('/\<a href\=\"(ed2k\:\/\/\|file\|(.*?)\|(\d+)\|([a-zA-Z0-9]+)\|\/)\"\>/', $data, $m);

      $r = array();
      foreach ($m[1] as $k => $name) {
        $r[] = array(
          'ed2k' => (rawurldecode($m[4][$k])),
          'id'   => (rawurldecode($m[1][$k])),
          'link' => (rawurldecode($m[1][$k])),
          'name' => (rawurldecode($m[2][$k])),
          'size' => (rawurldecode($m[3][$k]))
        );
      }

      return $r;
    }

    # to get transfer list
    public function transfers() {

      $data = $this->curl(array(
        CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vd'))
      ));
      if ($data === false) return false;

      # all is well, but there are no search results
      if (strpos($data, 'No files, please use search') !== false) {
        return array();
      }

      preg_match_all(
        # example: onMouseOver="mOvr(this);setTimeout(\'popLayer(\\\'Some.File.ext&lt;br&gt;File#: 66&lt;
        # '/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt/',

        # '/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt.*?\"loaded\" style\=\"height:2px\" width\=\"(.*?)\"/',
        '/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt.*?\"loaded\" style\=\"height:2px\" width\=\"(\d+)%\"/sim',

        $data,
        $matches
      );

      #var_dump($matches);
      #die();

      $unfinished_files = $this->getUnfinishedFiles();

      # no matches?
      if (!count($matches[0])) {
        # all is well, but there are no results
        # then raise error
        cl('Request failed, invalid response: '.var_export($data, true), VERBOSE_ERROR);
        return false;
      }

      $tmp = array();
      foreach ($matches[0] as $key => $notused) {
        $row = array(
          'name' => $matches[1][$key],
          'id' => (int)$matches[2][$key],
          'completed' => (int)$matches[3][$key],
        );

        # lets walk again and request info page per download, to get ed2klink
        $data = $this->curl(array(
          CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vd '.$row['id']))
        ));
        if ($data === false) return false;


        # <td class="chunkX" style="width:Xpx">

        # try to get chunks info
        $start = strpos($data, 'Chunks</td>');
        $end = strpos($data, '"Chunk size">Chunk size');
        $chunkweights = array();
        if ($start !== false && $end !== false) {
          $cut = substr($data, $start, $end - $start);
          # echo $cut;

          preg_match_all('/\<td class\=\"chunk(\d+)\"\s*style\=\"width\:(\d+)px\"\>/', $cut, $m);
          # did it fail?
          if (!count($m[0])) {
            # then raise error
            cl('Failed extracting chunks data for row '.$row['id'].', '.$row['name'], VERBOSE_ERROR);
            return false;
          }

          $weighttotal = 0;
          foreach ($m[2] as $key => $weight) {
            $weighttotal += (int)$weight;
          }

          foreach ($m[1] as $key => $type) {
            $chunkweights[] = array(
              'type' => (int)$type,
              'weight' => ($m[2][$key] / $weighttotal) * 100
            );
          }
        }

        # any ed2klink?
        preg_match_all('/\<a href\=\"(ed2k\:\/\/\|file\|(.*?)\|(\d+)\|([a-zA-Z0-9]+)\|\/)\"\>/', $data, $m);
        # did it fail?
        if (!count($m[0])) {
          # then raise error
          cl('Failed extracting detailed info about row '.$row['id'].', '.$row['name'], VERBOSE_ERROR);
          return false;
        }

        # a wild guess here that utf8 is already from mlnet here too
        $row['link'] = (rawurldecode($m[1][0]));
        $row['name'] = (rawurldecode($m[2][0]));
        $row['size'] = (rawurldecode($m[3][0]));
        $row['ed2k'] = (rawurldecode($m[4][0]));
        # $row['completed'] = (rawurldecode($m[5][0]));
        $row['sizecompleted'] = (float)rawurldecode($m[3][0]) * ((int)$row['completed']/100);
        $row['chunkweights'] = $chunkweights;

        if ($unfinished_files) {
          foreach ($unfinished_files as $file) {
            if (basename($file) === 'urn_ed2k_'.$row['ed2k']) {
              $row['modified'] = date('Y-m-d H:i:s', filemtime($file));
            }
          }
        }

        $tmp[] = $row;
      }

      # sort data descending by modified date
      usort($tmp, array($this, "sort_descending_by_modified"));

      return $tmp;
    }

    # to sort using usort by modified date
    private function sort_descending_by_modified($a, $b)
    {
      if (!isset($a['modified'], $b['modified'])) return 0;

      if (!isset($a['modified']) && isset($b['modified'])) return 1;

      if (!isset($b['modified']) && isset($a['modified'])) return -1;

      if ($a['modified'] == $b['modified']) {
        return 0;
      }
      return ($a['modified'] < $b['modified']) ? 1 : -1;
    }

    # to perform a search - searchtext, options can be supplied
    public function search($search, $options = array()) {

      # example: http://host:4080/submit?custom=Complex+Search&keywords=some+keywords&minsize=&minsize_unit=1048576&maxsize=&maxsize_unit=1048576&media=&media_propose=&format=&format_propose=&artist=&album=&title=&bitrate=&network=
      # fail   : http://host:4080/submit?album=&artist=&bitrate=&custom=Complex+Search&format=&format_propose=&keywords=spankingserver&maxsize=&maxsize_unit=1048576&media=&media_propose=Video&minsize=50&minsize_unit=1048576&network=&title=
      # default parameters - NOTE the ORDER of the arguments, wrong order results in 404 not found

      # note for mlnet: sending in 0 as min or max results in zero results

      $search = array(
        'custom'			=> 'Complex Search',
        'keywords'			=> $search,
        'minsize'			=> isset($options['min']) && (int)$options['min'] !== 0 ? $options['min'] : '',
        'minsize_unit' 		=> 1048576, # MB
        'maxsize'			=> isset($options['max']) && (int)$options['max'] !== 0 ? $options['max'] : '',
        'maxsize_unit'		=> 1048576, # MB
        'media'				=> '',
        'media_propose'		=> '',
        'format'			=> '',
        'format_propose'	=> '',
        'artist'			=> '',
        'album'				=> '',
        'title'				=> '',
        'bitrate'			=> '',
        'network'			=> ''
      );

      # is type defined?
      if (isset($options['type']) && strlen($options['type'])) {
        # awkward formatting to suit emh - make sure first letter is uppercase
        $options['type'] = ucfirst($options['type']);
        # still not matching?
        if (!array_key_exists($options['type'], $this->types)) {
          cl('Search request failed, unknown type: '.var_export($search['type'], true), VERBOSE_ERROR);
          return false;
        }

        $search['media_propose'] = $options['type'];
      }

      # submit the search
      $r = $this->curl(array(CURLOPT_URL => $this->getUrl().'submit?'.http_build_query($search)));
      if ($r === false) return false;

      # look for something that indicates a working search
      if (strpos($r, 'Sending query !!!') === false) {
        cl('Search request failed, unknown type: '.var_export($r, true), VERBOSE_ERROR);
        return false;
      }

      return $this->results();
    }
  }
?>
