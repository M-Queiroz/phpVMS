<?php
/**
 * phpVMS - Virtual Airline Administration Software
 * Copyright (c) 2008 Nabeel Shahzad
 * For more information, visit www.phpvms.net
 *	Forums: http://www.phpvms.net/forum
 *	Documentation: http://www.phpvms.net/docs
 *
 * phpVMS is licenced under the following license:
 *   Creative Commons Attribution Non-commercial Share Alike (by-nc-sa)
 *   View license.txt in the root, or visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 *
 * @author Nabeel Shahzad
 * @copyright Copyright (c) 2008, Nabeel Shahzad
 * @link http://www.phpvms.net
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 */
 
class PilotData extends CodonData
{
	
	public static $pilot_data = array();
	
	
	/**
	 * Find any pilots based on the parameters passed in
	 *
	 * @param array $params All the parameters
	 * @param int $limit Number of results to return
	 * @param int $start Record to start from
	 * @return array Returns all the pilots requested
	 *
	 */
	public static function findPilots($params, $limit='', $start = '')
	{
		$sql = "SELECT p.*, r.`rankimage`, r.`payrate`
				FROM ".TABLE_PREFIX."pilots p
				LEFT JOIN ".TABLE_PREFIX."ranks r ON r.rank=p.rank ";
		
		/* Build the select "WHERE" based on the columns passed, this is a generic function */
		$sql .= DB::build_where($params);
		
		if(strlen($limit) != 0)
		{
			$sql .= ' LIMIT '.$limit;
		}
		
		if(strlen($start) != 0)
		{
			$sql .= ' OFFSET '. $start;
		}
		
		$ret = DB::get_results($sql);
		return $ret;
	}
	
	/**
	 * Get all the pilots, or the pilots who's last names start
	 * with the letter
	 */
	public static function getAllPilots($letter='')
	{
		$params = array();
		
		if(!empty($letter))
		{
			$params['lastname'] = $letter.'%';
		}
		
		return self::findPilots($params);
	}
	
	/**
	 * Get all the detailed pilot's information
	 */
	public static function getAllPilotsDetailed($limit=20, $start='')
	{
		return self::findPilots(array(), $limit, $start);
	}
	
	/**
	 * Get a pilot's avatar
	 */
	public static function getPilotAvatar($pilotid)
	{
		if(is_numeric($pilotid))
		{
			$pilot = self::getPilotData($pilotid);
			$pilotid = self::getPilotCode($pilot->code, $pilot->pilotid);
		}
		
		$link = AVATAR_PATH.'/'.$pilotid.'.png';
		
		if(!file_exists(SITE_ROOT.'/'.$link))
		{
			return SITE_URL.'/lib/images/noavatar.png';
		}
		
		return SITE_URL.'/'.$link;
	}
	
	/**
	 * Get all the pilots on a certain hub
	 */
	public static function getAllPilotsByHub($hub)
	{
		return self::findPilots(array('p.hub'=>$hub));
	}
	
	/**
	 * Return the pilot's code (ie DVA1031), using
	 * the code and their DB ID
	 */
	public static function getPilotCode($code, $pilotid)
	{
		# Make sure values are entered
		if(Config::Get('PILOTID_LENGTH') == '')
			Config::Set('PILOTID_LENGTH', 4);
		
		if(Config::Get('PILOTID_OFFSET') == '')
			Config::Set('PILOTID_OFFSET', 0);
			
		$pilotid = $pilotid + Config::Get('PILOTID_OFFSET');
		return $code . str_pad($pilotid, Config::Get('PILOTID_LENGTH'), '0', STR_PAD_LEFT);
	}
	
	/**
	 * The the basic pilot information
	 * Quasi 'cached' in case it's called multiple times
	 * for the same pilot in one script
	 */
	public static function getPilotData($pilotid)
	{	
		$pilot = self::findPilots(array('p.pilotid' => $pilotid), 1);
		
		if(!$pilot)
		{
			return false;
		}
		
		return $pilot[0];
	}
	
	/**
	 * Get a pilot's information by email
	 */
	public static function getPilotByEmail($email)
	{
		$pilot = self::findPilots(array('p.email'=>$email));
		if(!$pilot)
		{
			return false;
		}
		
		return $pilot[0];
	}

	/**
	 * Get the list of all the pending pilots
	 */
	public static function getPendingPilots($count='')
	{
		$params = array(
			'p.confirmed' => PILOT_PENDING
		);
		
		return self::findPilots($params, $count);
	}
	
