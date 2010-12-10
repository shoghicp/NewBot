<?php
/*
  _    _                                      _                   _                 
 | |  | |                                    | |                 | |                
 | |  | |   __ _    __ _   _ __ ___     ___  | |   __ _   _ __   | |   __ _   _   _ 
 | |  | |  / _` |  / _` | | '_ ` _ \   / _ \ | |  / _` | | '_ \  | |  / _` | | | | |
 | |__| | | (_| | | (_| | | | | | | | |  __/ | | | (_| | | |_) | | | | (_| | | |_| |
  \____/   \__, |  \__,_| |_| |_| |_|  \___| |_|  \__,_| | .__/  |_|  \__,_|  \__, |
            __/ |                                        | |                   __/ |
           |___/                                         |_|                  |___/ 


    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 

 *
 * @author shoghicp@gmail.com
 *


WARNING!!!
	This class is obsolete and it's only used to learn and view evolution of code. 

*/
 
function scmp( $a, $b ) {
	 mt_srand((double)microtime()*1000000);
     return mt_rand(-1,1);
}

function UpdateBots(){
	$allbots = doquery("SELECT * FROM {{table}};", 'bots');
	while($bot = mysqli_fetch_array($allbots)){
		if(($bot['last_time'] + $bot['every_time']) < time()){
			$player = doquery("SELECT * FROM {{table}} WHERE `id` = '".$bot['player']."';", 'users', true);
			$thebot = new Bot($player, $bot);
			$thebot->PlayAll();
			unset($thebot);
		}	
	}
	unset($bot);
	unset($allbots);
}
class BotDatabase{
	private $SQLite;
	
	function __construct($Database){
		global $ugamelaplay_root_dir;
		if(!file_exists($ugamelaplay_root_dir.'/bot/db/'.$Database.'.ugadb')){		
			$this->SQLite = new SQLiteDatabase($ugamelaplay_root_dir.'/bot/db/'.$Database.'.ugadb');
			$this->SQLite->query("CREATE TABLE [actions] (
				[id] INTEGER  NOT NULL PRIMARY KEY,
				[function] TEXT  NOT NULL,
				[parameters] TEXT  NOT NULL,
				[priority] FLOAT DEFAULT '1' NOT NULL,
				[time] TIME  NOT NULL
				);

				CREATE TABLE [config] (
				[name] TEXT  UNIQUE NOT NULL PRIMARY KEY,
				[value] TEXT  NULL
				);

				CREATE TABLE [objetives] (
				[id] INTEGER  NOT NULL PRIMARY KEY,
				[id_user] INTEGER  NOT NULL,
				[id_planet] INTEGER  NOT NULL,
				[galaxy] INTEGER  NOT NULL,
				[system] INTEGER  NOT NULL,
				[planet] INTEGER  NOT NULL,
				[planet_type] INTEGER DEFAULT '1' NOT NULL,
				[priority] FLOAT DEFAULT '1' NOT NULL
				);");
		}else{
			$this->SQLite = new SQLiteDatabase($ugamelaplay_root_dir.'/bot/db/'.$Database.'.ugadb');
		}
	}
	
	function doquery($query, $table, $fetch){
	
	}

}

class Bot{
	protected $player;
	protected $Bot;
	var $Database;
	protected $CurrentPlanet;
	var $VERSION;
	
