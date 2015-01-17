<?php
/*
 * php-FIT-File-Reader
 * ===================
 * A PHP class for reading FIT files created by Garmin GPS devices.
 * Adrian Gibbons, 2015
 * 
 * http://www.thisisant.com/resources/fit
 */
 
define('DEFINITION_MESSAGE', 1);
define('DATA_MESSAGE', 0);

class phpFITFileReader {
	public $data_mesgs = [];  // Used to store the data read from the file in associative arrays.
	
	private $file_contents;		// FIT file is read-in to memory as a string, split into an array, and reversed. See __construct().
	private $defn_mesgs = [];	// Array of FIT 'Definition Messages', which describe the architecture, format, and fields of 'Data Messages'.
	private $file_header = [];	// Contains information about the FIT file such as the Protocol version, Profile version, and Data Size.
	private $timestamp = 0;		// Timestamps are used as the indexes for Record data (e.g. Speed, Heart Rate, etc).
	
	// Enumerated data looked up by get_enum_data().
	// Values from 'Profile.xls' contained within the FIT SDK.
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
	
	// Format string used by the PHP unpack() function to convert to an array from binary data.
	// 'tmp' is the name of the array created.
	private $types = array(
		0	=> 'Ctmp',	// enum
		1	=> 'ctmp',	// sint8
		2	=> 'Ctmp',	// uint8
		131	=> 'Stmp',	// sint16
		132	=> 'vtmp',	// uint16
		133	=> 'ltmp',	// sint32
		134	=> 'Vtmp',	// uint32
		7	=> 'Ctmp',	// string
		136	=> 'ftmp',	// float32
		137	=> 'dtmp',	// float64
		10	=> 'Ctmp',	// uint8z
		139	=> 'vtmp',	// uint16z
		140	=> 'Vtmp',	// uint32z
		13	=> 'Ctmp',	// byte
	);
	