	public static function getLatestPilots($count=10)
	{
		$sql = 'SELECT * FROM '.TABLE_PREFIX.'pilots
				ORDER BY `pilotid` DESC
				LIMIT '.$count;
	
		return DB::get_results($sql);
	}
	
	/**
	 * Change a pilot's name. This is separate because this is an
	 *  admin-only operation (strictly speaking), and isn't included
	 *  in a normal change of a pilot's profile (whereas SaveProfile
	 *  only changes minute information
	 */
	 
	public static function changeName($pilotid, $firstname, $lastname)
	{
		# Non-blank
		if(empty($pilotid) || empty($firstname) || empty($lastname))
		{
			return false;
		}
		
		$params = array(
			'firstname' => $firstname,
			'lastname' => $lastname,
		);
		
		return self::updateProfile($pilotid, $params);
	}
	
	public static function changePilotID($old_pilotid, $new_pilotid)
	{
		$pilot_exists = self::getPilotData($new_pilotid);
		if(is_object($pilot_exists))
		{
			return false;
		}
		
		// List of all the tables which need to update
		$table_list = array(
			'adminlog',
			'awardsgranted',
			'acarsdata',
			'sessions',
			'pilots',
			'pireps',
			'pirepcomments',
			'fieldvalues',
			'groupmembers',
			'bids',
		);
		
		foreach($table_list as $table)
		{
			$sql = 'UPDATE `'.TABLE_PREFIX.$table.'`
					SET `pilotid`='.$new_pilotid.'
					WHERE `pilotid`='.$old_pilotid;
					
			DB::query($sql);
		}
		
		return true;
	}
	
	public static function changePilotRank($pilotid, $rankid)
	{
		$rank = RanksData::getRankInfo($rankid);
		$rank_level = RanksData::getRankLevel($rankid);
		if(!$rank)
		{
			return false;
		}
		
		$data = array(
			'rankid' => $rank->rankid, 
			'rank' => $rank->rank,
			'ranklevel' => $rank_level,
		);
		
		return self::updateProfile($pilotid, $data);
	}
	
	/**
	 * Save any changes to a pilot's profile, calls updateProfile()
	 * 
	 * @deprecated Use updateProfile() instead!!
	 * 
	 */
	/*public static function saveProfile($params)
	{
		$pilotid = $params['pilotid'];
		unset($params['pilotid']);
		
		return self::updateProfile($pilotid, $params);
	}*/
	
	
	/**
	 * Update a pilot, $params is an array of column_name=>value
	 *
	 * @param mixed $pilotid This is a description
	 * @param mixed $params This is a description
	 * @return mixed This is the return value description
	 *
	 */
	public static function updateProfile($pilotid, $params)
	{
		/*$params = array(			
			'pilotid' => '',
			'code' => '',
			'email' => '',
			'location' => '',
			'hub' => '',
			'bgimage' => '',
			'retired' => false,
		);*/
		
		
		if(!is_array($params))
		{
			return false;
		}
		
		/* Cleanup any specific parameters */	
		if(isset($params['location']))
		{	
			$params['location'] = strtoupper($params['location']);
		}
		
		if(isset($params['retired']))
		{
			if(is_bool($params['retired']))
			{
				//$params['retired'] = intval($params['retired']);
				
				# The above doesn't work for false =\
				if($params['retired'] == true)
					$params['retired'] = 1;
				else
					$params['retired'] = 0;
			}
		}
		
		if(isset($params['pilotid']))
		{
			unset($params['pilotid']);
		}
		
		$sql = "UPDATE ".TABLE_PREFIX."pilots SET ";
		$sql .= DB::build_update($params);
		$sql .= " WHERE `pilotid`={$pilotid}";
		
		$res = DB::query($sql);
		
		if(DB::errno() != 0)
		{
			return false;
		}
				
		return true;
	}
	
	public static function updatePilotRankLevels()
	{
		$all_pilots = self::findPilots(array());
		
		foreach($all_pilots as $pilot)
		{
			$rank_level = RanksData::getRankLevel($pilot->rankid);
			self::updateProfile($pilot->pilotid, array('ranklevel'=>$rank_level));
		}
	}
	
