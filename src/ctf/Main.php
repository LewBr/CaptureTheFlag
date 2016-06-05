<?php
namespace ctf;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\nbt\tag\IntTag;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\network\protocol\PlayerArmorEquipmentPacket;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityMoveEvent;
use pocketmine\Player;
Class Main extends PluginBase implements Listener{
    public $editors;
    public $temp;
    
    public function onEnable(){
        
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder(). "config.yml", Config::YAML);
                $this->saveDefaultConfig();
		$this->reloadConfig();
                $this->getLogger()->info(TextFormat::DARK_BLUE . "CTF has been enabled!");
                $this->getConfig()->setDefaults(array("Maximum score to win (Integer)" => 15, "Game Length (Mins)" => 20));
                $this->getServer()->getScheduler()->scheduleRepeatingTask(new timer($this), 1200);
                
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if ($sender->isOp()) {        
            switch($command->getName()) {
                case "ctfbs":
                    $this->writeConfigCoords($sender, "Set spawn for Blue team.");
                    break;
                case "ctfbf":
                    $this->temp[$sender->getName()]["BlueFlag"] = true; 
                    $sender->sendMessage("Place a block in the desired spot for the flag");
                    break;
                case "ctfbr":
                    $this->writeConfigCoords($sender, "Set where to return blue flag.");
                    break;
                case "ctfrs":
                    $this->writeConfigCoords($sender, "Set spawn for Red team.");
                    break;
                case "ctfrf":
                    $this->temp[$sender->getName()]["RedFlag"] = true;
                    $sender->sendMessage("Place a block in the desired spot for the flag");
                    break;
                case "ctfrr":
                    $this->writeConfigCoords($sender, "Set where to return red flag.");
                    break;
                case "lobbyset":
                    $this->writeConfigCoords($sender, "Set the lobby.");
                    break;
                case "ctfedit":
                    if ($sender->isOp()) {
                        if (!isset($this->editors[$sender->getName()])) {
                        $this->editors[$sender->getName()] = true;
                        $sender->sendMessage("You are now allowed to edit!");
                        }
                        else {
                        unset($this->editors[$sender->getName()]);
                        $sender->sendMessage("You are not allowed to edit!");
                        }
                    }
                    else {
                        $sender->sendMessage("You need to be OP to do this.");
                    }
                    break;                
            }
        }
    }
    public function onBlockBreak(BlockBreakEvent $event) {
            
        $player = $event->getPlayer();
        $x = (Int)$event->getBlock()->getX();
        $y = (Int)$event->getBlock()->getY();
        $z = (Int)$event->getBlock()->getZ();
        $level = $event->getBlock()->getLevel()->getName();
        $blue = $this->getConfig()->get("BlueFlag");
        $red = $this->getConfig()->get("RedFlag");
        
        if (isset($this->editors[$player->getName()])) {
            $event->setCancelled(false);
        }
        
        else if ($x == $blue["x"] and $y == $blue["y"] and $z == $blue["z"] and $level = $blue["level"]) {
            if (array_key_exists($player->getName(), $this->temp["RedPlayers"])) { // player is on red team
                $event->setCancelled();
                $item = new Item(35, 11, 1);
                $player->getInventory()->addItem($item);
                if (isset($this->temp[$player->getName()])) {
                $player->sendMessage("You can only place the flag once.");
                }
                else {
                $this->getServer()->broadcastMessage("CTF".$player->getDisplayName() ." captured the BLUE teams flag!");
                $this->temp[$player->getName()] = true;
                $player->setNameTag(TextFormat::RED. $player->getName());

                }
            }
            else {
            $event->setCancelled();
            }
        }
        else if ($x == $red["x"] and $y == $red["y"] and $z == $red["z"] and $level == $red["level"]) {
            if (array_key_exists($player->getName(), $this->temp["BluePlayers"])) { // player is on blue team
                $event->setCancelled();
                $item = new Item(35,14, 1);
                $player->getInventory()->addItem($item);
                if (isset($this->temp[$player->getName()])) {
                $player->sendMessage("You can only place the flag once.");
                }
                else {
                $this->getServer()->broadcastMessage("CTF".$player->getDisplayName() ." captured the RED teams flag!");
                $this->temp[$player->getName()] = true;
                $player->setNameTag(TextFormat::BLUE. $player->getName());
                }
            }
            else {
                $event->setCancelled();
            }
        }
        else {
            $event->setCancelled();
        }
    }
    
    public function onBlockPlace (BlockPlaceEvent $event) {
        
        $player = $event->getPlayer();
        $x = (Int)$event->getBlock()->getX();
        $y = (Int)$event->getBlock()->getY();
        $z = (Int)$event->getBlock()->getZ();
        $level = $event->getBlock()->getLevel()->getName();
        
        if (isset($this->editors[$player->getName()])) {
            $event->setCancelled(false);
            }
        
        else if (isset($this->temp[$player->getName()]["RedFlag"])) {
            
            $coordarray = array("x" => $x, "y" => $y, "z" => $z, "level" => $level);
            $this->getConfig()->set("RedFlag", $coordarray);
            $player->sendMessage("Red flag set!");
            unset($this->temp[$player->getName()]);
        }
        else if (isset($this->temp[$player->getName()]["BlueFlag"])) {
            
            $coordarray = array("x" => $x, "y" => $y, "z" => $z, "level" => $level);
            $this->getConfig()->set("BlueFlag", $coordarray);
            $player->sendMessage("Blue flag set!");
            unset($this->temp[$player->getName()]);
        }
        
        else {
            if (isset($this->temp["RedPlayers"]) or isset($this->temp["BluePlayers"])) { // IDK why I made this function
            if(is_array($this->temp["RedPlayers"]) && array_key_exists($player->getName(), $this->temp["RedPlayers"])) { //Player is on the red team
                $config = $this->getConfig()->get("RedReturn");
                if($x == $config["x"] and $y == $config["y"] and $z == $config["z"] and $level == $config["level"]) { 
                    if (isset($this->temp["RedPoints"])) {
                        $points = $this->temp["RedPoints"] +1;
                        $this->temp["RedPoints"] = $points;
                        $event->setCancelled();
                    }
                    else { 
                        $this->temp["RedPoints"] = 1;
                    }
                    $event->setCancelled();  
                    $this->giveTeamItems($player->getName());
                $this->broadcastScore($player);
                    if (isset($this->temp[$player->getName()])) {
                    unset($this->temp[$player->getName()]);
                $player->setNameTag(TextFormat::RED. $player->getName());                    
                    }
                }
              } 
                if(is_array($this->temp["BluePlayers"]) && array_key_exists($player->getName(), $this->temp["BluePlayers"])) { //Player is on the blue team
                $config = $this->getConfig()->get("BlueReturn");
                if($x == $config["x"] and $y == $config["y"] and $z == $config["z"] and $level == $config["level"]) { 
                    if (isset($this->temp["BluePoints"])) {
                        $points = $this->temp["BluePoints"] +1;
                        $this->temp["BluePoints"] = $points;
                        $event->setCancelled();
                    }
                    else {
                        $this->temp["BluePoints"] = 1;
                    }
                    $event->setCancelled();
                    $this->giveTeamItems($player->getName());
                    $this->broadcastScore($player);
                    if (isset($this->temp[$player->getName()])) {
                    unset($this->temp[$player->getName()]);
                $player->setNameTag(TextFormat::BLUE. $player->getName());
                    }
                }
            }
        }
              $event->setCancelled();
      }
    }
    
    public function onPlayerJoin (PlayerJoinEvent $event) {
        
            $player = $event->getPlayer();
            $this->assignPlayer($player);
            $this->giveTeamItems($player->getName()); //DEBUG getName or just player
    }
    
    public function onPlayerQuit (PlayerQuitEvent $event) {
        
            $player = $event->getPlayer();
            if (array_key_exists($player->getName(), $this->temp["BluePlayers"])) {
                unset($this->temp["BluePlayers"][$player->getName()]);
            }
            else {
                unset($this->temp["RedPlayers"][$player->getName()]);
            }
         }
   public function onEntityMove(EntityMoveEvent $event) {
        $x = round($event->getEntity()->getX());
        $y = round($event->getEntity()->getY());
        $z = round($event->getEntity()->getZ());
        $blue = $this->getConfig()->get("BlueReturn");
        $red = $this->getConfig()->get("RedReturn");
        $blues = $this->getConfig()->get("BlueSpawn");
        $reds = $this->getConfig()->get("RedSpawn");
		
        
        if ($x == $blue["x"] and $y == $blue["y"] and $z == $blue["z"]){
            $pos = new Position($blues["x"], $blues["y"], $blues["z"], $event->getEntity()->getLevel());
            $event->getEntity()->teleport($pos);   
        }
        else if ($x == $red["x"] and $y == $red["y"] and $z == $red["z"]) {
            $pos = new Position($reds["x"], $reds["y"], $reds["z"] ,$event->getEntity()->getLevel());
            $event->getEntity()->teleport($pos);               
        }
    }
    
    
     public function onHurt(EntityDamageEvent $event) {
    if($event->getEntity() instanceof Player and $event instanceof EntityDamageByEntityEvent) {
      $player = $event->getEntity();
      $cause = $event->getDamager();
      if (($player instanceof Player) and ($cause instanceof Player)) {
          if (array_key_exists($player->getName(), $this->temp["BluePlayers"])) {
                if (array_key_exists($cause->getName(), $this->temp["BluePlayers"])) {
                $cause->sendMessage(TextFormat::RED."You cannot harm players on your team!");
                $event->setCancelled();
                    }
                }
            else if (array_key_exists($player->getName(), $this->temp["RedPlayers"])) {
               if (array_key_exists($cause->getName(), $this->temp["RedPlayers"])) {
               $cause->sendMessage(TextFormat::RED."You cannot harm players on your team!");
               $event->setCancelled();
                    }
                }
            }
         }
     }
    
    public function onEntityDeath (EntityDeathEvent $event) {
        $event->setDrops(array());
    }
    public function onPlayerRespawn (PlayerRespawnEvent $event) {
        
        $player = $event->getPlayer();
        if (is_array($this->temp["BluePlayers"]) && array_key_exists($player->getName(), $this->temp["BluePlayers"])) {
            $config = $this->getConfig()->get("BlueSpawn");
            $pos = new Position($config["x"], $config["y"], $config["z"], $this->getServer()->getLevelByName($config["level"]));
            $event->setRespawnPosition($pos);
            $player->sendMessage(TextFormat::GREEN."You have been teleported to your base!");
            $this->giveTeamItems($player->getName());
            $player->setNameTag(TextFormat::BLUE. $player->getName());
        }
        else if (is_array($this->temp["RedPlayers"]) && array_key_exists($player->getName(), $this->temp["RedPlayers"])) {
            $config = $this->getConfig()->get("RedSpawn");
            $pos = new Position($config["x"], $config["y"], $config["z"], $this->getServer()->getLevelByName($config["level"]));
            $event->setRespawnPosition($pos);
            $player->sendMessage(TextFormat::GREEN."You have been teleported to your base!");
            $this->giveTeamItems($player->getName());
              $player->setNameTag(TextFormat::RED. $player->getName());
        }
        
        
        
    }
    
    public function writeConfigCoords($sender, $coordname){
        
            $x = (int)$sender->x;
            $y = (int)$sender->y;
            $z = (int)$sender->z;
            $level = $sender->getLevel()->getName();
            $coordarray = array("x" => $x, "y" => $y, "z" => $z, "level" => $level);
            $this->getConfig()->set($coordname, $coordarray);
            $sender->sendMessage($coordname. " set!");
            
    }
    
    public function assignPlayer($player){
        
        $name = $player->getName();
        $red = $this->temp["RedPlayers"]; //sends minor error cuz not set yet 
        $blue = $this->temp["BluePlayers"]; 
        if (count($blue) > count($red)) {
            $assignment = "red";
        }
        else {
            $assignment = "blue";
        }
        
        if ($assignment === "blue") {
                $this->temp["BluePlayers"][$name] = true;
                $player->setDisplayName(TextFormat::BLUE." . $name);
                $player->setNameTag(TextFormat::BLUE." . $name);
        }
        else {
                $this->temp["RedPlayers"][$name] = true;
                $player->setDisplayName(TextFormat::RED." . $name);
                $player->setNameTag(TextFormat::RED." . $name);
        }
        
        $player->sendMessage(TextFormat::YELLOW."You have been assigned to the " . $assignment . " team!");
        
        if ($assignment === "red"){
            $spawn = "RedSpawn";
        }
        else {
            $spawn = "BlueSpawn";
        }
        
        if ($this->getConfig()->get($spawn) == false) {
            $player->sendMessage(TextFormat::DARK_AQUA."Plugin not yet configured. Come back soon!");
        }
        else {
            $config = $this->getConfig()->get($spawn);
            $pos = new Position($config["x"], $config["y"], $config["z"], $this->getServer()->getLevelByName($config["level"]));
            $player->teleport($pos);
            $player->sendMessage(TextFormat::GREEN."You have been teleported to your base!");
        }
    }
   
    public function giveTeamItems($player) { //gives items to $player according to which team he is on
		$p = $this->getServer()->getPlayer($player);
		$inv = $p->getInventory();
		$inv->setContents([]);
		$inv->setArmorItem(0, Item::get(Item::IRON_HELMET));
		$inv->setArmorItem(1, Item::get(Item::LEATHER_TUNIC));
		$inv->setArmorItem(2, Item::get(Item::IRON_LEGGINGS));
		$inv->setArmorItem(3, Item::get(Item::CHAIN_BOOTS));
		$inv->sendArmorContents($p);
		$inv->addItem(Item::get(Item::STEAK, 0, 5));
		$inv->addItem(Item::get(Item::STONE_SWORD, 0, 1));
		$inv->addItem(Item::get(Item::BOW, 0, 1));
		$inv->addItem(Item::get(Item::ARROW, 0, 32));
		$inv->sendContents($p);
		$pk = new PlayerArmorEquipmentPacket;
		$pk->eid = $p->getID();
		$armor = []; // TODO
		$pk->slots = $armor;
		foreach($this->getServer()->getOnlinePlayers() as $other){
			if($p->getID() !== $other->getID()){
				$other->dataPacket($pk);
			}
		}
	}
	
    public function onDisable() {
        $this->getConfig()->save();
    }
    }
