<?php
/*
 * php-FIT-File-Reader
 * ===================
 * A PHP class for reading FIT files created by Garmin GPS devices.
 * Adrian Gibbons, 2014
 * 
 * http://www.thisisant.com/resources/fit
 */
 
define("FIT_INVALID", 0);
define("FIT_DEFINITION", 1);
define("FIT_DATA", 2);

class phpFITFileReader {
	public $data = [];  // used to store the data read from the file in associative arrays.
	
	private $file_contents;
	private $FITDefnMesgs = [];
	private $file_header = [];
	private $timestamp = 0;
	
	private $enum_data = [
		'activity' => [0 => 'manual', 1 => 'auto_multi_sport'],
		'battery_status' => [1 => 'new', 2 => 'good', 3 => 'ok', 4 => 'low', 5 => 'critical'],
		'display_measure' => [],  /****** NOT IN THE PROFILE.XLS DOCUMENT!!! ******/
		'event' => [
			0 => 'timer',
			3 => 'workout',
			4 => 'workout_step',
			5 => 'power_down',
			6 => 'power_up',
			7 => 'off_course',
			8 => 'session',
			9 => 'lap',
			10 => 'course_point',
			11 => 'battery',
			12 => 'virtual_partner_pace',
			13 => 'hr_high_alert',
			14 => 'hr_low_alert',
			15 => 'speed_high_alert',
			16 => 'speed_low_alert',
			17 => 'cad_high_alert',
			18 => 'cad_low_alert',
			19 => 'power_high_alert',
			20 => 'power_low_alert',
			21 => 'recovery_hr',
			22 => 'battery_low',
			23 => 'time_duration_alert',
			24 => 'distance_duration_alert',
			25 => 'calorie_duration_alert',
			26 => 'activity',
			27 => 'fitness_equipment',
			28 => 'length',
			32 => 'user_marker',
			33 => 'sport_point',
			36 => 'calibration',
			42 => 'front_gear_change',
			43 => 'rear_gear_change',
			45 => 'elev_high_alert',
			46 => 'elev_low_alert'
		],
		'event_type' => [
			0 => 'start',
			1 => 'stop',
			2 => 'consecutive_depreciated',
			3 => 'marker',
			4 => 'stop_all',
			5 => 'begin_depreciated',
			6 => 'end_depreciated',
			7 => 'end_all_depreciated',
			8 => 'stop_disable',
			9 => 'stop_disable_all'
		],
		'file' => [
			1 => 'device',
			2 => 'settings',
			3 => 'sport',
			4 => 'activity',
			5 => 'workout',
			6 => 'course',
			7 => 'schedules',
			9 => 'weight',
			10 => 'totals',
			11 => 'goals',
			14 => 'blood_pressure',
			15 => 'monitoring_a',
			20 => 'activity_summary',
			28 => 'monitoring_daily',
			32 => 'monitoring_b',
			0xF7 => 'mfg_range_min',
			0xFE => 'mfg_range_max'
		],
		'intensity' => [0 => 'active', 1 => 'rest', 2 => 'warmup', 3 => 'cooldown'],
		'length_type' => [0 => 'idle', 1 => 'active'],
		'manufacturer' => [
			1 => 'garmin',
			2 => 'garmin_fr405_antfs',
			3 => 'zephyr',
			4 => 'dayton',
			5 => 'idt',
			6 => 'srm',
			7 => 'quarq',
			8 => 'ibike',
			9 => 'saris',
			10 => 'spark_hk',
			11 => 'tanita',
			12 => 'echowell',
			13 => 'dynastream_oem',
			14 => 'nautilus',
			15 => 'dynastream',
			16 => 'timex',
			17 => 'metrigear',
			18 => 'xelic',
			19 => 'beurer',
			20 => 'cardiosport',
			21 => 'a_and_d',
			22 => 'hmm',
			23 => 'suunto',
			24 => 'thita_elektronik',
			25 => 'gpulse',
			26 => 'clean_mobile',
			27 => 'pedal_brain',
			28 => 'peaksware',
			29 => 'saxonar',
			30 => 'lemond_fitness',
			31 => 'dexcom',
			32 => 'wahoo_fitness',
			33 => 'octane_fitness',
			34 => 'archinoetics',
			35 => 'the_hurt_box',
			36 => 'citizen_systems',
			37 => 'magellan',
			38 => 'osynce',
			39 => 'holux',
			40 => 'concept2',
			42 => 'one_giant_leap',
			43 => 'ace_sensor',
			44 => 'brim_brothers',
			45 => 'xplova',
			46 => 'perception_digital',
			47 => 'bf1systems',
			48 => 'pioneer',
			49 => 'spantec',
			50 => 'metalogics',
			51 => '4iiiis',
			52 => 'seiko_epson',
			53 => 'seiko_epson_oem',
			54 => 'ifor_powell',
			55 => 'maxwell_guider',
			56 => 'star_trac',
			57 => 'breakaway',
			58 => 'alatech_technology_ltd',
			59 => 'mio_technology_europe',
			60 => 'rotor',
			61 => 'geonaute',
			62 => 'id_bike',
			63 => 'specialized',
			64 => 'wtek',
			65 => 'physical_enterprises',
			66 => 'north_pole_engineering',
			67 => 'bkool',
			68 => 'cateye',
			69 => 'stages_cycling',
			70 => 'sigmasport',
			71 => 'tomtom',
			72 => 'peripedal',
			73 => 'wattbike',
			76 => 'moxy',
			77 => 'ciclosport',
			78 => 'powerbahn',
			79 => 'acorn_projects_aps',
			80 => 'lifebeam',
			81 => 'bontrager',
			82 => 'wellgo',
			83 => 'scosche',
			84 => 'magura',
			85 => 'woodway',
			86 => 'elite',
			87 => 'nielsen_kellerman',
			88 => 'dk_city',
			89 => 'tacx',
			90 => 'direction_technology',
			91 => 'magtonic',
			92 => '1partcarbon',
			93 => 'inside_ride_technologies',
			255 => 'development',
			257 => 'healthandlife',
			258 => 'lezyne',
			259 => 'scribe_labs',
			5759 => 'actigraphcorp'
		],
		'product' => [
			1 => 'hrm1',
			2 => 'axh01',
			3 => 'axb01',
			4 => 'axb02',
			5 => 'hrm2ss',
			6 => 'dsi_alf02',
			7 => 'hrm3ss',
			8 => 'hrm_run_single_byte_product_id',
			9 => 'bsm',
			10 => 'bcm',
			473 => 'fr301_china',
			474 => 'fr301_japan',
			475 => 'fr301_korea',
			494 => 'fr301_taiwan',
			717 => 'fr405',
			782 => 'fr50',
			987 => 'fr405_japan',
			988 => 'fr60',
			1011 => 'dsi_alf01',
			1018 => 'fr310xt',
			1036 => 'edge500',
			1124 => 'fr110',
			1169 => 'edge800',
			1199 => 'edge500_taiwan',
			1213 => 'edge500_japan',
			1253 => 'chirp',
			1274 => 'fr110_japan',
			1325 => 'edge200',
			1328 => 'fr910xt',
			1333 => 'edge800_taiwan',
			1334 => 'edge800_japan',
			1341 => 'alf04',
			1345 => 'fr610',
			1360 => 'fr210_japan',
			1380 => 'vector_ss',
			1381 => 'vector_cp',
			1386 => 'edge800_china',
			1387 => 'edge500_china',
			1410 => 'fr610_japan',
			1422 => 'edge500_korea',
			1436 => 'fr70',
			1446 => 'fr310xt_4t',
			1461 => 'amx',
			1482 => 'fr10',
			1497 => 'edge800_korea',
			1499 => 'swim',
			1537 => 'fr910xt_china',
			1551 => 'fenix',
			1555 => 'edge200_taiwan',
			1561 => 'edge510',
			1567 => 'edge810',
			1570 => 'tempe',
			1600 => 'fr910xt_japan',
			1623 => 'fr620',
			1632 => 'fr220',
			1664 => 'fr910xt_korea',
			1688 => 'fr10_japan',
			1721 => 'edge810_japan',
			1735 => 'virb_elite',
			1736 => 'edge_touring',
			1742 => 'edge510_japan',
			1752 => 'hrm_run',
			1821 => 'edge510_asia',
			1822 => 'edge810_china',
			1823 => 'edge810_taiwan',
			1836 => 'edge1000',
			1837 => 'vivo_fit',
			1853 => 'virb_remote',
			1885 => 'vivo_ki',
			1903 => 'fr15',
			1918 => 'edge510_korea',
			1928 => 'fr620_japan',
			1929 => 'fr620_china',
			1930 => 'fr220_japan',
			1931 => 'fr220_china',
			1967 => 'fenix2',
			10007 => 'sdm4',
			10014 => 'edge_remote',
			20119 => 'training_center',
			65532 => 'android_antplus_plugin',
			65534 => 'connect'
		],
		'sport' => [
			0 => 'generic',
			1 => 'running',
			2 => 'cycling',
			3 => 'transition',
			4 => 'fitness_equipment',
			5 => 'swimming',
			6 => 'basketball',
			7 => 'soccer',
			8 => 'tennis',
			9 => 'american_football',
			10 => 'training',
			11 => 'walking',
			12 => 'cross_country_skiing',
			13 => 'alpine_skiing',
			14 => 'snowboarding',
			15 => 'rowing',
			16 => 'mountaineering',
			17 => 'hiking',
			18 => 'multisport',
			19 => 'paddling',
			254 => 'all'
		],
		'sub_sport' => [
			0 => 'generic',
			1 => 'treadmill',
			2 => 'street',
			3 => 'trail',
			4 => 'track',
			5 => 'spin',
			6 => 'indoor_cycling',
			7 => 'road',
			8 => 'mountain',
			9 => 'downhill',
			10 => 'recumbent',
			11 => 'cyclocross',
			12 => 'hand_cycling',
			13 => 'track_cycling',
			14 => 'indoor_rowing',
			15 => 'elliptical',
			16 => 'stair_climbing',
			17 => 'lap_swimming',
			18 => 'open_water',
			19 => 'flexibility_training',
			20 => 'strength_training',
			21 => 'warm_up',
			22 => 'match',
			23 => 'exercise',
			24 => 'challenge',
			25 => 'indoor_skiing',
			26 => 'cardio_training',
			27 => 'indoor_walking',
			254 => 'all'
		],
		'session_trigger' => [0 => 'activity_end', 1 => 'manual', 2 => 'auto_multi_sport', 3 => 'fitness_equipment'],
		'swim_stroke' => [0 => 'freestyle', 1 => 'backstroke', 2 => 'breaststroke', 3 => 'butterfly', 4 => 'drill', 5 => 'mixed', 6 => 'im']
	];
	