	public static function setPilotRetired($pilotid, $retired)
	{
		if($retired === true || $retired == '1')
		{
			$retired = 1;
		}
		else
		{
			$retired = 0;
		}
			
		return self::updateProfile($pilotid, array('retired'=>$retired));
	}
	
	
	/**
	 * Returns an array with a list of background images available
	 *
	 * @return array The background images list
	 *
	 */
	public static function getBackgroundImages()
	{
		$list = array();
		$files = scandir(SITE_ROOT.'/lib/signatures/background');

		foreach($files as $file)
		{   
			if($file == '.' || $file == '..') continue;
			
    		if(strstr($file, '.png') !== false)
				$list[] = $file;
		}
		
		return $list;
	}
	
	/**
	 * Save avatars
	 */
	public static function saveAvatar($code, $pilotid, $_FILES)
	{
		# Check the proper file size
		#  Ignored for now since there is a resize
		/*if ($_FILES['avatar']['size'] > Config::Get('AVATAR_FILE_SIZE'))
		{
			return false;
		}*/
		
		if(!$_FILES['avatar']['type'])
			return false;
			
		# Create the image so we can convert it to PNG
		if($_FILES['avatar']['type'] == 'image/gif')
		{
			$img = imagecreatefromgif($_FILES['file']['tmp_name']);
		}
		elseif($_FILES['avatar']['type'] == 'image/jpeg' 
				|| $_FILES['avatar']['type'] == 'image/pjpeg')
		{
			$img = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
		}
		elseif($_FILES['avatar']['type'] == 'image/png')
		{
			$img = imagecreatefrompng($_FILES['avatar']['tmp_name']);
		}
		
		# Resize it
		$height = imagesy($img);
		$width = imagesx($img);
		
		$new_width = Config::Get('AVATAR_MAX_WIDTH');
		$new_height = floor( $height * ( Config::Get('AVATAR_MAX_HEIGHT') / $width ) );
		
		$avatarimg = imagecreatetruecolor($new_width, $new_height);
		imagecopyresized($avatarimg, $img, 0,0,0,0,$new_width, $new_height, $width, $height);
		
		# Output the file, to /lib/avatar/pilotcode.png
		$pilotCode = self::getPilotCode($code, $pilotid);
		imagepng($avatarimg, SITE_ROOT.AVATAR_PATH.'/'.$pilotCode.'.png');
		imagedestroy($img);
	}
	
	/**
	 * Accept the pilot (allow them into the system)
	 */
	public static function AcceptPilot($pilotid)
	{
		return self::updateProfile($pilotid, array('confirmed'=>PILOT_ACCEPTED, 'retired'=>'0'));
	}
	
	/**
	 * Reject a pilot
	 */
   	public static function RejectPilot($pilotid)
	{
		return self::DeletePilot($pilotid);
	}
	
		
	/**
	 * Completely delete a pilot	
	 *
	 * @param int $pilotid Pilot ID
	 * @return mixed This is the return value description
	 *
	 */
	public static function deletePilot($pilotid)
	{
		$sql = array();
		unset(self::$pilot_data[$pilotid]);
	
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'acarsdata WHERE pilotid='.$pilotid;
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'bids WHERE pilotid='.$pilotid;
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'pireps WHERE pilotid='.$pilotid;
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'pilots WHERE pilotid='.$pilotid;
		
		# These SHOULD delete on cascade, but incase they don't
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'fieldvalues WHERE pilotid='.$pilotid;
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'groupmembers WHERE pilotid='.$pilotid;
		$sql[] = 'DELETE FROM '.TABLE_PREFIX.'pirepcomments WHERE pilotid='.$pilotid;
		
		foreach($sql as $query)
		{
			$res = DB::query($query);
		}
		
		if(DB::errno() != 0)
			return false;
		
		return true;
	}
	
	/**
	 * Update the login time
	 */
	public static function updateLogin($pilotid)
	{
		return self::updateProfile($pilotid, array(
				'lastlogin'=>'NOW()', 
				'lastip' => $_SERVER['REMOTE_ADDR'],
			)
		);
	}
	
	public static function getPilotHours($pilotid)
	{
		$sql = 'SELECT `flighttime` FROM '.TABLE_PREFIX.'pireps
				WHERE `accepted`='.PIREP_ACCEPTED.'
					AND `pilotid`='.$pilotid;
		
		$pireps = DB::get_results($sql);
		
		if(!$pireps)
			return '0.0';
			
		$total = 0;	
		foreach($pireps as $report)
		{
			$total = Util::AddTime($total, $report->flighttime);
		}
	
		return $total;	
	}
	