	/*
	 * Field Def # => [Field Name, Field Type, Scale, Offset, Units]
	 *
	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 * 4.4 Scale/Offset
	 * When specified, the binary quantity is divided by the scale factor and then the offset is subtracted, yielding a floating point quantity.
	 */
	private $data_mesg_info = [
		0 => [
			'mesg_name' => 'file_id', 'field_defns' => [
				0 => ['field_name' => 'type',			'scale' => 1, 'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'manufacturer',	'scale' => 1, 'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'product',		'scale' => 1, 'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'serial_number',	'scale' => 1, 'offset' => 0, 'units' => ''],
				4 => ['field_name' => 'time_created',	'scale' => 1, 'offset' => 0, 'units' => ''],
				5 => ['field_name' => 'number',			'scale' => 1, 'offset' => 0, 'units' => ''],
			]
		],
		
		18 => [
			'mesg_name' => 'session', 'field_defns' => [
				0 => ['field_name' => 'event',					'scale' => 1, 'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'event_type',				'scale' => 1, 'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'start_time',				'scale' => 1, 'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'start_position_lat', 	'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				4 => ['field_name' => 'start_position_long',	'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				5 => ['field_name' => 'sport',					'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				6 => ['field_name' => 'sub_sport',				'scale' => 1, 'offset' => 0, 'units' => ''],
				7 => ['field_name' => 'total_elapsed_time',		'scale' => 1000, 'offset' => 0, 'units' => 's'],
				8 => ['field_name' => 'total_timer_time',		'scale' => 1000, 'offset' => 0, 'units' => 's'],
				9 => ['field_name' => 'total_distance',			'scale' => 100, 'offset' => 0, 'units' => 'm'],
				10 => ['field_name' => 'total_cycles',			'scale' => 1, 'offset' => 0, 'units' => 'cycles'],
				11 => ['field_name' => 'total_calories',		'scale' => 1, 'offset' => 0, 'units' => 'kcal'],
				13 => ['field_name' => 'total_fat_calories',	'scale' => 1, 'offset' => 0, 'units' => 'kcal'],
				14 => ['field_name' => 'avg_speed',				'scale' => 1000, 'offset' => 0, 'units' => 'm/s'],
				15 => ['field_name' => 'max_speed',				'scale' => 1000, 'offset' => 0, 'units' => 'm/s'],
				16 => ['field_name' => 'avg_heart_rate',		'scale' => 1, 'offset' => 0, 'units' => 'bpm'],
				17 => ['field_name' => 'max_heart_rate',		'scale' => 1, 'offset' => 0, 'units' => 'bpm'],
				18 => ['field_name' => 'avg_cadence',			'scale' => 1, 'offset' => 0, 'units' => 'rpm'],
				19 => ['field_name' => 'max_cadence',			'scale' => 1, 'offset' => 0, 'units' => 'rpm'],
				20 => ['field_name' => 'avg_power',				'scale' => 1, 'offset' => 0, 'units' => 'watts'],
				21 => ['field_name' => 'max_power',				'scale' => 1, 'offset' => 0, 'units' => 'watts'],
				22 => ['field_name' => 'total_ascent',			'scale' => 1, 'offset' => 0, 'units' => 'm'],
				23 => ['field_name' => 'total_descent',			'scale' => 1, 'offset' => 0, 'units' => 'm'],
				24 => ['field_name' => 'total_training_effect',	'scale' => 10, 'offset' => 0, 'units' => ''],
				25 => ['field_name' => 'first_lap_index',		'scale' => 1, 'offset' => 0, 'units' => ''],
				26 => ['field_name' => 'num_laps',				'scale' => 1, 'offset' => 0, 'units' => ''],
				27 => ['field_name' => 'event_group',			'scale' => 1, 'offset' => 0, 'units' => ''],
				28 => ['field_name' => 'trigger',				'scale' => 1, 'offset' => 0, 'units' => ''],
				29 => ['field_name' => 'nec_lat',				'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				30 => ['field_name' => 'nec_long',				'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				31 => ['field_name' => 'swc_lat',				'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				32 => ['field_name' => 'swc_long',				'scale' => 1, 'offset' => 0, 'units' => 'semicircles'],
				34 => ['field_name' => 'normalized_power',		'scale' => 1, 'offset' => 0, 'units' => 'watts'],
				35 => ['field_name' => 'training_stress_score',	'scale' => 10, 'offset' => 0, 'units' => 'tss'],
				36 => ['field_name' => 'intensity_factor',		'scale' => 1000, 'offset' => 0, 'units' => 'if'],
				37 => ['field_name' => 'left_right_balance',	'scale' => 1, 'offset' => 0, 'units' => ''],
				41 => ['field_name' => 'avg_stroke_count',		'scale' => 10, 'offset' => 0, 'units' => 'strokes/lap'],
				42 => ['field_name' => 'avg_stroke_distance',	'scale' => 100, 'offset' => 0, 'units' => 'm'],
				43 => ['field_name' => 'swim_stroke',			'scale' => 1, 'offset' => 0, 'units' => 'swim_stroke'],
				44 => ['field_name' => 'pool_length',			'scale' => 100, 'offset' => 0, 'units' => 'm'],
				46 => ['field_name' => 'pool_length_unit',		'scale' => 1, 'offset' => 0, 'units' => ''],
				47 => ['field_name' => 'num_active_lengths',	'scale' => 1, 'offset' => 0, 'units' => 'lengths'],
				48 => ['field_name' => 'total_work',			'scale' => 1, 'offset' => 0, 'units' => 'J'],
				68 => ['field_name' => 'time_in_power_zone',	'scale' => 1000, 'offset' => 0, 'units' => 's'],
				253 => ['field_name' => 'timestamp',			'scale' => 1, 'offset' => 0, 'units' => 's'],
				254 => ['field_name' => 'message_index',		'scale' => 1, 'offset' => 0, 'units' => ''],
			]
		],
		
		19 => [
			'mesg_name' => 'lap', 'field_defns' => [
				0 => ['field_name' => 'event',					'scale' => 1,		'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'event_type',				'scale' => 1,		'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'start_time',				'scale' => 1,		'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'start_position_lat',		'scale' => 1,		'offset' => 0, 'units' => 'semicircles'],
				4 => ['field_name' => 'start_position_long',	'scale' => 1,		'offset' => 0, 'units' => 'semicircles'],
				5 => ['field_name' => 'end_position_lat',		'scale' => 1,		'offset' => 0, 'units' => 'semicircles'],
				6 => ['field_name' => 'end_position_long',		'scale' => 1,		'offset' => 0, 'units' => 'semicircles'],
				7 => ['field_name' => 'total_elapsed_time',		'scale' => 1000,	'offset' => 0, 'units' => 's'],
				8 => ['field_name' => 'total_timer_time',		'scale' => 1000,	'offset' => 0, 'units' => 's'],
				9 => ['field_name' => 'total_distance',			'scale' => 100,		'offset' => 0, 'units' => 'm'],
				10 => ['field_name' => 'total_cycles',			'scale' => 1,		'offset' => 0, 'units' => 'cycles'],
				11 => ['field_name' => 'total_calories',		'scale' => 1,		'offset' => 0, 'units' => 'kcal'],
				12 => ['field_name' => 'total_fat_calories',	'scale' => 1,		'offset' => 0, 'units' => 'kcal'],
				13 => ['field_name' => 'avg_speed',				'scale' => 1000,	'offset' => 0, 'units' => 'm/s'],
				14 => ['field_name' => 'max_speed',				'scale' => 1000,	'offset' => 0, 'units' => 'm/s'],
				15 => ['field_name' => 'avg_heart_rate',		'scale' => 1,		'offset' => 0, 'units' => 'bpm'],
				16 => ['field_name' => 'max_heart_rate',		'scale' => 1,		'offset' => 0, 'units' => 'bpm'],
				17 => ['field_name' => 'avg_cadence',			'scale' => 1,		'offset' => 0, 'units' => 'rpm'],
				18 => ['field_name' => 'max_cadence',			'scale' => 1,		'offset' => 0, 'units' => 'rpm'],
				19 => ['field_name' => 'avg_power',				'scale' => 1,		'offset' => 0, 'units' => 'watts'],
				20 => ['field_name' => 'max_power',				'scale' => 1,		'offset' => 0, 'units' => 'watts'],
				21 => ['field_name' => 'total_ascent',			'scale' => 1,		'offset' => 0, 'units' => 'm'],
				22 => ['field_name' => 'total_descent',			'scale' => 1,		'offset' => 0, 'units' => 'm'],
				23 => ['field_name' => 'intensity',				'scale' => 1,		'offset' => 0, 'units' => ''],
				25 => ['field_name' => 'sport',					'scale' => 1,		'offset' => 0, 'units' => ''],
				26 => ['field_name' => 'event_group',			'scale' => 1,		'offset' => 0, 'units' => ''],
				32 => ['field_name' => 'num_lengths',			'scale' => 1,		'offset' => 0, 'units' => 'lengths'],
				33 => ['field_name' => 'normalized_power',		'scale' => 1,		'offset' => 0, 'units' => 'watts'],
				34 => ['field_name' => 'left_right_balance',	'scale' => 1,		'offset' => 0, 'units' => ''],
				35 => ['field_name' => 'first_length_index',	'scale' => 1,		'offset' => 0, 'units' => ''],
				37 => ['field_name' => 'avg_stroke_distance',	'scale' => 100,		'offset' => 0, 'units' => 'm'],
				38 => ['field_name' => 'swim_stroke',			'scale' => 1,		'offset' => 0, 'units' => ''],
				40 => ['field_name' => 'num_active_lengths',	'scale' => 1,		'offset' => 0, 'units' => 'lengths'],
				41 => ['field_name' => 'total_work',			'scale' => 1,		'offset' => 0, 'units' => 'J'],
				60 => ['field_name' => 'time_in_power_zone',	'scale' => 1000,	'offset' => 0, 'units' => 's'],
				253 => ['field_name' => 'timestamp',			'scale' => 1,		'offset' => 0, 'units' => 's'],
				254 => ['field_name' => 'message_index',		'scale' => 1,		'offset' => 0, 'units' => '']
			]
		],
		
		20 => [
			'mesg_name' => 'record', 'field_defns' => [
				0 => ['field_name' => 'position_lat',	'scale' => 1,		'offset' => 0,		'units' => 'semicircles'],
				1 => ['field_name' => 'position_long',	'scale' => 1,		'offset' => 0,		'units' => 'semicircles'],
				2 => ['field_name' => 'altitude',		'scale' => 5,		'offset' => 500,	'units' => 'm'],
				3 => ['field_name' => 'heart_rate',		'scale' => 1,		'offset' => 0,		'units' => 'bpm'],
				4 => ['field_name' => 'cadence',		'scale' => 1,		'offset' => 0,		'units' => 'rpm'],
				5 => ['field_name' => 'distance',		'scale' => 100,		'offset' => 0,		'units' => 'm'],
				6 => ['field_name' => 'speed',			'scale' => 1000,	'offset' => 0,		'units' => 'm/s'],
				7 => ['field_name' => 'power',			'scale' => 1,		'offset' => 0,		'units' => 'watts'],
				13 => ['field_name' => 'temperature',	'scale' => 1,		'offset' => 0,		'units' => 'C'],
				253 => ['field_name' => 'timestamp',	'scale' => 1,		'offset' => 0,		'units' => 's']
			]
		],
		
		21 => [
			'mesg_name' => 'event', 'field_defns' => [
				0 => ['field_name' => 'event',			'scale' => 1, 'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'event_type',		'scale' => 1, 'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'data',			'scale' => 1, 'offset' => 0, 'units' => ''],
				4 => ['field_name' => 'event_group',	'scale' => 1, 'offset' => 0, 'units' => ''],
				253 => ['field_name' => 'timestamp',	'scale' => 1, 'offset' => 0, 'units' => 's']
			]
		],
		
		23 => [
			'mesg_name' => 'device_info', 'field_defns' => [
				0 => ['field_name' => 'device_index',		'scale' => 1, 'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'device_type',		'scale' => 1, 'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'manufacturer',		'scale' => 1, 'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'serial_number',		'scale' => 1, 'offset' => 0, 'units' => ''],
				4 => ['field_name' => 'product',			'scale' => 1, 'offset' => 0, 'units' => ''],
				5 => ['field_name' => 'software_version',	'scale' => 1, 'offset' => 0, 'units' => ''],
				6 => ['field_name' => 'hardware_version',	'scale' => 1, 'offset' => 0, 'units' => ''],
				7 => ['field_name' => 'cum_operating_time',	'scale' => 1, 'offset' => 0, 'units' => ''],
				10 => ['field_name' => 'battery_voltage',	'scale' => 1, 'offset' => 0, 'units' => ''],
				11 => ['field_name' => 'battery_status',	'scale' => 1, 'offset' => 0, 'units' => ''],
				253 => ['field_name' => 'timestamp',		'scale' => 1, 'offset' => 0, 'units' => 's']
			]
		],
		
		34 => [
			'mesg_name' => 'activity', 'field_defns' => [
				0 => ['field_name' => 'total_timer_time',	'scale' => 1000, 'offset' => 0, 'units' => 's'],
				1 => ['field_name' => 'num_sessions',		'scale' => 1, 'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'type',				'scale' => 1, 'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'event',				'scale' => 1, 'offset' => 0, 'units' => ''],
				4 => ['field_name' => 'event_type',			'scale' => 1, 'offset' => 0, 'units' => ''],
				5 => ['field_name' => 'local_timestamp',	'scale' => 1, 'offset' => 0, 'units' => ''],
				6 => ['field_name' => 'event_group',		'scale' => 1, 'offset' => 0, 'units' => ''],
				253 => ['field_name' => 'timestamp',		'scale' => 1, 'offset' => 0, 'units' => 's']
			]
		],
		
		49 => [
			'mesg_name' => 'file_creator', 'field_defns' => [
				0 => ['field_name' => 'software_version', 'scale' => 1, 'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'hardware_version', 'scale' => 1, 'offset' => 0, 'units' => '']
			]
		],
		
		101 => [
			'mesg_name' => 'length', 'field_defns' => [
				0 => ['field_name' => 'event',					'scale' => 1,		'offset' => 0, 'units' => ''],
				1 => ['field_name' => 'event_type',				'scale' => 1,		'offset' => 0, 'units' => ''],
				2 => ['field_name' => 'start_time',				'scale' => 1,		'offset' => 0, 'units' => ''],
				3 => ['field_name' => 'total_elapsed_time',		'scale' => 1000,	'offset' => 0, 'units' => 's'],
				4 => ['field_name' => 'total_timer_time',		'scale' => 1000,	'offset' => 0, 'units' => 's'],
				5 => ['field_name' => 'total_strokes',			'scale' => 1,		'offset' => 0, 'units' => 'strokes'],
				6 => ['field_name' => 'avg_speed',				'scale' => 1000,	'offset' => 0, 'units' => 'm/s'],
				7 => ['field_name' => 'swim_stroke',			'scale' => 1,		'offset' => 0, 'units' => 'swim_stroke'],
				9 => ['field_name' => 'avg_swimming_cadence',	'scale' => 1,		'offset' => 0, 'units' => 'strokes/min'],
				10 => ['field_name' => 'event_group',			'scale' => 1,		'offset' => 0, 'units' => ''],
				11 => ['field_name' => 'total_calories',		'scale' => 1,		'offset' => 0, 'units' => 'kcal'],
				12 => ['field_name' => 'length_type',			'scale' => 1, 		'offset' => 0, 'units' => ''],
				253 => ['field_name' => 'timestamp',			'scale' => 1,		'offset' => 0, 'units' => 's'],
				254 => ['field_name' => 'message_index',		'scale' => 1,		'offset' => 0, 'units' => '']
			]
		]
	];
	
	// PHP Constructor - called when an object of the class is instantiated.
	function __construct($file_path, $options=NULL) {
		if(empty($file_path)) {
			throw new Exception('phpFITFileReader->__construct(): file_path is empty!');
		}
		if(!file_exists($file_path)) {
			throw new Exception('phpFITFileReader->__construct(): file \''.$file_path.'\' does not exist!');
		}
		
		/*
		 * 1. file_get_contents — Reads entire file into a string.
		 * 2. str_split — Convert a string to an array.
		 * 3. array_reverse — Return an array with elements in reverse order. This is so we can use array_pop() instead of the much slower array_shift().
		 */
		$this->file_contents = array_reverse(str_split(file_get_contents($file_path)));
		
		// Get rid of the first two array elements. These are the CRC bytes and unused by this class.
		array_shift($this->file_contents);  // array_shift() from the front of the array as CRC bytes were at end of file before it was reversed.
		array_shift($this->file_contents);
		
		// Process the file contents.
		$this->read_header();
		$this->read_data_records();
		$this->one_element_arrays();
		
		// Handle options.
		$this->fix_data($options);
		$this->set_units($options);
	}
	
	/*
	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 * Table 3-1. Byte Description of File Header
	 *
	 *  byte | parameter           | php format code for unpack()
	 * ------+---------------------+-------------------------------------------------------------
	 *   0   | Header Size         | C unsigned char
	 *   1   | Protocol Version    | C unsigned char
	 *   2   | Profile Version LSB | v unsigned short (always 16 bit, little endian byte order)
	 *   3   | Profile Version MSB | v unsigned short
	 *   4   | Data Size LSB       | V unsigned long (always 32 bit, little endian byte order)
	 *   5   | Data Size           | V unsigned long
	 *   6   | Data Size           | V unsigned long
	 *   7   | Data Size MSB       | V unsigned long
	 *   8   | Data Type Byte[0]   | C unsigned char
	 *   9   | Data Type Byte[1]   | C unsigned char
	 *   10  | Data Type Byte[2]   | C unsigned char
	 *   11  | Data Type Byte[3]   | C unsigned char
	 *   12  | CRC LSB             | *** Removed by array_shift() in __construct() ***
	 *   13  | CRC MSB             | *** Removed by array_shift() in __construct() ***
	 */
	private function read_header() {
		$this->file_header['header_size'] = unpack('C1tmp', array_pop($this->file_contents))['tmp'];
		
		if($this->file_header['header_size'] != 12 && $this->file_header['header_size'] != 14) {
			throw new Exception('phpFITFileReader->read_header(): not a valid header size!');
		}
		$this->file_header = unpack('C1protocol_version/'.
				'v1profile_version/'.
				'V1data_size/'.
				'C4data_type/'.
				'v1crc', $this->get_bytes($this->file_header['header_size'] - 1)
			);
		
		$file_extension = sprintf('%c%c%c%c', $this->file_header['data_type1'], $this->file_header['data_type2'], $this->file_header['data_type3'], $this->file_header['data_type4']);
		
		if($file_extension != '.FIT' || $this->file_header['data_size'] <= 0) {
			throw new Exception('phpFITFileReader->read_header(): not a valid FIT file!');
		}
		
		if(count($this->file_contents) !== $this->file_header['data_size']) {
			throw new Exception('phpFITFileReader->read_header(): file_header[\'data_size\'] does not seem correct!');
		}
	}
	
	private function get_bytes($numBytes) {
		$data = '';
		while($numBytes-- > 0) {
			$data .= array_pop($this->file_contents);
		}
		return $data;
	}
	
	private function read_data_records() {
		$record_header_byte = NULL;
		$local_mesg_type = NULL;
		$get_bytes_data = NULL;
		$message_type = NULL;
		
		while(($record_header_byte = array_pop($this->file_contents)) !== NULL) {
			$record_header_byte = ord($record_header_byte);
			
			/*
			 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
			 * Table 4-1. Normal Header Bit Field Description
			 *
			 *  Bit | Value  | Description
			 * ------+-------+------------------------------------------------
			 *   7  | 0      | Normal Header
			 *   6  | 0 or 1 | Message Type (1: Defn Message; 0 Data Message)
			 *   5  | 0      | Reserved
			 *   4  | 0      | Reserved
			 *  0-3 | 0 - 15 | Local Message Type
			 */
			if(($record_header_byte >> 7) & 1) {  // Check that it's a normal header
				throw new Exception('phpFITFileReader->read_data_records(): this class can only hand normal headers!');
			}
			$message_type = ($record_header_byte >> 6) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
			$local_mesg_type = $record_header_byte & 15;  // bindec('1111') == 15
			
			switch($message_type) {
				case DEFINITION_MESSAGE:
					/*
					 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
					 * Table 4-1. Normal Header Bit Field Description
					 *
					 *  Byte | Description           | Length            | Value
					 * ------+-----------------------+-------------------+-----------------------------------------------------------
					 *   0   | Reserved              | 1 Byte            | 0
					 *   1   | Architecture          | 1 Byte            | 0: Messages are Little Endian; 1: Messages are Big Endian
					 *  2-3  | Global Message Number | 2 Bytes           | 0:65535 – Unique to each message
					 *   4   | Fields                | 1 Byte            | Number of fields in the Data Message
					 * 5-end | Field Definition      | 3 Bytes per field | Field Definition Number, Size, Base Type
					 */
					
					array_pop($this->file_contents);  // Reserved - IGNORED
					array_pop($this->file_contents);  // Architecture - IGNORED
					
					$global_mesg_num = unpack('v1tmp', $this->get_bytes(2))['tmp'];
					$num_fields = ord(array_pop($this->file_contents));
					
					$field_definitions = [];
					$total_size = 0;
					while($num_fields-- > 0) {
						$field_definition_number = ord(array_pop($this->file_contents));
						$size = ord(array_pop($this->file_contents));
						$base_type = ord(array_pop($this->file_contents));
						
						$field_definitions[] = ['field_definition_number' => $field_definition_number, 'size' => $size, 'base_type' => $base_type];
						$total_size += $size;
					}
					
					$this->defn_mesgs[$local_mesg_type] = [
							'global_mesg_num' => $global_mesg_num,
							'num_fields' => $num_fields,
							'field_defns' => $field_definitions,
							'total_size' => $total_size
						];
					break;
				
				case DATA_MESSAGE:
					// Check that we have information on the Data Message.
					if(array_key_exists($this->defn_mesgs[$local_mesg_type]['global_mesg_num'], $this->data_mesg_info)) {
						foreach($this->defn_mesgs[$local_mesg_type]['field_defns'] as $field_defn) {
							// Check that we have information on the Field Definition.
							if(array_key_exists($field_defn['field_definition_number'], $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'])) {
								
								// If it's a Record data message and it's a Timestamp field, store the timestamp...
								if($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20 && $field_defn['field_definition_number'] === 253) {
									$this->timestamp = $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = (unpack($this->types[$field_defn['base_type']], $this->get_bytes($field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
								
								// Else, if it's another field in a Record data message, use the Timestamp as the index.
								else if($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20) {
									$get_bytes_data = '';  // Brought the get_bytes() function inline as it is quicker (~11%).
									while($field_defn['size']-- > 0) {
										$get_bytes_data .= array_pop($this->file_contents);
									}
									$this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][$this->timestamp] = (unpack($this->types[$field_defn['base_type']], $get_bytes_data)['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
								
								else {
									$this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = (unpack($this->types[$field_defn['base_type']], $this->get_bytes($field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
							}
							else {
								// drop the bytes - unset() is quicker than array_pop()
								$last_element = count($this->file_contents) - 1;
								while($field_defn['size']-- > 0) {
									unset($this->file_contents[$last_element - $field_defn['size']]);
								}
							}
						}
					}
					else {
						// drop the bytes - unset() is quicker than array_pop()
						$last_element = count($this->file_contents) - 1;
						$offset = $this->defn_mesgs[$local_mesg_type]['total_size'];
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
					$tmp[] = 'unknown';
			}
			return $tmp;
		}
		else {
			return isset($this->enum_data[$type][$val]) ? $this->enum_data[$type][$val] : 'unknown';
		}
	}
	
	private function one_element_arrays() {
		foreach($this->data_mesgs as $mesg_key => $mesg) {
			foreach($mesg as $field_key => $field) {
				if(count($field) === 1) {
// GETTING "Notice: Undefined offset: 0" FOR SWIM FILES
// FIRST ELEMENT IN ARRAY IS -1 AND NOT 0
//					if(array_key_exists(-1, $field)) {
//						echo '*** '.$mesg_key.' '.$field_key.' ***<br>';
//						var_dump($field);
//						echo '<br><br>';
//					}
					$this->data_mesgs[$mesg_key][$field_key] = $field[0];
				}
			}
		}
	}
	
	public function show_debug_info() {
		echo '<h3>Types</h3>';
		echo '<table class=\'table table-condensed table-striped\'>';
		echo '<thead>';
		echo '<th>key</th>';
		echo '<th>PHP unpack() format</th>';
		echo '</thead>';
		echo '<tbody>';
		foreach( $this->types as $key => $val ) {
			echo '<tr><td>'.$key.'</td><td>'.$val[0].'</td></tr>';
		}
		echo '</tbody>';
		echo '</table>';
		
		echo '<br><hr><br>';
		
		echo '<h3>Messages and Fields being listened for</h3>';
		foreach( $this->data_mesg_info as $key => $val ) {
		echo '<h4>'.$val['mesg_name'].' ('.$key.')</h4>';
			echo '<table class=\'table table-condensed table-striped\'>';
			echo '<thead><th>ID</th><th>Name</th><th>Type</th><th>Scale</th><th>Offset</th><th>Units</th></thead><tbody>';
			foreach( $val['field_defns'] as $key2 => $val2 ) {
				echo '<tr><td>'.$key2.'</td><td>'.$val2['field_name'].'</td><td>'.$val2['scale'].'</td><td>'.$val2['offset'].'</td><td>'.$val2['units'].'</td></tr>';
			}
			echo '</tbody></table><br><br>';
		}
		
		echo '<br><hr><br>';
		
		echo '<h3>FIT Definition Messages contained within the file</h3>';
        echo '<table class=\'table table-condensed table-striped\'>';
		echo '<thead>';
		echo '<th>global_mesg_num</th>';
		echo '<th>num_fields</th>';
		echo '<th>field defns</th>';
		echo '<th>total_size</th>';
		echo '</thead>';
		echo '<tbody>';
		foreach( $this->defn_mesgs as $key => $val ) {
			echo  '<tr><td>'.$val['global_mesg_num'].'</td><td>'.$val['num_fields'].'</td><td>';
			
			foreach($val['field_defns'] as $defn) {
				echo  'defn: '.$defn['field_definition_number'].'; size: '.$defn['size'].'; type: '.$defn['base_type'].'<br>';
			}
			echo  '</td><td>'.$val['total_size'].'</td></tr>';
		}
		echo '</tbody>';
		echo '</table>';
		
		echo '<br><hr><br>';
		
		echo '<h3>Messages found in file</h3>';
		foreach($this->data_mesgs as $mesg_key => $mesg) {
			echo '<table class=\'table table-condensed table-striped\'>';
			echo '<thead><th>'.$mesg_key.'</th><th>count()</th></thead><tbody>';
			foreach($mesg as $field_key => $field) {
				echo '<tr><td>'.$field_key.'</td><td>'.count($field).'</td></tr>';
			}
			echo '</tbody></table><br><br>';
		}
	}
	
	public function get_manufacturer() {
		$tmp = $this->get_enum_data('manufacturer', $this->data_mesgs['device_info']['manufacturer']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_product() {
		$tmp = $this->get_enum_data('product', $this->data_mesgs['device_info']['product']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_sport() {
		$tmp = $this->get_enum_data('sport', $this->data_mesgs['session']['sport']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_sub_sport() {
		$tmp = $this->get_enum_data('sub_sport', $this->data_mesgs['session']['sub_sport']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	public function get_swim_stroke() {
		if($this->get_sport() == 'swimming') {
			$tmp = $this->get_enum_data('swim_stroke', $this->data_mesgs['session']['swim_stroke']);
			return is_array($tmp) ? $tmp[0] : $tmp;
		}
		else {
			return 'n/a';
		}
	}
	
	/*
	 * 
	 */
	private function fix_data($options) {
		if(!isset($options['fix_data']))
			return;
		$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = false;
		if(in_array('all', $options['fix_data'])) {
			$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = true;
		}
		else {
			$bCadence = in_array('cadence', $options['fix_data']);
			$bDistance = in_array('distance', $options['fix_data']);
			$bHeartRate = in_array('heart_rate', $options['fix_data']);
			$bLatitudeLongitude = in_array('lat_lon', $options['fix_data']);
			$bSpeed = in_array('speed', $options['fix_data']);
			$bPower = in_array('power', $options['fix_data']);
		}
		$missing_distance_keys = [];
		$missing_hr_keys = [];
		$missing_lat_keys = [];
		$missing_lon_keys = [];
		$missing_speed_keys = [];
		$missing_power_keys = [];
		
		foreach($this->data_mesgs['record']['timestamp'] as $timestamp) {
			if($bCadence) {  // Assumes all missing cadence values are zeros
				if(!isset($this->data_mesgs['record']['cadence'][$timestamp])) {
					$this->data_mesgs['record']['cadence'][$timestamp] = 0;
				}
			}
			if($bDistance) {
				if(!isset($this->data_mesgs['record']['distance'][$timestamp])) {
					$missing_distance_keys[] = $timestamp;
				}
			}
			if($bHeartRate) {
				if(!isset($this->data_mesgs['record']['heart_rate'][$timestamp])) {
					$missing_hr_keys[] = $timestamp;
				}
			}
			if($bLatitudeLongitude) {
				if(!isset($this->data_mesgs['record']['position_lat'][$timestamp])) {
					$missing_lat_keys[] = $timestamp;
				}
				if(!isset($this->data_mesgs['record']['position_long'][$timestamp])) {
					$missing_lon_keys[] = $timestamp;
				}
			}
			if($bSpeed) {
				if(!isset($this->data_mesgs['record']['speed'][$timestamp])) {
					$missing_speed_keys[] = $timestamp;
				}
			}
			if($bPower) {
				if(!isset($this->data_mesgs['record']['power'][$timestamp])) {
					$missing_power_keys[] = $timestamp;
				}
			}
		}
		
		
		if($bCadence && isset($this->data_mesgs['record']['cadence'])) {
			ksort($this->data_mesgs['record']['cadence']);  // no interpolation; zeros added earlier
		}
		if($bDistance && isset($this->data_mesgs['record']['distance'])) {
			$this->interpolate_missing_data($missing_distance_keys, $this->data_mesgs['record']['distance']);
		}
		if($bHeartRate && isset($this->data_mesgs['record']['heart_rate'])) {
			$this->interpolate_missing_data($missing_hr_keys, $this->data_mesgs['record']['heart_rate']);
		}
		if($bLatitudeLongitude && isset($this->data_mesgs['record']['position_lat']) && isset($this->data_mesgs['record']['position_long'])) {
			$this->interpolate_missing_data($missing_lat_keys, $this->data_mesgs['record']['position_lat']);
			$this->interpolate_missing_data($missing_lon_keys, $this->data_mesgs['record']['position_long']);
		}
		if($bSpeed && isset($this->data_mesgs['record']['speed'])) {
			$this->interpolate_missing_data($missing_speed_keys, $this->data_mesgs['record']['speed']);
		}
		if($bPower && isset($this->data_mesgs['record']['power'])) {
			$this->interpolate_missing_data($missing_power_keys, $this->data_mesgs['record']['power']);
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
		if(!isset($options['set_units']) || in_array('metric', $options['set_units'])) {
			if(isset($this->data_mesgs['record']['speed'])) {  // convert  meters per second to kilometers per hour
				foreach($this->data_mesgs['record']['speed'] as &$value) {
					$value = round($value * 3.6, 3);
				}
			}
			if(isset($this->data_mesgs['record']['distance'])) {  // convert from meters to kilometers
				foreach($this->data_mesgs['record']['distance'] as &$value) {
					$value = round($value * 0.001, 2);
				}
			}
			if(isset($this->data_mesgs['record']['position_lat'])) {  // convert from semicircles to degress
				foreach($this->data_mesgs['record']['position_lat'] as &$value) {
					$value = round($value * (180.0 / pow(2,31)), 5);
				}
			}
			if(isset($this->data_mesgs['record']['position_long'])) {  // convert from semicircles to degress
				foreach($this->data_mesgs['record']['position_long'] as &$value) {
					$value = round($value * (180.0 / pow(2,31)), 5);
				}
			}
		}
		else if(in_array('statute', $options['set_units'])) {
			if(isset($this->data_mesgs['record']['speed'])) {  // convert  meters per second to miles per hour
				foreach($this->data_mesgs['record']['speed'] as &$value) {
					$value = round($value * 2.23693629, 3);
				}
			}
			if(isset($this->data_mesgs['record']['distance'])) {  // convert from meters to miles
				foreach($this->data_mesgs['record']['distance'] as &$value) {
					$value = round($value * 0.000621371192, 2);
				}
			}
			if(isset($this->data_mesgs['record']['altitude'])) {  // convert from meters to feet
				foreach($this->data_mesgs['record']['altitude'] as &$value) {
					$value = round($value * 3.2808399, 1);
				}
			}
			if(isset($this->data_mesgs['record']['position_lat'])) {  // convert from semicircles to degress
				foreach($this->data_mesgs['record']['position_lat'] as &$value) {
					$value = round($value * (180.0 / pow(2,31)), 5);
				}
			}
			if(isset($this->data_mesgs['record']['position_long'])) {  // convert from semicircles to degress
				foreach($this->data_mesgs['record']['position_long'] as &$value) {
					$value = round($value * (180.0 / pow(2,31)), 5);
				}
			}
			if(isset($this->data_mesgs['record']['temperature'])) {  // convert from celsius to fahrenheit
				foreach($this->data_mesgs['record']['temperature'] as &$value) {
					$value = round((($value * 9) / 5) + 32, 2);
				}
			}
		}
		else {  // raw
			// Do nothing - leave values as read from file.
		}
	}
}
?>