	private $types = array(
		'activity'			=> ['C1tmp', 1],  // enum
		'battery_status'	=> ['C1tmp', 1],  // uint8
		'date_time'			=> ['V1tmp', 4],  // uint32
		'device_index'		=> ['C1tmp', 1],  // uint8
		'display_measure'	=> ['C1tmp', 1],  // enum
		'event'				=> ['C1tmp', 1],  // enum
		'event_type'		=> ['C1tmp', 1],  // enum
		'file'				=> ['C1tmp', 1],  // enum
		'intensity'			=> ['C1tmp', 1],  // enum
		'left_right_balance_100'	=> ['v1tmp', 2],  // uint16
		'length_type'		=> ['C1tmp', 1],  // enum
		'local_date_time'	=> ['V1tmp', 4],  // uint32
		'manufacturer'		=> ['v1tmp', 2],  // uint16
		'message_index'		=> ['v1tmp', 2],  // uint16
		'sport'				=> ['C1tmp', 1],  // enum
		'sub_sport'			=> ['C1tmp', 1],  // enum
		'session_trigger'	=> ['C1tmp', 1],  // enum
		'swim_stroke'		=> ['C1tmp', 1],  // enum
		'enum'		=> ['C1tmp', 1],
		'sint8'		=> ['c1tmp', 1],
		'uint8'		=> ['C1tmp', 1],
		'sint16'	=> ['S1tmp', 2],
		'uint16'	=> ['v1tmp', 2],
		'sint32'	=> ['l1tmp', 4],
		'uint32'	=> ['V1tmp', 4],
	//	'string'	=> ['', 1], don't need these yet so haven't worked-out how to use PHP unpack()
	//	'float32'	=> ['', 4],
	//	'float64'	=> ['', 8],
	//	'uint8z'	=> ['', 1],
	//	'uint16z'	=> ['', 2],
		'uint32z'	=> ['V1tmp', 4],
	//	'byte'		=> ['', 1]
	);
	