	function __construct($player, $bot){
		$this->VERSION = '0.2';
		$this->player = $player;
		$this->Bot = $bot;
		$this->Database = new BotDatabase(md5($player['id']));
	}
	function PlayAll(){
		global $resource, $BotString;
		$this->HandleMessages();
		$iPlanetCount =  doquery ("SELECT count(*) AS `total` FROM {{table}} WHERE `id_owner` = '". $this->player['id'] ."' AND `planet_type` = '1'", 'planets',true);
		$maxfleet  = doquery("SELECT COUNT(fleet_owner) AS `actcnt` FROM {{table}} WHERE `fleet_owner` = '".$this->player['id']."';", 'fleets', true);
		$maxcolofleet  = doquery("SELECT COUNT(fleet_owner) AS `total` FROM {{table}} WHERE `fleet_owner` = '".$this->player['id']."' AND `fleet_mission` = '7';", 'fleets', true);
		$MaxFlyingFleets     = $maxfleet['actcnt'];
		$MaxFlottes         = $this->player[$resource[108]];
		$planetselected = false;
		$planetwork = false;
		$planetquery = doquery("SELECT * FROM {{table}} WHERE `id_owner` = '".$this->player['id']."' AND `planet_type` = '1' ORDER BY `id` ASC;",'planets', false);
		while($this->CurrentPlanet = mysqli_fetch_array($planetquery) ){
			if($planetselected == true and $this->CurrentPlanet['id_owner'] == $this->player['id']){
				CheckPlanetUsedFields ( $this->CurrentPlanet );
				//UpdatePlanetBatimentQueueList ( $planetrow, $user );
				$Queue = ShowBuildingQueue ( $this->CurrentPlanet, $this->player );
				if($BotString){
					$BotString .= '<tr><td colspan="2" class="c">'.$this->player['username'].'</td><tr><th>Planeta actual</th><th>'.$this->CurrentPlanet['name'].' ['.$this->CurrentPlanet['id'].']</th></tr></tr>';
				}
				$repeat = false;
				$this->BuildFleet($repeat);
				
				$this->BuildDefense($repeat);


				$this->BuildStores($Queue);
				if($iPlanetCount['total'] < 10 and $iPlanetCount['total'] < (8 + $this->player[$resource[150]]) and $maxcolofleet['total'] < ((8 + $this->player[$resource[150]]) - $maxcolofleet['total']) and $MaxFlyingFleets < $MaxFlottes and $this->CurrentPlanet[$resource[208]] >= 1 ){
					$this->Colonize();
				}
				$this->ResearchTechs();

				$this->BuildMines($Queue);
				$this->BuildUtils($Queue);
				if($this->CurrentPlanet['id'] == $this->player['id_planet']){
					if($MaxFlyingFleets < ($MaxFlottes + 1)){
						$this->HandleOtherFleets();
					}
				}elseif($MaxFlyingFleets < $MaxFlottes){
					$this->GetFleet();
				}
				$this->Update();
				$planetselected = false;
				$planetwork = true;
				$planetid = $this->CurrentPlanet['id'];
			}else{
				if($this->CurrentPlanet['id'] == $this->Bot['last_planet']){
					$planetselected = true;				
				}
			}
		}
		if($planetwork == false){
				$this->CurrentPlanet = doquery("SELECT * FROM {{table}} WHERE `id` = '".$this->player['id_planet']."';",'planets', true);
				CheckPlanetUsedFields ( $this->CurrentPlanet );
				//UpdatePlanetBatimentQueueList ( $planetrow, $user );
				$Queue = ShowBuildingQueue ( $this->CurrentPlanet, $this->player );
				if($BotString){
					$BotString .= '<tr><td colspan="2" class="c">'.$this->player['username'].'</td><tr><th>Planeta actual</th><th>'.$this->CurrentPlanet['name'].' ['.$this->CurrentPlanet['id'].']</th></tr></tr>';
				}
				$repeat = false;
				$this->BuildFleet($repeat);
				
				$this->BuildDefense($repeat);


				$this->BuildStores($Queue);
				if($iPlanetCount['total'] < 10 and $iPlanetCount['total'] < (8 + $this->player[$resource[150]]) and $maxcolofleet['total'] < ((8 + $this->player[$resource[150]]) - $maxcolofleet['total']) and $MaxFlyingFleets < $MaxFlottes and $this->CurrentPlanet[$resource[208]] >= 1 ){
					$this->Colonize();
				}
				$this->ResearchTechs();
				$this->BuildMines($Queue);
				$this->BuildUtils($Queue);
				
				if($this->CurrentPlanet['id'] == $this->player['id_planet']){
					if($MaxFlyingFleets < ($MaxFlottes + 1)){
						$this->HandleOtherFleets();
					}
				}elseif($MaxFlyingFleets < $MaxFlottes){
					$this->GetFleet();
				}
				$this->Update();
				$planetid = $this->player['id_planet'];		
		}
		$this->End($planetid);
	}
	