	/**
	 * Get the total number of hours for a pilot, add them up
	 *
	 * @param int $pilotid The pilot ID
	 * @return int Total hours for pilot
	 *
	 */
	public static function updateFlightHours($pilotid)
	{				
		$total = self::getPilotHours($pilotid);
		$ret = self::updateProfile($pilotid, array('totalhours'=>$total));
		return $total;
	}
		
	/**
	 * Update a pilot's flight data, ie after a pirep
	 *
	 * @param int $pilotid Pilot ID
	 * @param int $flighttime Number of hours.minutes to increment by
	 * @param int $numflights Number of flights (default 1)
	 * @return bool Success
	 *
	 */
	public static function updateFlightData($pilotid, $flighttime, $numflights=1)
	{	
		
		# Update the flighttime
		$pilotdata = PilotData::getPilotData($pilotid);		
		$flighttime = Util::AddTime($pilotdata->totalhours, $flighttime);
		
		if($numflights == '')
			$numflights = 1;
	
		$params = array(
			'totalhours' => $flighttime,
			'totalflights' => ($pilotdata->totalflights + $numflights),
		);
		
		return self::updateProfile($pilotid, $params);
	}
	
	public static function updatePilotStats($pilotid)
	{
		$pireps = PIREPData::findPIREPS(array('p.pilotid'=>$pilotid));
		
		$totalpireps = 0;
		$totalhours = 0;
			
		if(is_array($pireps))
		{
			foreach($pireps as $p)
			{
				if($p->accepted != PIREP_ACCEPTED)
				{
					continue;
				}
				
				$totalpireps++;
				$totalhours = Util::AddTime($p->flighttime, $totalhours);
			}
		}
		
		
		$params = array(
			'totalhours'=>$totalhours,
			'totalflights'=>$totalpireps,
		);
		
		return self::updateProfile($pilotid, $params);
	}
	
	
	/**
	 * Update the last PIREP date for a pilot
	 *
	 * @param int $pilotid Pilot ID
	 * @return bool Success
	 *
	 */
	public static function updateLastPIREPDate($pilotid)
	{
		return self::updateProfile($pilotid, array('lastpirep'=>'NOW()'));
	}
	
	/**
	 * Don't update the pilot's flight data, but just replace it
	 * 	with the values given
	 *
	 * $data = array(
			'pilotid' => '',
			'flighttime' => '',
			'numflights' => '',
			'totalpay' => '',
			'transferhours' => '',
		);
	 *
	 * @param int $pilotid Pilot ID
	 * @param int $flighttime Number of flight hours
	 * @param int $numflights Number of flights
	 * @param int $totalpay The total amount of money they have
	 * @return bool Success
	 *
	 */
	public static function replaceFlightData($params)
	{
		/*$data = array(
			'pilotid' => '',
			'flighttime' => '',
			'numflights' => '',
			'totalpay' => '',
			'transferhours' => '',
		);*/
		
		$pilotid = $data['pilotid'];
		unset($data['pilotid']);
		
		return self::updateProfile($pilotid, $params);
	}
	
	public static function resetPilotPay($pilotid)
	{
		$total = 0;
		
		self::updateProfile($pilotid, array('totalpay'=>0));
		
		$sql = "SELECT `pirepid`, `flighttime`, `pilotpay`
				FROM ".TABLE_PREFIX."pireps
				WHERE `pilotid`={$pilotid}";
		
		$results = DB::get_results($sql);
		
		if(!$results)
		{
			return $total;
		}
			
		foreach($results as $row)
		{
			$payupdate = self::getPilotPay($row->flighttime, $row->pilotpay);
			$total += $payupdate;
		}
		
		self::updateProfile($pilotid, array('totalpay'=>$total));
		
		return $total;
	}
			