	/*
	 * Field Def # => [Field Name, Field Type, Scale, Offset, Units]
	 *
	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 * 4.4 Scale/Offset
	 * When specified, the binary quantity is divided by the scale factor and then the offset is subtracted, yielding a floating point quantity.
	 */
	private $messages = [
		0 => [
			'file_id', [
				0 => ['type', 'file', 1, 0, ''],
				1 => ['manufacturer', 'manufacturer', 1, 0, ''],
				2 => ['product', 'uint16', 1, 0, ''],
				3 => ['serial_number', 'uint32z', 1, 0, ''],
				4 => ['time_created', 'date_time', 1, 0, ''],
				5 => ['number', 'uint16', 1, 0, '']
			]
		],
		
		18 => [
			'session', [
				0 => ['event', 'event', 1, 0, ''],
				1 => ['event_type', 'event_type', 1, 0, ''],
				2 => ['start_time', 'date_time', 1, 0, ''],
				3 => ['start_position_lat', 'sint32', 1, 0, 'semicircles'],
				4 => ['start_position_long', 'sint32', 1, 0, 'semicircles'],
				5 => ['sport', 'sport', 1, 0, ''],
				6 => ['sub_sport', 'sub_sport', 1, 0, ''],
				7 => ['total_elapsed_time', 'uint32', 1000, 0, 's'],
				8 => ['total_timer_time', 'uint32', 1000, 0, 's'],
				9 => ['total_distance', 'uint32', 100, 0, 'm'],
				10 => ['total_cycles', 'uint32', 1, 0, 'cycles'],
				11 => ['total_calories', 'uint16', 1, 0, 'kcal'],
				13 => ['total_fat_calories', 'uint16', 1, 0, 'kcal'],
				14 => ['avg_speed', 'uint16', 1000, 0, 'm/s'],
				15 => ['max_speed', 'uint16', 1000, 0, 'm/s'],
				16 => ['avg_heart_rate', 'uint8', 1, 0, 'bpm'],
				17 => ['max_heart_rate', 'uint8', 1, 0, 'bpm'],
				18 => ['avg_cadence', 'uint8', 1, 0, 'rpm'],
				19 => ['max_cadence', 'uint8', 1, 0, 'rpm'],
				20 => ['avg_power', 'uint16', 1, 0, 'watts'],
				21 => ['max_power', 'uint16', 1, 0, 'watts'],
				22 => ['total_ascent', 'uint16', 1, 0, 'm'],
				23 => ['total_descent', 'uint16', 1, 0, 'm'],
				24 => ['total_training_effect', 'uint8', 10, 0, ''],
				25 => ['first_lap_index', 'uint16', 1, 0, ''],
				26 => ['num_laps', 'uint16', 1, 0, ''],
				27 => ['event_group', 'uint8', 1, 0, ''],
				28 => ['trigger', 'session_trigger', 1, 0, ''],
				29 => ['nec_lat', 'sint32', 1, 0, 'semicircles'],
				30 => ['nec_long', 'sint32', 1, 0, 'semicircles'],
				31 => ['swc_lat', 'sint32', 1, 0, 'semicircles'],
				32 => ['swc_long', 'sint32', 1, 0, 'semicircles'],
				34 => ['normalized_power', 'uint16', 1, 0, 'watts'],
				35 => ['training_stress_score', 'uint16', 10, 0, 'tss'],
				36 => ['intensity_factor', 'uint16', 1000, 0, 'if'],
				37 => ['left_right_balance', 'left_right_balance_100', 1, 0, ''],
				41 => ['avg_stroke_count', 'uint32', 10, 0, 'strokes/lap'],
				42 => ['avg_stroke_distance', 'uint16', 100, 0, 'm'],
				43 => ['swim_stroke', 'swim_stroke', 1, 0, 'swim_stroke'],
				44 => ['pool_length', 'uint16', 100, 0, 'm'],
				46 => ['pool_length_unit', 'display_measure', 1, 0, ''],
				47 => ['num_active_lengths', 'uint16', 1, 0, 'lengths'],
				48 => ['total_work', 'uint32', 1, 0, 'J'],
				68 => ['time_in_power_zone', 'uint32', 1000, 0, 's'],
				253 => ['timestamp', 'date_time', 1, 0, 's'],
				254 => ['message_index', 'message_index', 1, 0, '']
			]
		],
		
		19 => [
			'lap', [
				0 => ['event', 'event', 1, 0, ''],
				1 => ['event_type', 'event_type', 1, 0, ''],
				2 => ['start_time', 'date_time', 1, 0, ''],
				3 => ['start_position_lat', 'sint32', 1, 0, 'semicircles'],
				4 => ['start_position_long', 'sint32', 1, 0, 'semicircles'],
				5 => ['end_position_lat', 'sint32', 1, 0, 'semicircles'],
				6 => ['end_position_long', 'sint32', 1, 0, 'semicircles'],
				7 => ['total_elapsed_time', 'uint32', 1000, 0, 's'],
				8 => ['total_timer_time', 'uint32', 1000, 0, 's'],
				9 => ['total_distance', 'uint32', 100, 0, 'm'],
				10 => ['total_cycles', 'uint32', 1, 0, 'cycles'],
				11 => ['total_calories', 'uint16', 1, 0, 'kcal'],
				12 => ['total_fat_calories', 'uint16', 1, 0, 'kcal'],
				13 => ['avg_speed', 'uint16', 1000, 0, 'm/s'],
				14 => ['max_speed', 'uint16', 1000, 0, 'm/s'],
				15 => ['avg_heart_rate', 'uint8', 1, 0, 'bpm'],
				16 => ['max_heart_rate', 'uint8', 1, 0, 'bpm'],
				17 => ['avg_cadence', 'uint8', 1, 0, 'rpm'],
				18 => ['max_cadence', 'uint8', 1, 0, 'rpm'],
				19 => ['avg_power', 'uint16', 1, 0, 'watts'],
				20 => ['max_power', 'uint16', 1, 0, 'watts'],
				21 => ['total_ascent', 'uint16', 1, 0, 'm'],
				22 => ['total_descent', 'uint16', 1, 0, 'm'],
				23 => ['intensity', 'intensity', 1, 0, ''],
				25 => ['sport', 'sport', 1, 0, ''],
				26 => ['event_group', 'uint8', 1, 0, ''],
				32 => ['num_lengths', 'uint16', 1, 0, 'lengths'],
				33 => ['normalized_power', 'uint16', 1, 0, 'watts'],
				34 => ['left_right_balance', 'left_right_balance_100', 1, 0, ''],
				35 => ['first_length_index', 'uint16', 1, 0, ''],
				37 => ['avg_stroke_distance', 'uint16', 100, 0, 'm'],
				38 => ['swim_stroke', 'swim_stroke', 1, 0, ''],
				40 => ['num_active_lengths', 'uint16', 1, 0, 'lengths'],
				41 => ['total_work', 'uint32', 1, 0, 'J'],
				60 => ['time_in_power_zone', 'uint32', 1000, 0, 's'],
				253 => ['timestamp', 'date_time', 1, 0, 's'],
				254 => ['message_index', 'message_index', 1, 0, '']
			]
		],
		
		20 => [
			'record', [
				0 => ['position_lat', 'sint32', 1, 0, 'semicircles'],
				1 => ['position_long', 'sint32', 1, 0, 'semicircles'],
				2 => ['altitude', 'uint16', 5, 500, 'm'],
				3 => ['heart_rate', 'uint8', 1, 0, 'bpm'],
				4 => ['cadence', 'uint8', 1, 0, 'rpm'],
				5 => ['distance', 'uint32', 100, 0, 'm'],
				6 => ['speed', 'uint16', 1000, 0, 'm/s'],
				7 => ['power', 'uint16', 1, 0, 'watts'],
				13 => ['temperature', 'sint8', 1, 0, 'C'],
				253 => ['timestamp', 'date_time', 1, 0, 's']
			]
		],
		
		21 => [
			'event', [
				0 => ['event', 'event', 1, 0, ''],
				1 => ['event_type', 'event_type', 1, 0, ''],
				3 => ['data', 'uint32', 1, 0, ''],
				4 => ['event_group', 'uint8', 1, 0, ''],
				253 => ['timestamp', 'date_time', 1, 0, 's']
			]
		],
		
		23 => [
			'device_info', [
				0 => ['device_index', 'device_index', 1, 0, ''],
				1 => ['device_type', 'uint8', 1, 0, ''],
				2 => ['manufacturer', 'manufacturer', 1, 0, ''],
				3 => ['serial_number', 'uint32z', 1, 0, ''],
				4 => ['product', 'uint16', 1, 0, ''],
				5 => ['software_version', 'uint16', 1, 0, ''],
				6 => ['hardware_version', 'uint8', 1, 0, ''],
				7 => ['cum_operating_time', 'uint32', 1, 0, ''],
				10 => ['battery_voltage', 'uint16', 1, 0, ''],
				11 => ['battery_status', 'battery_status', 1, 0, ''],
				253 => ['timestamp', 'date_time', 1, 0, 's']
			]
		],
		
		34 => [
			'activity', [
				0 => ['total_timer_time', 'uint32', 1000, 0, 's'],
				1 => ['num_sessions', 'uint16', 1, 0, ''],
				2 => ['type', 'activity', 1, 0, ''],
				3 => ['event', 'event', 1, 0, ''],
				4 => ['event_type', 'event_type', 1, 0, ''],
				5 => ['local_timestamp', 'local_date_time', 1, 0, ''],
				6 => ['event_group', 'uint8', 1, 0, ''],
				253 => ['timestamp', 'date_time', 1, 0, 's']
			]
		],
		
		49 => [
			'file_creator', [
				0 => ['software_version', 'uint16', 1, 0, ''],
				1 => ['hardware_version', 'uint8', 1, 0, '']
			]
		],
		
		101 => [
			'length', [
				0 => ['event', 'event', 1, 0, ''],
				1 => ['event_type', 'event_type', 1, 0, ''],
				2 => ['start_time', 'date_time', 1, 0, ''],
				3 => ['total_elapsed_time', 'uint32', 1000, 0, 's'],
				4 => ['total_timer_time', 'uint32', 1000, 0, 's'],
				5 => ['total_strokes', 'uint16', 1, 0, 'strokes'],
				6 => ['avg_speed', 'uint16', 1000, 0, 'm/s'],
				7 => ['swim_stroke', 'swim_stroke', 1, 0, 'swim_stroke'],
				9 => ['avg_swimming_cadence', 'uint8', 1, 0, 'strokes/min'],
				10 => ['event_group', 'uint8', 1, 0, ''],
				11 => ['total_calories', 'uint16', 1, 0, 'kcal'],
				12 => ['length_type', 'length_type', 1, 0, ''],
				253 => ['timestamp', 'date_time', 1, 0, 's'],
				254 => ['message_index', 'message_index', 1, 0, '']
			]
		]
	];
	