	protected function HandleMessages(){
		$UsrMess       = doquery("SELECT * FROM {{table}} WHERE `message_owner` = '".$this->player['id']."' AND `message_type` = '1' AND `delete` = '0' ORDER BY `message_time` DESC;", 'messages');
		while ($CurMess =  mysqli_fetch_array($UsrMess)) {
			if($CurMess['message_sender'] != $this->player['id'] and $CurMess['message_sender'] != 0){
				SendSimpleMessage ( $CurMess['message_sender'], $this->player['id'], '', 1, $this->player['username'], 'Respuesta', 'Soy un Bot, no me digas nada');
				doquery("UPDATE {{table}} SET `message_read` = 1 WHERE `message_id` = '".$CurMess['message_id']."';", 'messages');
			}
		}
	
	}
	protected function Colonize(){
		global $resource, $pricelist;	
			$planet = mt_rand(1, MAX_PLANET_IN_SYSTEM);
			$system = mt_rand(1, MAX_SYSTEM_IN_GALAXY);
			$galaxy = mt_rand(1, MAX_GALAXY_IN_WORLD);
			$Colo = doquery("SELECT count(*) AS `total` FROM {{table}} WHERE `galaxy` = '".$galaxy."' AND `system` = '".$system."' AND `planet` = '".$planet."' AND `planet_type` = '1';", 'planets', true);
			if($Colo['total'] == 0){
				$fleetarray         = array(208 => 1);
				$AllFleetSpeed  = GetFleetMaxSpeed ($fleetarray, 0, $this->player);
				$MaxFleetSpeed  = min($AllFleetSpeed);
				$distance      = GetTargetDistance ( $this->CurrentPlanet['galaxy'], $galaxy, $this->CurrentPlanet['system'], $system, $this->CurrentPlanet['planet'], $planet );
				$duration      = GetMissionDuration ( 10, $MaxFleetSpeed, $distance, GetGameSpeedFactor () );
				$consumption   = GetFleetConsumption ( $fleetarray, GetGameSpeedFactor (), $duration, $distance, $MaxFleetSpeed, $this->player );
				$StayDuration    = 0;
				$StayTime        = 0;
				$fleet['start_time'] = $duration + time();
				$fleet['end_time']   = $StayDuration + (2 * $duration) + time();
				$FleetStorage        = 0;
				$fleet_array2 = '';
				$FleetShipCount      = 0;
				$FleetSubQRY         = "";
				foreach ($fleetarray as $Ship => $Count) {
					$FleetStorage    += $pricelist[$Ship]["capacity"] * $Count;
					$FleetShipCount  += $Count;
					$fleet_array2     .= $Ship .",". $Count .";";
					$FleetSubQRY     .= "`".$resource[$Ship] . "` = `" . $resource[$Ship] . "` - " . $Count . " , ";
				}
				
				$QryInsertFleet  = "INSERT INTO {{table}} SET ";
				$QryInsertFleet .= "`fleet_owner` = '". $this->player['id'] ."', ";
				$QryInsertFleet .= "`fleet_mission` = '7', ";
				$QryInsertFleet .= "`fleet_amount` = '". $FleetShipCount ."', ";
				$QryInsertFleet .= "`fleet_array` = '". $fleet_array2 ."', ";
				$QryInsertFleet .= "`fleet_start_time` = '". $fleet['start_time'] ."', ";
				$QryInsertFleet .= "`fleet_start_galaxy` = '". $this->CurrentPlanet['galaxy'] ."', ";
				$QryInsertFleet .= "`fleet_start_system` = '". $this->CurrentPlanet['system'] ."', ";
				$QryInsertFleet .= "`fleet_start_planet` = '". $this->CurrentPlanet['planet'] ."', ";
				$QryInsertFleet .= "`fleet_start_type` = '". $this->CurrentPlanet['planet_type'] ."', ";
				$QryInsertFleet .= "`fleet_end_time` = '". $fleet['end_time'] ."', ";
				$QryInsertFleet .= "`fleet_end_stay` = '". $StayTime ."', ";
				$QryInsertFleet .= "`fleet_end_galaxy` = '". $galaxy ."', ";
				$QryInsertFleet .= "`fleet_end_system` = '". $system ."', ";
				$QryInsertFleet .= "`fleet_end_planet` = '". $planet ."', ";
				$QryInsertFleet .= "`fleet_end_type` = '1', ";
				$QryInsertFleet .= "`fleet_resource_metal` = '0', ";
				$QryInsertFleet .= "`fleet_resource_crystal` = '0', ";
				$QryInsertFleet .= "`fleet_resource_deuterium` = '0', ";
				$QryInsertFleet .= "`fleet_resource_hidrogeno` = '0', ";
				$QryInsertFleet .= "`fleet_target_owner` = '0', ";
				$QryInsertFleet .= "`fleet_group` = '0', ";
				$QryInsertFleet .= "`start_time` = '". time() ."';";
				doquery( $QryInsertFleet, 'fleets');
				$QryUpdatePlanet  = "UPDATE {{table}} SET ";
				$QryUpdatePlanet .= $FleetSubQRY;
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."' ";
				$QryUpdatePlanet .= "WHERE ";
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."'";
				doquery("LOCK TABLE {{table}} WRITE", 'planets');
				doquery ($QryUpdatePlanet, "planets");
				doquery("UNLOCK TABLES", '');
				$this->CurrentPlanet["deuterium"]  -= $consumption;
			}else{
				$this->Colonize();
			}

		
	}
	protected function GetFleet(){
		global $resource, $reslist, $pricelist;
			$planet = $this->player['planet'];
			$system = $this->player['system'];
			$galaxy = $this->player['galaxy'];
			$fleetarray = array();
			$totalships = 0;
			foreach($reslist['fleet'] as $Element){
				if($this->CurrentPlanet[$resource[$Element]] > 0 and $Element != 212){
					$fleetarray[$Element] = $this->CurrentPlanet[$resource[$Element]];
					$totalships += $this->CurrentPlanet[$resource[$Element]];
				}
			}
			if($totalships > 5000){
				$AllFleetSpeed  = GetFleetMaxSpeed ($fleetarray, 0, $this->player);
				$MaxFleetSpeed  = min($AllFleetSpeed);
				$distance      = GetTargetDistance ( $this->CurrentPlanet['galaxy'], $galaxy, $this->CurrentPlanet['system'], $system, $this->CurrentPlanet['planet'], $planet );
				$duration      = GetMissionDuration ( 10, $MaxFleetSpeed, $distance, GetGameSpeedFactor () );
				$consumption   = GetFleetConsumption ( $fleetarray, GetGameSpeedFactor (), $duration, $distance, $MaxFleetSpeed, $this->player );
				$StayDuration    = 0;
				$StayTime        = 0;
				$fleet['start_time'] = $duration + time();
				$fleet['end_time']   = $StayDuration + (2 * $duration) + time();
				$FleetStorage        = 0;
				$fleet_array2 = '';
				$FleetShipCount      = 0;
				$FleetSubQRY         = "";
				$Mining = array();
				foreach ($fleetarray as $Ship => $Count) {
					$FleetStorage    += $pricelist[$Ship]["capacity"] * $Count;
					$FleetShipCount  += $Count;
					$fleet_array2     .= $Ship .",". $Count .";";
					$FleetSubQRY     .= "`".$resource[$Ship] . "` = `" . $resource[$Ship] . "` - " . $Count . " , ";
				}
				$FleetStorage        -= $consumption;
				if (($this->CurrentPlanet['metal']) > ($FleetStorage / 3)) {
					$Mining['metal']   = $FleetStorage / 3;
					$FleetStorage      = $FleetStorage - $Mining['metal'];
				} else {
					$Mining['metal']   = $this->CurrentPlanet['metal'];
					$FleetStorage      = $FleetStorage - $Mining['metal'];
				}
				if (($this->CurrentPlanet['crystal']) > ($FleetStorage / 2)) {
					$Mining['crystal'] = $FleetStorage / 2;
					$FleetStorage      = $FleetStorage - $Mining['crystal'];
				} else {
					$Mining['crystal'] = $this->CurrentPlanet['crystal'];
					$FleetStorage      = $FleetStorage - $Mining['crystal'];
				}
				if (($this->CurrentPlanet['deuterium']) > $FleetStorage) {
					$Mining['deuterium']  = $FleetStorage;
					$FleetStorage      = $FleetStorage - $Mining['deuterium'];
				} else {
					$Mining['deuterium']  = $this->CurrentPlanet['deuterium'];
					$FleetStorage      = $FleetStorage - $Mining['deuterium'];
				}				
				$QryInsertFleet  = "INSERT INTO {{table}} SET ";
				$QryInsertFleet .= "`fleet_owner` = '". $this->player['id'] ."', ";
				$QryInsertFleet .= "`fleet_mission` = '4', ";
				$QryInsertFleet .= "`fleet_amount` = '". $FleetShipCount ."', ";
				$QryInsertFleet .= "`fleet_array` = '". $fleet_array2 ."', ";
				$QryInsertFleet .= "`fleet_start_time` = '". $fleet['start_time'] ."', ";
				$QryInsertFleet .= "`fleet_start_galaxy` = '". $this->CurrentPlanet['galaxy'] ."', ";
				$QryInsertFleet .= "`fleet_start_system` = '". $this->CurrentPlanet['system'] ."', ";
				$QryInsertFleet .= "`fleet_start_planet` = '". $this->CurrentPlanet['planet'] ."', ";
				$QryInsertFleet .= "`fleet_start_type` = '". $this->CurrentPlanet['planet_type'] ."', ";
				$QryInsertFleet .= "`fleet_end_time` = '". $fleet['end_time'] ."', ";
				$QryInsertFleet .= "`fleet_end_stay` = '". $StayTime ."', ";
				$QryInsertFleet .= "`fleet_end_galaxy` = '". $galaxy ."', ";
				$QryInsertFleet .= "`fleet_end_system` = '". $system ."', ";
				$QryInsertFleet .= "`fleet_end_planet` = '". $planet ."', ";
				$QryInsertFleet .= "`fleet_end_type` = '1', ";
				$QryInsertFleet .= "`fleet_resource_metal` = '".$Mining['metal']."', ";
				$QryInsertFleet .= "`fleet_resource_crystal` = '".$Mining['crystal']."', ";
				$QryInsertFleet .= "`fleet_resource_deuterium` = '".$Mining['deuterium']."', ";
				$QryInsertFleet .= "`fleet_resource_hidrogeno` = '0', ";
				$QryInsertFleet .= "`fleet_target_owner` = '0', ";
				$QryInsertFleet .= "`fleet_group` = '0', ";
				$QryInsertFleet .= "`start_time` = '". time() ."';";
				doquery( $QryInsertFleet, 'fleets');
				$QryUpdatePlanet  = "UPDATE {{table}} SET ";
				$QryUpdatePlanet .= $FleetSubQRY;
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."' ";
				$QryUpdatePlanet .= "WHERE ";
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."'";
				doquery("LOCK TABLE {{table}} WRITE", 'planets');
				doquery ($QryUpdatePlanet, "planets");
				doquery("UNLOCK TABLES", '');
				$this->CurrentPlanet["metal"]  -= $Mining['metal'];
				$this->CurrentPlanet["crystal"]  -= $Mining['crystal'];
				$this->CurrentPlanet["deuterium"]  -= $consumption + $Mining['deuterium'];
			}
		
	}
	protected function GetProductionLevel(){
			if(($this->CurrentPlanet['energy_max'] == 0 &&
				$this->CurrentPlanet['energy_used'] > 0) or $this->player['urlaubs_modus'] == 1) {
				$prod = 0;
			} elseif ($this->CurrentPlanet['energy_max']  > 0 &&
				abs($this->CurrentPlanet['energy_used']) > $this->CurrentPlanet['energy_max']) {
				$prod = floor(($this->CurrentPlanet['energy_max']) / $this->CurrentPlanet['energy_used'] * 100);
			} elseif ($this->CurrentPlanet['energy_max'] == 0 &&
				abs($this->CurrentPlanet['energy_used']) > $this->CurrentPlanet['energy_max']) {
				$prod = 0;
			} else {
				$prod = 100;
			}
			if ($prod > 100) {
				$prod = 100;
			}
			
		return $prod;
	}
	protected function BuildMines(&$Queue2){
		global $resource;		
		$MinesLevel = array(1 => 230, 2 => 230, 3 => 230, 5 => 100);
		if($this->CurrentPlanet[$resource[4]] < 1000 and $this->GetProductionLevel() < 100 and $this->CurrentPlanet["field_current"] < ( CalculateMaxPlanetFields($this->CurrentPlanet) - $Queue2['lenght'] ) and $Queue2['lenght'] < 2){
			$this->Build(4, $Queue2['buildingarray']);			
			++$Queue2['lenght'];
		}
		uasort( $MinesLevel, 'scmp' );
		foreach($MinesLevel as $Element => $Max){
			if($Element != 0 and IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Element) and $this->CurrentPlanet[$resource[$Element]] < $Max and $this->GetProductionLevel() == 100 and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and ($this->CurrentPlanet["field_current"] + $Queue2['lenght']) <  CalculateMaxPlanetFields($this->CurrentPlanet)){
					if($this->CurrentPlanet[$resource[1]] >= $this->CurrentPlanet[$resource[$Element]] and $this->CurrentPlanet[$resource[2]] >= $this->CurrentPlanet[$resource[$Element]] and $this->CurrentPlanet[$resource[3]] >= $this->CurrentPlanet[$resource[$Element]]){
						$this->Build($Element, $Queue2['buildingarray']);
						++$Queue2['lenght'];	
						break;
					}
			}
		}
	}
	protected function BuildStores(&$Queue2){
		global $resource;
		
		$StoreLevel = array(22 => 20, 23 => 20, 24 => 20, 25 => 20);
		foreach($StoreLevel as $Element => $Max){

			if($Element == 22){
				if($this->CurrentPlanet[$resource[$Element]] < $Max and $this->CurrentPlanet['metal'] >= $this->CurrentPlanet['metal_max'] and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and $this->CurrentPlanet["field_current"] < ( CalculateMaxPlanetFields($this->CurrentPlanet) - $Queue2['lenght'] +1 )){
					$this->Build($Element, $Queue2['buildingarray']);
					++$Queue2['lenght'];break;
				}
			}elseif($Element == 23){
				if($this->CurrentPlanet[$resource[$Element]] < $Max and $this->CurrentPlanet['crystal'] >= $this->CurrentPlanet['crystal_max'] and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and $this->CurrentPlanet["field_current"] < ( CalculateMaxPlanetFields($this->CurrentPlanet) - $Queue2['lenght'] +1 )){
					$this->Build($Element, $Queue2['buildingarray']);
					++$Queue2['lenght'];break;
				}
			}elseif($Element == 24){
				if($this->CurrentPlanet[$resource[$Element]] < $Max and $this->CurrentPlanet['deuterium'] >= $this->CurrentPlanet['deuterium_max'] and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and $this->CurrentPlanet["field_current"] < ( CalculateMaxPlanetFields($this->CurrentPlanet) - $Queue2['lenght'] +1 )){
					$this->Build($Element, $Queue2['buildingarray']);
					++$Queue2['lenght'];break;
				}
			}elseif($Element == 25){
				if($this->CurrentPlanet[$resource[$Element]] < $Max and $this->CurrentPlanet['hidrogeno'] >= $this->CurrentPlanet['hidrogeno_max'] and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and $this->CurrentPlanet["field_current"] < ( CalculateMaxPlanetFields($this->CurrentPlanet) - $Queue2['lenght'] +1 )){
					$this->Build($Element, $Queue2['buildingarray']);
					++$Queue2['lenght'];break;
				}
			}elseif($Element == 0){
			}
		}
	}

	protected function BuildUtils(&$Queue2){
		global $resource;
		
		$UtilLevel =  array(33 => 100, 14 => 20, 15 => 10, 21 => 17, 31 => 16 );
		uasort( $UtilLevel, 'scmp' );
		foreach($UtilLevel as $Element => $Max){
			if($Element != 0 and $Element != 33 and IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Element) and $this->CurrentPlanet[$resource[$Element]] < $Max and $this->GetProductionLevel() == 100 and $Queue2['lenght'] < 2 and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false) and ($this->CurrentPlanet["field_current"] + $Queue2['lenght']) <  CalculateMaxPlanetFields($this->CurrentPlanet)){
				$this->Build($Element, $Queue2['buildingarray']);
				++$Queue2['lenght'];
				break;
			}elseif($Element == 33 and $this->CurrentPlanet["field_current"] >= CalculateMaxPlanetFields($this->CurrentPlanet) and IsElementBuyable ($this->player, $this->CurrentPlanet, $Element, true, false)){
				$this->Build($Element, $Queue2['buildingarray']);
				 ++$Queue2['lenght'];
				 break;
			}		
		}
	}
	protected function ResearchTechs(){
		global $resource;
		if (CheckLabSettingsInQueue ( $this->CurrentPlanet ) == true) {		
			$TechLevel =  array(122 => 5, 150 => 60, 114 => 9, 118 => 11, 109 => 20, 108 => 20, 113 => 12, 115 => 8, 117 => 8, 124 => 3, 120 => 12, 106 => 12, 111 => 4, 110 => 20, 121 => 7, 199 => 1  );
			uasort( $TechLevel, 'scmp' );
			foreach($TechLevel as $Techno => $Max){
				if($Techno == 0){
				}elseif( $this->player[$resource[$Techno]] < $Max and IsElementBuyable($this->player, $this->CurrentPlanet, $Techno) and IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Techno)){
					$this->Research($Techno);
					break;
				}		
			}
		}
	}

	protected function BuildFleet(&$repeat){
		global $resource;		
			$FleetLevel =  array(212 => 300,218 => 200, 219 => 150, 215 => 150, 214 => 50, 211 => 200, 207 => 500, 209 => 500, 202 => 200,203 => 150, 204 => 345, 205 => 100, 206 => 30, 208 => 1, 210 => 20, 213 => 100);
			uasort( $FleetLevel, 'scmp' );
			foreach($FleetLevel as $Element => $Max){
				if($Element == 0){
					continue;
				}
				if($Element == 212 and $this->GetProductionLevel() == 100){
					continue;
				}
				if($Element == 218 and $this->GetProductionLevel() == 100){
					continue;
				}
				$MaxElements   = GetMaxConstructibleElements ( $Element,$this->CurrentPlanet );
				$Count = $MaxElements;
				if ($Count > $Max) {
					$Count = $Max;
				}
				if(IsElementBuyable($this->player, $this->CurrentPlanet, $Element) and IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Element)){
					$this->HangarBuild($Element, $Count, $repeat);
				}		
			}
	}
	protected function BuildDefense(&$repeat){
		global $resource;	
			$DefLevel =  array(401 => 150,402 => 150, 403 => 90, 403 => 110,404 => 70,  406 => 50, 407 => 1, 408 => 1 );
			uasort( $DefLevel, 'scmp' );
			foreach($DefLevel as $Element => $Max){
				if($Element == 0){
					continue;
				}
				$MaxElements   = GetMaxConstructibleElements ( $Element,$this->CurrentPlanet );
				$Count = $MaxElements;
				if ($Count > $Max) {
					$Count = $Max;
				}
				if( IsElementBuyable($this->player, $this->CurrentPlanet, $Element) and IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Element)){
					$this->HangarBuild($Element, $Count, $repeat);
				}		
			}
	}		
	protected function Research($Techno){
        if ( IsTechnologieAccessible($this->player, $this->CurrentPlanet, $Techno) && IsElementBuyable($this->player, $this->CurrentPlanet, $Techno) ) {
			$costs                        = GetBuildingPrice($this->player, $this->CurrentPlanet, $Techno);
			$this->CurrentPlanet['metal']      -= $costs['metal'];
			$this->CurrentPlanet['crystal']    -= $costs['crystal'];
			$this->CurrentPlanet['deuterium']  -= $costs['deuterium'];
			$this->CurrentPlanet['hidrogeno']  -= $costs['hidrogeno'];
			$this->CurrentPlanet["b_tech_id"]   = $Techno;
			$this->CurrentPlanet["b_tech"]      = time() + GetBuildingTime($this->player, $this->CurrentPlanet, $Techno);
			$this->player["b_tech_planet"] = $this->CurrentPlanet["id"];
			
			$QryUpdatePlanet  = "UPDATE {{table}} SET ";
			$QryUpdatePlanet .= "`b_tech_id` = '".   $this->CurrentPlanet['b_tech_id']   ."', ";
			$QryUpdatePlanet .= "`b_tech` = '".      $this->CurrentPlanet['b_tech']      ."' ";
			$QryUpdatePlanet .= "WHERE ";
			$QryUpdatePlanet .= "`id` = '".          $this->CurrentPlanet['id']          ."';";
			doquery( $QryUpdatePlanet, 'planets');

			$QryUpdateUser  = "UPDATE {{table}} SET ";
			$QryUpdateUser .= "`b_tech_planet` = '". $this->player['b_tech_planet'] ."' ";
			$QryUpdateUser .= "WHERE ";
			$QryUpdateUser .= "`id` = '".            $this->player['id']            ."';";
			doquery( $QryUpdateUser, 'users');
		}
	}
	protected function Build($Element, $QueueArray2){
	global $resource;
        //if($QueueArray2[$Element] <= $this->CurrentPlanet[$resource[$Element]]){
			AddBuildingToQueue ( $this->CurrentPlanet, $this->player, $Element, true );
			BuildingSavePlanetRecord ($this->CurrentPlanet );
			BuildingSaveUserRecord ( $this->CurrentPlanet );
		//}
	}
	
	protected function HangarBuild($Element, $Count, &$repeat){
        $Ressource = GetElementRessources ( $Element, $Count );
		$BuildTime = GetBuildingTime($this->player,$this->CurrentPlanet, $Element, 1);
		if (($Count >= 1 and $this->CurrentPlanet['b_hangar_id'] == "") or ($Count >= 1 and $repeat == true)) {
			$this->CurrentPlanet['metal']           -= $Ressource['metal'];
			$this->CurrentPlanet['crystal']         -= $Ressource['crystal'];
			$this->CurrentPlanet['deuterium']       -= $Ressource['deuterium'];
			$this->CurrentPlanet['hidrogeno']       -= $Ressource['hidrogeno'];
			$this->CurrentPlanet['b_hangar_id']     .= "". $Element .",". $Count .";";
			$repeat = true;
		}
	}	
	protected function HandleOwnFleets(){
		$_fleets = doquery("SELECT * FROM {{table}} WHERE (`fleet_start_time` <= '".time()."') OR (`fleet_end_time` <= '".time()."');", 'fleets'); //  OR fleet_end_time <= ".time()
		while ($row =  mysqli_fetch_array($_fleets)) {
			//Actualizar solo flotas que afecten al jugador actual
			if($row['fleet_owner'] == $this->player['id'] or $row['fleet_target_owner'] == $this->player['id']){
				$array                = array();
				$array['galaxy']      = $row['fleet_start_galaxy'];
				$array['system']      = $row['fleet_start_system'];
				$array['planet']      = $row['fleet_start_planet'];
				if($row['fleet_start_time'] <= time()){
					$array['planet_type'] = $row['fleet_start_type'];
				}else{
					$array['planet_type'] = $row['fleet_end_type'];
				}

				FlyingFleetHandler($array);
				unset($array);
			}
			unset($row);
		}
		unset($_fleets);
	}
	protected function HandleOtherFleets(){
	global $resource, $reslist, $pricelist;
		$_fleets = doquery("SELECT * FROM {{table}} WHERE `fleet_owner` != '".$this->player['id']."' AND `fleet_target_owner` = '".$this->player['id']."' AND `fleet_end_time` <= ".time().";", 'fleets');
		while ($row =  mysqli_fetch_array($_fleets)) {
			//Actualizar solo flotas que afecten al jugador actual
			if(($row['fleet_mission'] == 1 or $row['fleet_mission'] == 2 or $row['fleet_mission'] == 11) and ($row['fleet_end_galaxy'] == $this->CurrentPlanet['galaxy'] and $row['fleet_end_system'] == $this->CurrentPlanet['system'] and $row['fleet_end_planet'] == $this->CurrentPlanet['planet'])){
				$array                = array();
				$array['galaxy']      = $row['fleet_start_galaxy'];
				$array['system']      = $row['fleet_start_system'];
				$array['planet']      = $row['fleet_start_planet'];
				if($row['fleet_start_time'] <= time()){
					$array['planet_type'] = $row['fleet_start_type'];
				}else{
					$array['planet_type'] = $row['fleet_end_type'];
				}

				FlyingFleetHandler($array);
				unset($array);
				$planet = $this->player['planet'];
				$system = $this->player['system'];
				$galaxy = $this->player['galaxy'];
				$fleetarray = array();
				$totalships = 0;
				foreach($reslist['fleet'] as $Element){
					if($this->CurrentPlanet[$resource[$Element]] > 0 and $Element != 212){
						$fleetarray[$Element] = $this->CurrentPlanet[$resource[$Element]];
						$totalships += $this->CurrentPlanet[$resource[$Element]];
					}
				}
				if($totalships > 0){
				$AllFleetSpeed  = GetFleetMaxSpeed ($fleetarray, 0, $this->player);
				$MaxFleetSpeed  = min($AllFleetSpeed);
				$distance      = GetTargetDistance ( $this->CurrentPlanet['galaxy'], $galaxy, $this->CurrentPlanet['system'], $system, $this->CurrentPlanet['planet'], $planet );
				$duration      = GetMissionDuration ( 1, $MaxFleetSpeed, $distance, GetGameSpeedFactor () );
				$consumption   = GetFleetConsumption ( $fleetarray, GetGameSpeedFactor (), $duration, $distance, $MaxFleetSpeed, $this->player );
				$StayDuration    = 0;
				$StayTime        = 0;
				$fleet['start_time'] = $duration + time();
				$fleet['end_time']   = $StayDuration + (2 * $duration) + time();
				$FleetStorage        = 0;
				$fleet_array2 = '';
				$FleetShipCount      = 0;
				$FleetSubQRY         = "";
				foreach ($fleetarray as $Ship => $Count) {
					$FleetStorage    += $pricelist[$Ship]["capacity"] * $Count;
					$FleetShipCount  += $Count;
					$fleet_array2     .= $Ship .",". $Count .";";
					$FleetSubQRY     .= "`".$resource[$Ship] . "` = `" . $resource[$Ship] . "` - " . $Count . " , ";
				}
				$FleetStorage        -= $consumption;
				if (($this->CurrentPlanet['metal']) > ($FleetStorage / 3)) {
					$Mining['metal']   = $FleetStorage / 3;
					$FleetStorage      = $FleetStorage - $Mining['metal'];
				} else {
					$Mining['metal']   = $this->CurrentPlanet['metal'];
					$FleetStorage      = $FleetStorage - $Mining['metal'];
				}
				if (($this->CurrentPlanet['crystal']) > ($FleetStorage / 2)) {
					$Mining['crystal'] = $FleetStorage / 2;
					$FleetStorage      = $FleetStorage - $Mining['crystal'];
				} else {
					$Mining['crystal'] = $this->CurrentPlanet['crystal'];
					$FleetStorage      = $FleetStorage - $Mining['crystal'];
				}
				if (($this->CurrentPlanet['deuterium']) > $FleetStorage) {
					$Mining['deuterium']  = $FleetStorage;
					$FleetStorage      = $FleetStorage - $Mining['deuterium'];
				} else {
					$Mining['deuterium']  = $this->CurrentPlanet['deuterium'];
					$FleetStorage      = $FleetStorage - $Mining['deuterium'];
				}				
				$QryInsertFleet  = "INSERT INTO {{table}} SET ";
				$QryInsertFleet .= "`fleet_owner` = '". $this->player['id'] ."', ";
				$QryInsertFleet .= "`fleet_mission` = '4', ";
				$QryInsertFleet .= "`fleet_amount` = '". $FleetShipCount ."', ";
				$QryInsertFleet .= "`fleet_array` = '". $fleet_array2 ."', ";
				$QryInsertFleet .= "`fleet_start_time` = '". $fleet['start_time'] ."', ";
				$QryInsertFleet .= "`fleet_start_galaxy` = '". $this->CurrentPlanet['galaxy'] ."', ";
				$QryInsertFleet .= "`fleet_start_system` = '". $this->CurrentPlanet['system'] ."', ";
				$QryInsertFleet .= "`fleet_start_planet` = '". $this->CurrentPlanet['planet'] ."', ";
				$QryInsertFleet .= "`fleet_start_type` = '". $this->CurrentPlanet['planet_type'] ."', ";
				$QryInsertFleet .= "`fleet_end_time` = '". $fleet['end_time'] ."', ";
				$QryInsertFleet .= "`fleet_end_stay` = '". $StayTime ."', ";
				$QryInsertFleet .= "`fleet_end_galaxy` = '". $galaxy ."', ";
				$QryInsertFleet .= "`fleet_end_system` = '". $system ."', ";
				$QryInsertFleet .= "`fleet_end_planet` = '". $planet ."', ";
				$QryInsertFleet .= "`fleet_end_type` = '1', ";
				$QryInsertFleet .= "`fleet_resource_metal` = '".$Mining['metal']."', ";
				$QryInsertFleet .= "`fleet_resource_crystal` = '".$Mining['crystal']."', ";
				$QryInsertFleet .= "`fleet_resource_deuterium` = '".$Mining['deuterium']."', ";
				$QryInsertFleet .= "`fleet_resource_hidrogeno` = '0', ";
				$QryInsertFleet .= "`fleet_target_owner` = '0', ";
				$QryInsertFleet .= "`fleet_group` = '0', ";
				$QryInsertFleet .= "`start_time` = '". time() ."';";
				doquery( $QryInsertFleet, 'fleets');
				$QryUpdatePlanet  = "UPDATE {{table}} SET ";
				$QryUpdatePlanet .= $FleetSubQRY;
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."' ";
				$QryUpdatePlanet .= "WHERE ";
				$QryUpdatePlanet .= "`id` = '". $this->CurrentPlanet['id'] ."'";
				doquery("LOCK TABLE {{table}} WRITE", 'planets');
				doquery ($QryUpdatePlanet, "planets");
				doquery("UNLOCK TABLES", '');
				$this->CurrentPlanet["metal"]  -= $Mining['metal'];
				$this->CurrentPlanet["crystal"]  -= $Mining['crystal'];
				$this->CurrentPlanet["deuterium"]  -= $consumption + $Mining['deuterium'];
				}		
			}
			unset($row);
		}
		unset($_fleets);
	}	
	protected function Update(){
		HandleTechnologieBuild($this->CurrentPlanet,$this->player);
		UpdatePlanetBatimentQueueList ( $this->CurrentPlanet, $this->player );
		UpdatePlanet($this->CurrentPlanet, $this->player, time(), false);
	}
	protected function End($planetid){
		$this->HandleOwnFleets();
		$QryUpdateUser  = "UPDATE {{table}} SET ";
		$QryUpdateUser .= "`onlinetime` = '". time() ."', ";
		$QryUpdateUser .= "`user_lastip` = 'BOT', ";
		$QryUpdateUser .= "`user_agent` = 'UGamelaPlay Bot v". $this->VERSION ."' ";
		$QryUpdateUser .= "WHERE ";
		$QryUpdateUser .= "`id` = '". $this->player['id'] ."' LIMIT 1;";
		doquery( $QryUpdateUser, 'users');
		$QryUpdateBot  = " UPDATE {{table}} SET ";
		$QryUpdateBot .= "`last_time` = '". time() ."', ";
		$QryUpdateBot .= "`last_planet` = '".$planetid."' ";
		$QryUpdateBot .= "WHERE ";
		$QryUpdateBot .= "`id` = '". $this->Bot['id'] ."' LIMIT 1;";
		doquery( $QryUpdateBot, 'bots'); //Multi-Query
	}

}
?>
