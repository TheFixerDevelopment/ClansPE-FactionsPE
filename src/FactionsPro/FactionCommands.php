<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;
class FactionCommands {

	// ASCII Map
	CONST MAP_WIDTH = 48;
	CONST MAP_HEIGHT = 8;
	CONST MAP_HEIGHT_FULL = 17;

	CONST MAP_KEY_CHARS = "\\/#?ç¬£$%=&^ABCDEFGHJKLMNOPQRSTUVWXYZÄÖÜÆØÅ1234567890abcdeghjmnopqrsuvwxyÿzäöüæøåâêîûô";
	CONST MAP_KEY_WILDERNESS = TextFormat::GRAY . "-";
	CONST MAP_KEY_SEPARATOR = TextFormat::AQUA . "+";
	CONST MAP_KEY_OVERFLOW = TextFormat::WHITE . "-" . TextFormat::WHITE; # ::MAGIC?
	CONST MAP_OVERFLOW_MESSAGE = self::MAP_KEY_OVERFLOW . ": Too Many Factions (>" . 107 . ") on this Map.";
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			if(strtolower($command->getName('f'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f help §6for a list of Faction commands"));
					return true;
				}
				if(count($args == 2)) {
					
					///////////////////////////////// WAR /////////////////////////////////
					
					if($args[0] == "war") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f war <faction name:tp> §6to request a faction war!"));
							return true;
						}
						if(strtolower($args[1]) == "tp") {
							foreach($this->plugin->wars as $r => $f) {
								$fac = $this->plugin->getPlayerFaction($player);
								if($r == $fac) {
									$x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
									$tper = $this->plugin->war_players[$f][$x];
									$sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
									return;
								}
								if($f == $fac) {
									$x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
									$tper = $this->plugin->war_players[$r][$x];
									$sender->teleport($this->plugin->getServer()->getPlayer($tper));
									return;
								}
							}
							$sender->sendMessage("§4[Error] §cYou must be in a war to do that!");
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou may only use letters and numbers!"));
							return true;
						}
						if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cFaction dosent exist!"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this!"));
							return true;
						}
						if(!$this->plugin->isLeader($player)){
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cOnly your faction leader may start wars!"));
							return true;
						} 
						if(!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction is not enemied with $args[1]!"));
                            return true;
                        } else {
							$factionName = $args[1];
							$sFaction = $this->plugin->getPlayerFaction($player);
							foreach($this->plugin->war_req as $r => $f) {
								if($r == $args[1] && $f == $sFaction) {
									foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
										$task = new FactionWar($this->plugin, $r);
										$handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
										$task->setHandler($handler);
										$p->sendMessage("§7The war against §e$factionName and $sFaction §7has started!");
										if($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
											$this->plugin->war_players[$sFaction][] = $p->getName();
										}
										if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
											$this->plugin->war_players[$factionName][] = $p->getName();
										}
									}
									$this->plugin->wars[$factionName] = $sFaction;
									unset($this->plugin->war_req[strtolower($args[1])]);
									return true;
								}
							}
							$this->plugin->war_req[$sFaction] = $factionName;
							foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
								if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
									if($this->plugin->getLeader($factionName) == $p->getName()) {
										$p->sendMessage("§a$sFaction wants to start war, §2'/f war $sFaction' §ato start!");
										$sender->sendMessage("§aFaction war requested!");
										return true;
									}
								}
							}
							$sender->sendMessage("§4[Error] §cFaction leader is not online.");
							return true;
						}
					}
						
					/////////////////////////////// CREATE ///////////////////////////////
					
					if($args[0] == "create") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f create <faction name> §6to create a faction."));
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou may only use letters and numbers!"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThis name is not allowed."));
							return true;
						}
						if($this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cFaction already exists"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThis name is too long. Please try again!"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Eror] §cYou must leave this faction first"));
							return true;
						} else {
							$factionName = $args[1];
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
							$this->plugin->updateTag($sender->getName());
							$sender->sendMessage($this->plugin->formatMessage("§bFaction successfully created!", true));
							return true;
						}
					}
					
					/////////////////////////////// INVITE ///////////////////////////////
					
					if($args[0] == "invite") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f invite <player> §6to invite a player to your faction."));
							return true;
						}
						if($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cFaction is full. Please kick players to make room."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if(!($invited instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cPlayer not online!"));
							return true;
						}
						if($this->plugin->isInFaction($invited) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cPlayer is currently in a faction"));
							return true;
						}
						if($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if(!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))){
							    $sender->sendMessage($this->plugin->formatMessage("Only your faction leader/officers may invite!"));
							    return true;
                            } 
						}
                        if($invited->getName() == $player){
                            
				            $sender->sendMessage($this->plugin->formatMessage("You can't invite yourself into your own faction"));
                            return true;
                        }
						
				        $factionName = $this->plugin->getPlayerFaction($player);
				        $invitedName = $invited->getName();
				        $rank = "Member";
								
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
				        $stmt->bindValue(":player", $invitedName);
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":invitedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
				        $sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited!", true));
				        $invited->sendMessage($this->plugin->formatMessage("§7You have been invited to §e$factionName. §7Type §e'/f accept' or '/f deny' §6into chat to accept or deny!", true));
						
					}
					
					/////////////////////////////// LEADER ///////////////////////////////
					
					if($args[0] == "leader") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f leader <player> §6to transfer leadership of your faction."));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§3[Error] §cYou must be in a faction to use this!"));
                            return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this"));
                            return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Add player to faction first!"));
                            return true;
						}		
						if(!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
                            return true;
						}
                        if($args[1] == $sender->getName()){
                            
				            $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou can't transfer the leadership to yourself"));
                            return true;
                        }
				        $factionName = $this->plugin->getPlayerFaction($player);
	
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", $player);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
	
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Leader");
				        $result = $stmt->execute();
	
	
						$sender->sendMessage($this->plugin->formatMessage("§2You are no longer leader!", true));
						$this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("§aYou are now leader \nof §2$factionName!",  true));
						$this->plugin->updateTag($sender->getName());
						$this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
				    }
					
					/////////////////////////////// PROMOTE ///////////////////////////////
					
					if($args[0] == "promote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f promote <player> §6to promote a player in your faction."));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to use this!"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cPlayer is not in this faction!"));
							return true;
						}
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cMeh. You can't promote yourself."));
							return true;
                        }
                        
						if($this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is already Officer"));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Officer");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayerExact($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("$args[1] has been promoted to Officer!", true));
                        
						if($player instanceof Player) {
						    $player->sendMessage($this->plugin->formatMessage("You were promoted to officer of $factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
					}
					
					/////////////////////////////// DEMOTE ///////////////////////////////
					
					if($args[0] == "demote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f demote <player> §6to demote a player from your faction."));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
							return true;
						}
						
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("Meh. You can't demote yourself."));
							return true;
                        }
                        if(!$this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cPlayer is already Member"));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $args[1]);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayerExact($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("$args[1] has been demoted to Member!", true));
						if($player instanceof Player) {
						    $player->sendMessage($this->plugin->formatMessage("You were demoted to member of $factionName!", true));
						    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
					}
					
					/////////////////////////////// KICK ///////////////////////////////
					
					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f kick <player> §6to kick a player from your faction."));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cPlayer is not in this faction!"));
							return true;
						}
                        if($args[1] == $sender->getName()){
                            $sender->sendMessage($this->plugin->formatMessage("Meh. You can't kick yourself."));
							return true;
                        }
						$kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("§bYou successfully kicked §3$args[1]!", true));
                        $this->plugin->subtractFactionPower($factionName,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						
						if($kicked instanceof Player) {
			                $kicked->sendMessage($this->plugin->formatMessage("§bYou have been kicked from \n §3$factionName!",true));
							$this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
							return true;
						}
					}
					
					/////////////////////////////// INFO ///////////////////////////////
					
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cFaction does not exist"));
							    $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cMake sure the name of the selected faction is ABSOLUTELY EXACT."));
								return true;
							}
							$faction = $args[1];
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Faction]| : " . TextFormat::GREEN . "$faction".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Players^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Strength&| : " . TextFormat::RED . "$power" . " STR".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Description*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
						} else {
                            if(!$this->plugin->isInFaction($player)){
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
                                return true;
                            }
							$faction = $this->plugin->getPlayerFaction(($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Faction]| : " . TextFormat::GREEN . "$faction".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Players^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Strength&| : " . TextFormat::RED . "$power" . " STR".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Description*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message".TextFormat::RESET);
							$sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
						}
					}
					if(strtolower($args[0]) == "help") {
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 1 of 6" . TextFormat::RED . "§a\n/f about\n/f accept\n/f overclaim [Takeover the plot of the requested faction]\n/f claim\n/f create <name>\n/f del\n/f demote <player>\n/f deny");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 2 of 6" . TextFormat::RED . "§b\n/f home\n/f help <page>\n/f info\n/f info <faction>\n/f invite <player>\n/f kick <player>\n/f leader <player>\n/f leave");
							return true;
						} 
                        if($args[1] == 3) {
							$sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 3 of 6" . TextFormat::RED . "§c\n/f sethome\n/f unclaim\n/f unsethome\n/f ourmembers - {Members + Statuses}\n/f ourofficers - {Officers + Statuses}\n/f ourleader - {Leader + Status}\n/f allies - {The allies of your faction");
							return true;
						} 
                        if($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 4 of 6" . TextFormat::RED . "§d\n/f desc\n/f promote <player>\n/f allywith <faction>\n/f breakalliancewith <faction>\n\n/f allyok [Accept a request for alliance]\n/f allyno [Deny a request for alliance]\n/f allies <faction> - {The allies of your chosen faction}");
							return true;
                        } 
                        if($args[1] == 5){
                            $sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 5 of 6" . TextFormat::RED . "§e\n/f membersof <faction>\n/f officersof <faction>\n/f leaderof <faction>\n/f say <send message to everyone in your faction>\n/f pf <player>\n/f top");
							return true;
                        }
                        else {
                            $sender->sendMessage(TextFormat::GOLD . "FactionsPro Help Page 6 of 6" . TextFormat::RED . "§1\n/f forceunclaim <faction> [Unclaim a faction plot by force - OP]\n\n/f forcedelete <faction> [Delete a faction by force - OP]\n\n/f addstrto <faction> <STR> [Add positive/negative STR to a faction - OP]\n\n/f map [Show faction map]");
							return true;
                        }
					}
				}
				if(count($args == 1)) {
					
					/////////////////////////////// CLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this."));
							return true;
						}
                        
						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYour faction has already claimed this area."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou need $needed_players more players in your faction to claim a faction plot"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYour faction doesn't have enough STR to claim a land."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power STR is required but your faction has only $faction_power STR."));
                            return true;
                        }
						
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                            
							return true;
						}
                        
						$sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("§aYour land has been claimed.", true));
					
					}
                    if(strtolower($args[0]) == 'plotinfo'){
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if(!$this->plugin->isInPlot($sender)){
                            $sender->sendMessage($this->plugin->formatMessage("§bThis plot is not claimed by anyone. You can claim it by typing §3/f claim", true));
							return true;
                        }
                        
                        $fac = $this->plugin->factionFromPoint($x,$z);
                        $power = $this->plugin->getFactionPower($fac);
                        $sender->sendMessage($this->plugin->formatMessage("§cThis plot is claimed by $fac with $power STR"));
                    }
                    if(strtolower($args[0]) == 'top'){
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    if(strtolower($args[0]) == 'forcedelete') {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f forcedelete <faction> §6to force delete a faction. [For OPS ONLY]"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThe requested faction doesn't exist."));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be OP to do this."));
                            return true;
						}
						$this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
				        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
				        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
				        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
				        $sender->sendMessage($this->plugin->formatMessage("§bUnwanted faction was successfully deleted and their faction plot was unclaimed!", true));
                    }
                    if(strtolower($args[0]) == 'addstrto') {
                        if(!isset($args[1]) or !isset($args[2])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f addstrto <faction> <STR> §6to add strength. [FOR OPS ONLY.]"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThe requested faction doesn't exist."));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be OP to do this."));
                            return true;
						}
                        $this->plugin->addFactionPower($args[1],$args[2]);
				        $sender->sendMessage($this->plugin->formatMessage("§bSuccessfully added $args[2] STR to $args[1]", true));
                    }
                    if(strtolower($args[0]) == 'pf'){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f pf <player> §6to check a player's profile."));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThe selected player is not in a faction or doesn't exist."));
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cMake sure the name of the selected player is ABSOLUTELY EXACT."));
                            return true;
						}
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-",true));
                        
                    }
                    
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this."));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){
                           
                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") - 
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou need $needed_players more players in your faction to overclaim a faction plot"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYour faction doesn't have enough STR to claim a land."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power STR is required but your faction has only $faction_power STR."));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if($this->plugin->prefs->get("EnableOverClaim")){
                            if($this->plugin->isInPlot($sender)){
                                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if($this->plugin->inOwnPlot($sender)){
                                    $sender->sendMessage($this->plugin->formatMessage("You can't overclaim your own plot."));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou can't overclaim the plot of $faction_victim because your STR is lower than theirs."));
                                        return true;
                                    } else {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
						                $sender->sendMessage($this->plugin->formatMessage("§bThe land of $faction_victim has been claimed. It is now yours.", true));
                                        return true;
                                    }
                                    
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction plot."));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§bOverclaiming is disabled."));
                            return true;
                        }
                        
					}
                    
					
					/////////////////////////////// UNCLAIM ///////////////////////////////
					
					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction."));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§bYour land has been unclaimed.", true));
					}
					
					/////////////////////////////// DESCRIPTION ///////////////////////////////
					
					if(strtolower($args[0]) == "desc") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to use this!"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to use this"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("§bType your message in chat. It will not be visible to other players", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", $sender->getName());
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}
					
					/////////////////////////////// ACCEPT ///////////////////////////////
					
					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou have not been invited to any factions!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $invitedTime) <= 60) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", ($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("§bYou successfully joined §3$faction!", true));
                            $this->plugin->addFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the faction!", true));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}
					
					/////////////////////////////// DENY ///////////////////////////////
					
					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou have not been invited to any factions!"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Invite declined!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invite!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}
					
					/////////////////////////////// DELETE ///////////////////////////////
					
					if(strtolower($args[0]) == "del") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
								$this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
								$this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
								$sender->sendMessage($this->plugin->formatMessage("Faction successfully disbanded and the faction plot was unclaimed!", true));
								$this->plugin->updateTag($sender->getName());
							} else {
								$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou are not leader!"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou are not in a faction!"));
						}
					}
					
					/////////////////////////////// LEAVE ///////////////////////////////
					
					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("§bYou successfully left §3$faction", true));
                            
                            $this->plugin->subtractFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->updateTag($sender->getName());
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must delete the faction or give\nleadership to someone else first!"));
						}
					}
					
					/////////////////////////////// SETHOME ///////////////////////////////

					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to set home."));
							return true;
						}
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("Home set!", true));
					}

					/////////////////////////////// UNSETHOME ///////////////////////////////

					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be leader to unset home."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("Home unset!", true));
					}

					/////////////////////////////// HOME ///////////////////////////////

					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)){
							$sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->plugin->getServer()->getLevelByName("Factions")));
							$sender->sendMessage($this->plugin->formatMessage("Teleported home.", true));
						} 
						else{
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cHome is not set."));
						}
					}
                    
                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if(strtolower($args[0] == "ourmembers")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Member");
                       
                    }
                    if(strtolower($args[0] == "membersof")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /f membersof <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThe requested faction doesn't exist."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Member");
                       
                    }
                    if(strtolower($args[0] == "ourofficers")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Officer");
                    }
                    if(strtolower($args[0] == "officersof")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /f officersof <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cThe requested faction doesn't exist."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Officer");
                       
                    }
                    if(strtolower($args[0] == "ourleader")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§4[Error] §cYou must be in a faction to do this."));
                            return true;
						}
                        $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Leader");
                    }
                    if(strtolower($args[0] == "leaderof")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f leaderof <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Leader");
                       
                    }
                    if(strtolower($args[0] == "say")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f say <message>"));
                            return true;
                        }
                        if(!($this->plugin->isInFaction($player))){
                            
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a faction to send faction messages"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);
                        
                        if($this->plugin->isOfficer($player)){
                            $rank = "*";
                        } else if($this->plugin->isLeader($player)){
                            $rank = "**";
                        }
                        $message = "-> ";
                        for($i=0;$i<$r-1;$i=$i+1){
                            $message = $message.$args[$i+1]." "; 
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for($i=0;$resultArr = $result->fetchArray(SQLITE3_ASSOC);$i=$i+1){
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if($p instanceof Player){
                                $p->sendMessage(TextFormat::ITALIC.TextFormat::RED."<FM>".TextFormat::AQUA." <$rank$f> ".TextFormat::GREEN."<$player> ".": ".TextFormat::RESET);
                                $p->sendMessage(TextFormat::ITALIC.TextFormat::DARK_AQUA.$message.TextFormat::RESET);
                                
                            }
                        } 
                            
                    }
                    
                  
                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
					if(strtolower($args[0] == "enemywith")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f enemywith <faction>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction can not enemy with itself."));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction is already enemied with $args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested faction is offline."));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("You enemied with $args[1]!",true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac has enemied your faction.",true));
                        
                    }
                    if(strtolower($args[0] == "allywith")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f allywith <faction>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction can not ally with itself."));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction is already allied with $args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested faction is offline."));
                            return true;
                        }
                        if($this->plugin->getAlliesCount($args[1])>=$this->plugin->getAlliesLimit()){
                           $sender->sendMessage($this->plugin->formatMessage("The requested faction has the maximum amount of allies.",false));
                           return true;
                        }
                        if($this->plugin->getAlliesCount($fac)>=$this->plugin->getAlliesLimit()){
                           $sender->sendMessage($this->plugin->formatMessage("Your faction has the maximum amount of allies.",false));
                           return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
				        $stmt->bindValue(":player", $leader->getName());
				        $stmt->bindValue(":faction", $args[1]);
				        $stmt->bindValue(":requestedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("You requested to ally with $args[1]!\nWait for the leader's response...",true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac requested an alliance.\nType /f allyok to accept or /f allyno to deny.",true));
                        
                    }
                    if(strtolower($args[0] == "breakalliancewith")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f breakalliancewith <faction>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this."));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction can not break alliance with itself."));
                            return true;
                        }
                        if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("Your faction is not allied with $args[1]!"));
                            return true;
                        }
                        
                        $fac = $this->plugin->getPlayerFaction($player);        
						$leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac,$args[1]);
                        $this->plugin->deleteAllies($args[1],$fac);
                        $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("Your faction $fac is no longer allied with $args[1]!",true));
                        if($leader instanceof Player){
                            $leader->sendMessage($this->plugin->formatMessage("The leader of $fac broke the alliance with your faction $args[1]!",false));
                        }
                        
                        
                    }
                    if(strtolower($args[0] == "forceunclaim")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§7Please use: §e/f forceunclaim <faction>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("Successfully unclaimed the unwanted plot of $args[1]!"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        
                    }
                    
                    if(strtolower($args[0] == "allies")){
                        if(!isset($args[1])){
                            if(!$this->plugin->isInFaction($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                                return true;
						    }
                            
                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender,$this->plugin->getPlayerFaction($player));
                        } else {
                            if(!$this->plugin->factionExists($args[1])) {
							    $sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
                                return true;
						    }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender,$args[1]);
                            
                        }
                        
                    }
                    if(strtolower($args[0] == "allyok")){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this."));
                            return true;
						}
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Your faction has not been requested to ally with any factions!"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->setAllies($requested_fac,$sender_fac);
							$this->plugin->setAllies($sender_fac,$requested_fac);
                            $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
							$sender->sendMessage($this->plugin->formatMessage("Your faction has successfully allied with $requested_fac!", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has accepted the alliance!", true));
                            
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Request has timed out!"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
                        
                    }
                    if(strtolower($args[0]) == "allyno") {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this."));
                            return true;
						}
						$lowercaseName = ($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("Your faction has not been requested to ally with any factions!"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $allyTime) <= 60 ) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("Your faction has successfully declined the alliance request.", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                            
						} else {
							$sender->sendMessage($this->plugin->formatMessage("Request has timed out!"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
					}
                           
                    
					/////////////////////////////// ABOUT ///////////////////////////////
					
					if(strtolower($args[0] == 'about')) {
						$sender->sendMessage(TextFormat::GREEN . "§c[Original] §bBy" . TextFormat::BOLD . "Tethered_");
						$sender->sendMessage(TextFormat::GOLD . "[Code] edited by " . TextFormat::BOLD . "Zeao");
						$sender->sendMessage(TextFormat::DARK_PURPLE . "[Code] edited by ". TextFormat::BOLD . "Zeao");
					}
					/////////////////////////////// MAP, map by Primus (no compass) ////////////////////////////////
					// Coupon for compass: G1wEmEde0mp455

					if(strtolower($args[0] == "map")) {
						$map = $this->getMap($sender, self::MAP_WIDTH, self::MAP_HEIGHT, $sender->getYaw(), $this->plugin->prefs->get("PlotSize"));
						foreach($map as $line) {
							$sender->sendMessage($line);
						}
						return true;
					}

					////////////////////////////// CHAT ////////////////////////////////
					if(strtolower($args[0]) == "chat" or strtolower($args[0]) == "c"){
						if($this->plugin->isInFaction($player)){
							if(isset($this->plugin->factionChatActive[$player])){
								unset($this->plugin->factionChatActive[$player]);
								$sender->sendMessage($this->plugin->formatMessage("Faction chat disabled!", false));
								return true;
							}
							else{
								$this->plugin->factionChatActive[$player] = 1;
								$sender->sendMessage($this->plugin->formatMessage("§aFaction chat enabled!", false));
								return true;
							}
						}
						else{
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction"));
							return true;
						}
					}
					if(strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac"){
						if($this->plugin->isInFaction($player)){
							if(isset($this->plugin->allyChatActive[$player])){
								unset($this->plugin->allyChatActive[$player]);
								$sender->sendMessage($this->plugin->formatMessage("Ally chat disabled!", false));
								return true;
							}
							else{
								$this->plugin->allyChatActive[$player] = 1;
								$sender->sendMessage($this->plugin->formatMessage("§aAlly chat enabled!", false));
								return true;
							}
						}
						else{
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction"));
							return true;
						}
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
		}
	}

	public function getMap(Player $observer, int $width, int $height, int $inDegrees, int $size = 16) { // No compass
		$to = (int)sqrt($size);
		$centerPs = new Vector3($observer->x >> $to, 0, $observer->z >> $to);

		$map = [];

		$centerFaction = $this->plugin->factionFromPoint($observer->getFloorX(), $observer->getFloorZ());
		$centerFaction = $centerFaction ? $centerFaction : "Wilderness";

		$head = TextFormat::GREEN . " (" . $centerPs->getX() . "," . $centerPs->getZ() . ") " . $centerFaction . " " . TextFormat::WHITE;
		$head = TextFormat::GOLD . str_repeat("_", (($width - strlen($head)) / 2)) . ".[" . $head . TextFormat::GOLD . "]." . str_repeat("_", (($width - strlen($head)) / 2));

		$map[] = $head;

		$halfWidth = $width / 2;
		$halfHeight = $height / 2;
		$width = $halfWidth * 2 + 1;
		$height = $halfHeight * 2 + 1;

		$topLeftPs = new Vector3($centerPs->x + -$halfWidth, 0, $centerPs->z + -$halfHeight);

		// Get the compass
		//$asciiCompass = ASCIICompass::getASCIICompass($inDegrees, TextFormat::RED, TextFormat::GOLD);

		// Make room for the list of names
		$height--;

		/** @var string[] $fList */
		$fList = array();
		$chrIdx = 0;
		$overflown = false;
		$chars = self::MAP_KEY_CHARS;

		// For each row
		for ($dz = 0; $dz < $height; $dz++) {
			// Draw and add that row
			$row = "";
			for ($dx = 0; $dx < $width; $dx++) {
				if ($dx == $halfWidth && $dz == $halfHeight) {
					$row .= (self::MAP_KEY_SEPARATOR);
					continue;
				}

				if (!$overflown && $chrIdx >= strlen(self::MAP_KEY_CHARS)) $overflown = true;
				$herePs = $topLeftPs->add($dx, 0, $dz);
				$hereFaction = $this->plugin->factionFromPoint($herePs->x << $to, $herePs->z << $to);
				$contains = in_array($hereFaction, $fList, true);
				if ($hereFaction === NULL) {
					$row .= self::MAP_KEY_WILDERNESS;
				} elseif (!$contains && $overflown) {
					$row .= self::MAP_KEY_OVERFLOW;
				} else {
					if (!$contains) $fList[$chars{$chrIdx++}] = $hereFaction;
					$fchar = array_search($hereFaction, $fList);
					$row .= $this->getColorForTo($observer, $hereFaction) . $fchar;
				}
			}

			$line = $row; // ... ---------------

			// Add the compass
			//if ($dz == 0) $line = $asciiCompass[0] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
			//if ($dz == 1) $line = $asciiCompass[1] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
			//if ($dz == 2) $line = $asciiCompass[2] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));

			$map[] = $line;
		}
		$fRow = "";
		foreach ($fList as $char => $faction) {
			$fRow .= $this->getColorForTo($observer, $faction) . $char . ": " . $faction . " ";
		}
		if ($overflown) $fRow .= self::MAP_OVERFLOW_MESSAGE;
		$fRow = trim($fRow);
		$map[] = $fRow;

		return $map;
	}

	public function getColorForTo(Player $player, $faction) {
		if($this->plugin->getPlayerFaction($player->getName()) === $faction) {
			return TextFormat::GREEN;
		}
		return TextFormat::LIGHT_PURPLE;
	}

}
