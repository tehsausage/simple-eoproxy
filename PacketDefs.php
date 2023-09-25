<?php

require_once dirname(__FILE__) . '/packet_constants.php';

// Convert a packet family ID in to a name if possible
function packet_family_name($i)
{
	static $packet_family_names = [
		1 => 'Security', // aka Connection
		2 => 'System', // aka Message
		3 => 'Login',
		4 => 'Account',
		5 => 'Play', // aka Welcome
		6 => 'Character',
		// 7 => '', // new / unknown
		8 => 'Move', // aka Walk
		9 => 'Dir', // aka Face
		10 => 'Sit',
		11 => 'Emote',
		// 12 => 'Warp', // unused family
		13 => 'Attack',
		14 => 'Magic', // aka Spell
		15 => 'Shop',
		16 => 'Item',
		// 17 => 'Spell', // unused family
		18 => 'Skill',
		19 => 'PChat', // aka Global
		20 => 'Chat',
		21 => 'Map', // aka Warp
		22 => 'Channel', // new!
		// 23 => 'Talk', // unused family
		24 => 'Jukebox',
		25 => 'Players',
		26 => 'Player', // aka Avatar
		27 => 'Group', // aka Party
		28 => 'Refresh',
		29 => 'NPC',
		30 => 'PlayerRange', // aka PlayerAutoRefresh
		31 => 'NPCRange', // aka NPCAutoRefresh
		32 => 'Range', // aka Appear
		33 => 'Paperdoll',
		34 => 'Effect',
		35 => 'Trade',
		36 => 'Chest',
		37 => 'Door',
		38 => 'Bank',
		39 => 'Locker',
		40 => 'Barber',
		41 => 'Guild',
		42 => 'Sound', // aka Music
		43 => 'Rest', // aka Sit (ground)
		44 => 'Gamebar', // aka Recover
		45 => 'MsgBoard', // aka Board
		46 => 'MagicNPC', // aka Cast
		47 => 'Arena',
		48 => 'Priest',
		49 => 'Law',
		50 => 'Info', // new!
		51 => 'Inn', // aka Citizen
		52 => 'Quest',
		53 => 'MagicAttack', // new!
		54 => 'Boss',
		55 => 'Gather', // new!
		56 => 'Charger',
		249 => 'Captcha', // new!
		255 => 'Init'
	];

	if (isset($packet_family_names[$i]))
		return $packet_family_names[$i];
	else
		return $i;
}

// Convert a packet action ID in to a name if possible
function packet_action_name($i)
{
	static $packet_action_names = [
		1 => 'Request',
		// 2 => '', // ???
		3 => 'Confirm', // aka Accept
		// 4 => '', // used when warp is rejected by lacking quest req., exp updates, gather-attacks from other players
		5 => 'Result', // aka Reply
		6 => 'Delete', // aka Remove
		7 => 'Update', // aka Agree
		// 8 => '', // used for other-player levelup
		9 => 'New', // aka Create
		10 => 'Add',
		11 => 'Set', // aka Player
		12 => 'Get', // aka Take
		13 => 'Do', // aka Use
		14 => 'Buy',
		15 => 'Sell',
		16 => 'Open',
		17 => 'Close',
		18 => 'Switch', // new! --- used for switching paperdolls between 1/2
		19 => 'Play', // aka Msg
		20 => 'Hit', // aka Spec
		21 => 'Cast', // aka Admin
		22 => 'List',
		// 23 => 'Normal', // unconfirmed / unused
		24 => 'Private',
		25 => 'Public', // aka Report
		26 => 'Global', // aka Announce
		27 => 'System', // aka Server
		28 => 'Drop',
		29 => 'Junk',
		30 => 'Give', // aka Obtain
		//31 => 'Time', // new? used to swap map between day/night mode (MAP_31)
		32 => 'Spawn', // new! -- used for gathering node respawn (GATHER_32)
		33 => 'Swap', // new! -- used for paperdoll weapon swap reply
		34 => 'Pickup', // aka Get
		35 => 'Remove', // aka Kick
		36 => 'Rank',
		37 => 'Self', // aka Target_Self
		38 => 'Target', // aka Target_Other
		// 39 => 'Area', // unused
		40 => 'Group', // aka Target_Group
		41 => 'Tank', // aka Dialog
		231 => 'Syn1', // aka Ping
		232 => 'Syn2', // aka Pong
		233 => 'Syn3', // aka Net3 / Net242
		234 => 'Syn4', // aka Net243
		235 => 'Syn5', // aka Net244
		236 => 'Syn6', // aka Net245
		250 => 'Error',
		255 => 'Init'
	];

	if (isset($packet_action_names[$i]))
		return $packet_action_names[$i];
	else
		return $i;
}