	function __construct($file_path, $options=NULL) {
		if(empty($file_path)) {
			throw new Exception("phpFITFileReader->__construct(): file_path is empty!");
		}
		if(!file_exists($file_path)) {
			throw new Exception("phpFITFileReader->__construct(): file '$file_path' does not exist!");
		}
		
		/*
		 * 1. Read entire file using file_get_contents()
		 * 2. Split the string into bytes using str_split()
		 * 3. Reverse the array so we can use array_pop()
		 */
		$this->file_contents = array_reverse(str_split(file_get_contents($file_path)));
		
		// Get rid of the first two array elements. These are the CRC bytes and unused by this class.
		array_shift($this->file_contents);
		array_shift($this->file_contents);
		
		$this->read_file_header();
		$this->read_file_data();
		$this->one_element_arrays();
		
		/*
		 * Handle options
		 */
		$this->fix_data($options);
		$this->set_units($options);
	}
	
	/*
	 * Byte Description of FIT File Header
	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 * Table 3-1. Byte Description of File Header
	 *
	 *  byte | parameter           | php format code for unpack()
	 * ------+---------------------+-------------------------------------------------------------
	 *   0   | Header Size         | C unsigned char
	 *   1   | Protocol Version    | C unsigned char
	 *   2   | Profile Version LSB | v unsigned short (always 16 bit, little endian byte order)
	 *   3   | Profile Version MSB |
	 *   4   | Data Size LSB       | V unsigned long (always 32 bit, little endian byte order)
	 *   5   | Data Size           |
	 *   6   | Data Size           |
	 *   7   | Data Size MSB       |
	 *   8   | Data Type Byte[0]   | C unsigned char
	 *   9   | Data Type Byte[1]   | C unsigned char
	 *   10  | Data Type Byte[2]   | C unsigned char
	 *   11  | Data Type Byte[3]   | C unsigned char
	 *   12  | CRC LSB             | v unsigned short (always 16 bit, little endian byte order)
	 *   13  | CRC MSB             |
	 */
	private function read_file_header() {
		$this->file_header["header_size"] = unpack("C1tmp", array_pop($this->file_contents))["tmp"];
		
		if($this->file_header["header_size"] != 12 && $this->file_header["header_size"] != 14) {
			throw new Exception("phpFITFileReader->read_file_header(): not a valid header size!");
		}
		$this->file_header = unpack("C1protocol_version/".
				"v1profile_version/".
				"V1data_size/".
				"C4data_type/".
				"v1crc", $this->get_bytes($this->file_header["header_size"] - 1)
			);
		
		$file_type = sprintf("%c%c%c%c", $this->file_header["data_type1"], $this->file_header["data_type2"], $this->file_header["data_type3"], $this->file_header["data_type4"]);
		
		if($file_type != ".FIT" || $this->file_header["data_size"] <= 0) {
			throw new Exception("phpFITFileReader->read_file_header(): not a valid FIT file!");
		}
	}
	