	/**
	 * Update a pilot's pay. Pass the pilot ID, and the number of
	 * hours they are being paid for
	 *
	 * @param int $pilotid The pilot ID
	 * @param int $flighthours Number of hours to pay the pilot for
	 * @return bool Success
	 *
	 */
	public static function updatePilotPay($pilotid, $flighthours)
	{
		$sql = 'SELECT payrate 
					FROM '.TABLE_PREFIX.'ranks r, '.TABLE_PREFIX.'pilots p 
					WHERE p.rank=r.rank 
						AND p.pilotid='.$pilotid;
						
		$payrate = DB::get_row($sql);
		
		$payupdate = self::getPilotPay($flighthours, $payrate->payrate);
		
		$sql = 'UPDATE '.TABLE_PREFIX.'pilots 
					SET totalpay=totalpay+'.$payupdate.'
					WHERE pilotid='.$pilotid;
					
		DB::query($sql);
		
		if(DB::errno() != 0)
			return false;
		
		return true;	
	}
	
	/**
	 * Get the total pay for a flight at a certain rate, 
	 *	for a certain number of hours
	 *
	 * @param float $hours Number of hours in Hours.Minutes format
	 * @param float $rate Hourly rate
	 * @return float Returns the total
	 *
	 */
	public static function getPilotPay($hours, $rate)
	{
		/* Hours are in hours.minutes
			convert to minutes */
		$peices = explode('.', $hours);
		$minutes = ($peices[0] * 60) + $peices[1];
		$payupdate = $minutes * ($rate/60);
		
		return $payupdate;
	}
	
	
	/**
	 * Find and set any pilots as retired
	 *
	 * @return mixed This is the return value description
	 *
	 */
	public static function findRetiredPilots()
	{
		$days = Config::Get('PILOT_INACTIVE_TIME');
		
		if($days == '')
			$days = 90;
			
		$sql = "SELECT * FROM ".TABLE_PREFIX."pilots
				WHERE DATE_SUB(CURDATE(), INTERVAL  {$days} DAY) > `lastlogin`  
					AND `totalflights` = 0 AND `lastlogin` != 0
					AND `retired` = 0";
				  
		$results = DB::get_results($sql);
		
		$sql = "SELECT * FROM ".TABLE_PREFIX."pilots
				WHERE DATE_SUB(CURDATE(), INTERVAL  {$days} DAY) > `lastpirep` 
					AND `totalflights` > 0 AND `lastpirep` != 0
					AND `retired` = 0";
				  
		$results2 = DB::get_results($sql);
		
		// messy but two queries, merge them both
		if(!is_array($results) && !is_array($results2))
		{
			return false;
		}
		else
		{
			if(is_array($results) && is_array($results2))
			{
				$results = array_merge($results, $results2);
			}
			
			if(!is_array($results) && is_array($results2))
			{
				$results = $results2;
			}
		}
		
		if(!$results)
		{
			return false;
		}
		
		foreach($results as $row)
		{
			// Set them retired
			self::updateProfile($row->pilotid, array('retired'=>1));
			
			Template::Set('pilot', $row);
			$pilot_retired_template = Template::Get('email_pilot_retired.tpl', true, true, true);
			
			Util::SendEmail($row->email, Lang::get('email.pilot.retired.subject'), $pilot_retired_template);
		}
	}
	
	
	/**
	 * This saves all of the custom fields attributed to pilot
	 * Pass an associated array (fieldname NOT title) to value
	 *
	 * @param int $pilotid Pilot ID
	 * @param array $list fieldname=>fieldvalue associated array
	 * @return bool Success value
	 *
	 */
	public static function SaveFields($pilotid, $list)
	{
		$allfields = RegistrationData::getCustomFields(true);
		
		if(!$allfields)
			return true;
			
		foreach($allfields as $field)
		{
			$sql = 'SELECT id FROM '.TABLE_PREFIX.'fieldvalues 
						WHERE fieldid='.$field->fieldid.' 
							AND pilotid='.$pilotid;
							
			$res = DB::get_row($sql);

			$fieldname =str_replace(' ', '_', $field->fieldname);
			
			if(!isset($list[$fieldname]))
				continue;
				
			$value = $list[$fieldname];
				
			// if it exists
			if($res)
			{
				$sql = 'UPDATE '.TABLE_PREFIX.'fieldvalues
							SET value="'.$value.'" 
							WHERE fieldid='.$field->fieldid.' AND pilotid='.$pilotid;
			}
			else
			{
				$sql = "INSERT INTO ".TABLE_PREFIX."fieldvalues
						(fieldid, pilotid, value) VALUES ($field->fieldid, $pilotid, '$value')";
			}
			
			DB::query($sql);
		}
		
		return true;
	}
		