	private function get_bytes($numBytes) {
		$data = "";
		while($numBytes-- > 0) {
			$data .= array_pop($this->file_contents);
		}
		return $data;
	}
	
	private function whats_next($byte) {
		if(!($byte ^ bindec('11111111')) || $byte === false)
			return FIT_INVALID;
		else if(($byte >> 6) & 1)
			return FIT_DEFINITION;
		else
			return FIT_DATA;
	}
	
	private function read_file_data() {
		$record_header = NULL;
		
		while(($record_header = array_pop($this->file_contents)) !== NULL) {
			$record_header = ord($record_header);
			
			$local_mesg_num = $record_header & bindec('1111');
			
			if(!($record_header >> 7) ^ 1) {  // check if its a normal header
				echo "not normal!<br>";
				break;
			}
			
			switch($this->whats_next($record_header)) {
				case FIT_DEFINITION:
					// don't really care about these two so not going to do anything with them
					array_pop($this->file_contents);  // reserved
					array_pop($this->file_contents);  // architecture
					
					$global_mesg_num = unpack('v1global_mesg_num', $this->get_bytes(2))["global_mesg_num"];
					$num_fields = ord(array_pop($this->file_contents));
					
					$field_definitions = [];
					$total_size = 0;
					for($i=0; $i<$num_fields; ++$i) {
						$defn = ord(array_pop($this->file_contents));
						$size = ord(array_pop($this->file_contents));
						$type = ord(array_pop($this->file_contents));
						
						$field_definitions[$i] = ["defn" => $defn, "size" => $size, "type" => $type];
						$total_size += $size;
					}
					
					$this->FITDefnMesgs[$local_mesg_num] = [
							"global_mesg_num" => $global_mesg_num,
							"num_fields" => $num_fields,
							"field_defns" => $field_definitions,
							"total_size" => $total_size
						];
					break;
				
				case FIT_DATA:
					if(array_key_exists($this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"], $this->messages)) {
						foreach($this->FITDefnMesgs[$local_mesg_num]["field_defns"] as $field_defn) {
							if(array_key_exists($field_defn['defn'], $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1])) {
								if($this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"] === 20 && $field_defn['defn'] === 253) {
									$this->timestamp = $this->data[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][0]][$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][0]][] = (unpack($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][0], $this->get_bytes($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][1]))["tmp"] / $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][2]) - $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][3];
								}
								else if($this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"] === 20) {
									$this->data[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][0]][$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][0]][$this->timestamp] = (unpack($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][0], $this->get_bytes($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][1]))["tmp"] / $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][2]) - $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][3];
								}
								else {
									$this->data[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][0]][$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][0]][] = (unpack($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][0], $this->get_bytes($this->types[$this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][1]][1]))["tmp"] / $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][2]) - $this->messages[$this->FITDefnMesgs[$local_mesg_num]["global_mesg_num"]][1][$field_defn['defn']][3];
								}
							}
							else {
								// drop the bytes - unset() is quicker than array_pop()
								$last_element = count($this->file_contents) - 1;
								while($field_defn["size"]-- > 0) {
									unset($this->file_contents[$last_element - $field_defn["size"]]);
								}
							}
						}
					}
					else {
						// drop the bytes - unset() is quicker than array_pop()
						$last_element = count($this->file_contents) - 1;
						$offset = $this->FITDefnMesgs[$local_mesg_num]["total_size"];
						while($offset-- > 0) {
							unset($this->file_contents[$last_element - $offset]);
						}
					}
			}
		}
	}
	
	public function get_enum_data($type, $val) {
		if(is_array($val)) {
			$tmp = [];
			foreach($val as $element) {
				if(isset($this->enum_data[$type][$element]))
					$tmp[] = $this->enum_data[$type][$element];
				else
					$tmp[] = "unknown";
			}
			return $tmp;
		}
		else {
			return isset($this->enum_data[$type][$val]) ? $this->enum_data[$type][$val] : "unknown";
		}
	}
	
	private function one_element_arrays() {
		foreach($this->data as $mesg_key => $mesg) {
			foreach($mesg as $field_key => $field) {
				if(count($field) === 1) {
// GETTING "Notice: Undefined offset: 0" FOR SWIM FILES
// FIRST ELEMENT IN ARRAY IS -1 AND NOT 0
//					if(array_key_exists(-1, $field)) {
//						echo "*** $mesg_key $field_key ***<br>";
//						var_dump($field);
//						echo "<br><br>";
//					}
					$this->data[$mesg_key][$field_key] = $field[0];
				}
			}
		}
	}
	
	public function show_debug_info() {
		echo "<h3>Types</h3>";
		echo "<table class='table table-condensed table-striped'>";
		echo "<thead>";
		echo "<th>key</th>";
		echo "<th>PHP unpack() format</th>";
		echo "<th>bytes</th>";
		echo "</thead>";
		echo "<tbody>";
		foreach( $this->types as $key => $val ) {
			echo "<tr><td>$key</td><td>$val[0]</td><td>$val[1]</td></tr>";
		}
		echo "</tbody>";
		echo "</table>";
		echo " * note: the 'tmp' suffix to the format is required to make unpack() work as required.";
		
		echo "<br><hr><br>";
		
		echo "<h3>Messages and Fields being listened for</h3>";
		foreach( $this->messages as $key => $val ) {
		echo "<h4>$val[0] ($key)</h4>";
			echo "<table class='table table-condensed table-striped'>";
			echo "<thead><th>ID</th><th>Name</th><th>Type</th><th>Scale</th><th>Offset</th><th>Units</th></thead><tbody>";
			foreach( $val[1] as $key2 => $val2 ) {
				echo "<tr><td>$key2</td><td>$val2[0]</td><td>$val2[1]</td><td>$val2[2]</td><td>$val2[3]</td><td>$val2[4]</td></tr>";
			}
			echo "</tbody></table><br><br>";
		}
		
		echo "<br><hr><br>";
		
		echo "<h3>FIT Definition Messages contained within the file</h3>";
        echo "<table class='table table-condensed table-striped'>";
		echo "<thead>";
		echo "<th>global_mesg_num</th>";
		echo "<th>num_fields</th>";
		echo "<th>field defns</th>";
		echo "<th>total_size</th>";
		echo "</thead>";
		echo "<tbody>";
		foreach( $this->FITDefnMesgs as $key => $val ) {
			echo  "<tr><td>".$val['global_mesg_num']."</td><td>".$val['num_fields']."</td><td>";
			
			foreach($val['field_defns'] as $defn) {
				echo  "defn: ".$defn['defn']."; size: ".$defn['size']."; type: ".$defn['type']."<br>";
			}
			echo  "</td><td>".$val['total_size']."</td></tr>";
		}
		echo "</tbody>";
		echo "</table>";
		
		echo "<br><hr><br>";
		
		echo "<h3>Messages found in file</h3>";
		foreach($this->data as $mesg_key => $mesg) {
			echo "<table class='table table-condensed table-striped'>";
			echo "<thead><th>$mesg_key</th><th>count()</th></thead><tbody>";
			foreach($mesg as $field_key => $field) {
				echo "<tr><td>$field_key</td><td>".count($field)."</td></tr>";
			}
			echo "</tbody></table><br><br>";
		}
	}
	
	public function get_manufacturer() {
		$tmp = $this->get_enum_data("manufacturer", $this->data["device_info"]["manufacturer"]);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_product() {
		$tmp = $this->get_enum_data("product", $this->data["device_info"]["product"]);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_sport() {
		$tmp = $this->get_enum_data("sport", $this->data["session"]["sport"]);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_sub_sport() {
		$tmp = $this->get_enum_data("sub_sport", $this->data["session"]["sub_sport"]);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_swim_stroke() {
		if($this->get_sport() == "swimming") {
			$tmp = $this->get_enum_data("swim_stroke", $this->data["session"]["swim_stroke"]);
			return is_array($tmp) ? $tmp[0] : $tmp;
		}
		else {
			return "n/a";
		}
	}
	
	/*
	 * 
	 */
	private function fix_data($options) {
		if(!isset($options['fix_data_options']))
			return;
		$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = false;
		if(in_array('all', $options['fix_data_options'])) {
			$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = true;
		}
		else {
			$bCadence = in_array('cadence', $options['fix_data_options']);
			$bDistance = in_array('distance', $options['fix_data_options']);
			$bHeartRate = in_array('heart_rate', $options['fix_data_options']);
			$bLatitudeLongitude = in_array('lat_lon', $options['fix_data_options']);
			$bSpeed = in_array('speed', $options['fix_data_options']);
			$bPower = in_array('power', $options['fix_data_options']);
		}
		$missing_distance_keys = [];
		$missing_hr_keys = [];
		$missing_lat_keys = [];
		$missing_lon_keys = [];
		$missing_speed_keys = [];
		$missing_power_keys = [];
		
		foreach($this->data["record"]["timestamp"] as $timestamp) {
			if($bCadence) {  // Assumes all missing cadence values are zeros
				if(!isset($this->data["record"]["cadence"][$timestamp])) {
					$this->data["record"]["cadence"][$timestamp] = 0;
				}
			}
			if($bDistance) {
				if(!isset($this->data["record"]["distance"][$timestamp])) {
					$missing_distance_keys[] = $timestamp;
				}
			}
			if($bHeartRate) {
				if(!isset($this->data["record"]["heart_rate"][$timestamp])) {
					$missing_hr_keys[] = $timestamp;
				}
			}
			if($bLatitudeLongitude) {
				if(!isset($this->data["record"]["position_lat"][$timestamp])) {
					$missing_lat_keys[] = $timestamp;
				}
				if(!isset($this->data["record"]["position_long"][$timestamp])) {
					$missing_lon_keys[] = $timestamp;
				}
			}
			if($bSpeed) {
				if(!isset($this->data["record"]["speed"][$timestamp])) {
					$missing_speed_keys[] = $timestamp;
				}
			}
			if($bPower) {
				if(!isset($this->data["record"]["power"][$timestamp])) {
					$missing_power_keys[] = $timestamp;
				}
			}
		}
		
		
		if($bCadence && isset($this->data["record"]["cadence"])) {
			ksort($this->data["record"]["cadence"]);  // no interpolation; zeros added earlier
		}
		if($bDistance && isset($this->data["record"]["distance"])) {
			$this->interpolate_missing_data($missing_distance_keys, $this->data["record"]["distance"]);
		}
		if($bHeartRate && isset($this->data["record"]["heart_rate"])) {
			$this->interpolate_missing_data($missing_hr_keys, $this->data["record"]["heart_rate"]);
		}
		if($bLatitudeLongitude && isset($this->data["record"]["position_lat"]) && isset($this->data["record"]["position_long"])) {
			$this->interpolate_missing_data($missing_lat_keys, $this->data["record"]["position_lat"]);
			$this->interpolate_missing_data($missing_lon_keys, $this->data["record"]["position_long"]);
		}
		if($bSpeed && isset($this->data["record"]["speed"])) {
			$this->interpolate_missing_data($missing_speed_keys, $this->data["record"]["speed"]);
		}
		if($bPower && isset($this->data["record"]["power"])) {
			$this->interpolate_missing_data($missing_power_keys, $this->data["record"]["power"]);
		}
	}
	
	private function interpolate_missing_data(&$missing_keys, &$array){
		$num_points = 2;
		$prev_value;
		$next_value;
		
		for($i=0; $i<count($missing_keys); ++$i) {
			if($missing_keys[$i] !== 0) {
				while($missing_keys[$i] > key($array)) {
					$prev_value = current($array);
					next($array);
				}
				for($j=$i+1; $j<count($missing_keys); ++$j) {
					if($missing_keys[$j] < key($array)) {
						$num_points++;
					}
					else {
						break;
					}
				}
				$next_value = current($array);
				$gap = ($next_value - $prev_value) / $num_points;
				
				for($k=0; $k<=$num_points-2; ++$k) {
					$array[$missing_keys[$i+$k]] = $prev_value + ($gap * ($k+1));
				}
				for($k=0; $k<=$num_points-2; ++$k) {
					$missing_keys[$i+$k] = 0;
				}
				
				$num_points = 2;
			}
		}
		
		ksort($array);  // sort using keys
	}
	
	private function set_units($options) {
		if(!isset($options['set_units_options']) || in_array('metric', $options['set_units_options'])) {
			if(isset($this->data["record"]["speed"]))
				array_walk($this->data["record"]["speed"], function(&$val) { $val = round($val * 3.6, 3); });  // convert  meters per second to kilometers per hour
			if(isset($this->data["record"]["distance"]))
				array_walk($this->data["record"]["distance"], function(&$val) { $val = round($val * 0.001, 2); });  // convert from meters to kilometers
			if(isset($this->data["record"]["position_lat"]))
				array_walk($this->data["record"]["position_lat"], function(&$val) { $val = round($val * (180.0 / pow(2,31)), 5); });  // convert from semicircles to degress
			if(isset($this->data["record"]["position_long"]))
				array_walk($this->data["record"]["position_long"], function(&$val) { $val = round($val * (180.0 / pow(2,31)), 5); });  // convert from semicircles to degress
		}
		else if(in_array('statute', $options['set_units_options'])) {
			if(isset($this->data["record"]["speed"]))
				array_walk($this->data["record"]["speed"], function(&$val) { $val = round($val * 2.23693629, 3); });  // convert  meters per second to miles per hour
			if(isset($this->data["record"]["distance"]))
				array_walk($this->data["record"]["distance"], function(&$val) { $val = round($val * 0.000621371192, 2); });  // convert from meters to miles
			if(isset($this->data["record"]["altitude"]))
				array_walk($this->data["record"]["altitude"], function(&$val) { $val = round($val * 3.2808399, 1); });  // convert from meters to feet
			if(isset($this->data["record"]["position_lat"]))
				array_walk($this->data["record"]["position_lat"], function(&$val) { $val = round($val * (180.0 / pow(2,31)), 5); });  // convert from semicircles to degress
			if(isset($this->data["record"]["position_long"]))
				array_walk($this->data["record"]["position_long"], function(&$val) { $val = round($val * (180.0 / pow(2,31)), 5); });  // convert from semicircles to degress
			if(isset($this->data["record"]["temperature"]))
				array_walk($this->data["record"]["temperature"], function(&$val) { $tmp = (($val * 9) / 5) + 32; $val = round($tmp, 2); });  // convert from celsius to fahrenheit
		}
		else {  // raw
			// Do nothing - leave values as read from file.
		}
	}
}
?>