	/**
	 * Get all of the custom fields and values for a pilot
	 *
	 * @param int $pilotid The pilot ID
	 * @param bool $inclprivate TRUE to also include private fields (default false)
	 * @return array Returns all of the fields (names and values)
	 *
	 */
	public static function getFieldData($pilotid, $inclprivate=false)
	{
		$sql = 'SELECT f.fieldid, f.title, f.type, f.fieldname, f.value as fieldvalues, v.value, f.public
					FROM '.TABLE_PREFIX.'customfields f
					LEFT JOIN '.TABLE_PREFIX.'fieldvalues v
						ON f.fieldid=v.fieldid
						AND v.pilotid='.$pilotid;
								
		if($inclprivate == false)
			$sql .= ' WHERE f.public=1 ';
			
		return DB::get_results($sql);
	}
	
	
	/**
	 * Get the value of a "custom field" for a pilot
	 *
	 * @param int $pilotid The pilot ID
	 * @param string $title Full title of field, as enter "VATSIM ID"
	 * @return string Returns the value of that field
	 *
	 */
	public static function getFieldValue($pilotid, $title)
	{
		$sql = "SELECT f.fieldid, v.value 
					FROM phpvms_customfields f, phpvms_fieldvalues v 
					WHERE f.fieldid=v.fieldid 
						AND f.title='$title' 
						AND v.pilotid=$pilotid";
						
		$res = DB::get_row($sql);
		return $res->value;
	}
	
	
	/**
	 * Get all of the groups a pilot is a member of
	 *
	 * @param int $pilotid The pilot ID
	 * @return array Groups the pilot is in (groupid and groupname)
	 *
	 */
	public static function getPilotGroups($pilotid)
	{
		$pilotid = DB::escape($pilotid);
		
		$sql = 'SELECT g.groupid, g.name
					FROM ' . TABLE_PREFIX . 'groupmembers u,'.TABLE_PREFIX.'groups g
					WHERE u.pilotid='.$pilotid.' AND g.groupid=u.groupid';
		
		$ret = DB::get_results($sql);
		
		return $ret;
	}
	
	/**
	 * This generates the forum signature of a pilot which
	 *  can be used wherever. It's dynamic, and adjusts it's
	 *  size, etc based on the background image.
	 * 
	 * Each image is output into the /lib/signatures directory,
	 *  and is named by the pilot code+number (ie, VMA0001.png)
	 * 
	 * This is called whenever a PIREP is accepted by an admin,
	 *  as not to burden a server with image generation
	 * 
	 * Also requires GD to be installed on the server
	 * 
	 * @param int The pilot ID for which to generate a signature for
	 * @return bool Success
	 */	 
	public function generateSignature($pilotid)
	{
		$pilot = self::getPilotData($pilotid);
		$pilotcode = self::getPilotCode($pilot->code, $pilot->pilotid);
		
		if(Config::Get('TRANSFER_HOURS_IN_RANKS') === true)
		{
			$totalhours = floatval($pilot->totalhours) + floatval($pilot->transferhours);
		}
		else
		{
			$totalhours = floatval($pilot->totalhours);
		}
		
		# Configure what we want to show on each line
		$output = array();
		$output[] = $pilotcode.' '. $pilot->firstname.' '.$pilot->lastname;
		$output[] = $pilot->rank.', '.$pilot->hub;
		$output[] = 'Total Flights: ' . $pilot->totalflights;
		$output[] = 'Total Hours: ' . $totalhours;
		
		if(Config::Get('SIGNATURE_SHOW_EARNINGS') == true)
		{
			$output[] = 'Total Earnings: ' . $pilot->totalpay;
		}
		
		# Load up our image
		# Get the background image the pilot selected
		if(empty($pilot->bgimage))
			$bgimage = SITE_ROOT.'/lib/signatures/background/background.png';
		else
			$bgimage = SITE_ROOT.'/lib/signatures/background/'.$pilot->bgimage;
		
		if(!file_exists($bgimage))
		{
			# Doesn't exist so use the default
			$bgimage = SITE_ROOT.'/lib/signatures/background/background.png';
			
			if(!file_exists($bgimage))
			{
				return false;
			}
		}
		
		$img = @imagecreatefrompng($bgimage);
		if(!$img)
		{
			$img = imagecreatetruecolor(300, 50);
		}
		
		$height = imagesy($img);
		$width = imagesx($img);
			
		$txtcolor = str_replace('#', '',  Config::Get('SIGNATURE_TEXT_COLOR'));
		$color = sscanf($txtcolor, '%2x%2x%2x');
		$textcolor = imagecolorallocate($img, $color[0], $color[1], $color[2]);
		$font = 3; // Set the font-size
		
		$xoffset = Config::Get('SIGNATURE_X_OFFSET'); # How many pixels, from left, to start
		$yoffset = Config::Get('SIGNATURE_Y_OFFSET'); # How many pixels, from top, to start
		
		$font = Config::Get('SIGNATURE_FONT_PATH');
		$font_size = Config::Get('SIGNATURE_FONT_SIZE');
		
		if(function_exists('imageantialias'))
		{
			imageantialias($img, true);
		}
		
		
		/* Font stuff */
		
		if(!function_exists('imagettftext'))
		{
			Config::Set('SIGNATURE_USE_CUSTOM_FONT', false);
		}
		
		# The line height of each item to fit nicely, dynamic
		
		if(Config::Get('SIGNATURE_USE_CUSTOM_FONT') == false)
		{
			$stepsize = imagefontheight($font);
			$fontwidth = imagefontwidth($font);
		}
		else
		{
			// get the font width and step size
			$bb = imagettfbbox  ( $font_size, 0, $font, 'A');
			
			$stepsize = $bb[3] - $bb[5] + Config::Get('SIGNATURE_FONT_PADDING');
			$fontwidth = $bb[2] - $bb[0];
		}
		
		
		$currline = $yoffset;
		$total = count($output);
		for($i=0;$i<$total;$i++)
		{		
			if(Config::Get('SIGNATURE_USE_CUSTOM_FONT') == false)
			{	
				imagestring($img, $font, $xoffset, $currline, $output[$i], $textcolor);	
			}
			else
			{
				// Use TTF
				$tmp = imagettftext($img, $font_size, 0, $xoffset, $currline, $textcolor, $font, $output[$i]);
				
				// Flag is placed at the end of of the first line, so have that bounding box there
				if($i==0)
				{
					$flag_bb = $tmp;
				}
			}
			
			$currline+=$stepsize;
		}
		
		# Add the country flag, line it up with the first line, which is the
		#	pilot code/name
		$country = strtolower($pilot->location);
		if(file_exists(SITE_ROOT.'/lib/images/countries/'.$country.'.png'))
		{
			$flagimg = imagecreatefrompng(SITE_ROOT.'/lib/images/countries/'.$country.'.png');
			
			if(Config::Get('SIGNATURE_USE_CUSTOM_FONT') == false)
			{	
				$ret = imagecopy($img, $flagimg, strlen($output[0])*$fontwidth, 
							($yoffset+($stepsize/2)-5.5), 0, 0, 16, 11);
			}
			else
			{
				# figure out where it would go 
				$ret = imagecopy($img, $flagimg, $flag_bb[4]+5, $flag_bb[5]+2, 0, 0, 16, 11);
			}
		}
							
		# Add the Rank image
		if(Config::Get('SIGNATURE_SHOW_RANK_IMAGE') == true && $pilot->rankimage!=''
				&& file_exists($pilot->rankimage))
		{
			$ext = substr($pilot->rankimage, strlen($pilot->rankimage)-3, 3);
		
			# Get the rank image type, just jpg, gif or png
			if($ext == 'png')
				$rankimg = @imagecreatefrompng($pilot->rankimage);
			elseif($ext == 'gif')
				$rankimg = @imagecreatefromgif($pilot->rankimage);
			else	
				$rankimg = @imagecreatefromjpg($pilot->rankimage);
				
			if(!$rankimg) { echo '';}
			else 
			{		
				$r_width = imagesx($rankimg);
				$r_height = imagesy($rankimg);
				
				imagecopy($img, $rankimg, $width-$r_width-$xoffset, $yoffset, 0, 0, $r_width, $r_height);
			}
		}	
		
		if(Config::Get('SIGNATURE_SHOW_COPYRIGHT') == true)
		{
			#
			#  DO NOT remove this, as per the phpVMS license
			$font = 1;
			$text = 'powered by phpvms, '. SITE_NAME.' ';
			imagestring($img, $font, $width-(strlen($text)*imagefontwidth($font)), 
						$height-imagefontheight($font), $text, $textcolor);
		}
		
		imagepng($img, SITE_ROOT.SIGNATURE_PATH.'/'.$pilotcode.'.png', 1);
		imagedestroy($img);
	}
}