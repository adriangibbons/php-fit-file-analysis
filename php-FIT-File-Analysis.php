<?php
/*
 * php-FIT-File-Analysis
 * =====================
 * A PHP class for Analysing FIT files created by Garmin GPS devices.
 * Adrian Gibbons, 2015
 * Adrian.GitHub@gmail.com
 *
 * https://github.com/adriangibbons/php-FIT-File-Analysis
 * http://www.thisisant.com/resources/fit
 */
 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

define('DEFINITION_MESSAGE', 1);
define('DATA_MESSAGE', 0);

class phpFITFileAnalysis {
	public $data_mesgs = [];  // Used to store the data read from the file in associative arrays.
	
	private $file_contents = '';			// FIT file is read-in to memory as a string, split into an array, and reversed. See __construct().
	private $file_pointer = 0;				// Points to the location in the file that shall be read next.
	private $defn_mesgs = [];				// Array of FIT 'Definition Messages', which describe the architecture, format, and fields of 'Data Messages'.
	private $file_header = [];				// Contains information about the FIT file such as the Protocol version, Profile version, and Data Size.
	private $timestamp = 0;					// Timestamps are used as the indexes for Record data (e.g. Speed, Heart Rate, etc).
	private $php_trader_ext_loaded = false;	// Is the PHP Trader extension loaded? Use $this->sma() algorithm if not available.
	
	// Enumerated data looked up by enum_data().
	// Values from 'Profile.xls' contained within the FIT SDK.
	private $enum_data = [
		'activity' => [0 => 'manual', 1 => 'auto_multi_sport'],
		'battery_status' => [1 => 'new', 2 => 'good', 3 => 'ok', 4 => 'low', 5 => 'critical'],
		'display_measure' => [0 => 'metric', 1 => 'statute'],
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
			1 => 'Garmin',  // 'garmin',
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
		'product' => [  // Have formatted for devices known to use FIT format. (Original text commented-out).
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
			473 => 'Forerunner 301',  // 'fr301_china',
			474 => 'Forerunner 301',  // 'fr301_japan',
			475 => 'Forerunner 301',  // 'fr301_korea',
			494 => 'Forerunner 301',  // 'fr301_taiwan',
			717 => 'Forerunner 405',  // 'fr405',
			782 => 'Forerunner 50',  // 'fr50',
			987 => 'Forerunner 405',  // 'fr405_japan',
			988 => 'Forerunner 60',  // 'fr60',
			1011 => 'dsi_alf01',
			1018 => 'Forerunner 310XT',  // 'fr310xt',
			1036 => 'Edge 500',  // 'edge500',
			1124 => 'Forerunner 110',  // 'fr110',
			1169 => 'Edge 800',  // 'edge800',
			1199 => 'Edge 500',  // 'edge500_taiwan',
			1213 => 'Edge 500',  // 'edge500_japan',
			1253 => 'chirp',
			1274 => 'Forerunner 110',  // 'fr110_japan',
			1325 => 'edge200',
			1328 => 'Forerunner 910XT',  // 'fr910xt',
			1333 => 'Edge 800',  // 'edge800_taiwan',
			1334 => 'Edge 800',  // 'edge800_japan',
			1341 => 'alf04',
			1345 => 'Forerunner 610',  // 'fr610',
			1360 => 'Forerunner 210',  // 'fr210_japan',
			1380 => 'vector_ss',
			1381 => 'vector_cp',
			1386 => 'Edge 800',  // 'edge800_china',
			1387 => 'Edge 500',  // 'edge500_china',
			1410 => 'Forerunner 610',  // 'fr610_japan',
			1422 => 'Edge 500',  // 'edge500_korea',
			1436 => 'Forerunner 70',  // 'fr70',
			1446 => 'Forerunner 310XT',  // 'fr310xt_4t',
			1461 => 'amx',
			1482 => 'Forerunner 10',  // 'fr10',
			1497 => 'Edge 800',  // 'edge800_korea',
			1499 => 'swim',
			1537 => 'Forerunner 910XT',  // 'fr910xt_china',
			1551 => 'fenix',
			1555 => 'edge200_taiwan',
			1561 => 'Edge 510',  // 'edge510',
			1567 => 'Edge 810',  // 'edge810',
			1570 => 'tempe',
			1600 => 'Forerunner 910XT',  // 'fr910xt_japan',
			1623 => 'Forerunner 620',  // 'fr620',
			1632 => 'Forerunner 220',  // 'fr220',
			1664 => 'Forerunner 910XT',  // 'fr910xt_korea',
			1688 => 'Forerunner 10',  // 'fr10_japan',
			1721 => 'Edge 810',  // 'edge810_japan',
			1735 => 'virb_elite',
			1736 => 'edge_touring',
			1742 => 'Edge 510',  // 'edge510_japan',
			1752 => 'hrm_run',
			1821 => 'Edge 510',  // 'edge510_asia',
			1822 => 'Edge 810',  // 'edge810_china',
			1823 => 'Edge 810',  // 'edge810_taiwan',
			1836 => 'Edge 1000',  // 'edge1000',
			1837 => 'vivo_fit',
			1853 => 'virb_remote',
			1885 => 'vivo_ki',
			1903 => 'Forerunner 15',  // 'fr15',
			1918 => 'Edge 510',  // 'edge510_korea',
			1928 => 'Forerunner 620',  // 'fr620_japan',
			1929 => 'Forerunner 620',  // 'fr620_china',
			1930 => 'Forerunner 220',  // 'fr220_japan',
			1931 => 'Forerunner 220',  // 'fr220_china',
			1967 => 'fenix2',
			10007 => 'sdm4',
			10014 => 'edge_remote',
			20119 => 'training_center',
			65532 => 'android_antplus_plugin',
			65534 => 'connect'
		],
		'sport' => [  // Have capitalised and replaced underscores with spaces.
			0 => 'Generic',
			1 => 'Running',
			2 => 'Cycling',
			3 => 'Transition',
			4 => 'Fitness equipment',
			5 => 'Swimming',
			6 => 'Basketball',
			7 => 'Soccer',
			8 => 'Tennis',
			9 => 'American football',
			10 => 'Training',
			11 => 'Walking',
			12 => 'Cross country skiing',
			13 => 'Alpine skiing',
			14 => 'Snowboarding',
			15 => 'Rowing',
			16 => 'Mountaineering',
			17 => 'Hiking',
			18 => 'Multisport',
			19 => 'Paddling',
			254 => 'All'
		],
		'sub_sport' => [  // Have capitalised and replaced underscores with spaces.
			0 => 'Generic',
			1 => 'Treadmill',
			2 => 'Street',
			3 => 'Trail',
			4 => 'Track',
			5 => 'Spin',
			6 => 'Indoor cycling',
			7 => 'Road',
			8 => 'Mountain',
			9 => 'Downhill',
			10 => 'Recumbent',
			11 => 'Cyclocross',
			12 => 'Hand cycling',
			13 => 'Track cycling',
			14 => 'Indoor rowing',
			15 => 'Elliptical',
			16 => 'Stair climbing',
			17 => 'Lap swimming',
			18 => 'Open water',
			19 => 'Flexibility training',
			20 => 'Strength training',
			21 => 'Warm up',
			22 => 'Match',
			23 => 'Exercise',
			24 => 'Challenge',
			25 => 'Indoor skiing',
			26 => 'Cardio training',
			27 => 'Indoor walking',
			254 => 'All'
		],
		'session_trigger' => [0 => 'activity_end', 1 => 'manual', 2 => 'auto_multi_sport', 3 => 'fitness_equipment'],
		'swim_stroke' => [0 => 'Freestyle', 1 => 'Backstroke', 2 => 'Breaststroke', 3 => 'Butterfly', 4 => 'Drill', 5 => 'Mixed', 6 => 'IM']  // Have capitalised.
	];
	
	/*
	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 * Table 4-6. FIT Base Types and Invalid Values
	 * 
	 * $types array holds a string used by the PHP unpack() function to format binary data.
	 * 'tmp' is the name of the (single element) array created.
	 */
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
			throw new Exception('phpFITFileAnalysis->__construct(): file_path is empty!');
		}
		if(!file_exists($file_path)) {
			throw new Exception('phpFITFileAnalysis->__construct(): file \''.$file_path.'\' does not exist!');
		}
		$this->php_trader_ext_loaded = extension_loaded('trader');
		
		/*
	 	 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
	 	 * 3.3 FIT File Structure
		 * Header . Data Records . CRC
	 	 */
		$this->file_contents = file_get_contents($file_path);  // Read the entire file into a string
		
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
	 */
	private function read_header() {
		$header_size = unpack('C1header_size', substr($this->file_contents, $this->file_pointer, 1))['header_size'];
		$this->file_pointer++;
		
		if($header_size != 12 && $header_size != 14) {
			throw new Exception('phpFITFileAnalysis->read_header(): not a valid header size!');
		}
		$this->file_header = unpack(
			'C1protocol_version/'.
			'v1profile_version/'.
			'V1data_size/'.
			'C4data_type/'.
			'v1crc', substr($this->file_contents, $this->file_pointer, $header_size - 1)
		);
		$this->file_header['header_size'] = $header_size;
			
		$this->file_pointer += $this->file_header['header_size'] - 1;
		
		$file_extension = sprintf('%c%c%c%c', $this->file_header['data_type1'], $this->file_header['data_type2'], $this->file_header['data_type3'], $this->file_header['data_type4']);
		
		if($file_extension != '.FIT' || $this->file_header['data_size'] <= 0) {
			throw new Exception('phpFITFileAnalysis->read_header(): not a valid FIT file!');
		}
		
		if(strlen($this->file_contents) - $header_size - 2 !== $this->file_header['data_size']) {
			throw new Exception('phpFITFileAnalysis->read_header(): file_header[\'data_size\'] does not seem correct!');
		}
	}
	
	/*
	 * Reads the remainder of $this->file_contents and store the data in the $this->data_mesgs array.
	 */
	private function read_data_records() {
		$record_header_byte;
		$message_type;
		$local_mesg_type;
		
		while($this->file_header['header_size'] + $this->file_header['data_size'] > $this->file_pointer) {
			$record_header_byte = ord(substr($this->file_contents, $this->file_pointer, 1));
			$this->file_pointer++;
			
			/*
			 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
			 * Table 4-1. Normal Header Bit Field Description
			 */
			if(($record_header_byte >> 7) & 1) {  // Check that it's a normal header
				throw new Exception('phpFITFileAnalysis->read_data_records(): this class can only hand normal headers!');
			}
			$message_type = ($record_header_byte >> 6) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
			$local_mesg_type = $record_header_byte & 15;  // bindec('1111') == 15
			
			switch($message_type) {
				case DEFINITION_MESSAGE:
					/*
					 * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
					 * Table 4-1. Normal Header Bit Field Description
					 */
					
					$this->file_pointer++;  // Reserved - IGNORED
					$this->file_pointer++;  // Architecture - IGNORED
					
					$global_mesg_num = unpack('v1tmp', substr($this->file_contents, $this->file_pointer, 2))['tmp'];
					$this->file_pointer += 2;
					
					$num_fields = ord(substr($this->file_contents, $this->file_pointer, 1));
					$this->file_pointer++;
					
					$field_definitions = [];
					$total_size = 0;
					while($num_fields-- > 0) {
						$field_definition_number = ord(substr($this->file_contents, $this->file_pointer, 1));
						$this->file_pointer++;
						$size = ord(substr($this->file_contents, $this->file_pointer, 1));
						$this->file_pointer++;
						$base_type = ord(substr($this->file_contents, $this->file_pointer, 1));
						$this->file_pointer++;
						
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
					if(isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']])) {
						foreach($this->defn_mesgs[$local_mesg_type]['field_defns'] as $field_defn) {
							// Check that we have information on the Field Definition.
							if(isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']])) {
								
								// If it's a Record data message and it's a Timestamp field, store the timestamp...
								if($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20 && $field_defn['field_definition_number'] === 253) {
									$this->timestamp = $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = (unpack($this->types[$field_defn['base_type']], substr($this->file_contents, $this->file_pointer, $field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
								
								// Else, if it's another field in a Record data message, use the Timestamp as the index.
								else if($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20) {
									$this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][$this->timestamp] = (unpack($this->types[$field_defn['base_type']], substr($this->file_contents, $this->file_pointer, $field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
								
								else {
									$this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = (unpack($this->types[$field_defn['base_type']], substr($this->file_contents, $this->file_pointer, $field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale']) - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
								}
							}
							$this->file_pointer += $field_defn['size'];
						}
					}
					else {
						$this->file_pointer += $this->defn_mesgs[$local_mesg_type]['total_size'];
					}
			}
		}
	}
	
	/*
	 * If the user has requested for the data to be fixed, identify the missing keys for that data.
	 */
	private function fix_data($options) {
		if(!isset($options['fix_data']))
			return;
		array_walk($options['fix_data'], function(&$value) { $value = strtolower($value); } );  // Make all lower-case.
		$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = false;
		if(in_array('all', $options['fix_data'])) {
			$bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = true;
		}
		else {
			if(isset($this->data_mesgs['record']['timestamp'])) {
				$count_timestamp = count($this->data_mesgs['record']['timestamp']);  // No point try to insert missing values if we know there aren't any.
				if(isset($this->data_mesgs['record']['cadence']))
					$bCadence = (count($this->data_mesgs['record']['cadence']) === $count_timestamp) ? false : in_array('cadence', $options['fix_data']);
				if(isset($this->data_mesgs['record']['distance']))
					$bDistance = (count($this->data_mesgs['record']['distance']) === $count_timestamp) ? false : in_array('distance', $options['fix_data']);
				if(isset($this->data_mesgs['record']['heart_rate']))
					$bHeartRate = (count($this->data_mesgs['record']['heart_rate']) === $count_timestamp) ? false : in_array('heart_rate', $options['fix_data']);
				if(isset($this->data_mesgs['record']['position_lat']) && isset($this->data_mesgs['record']['position_long']))
					$bLatitudeLongitude = (count($this->data_mesgs['record']['position_lat']) === $count_timestamp
						&& count($this->data_mesgs['record']['position_long']) === $count_timestamp) ? false : in_array('lat_lon', $options['fix_data']);
				if(isset($this->data_mesgs['record']['speed']))
					$bSpeed = (count($this->data_mesgs['record']['speed']) === $count_timestamp) ? false : in_array('speed', $options['fix_data']);
				if(isset($this->data_mesgs['record']['power']))
					$bPower = (count($this->data_mesgs['record']['power']) === $count_timestamp) ? false : in_array('power', $options['fix_data']);
			}
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
		
		if($bCadence) {
			ksort($this->data_mesgs['record']['cadence']);  // no interpolation; zeros added earlier
		}
		if($bDistance) {
			$this->interpolate_missing_data($missing_distance_keys, $this->data_mesgs['record']['distance']);
		}
		if($bHeartRate) {
			$this->interpolate_missing_data($missing_hr_keys, $this->data_mesgs['record']['heart_rate']);
		}
		if($bLatitudeLongitude) {
			$this->interpolate_missing_data($missing_lat_keys, $this->data_mesgs['record']['position_lat']);
			$this->interpolate_missing_data($missing_lon_keys, $this->data_mesgs['record']['position_long']);
		}
		if($bSpeed) {
			$this->interpolate_missing_data($missing_speed_keys, $this->data_mesgs['record']['speed']);
		}
		if($bPower) {
			$this->interpolate_missing_data($missing_power_keys, $this->data_mesgs['record']['power']);
		}
	}
	
	/*
	 * For the missing keys in the data, interpolate using values either side and insert as necessary.
	 */
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
	
	/*
	 * Change arrays that contain only one element into non-arrays so you can use $variable rather than $variable[0] to access.
	 */
	private function one_element_arrays() {
		foreach($this->data_mesgs as $mesg_key => $mesg) {
			foreach($mesg as $field_key => $field) {
				if(count($field) === 1) {
					$first_key = key($field);
					$this->data_mesgs[$mesg_key][$field_key] = $field[$first_key];
				}
			}
		}
	}
	
	/*
	 * The FIT protocol makes use of enumerated data types.
	 * Where these values have been identified in the FIT SDK, they have been included in $this->enum_data
	 * This function returns the enumerated value for a given message type.
	 */
	public function enum_data($type, $value) {
		if(is_array($value)) {
			$tmp = [];
			foreach($value as $element) {
				if(isset($this->enum_data[$type][$element]))
					$tmp[] = $this->enum_data[$type][$element];
				else
					$tmp[] = 'unknown';
			}
			return $tmp;
		}
		else {
			return isset($this->enum_data[$type][$value]) ? $this->enum_data[$type][$value] : 'unknown';
		}
	}
	
	/*
	 * Short-hand access to commonly used enumerated data.
	 */
	public function manufacturer() {
		$tmp = $this->enum_data('manufacturer', $this->data_mesgs['device_info']['manufacturer']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	public function product() {
		$tmp = $this->enum_data('product', $this->data_mesgs['device_info']['product']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	public function sport() {
		$tmp = $this->enum_data('sport', $this->data_mesgs['session']['sport']);
		return is_array($tmp) ? $tmp[0] : $tmp;
	}
	
	/*
	 * Transform the values read from the FIT file into the units requested by the user.
	 */
	private function set_units($options) {
		$units = '';
		
		if(isset($options['units'])) {
			// Handle $options['units'] not being passed as array and/or not in lowercase.
			$units = strtolower((is_array($options['units'])) ? $options['units'][0] : $options['units']);
		}
		
		//  Handle $options['pace'] being pass as array and/or boolean vs string and/or lowercase.
		$bPace = false;
		if(isset($options['pace'])) {
			$pace = is_array($options['pace']) ? $options['pace'][0] : $options['pace'];
			if(is_bool($options['pace'])) {
				$bPace = $pace;
			}
			else if(is_string($options['pace'])) {
				$bPace = (strtolower($pace) === 'true') ? true : false;
			}
		}
		
		switch($units) {
			case 'statute':
				if(isset($this->data_mesgs['record']['speed'])) {  // convert  meters per second to miles per hour
					if(is_array($this->data_mesgs['record']['speed'])) {
						foreach($this->data_mesgs['record']['speed'] as &$value) {
							if($bPace) {
								$value = round(60 / 2.23693629 / $value, 3);
							}
							else {
								$value = round($value * 2.23693629, 3);
							}
						}
					}
					else {
						if($bPace) {
							$this->data_mesgs['record']['speed'] = round(60 / 2.23693629 / $this->data_mesgs['record']['speed'], 3);
						}
						else {
							$this->data_mesgs['record']['speed'] = round($this->data_mesgs['record']['speed'] * 2.23693629, 3);
						}
					}
				}
				if(isset($this->data_mesgs['record']['distance'])) {  // convert from meters to miles
					if(is_array($this->data_mesgs['record']['distance'])) {
						foreach($this->data_mesgs['record']['distance'] as &$value) {
							$value = round($value * 0.000621371192, 2);
						}
					}
					else {
						$this->data_mesgs['record']['distance'] = round($this->data_mesgs['record']['distance'] * 0.000621371192, 2);
					}
				}
				if(isset($this->data_mesgs['record']['altitude'])) {  // convert from meters to feet
					if(is_array($this->data_mesgs['record']['altitude'])) {
						foreach($this->data_mesgs['record']['altitude'] as &$value) {
							$value = round($value * 3.2808399, 1);
						}
					}
					else {
						$this->data_mesgs['record']['altitude'] = round($this->data_mesgs['record']['altitude'] * 3.2808399, 1);
					}
				}
				if(isset($this->data_mesgs['record']['position_lat'])) {  // convert from semicircles to degress
					if(is_array($this->data_mesgs['record']['position_lat'])) {
						foreach($this->data_mesgs['record']['position_lat'] as &$value) {
							$value = round($value * (180.0 / pow(2,31)), 5);
						}
					}
					else {
						$this->data_mesgs['record']['position_lat'] = round($this->data_mesgs['record']['position_lat'] * (180.0 / pow(2,31)), 5);
					}
				}
				if(isset($this->data_mesgs['record']['position_long'])) {  // convert from semicircles to degress
					if(is_array($this->data_mesgs['record']['position_long'])) {
						foreach($this->data_mesgs['record']['position_long'] as &$value) {
							$value = round($value * (180.0 / pow(2,31)), 5);
						}
					}
					else {
						$this->data_mesgs['record']['position_long'] = round($this->data_mesgs['record']['position_long'] * (180.0 / pow(2,31)), 5);
					}
				}
				if(isset($this->data_mesgs['record']['temperature'])) {  // convert from celsius to fahrenheit
					if(is_array($this->data_mesgs['record']['temperature'])) {
						foreach($this->data_mesgs['record']['temperature'] as &$value) {
							$value = round((($value * 9) / 5) + 32, 2);
						}
					}
					else {
						$this->data_mesgs['record']['temperature'] = round((($this->data_mesgs['record']['temperature'] * 9) / 5) + 32, 2);
					}
				}
				break;
			case 'raw':
				// Do nothing - leave values as read from file.
				break;
			default:  // Assume 'metric'.
				if(isset($this->data_mesgs['record']['speed'])) {  // convert  meters per second to kilometers per hour
					if(is_array($this->data_mesgs['record']['speed'])) {
						foreach($this->data_mesgs['record']['speed'] as &$value) {
							if($bPace) {
								$value = round(60 / 3.6 / $value, 3);
							}
							else {
								$value = round($value * 3.6, 3);
							}
						}
					}
					else {
						if($bPace) {
							$this->data_mesgs['record']['speed'] = round(60 / 3.6 / $this->data_mesgs['record']['speed'], 3);
						}
						else {
							$this->data_mesgs['record']['speed'] = round($this->data_mesgs['record']['speed'] * 3.6, 3);
						}
					}
				}
				if(isset($this->data_mesgs['record']['distance'])) {  // convert from meters to kilometers
					if(is_array($this->data_mesgs['record']['distance'])) {
						foreach($this->data_mesgs['record']['distance'] as &$value) {
							$value = round($value * 0.001, 2);
						}
					}
					else {
						$this->data_mesgs['record']['distance'] = round($this->data_mesgs['record']['distance'] * 0.001, 2);
					}
				}
				if(isset($this->data_mesgs['record']['position_lat'])) {  // convert from semicircles to degress
					if(is_array($this->data_mesgs['record']['position_lat'])) {
						foreach($this->data_mesgs['record']['position_lat'] as &$value) {
							$value = round($value * (180.0 / pow(2,31)), 5);
						}
					}
					else {
						$this->data_mesgs['record']['position_lat'] = round($this->data_mesgs['record']['position_lat'] * (180.0 / pow(2,31)), 5);
					}
				}
				if(isset($this->data_mesgs['record']['position_long'])) {  // convert from semicircles to degress
					if(is_array($this->data_mesgs['record']['position_long'])) {
						foreach($this->data_mesgs['record']['position_long'] as &$value) {
							$value = round($value * (180.0 / pow(2,31)), 5);
						}
					}
					else {
						$this->data_mesgs['record']['position_long'] = round($this->data_mesgs['record']['position_long'] * (180.0 / pow(2,31)), 5);
					}
				}
				break;
		}
	}
	
	/*
	 * Calculate HR zones using HRmax formula: zone = HRmax * percentage.
	 */
	public function hr_zones_max($hr_maximum, $percentages_array=[0.60, 0.75, 0.85, 0.95]) {
		if(array_walk($percentages_array, function(&$value, $key, $hr_maximum) { $value = round($value * $hr_maximum); }, $hr_maximum)) return $percentages_array;
		else throw new Exception('phpFITFileAnalysis->hr_zones_max(): cannot calculate zones, please check inputs!');
	}
	
	/*
	 * Calculate HR zones using HRreserve formula: zone = HRresting + ((HRmax - HRresting) * percentage).
	 */
	public function hr_zones_reserve($hr_resting, $hr_maximum, $percentages_array=[0.60, 0.65, 0.75, 0.82, 0.89, 0.94 ]) {
		if(array_walk($percentages_array, function(&$value, $key, $params) { $value = round($params[0] + ($value * $params[1])); }, [$hr_resting, $hr_maximum - $hr_resting])) return $percentages_array;
		else throw new Exception('phpFITFileAnalysis->hr_zones_reserve(): cannot calculate zones, please check inputs!');
	}
    
	/*
	 * Calculate power zones using Functional Threshold Power value: zone = FTP * percentage.
	 */
	public function power_zones($functional_threshold_power, $percentages_array=[0.55, 0.75, 0.90, 1.05, 1.20, 1.50]) {
		if(array_walk($percentages_array, function(&$value, $key, $functional_threshold_power) { $value = round($value * $functional_threshold_power) + 1; }, $functional_threshold_power)) return $percentages_array;
		else throw new Exception('phpFITFileAnalysis->power_zones(): cannot calculate zones, please check inputs!');
	}
	
	/*
	 * Partition the data (e.g. cadence, heart_rate, power, speed) using thresholds provided as an array.
	 */
	public function partition_data($record_field='', $thresholds=null, $percentages=true, $labels_for_keys=true) {
		if(!isset($this->data_mesgs['record'][$record_field])) throw new Exception('phpFITFileAnalysis->partition_data(): '.$record_field.' data not present in FIT file!');
		if(!is_array($thresholds)) throw new Exception('phpFITFileAnalysis->partition_data(): thresholds must be an array e.g. [10,20,30,40,50]!');
		
		foreach($thresholds as $threshold) {
			if(!is_numeric($threshold) || $threshold < 0) throw new Exception('phpFITFileAnalysis->partition_data(): '.$threshold.' not valid in thresholds!');
			if(isset($last_threshold) && $last_threshold >= $threshold) {
				throw new Exception('phpFITFileAnalysis->partition_data(): error near ..., '.$last_threshold.', '.$threshold.', ... - each element in thresholds array must be greater than previous element!');
			}
			$last_threshold = $threshold;
		}
		
		$result = array_fill(0, count($thresholds)+1, 0);
		
		foreach($this->data_mesgs['record'][$record_field] as $value) {
			$key = 0;
			for($key; $key<count($thresholds); ++$key) {
				if($value < $thresholds[$key]) {
					$result[$key]++;
					goto loop_end;
				}
			}
			$result[$key]++;
			loop_end:
		}
		
		array_unshift($thresholds, 0);
		$keys = [];
		
		if($labels_for_keys === true) {
			for($i=0; $i<count($thresholds); ++$i) {
				$keys[] = $thresholds[$i] . (isset($thresholds[$i+1]) ? '-'.($thresholds[$i+1] - 1) : '+');
			}
			$result = array_combine($keys, $result);
		}
		
		if($percentages === true) {
			$total = array_sum($result);
			array_walk($result, function (&$value, $key, $total) { $value = round($value / $total * 100, 1); }, $total);
		}
		
		return $result;
	}
	
	/*
	 * Split data into buckets/bins using a Counting Sort algorithm (http://en.wikipedia.org/wiki/Counting_sort) to generate data for a histogram plot.
	 */
	public function histogram($bucket_width=25, $record_field='') {
		if(!isset($this->data_mesgs['record'][$record_field])) throw new Exception('phpFITFileAnalysis->histogram(): '.$record_field.' data not present in FIT file!');
		if(!is_numeric($bucket_width) || $bucket_width <= 0) throw new Exception('phpFITFileAnalysis->histogram(): bucket width is not valid!');
		
		foreach($this->data_mesgs['record'][$record_field] as $value) {
			$key = round($value / $bucket_width) * $bucket_width;
			isset($result[$key]) ? $result[$key]++ : $result[$key] = 1;
		}
		
		for($i=0; $i<max(array_keys($result)) / $bucket_width; ++$i) {
			if(!isset($result[$i * $bucket_width]))
				$result[$i * $bucket_width] = 0;
		}
		
		ksort($result);
		return $result;
	}
	
	/*
	 * Helper functions / shortcuts.
	 */	
	public function hr_partioned_HRmaximum($hr_maximum) {
		return $this->partition_data('heart_rate', $this->hr_zones_max($hr_maximum));
	}
	public function hr_partioned_HRreserve($hr_resting, $hr_maximum) {
		return $this->partition_data('heart_rate', $this->hr_zones_reserve($hr_resting, $hr_maximum));
	}
	public function power_partioned($functional_threshold_power) {
		return $this->partition_data('power', $this->power_zones($functional_threshold_power));
	}
	public function power_histogram($bucket_width=25) {
		return $this->histogram($bucket_width, 'power');
	}
	
	/*
	 * Simple moving average algorithm
	 */
	private function sma($array, $time_period) {
		$sma_data = [];
		$data = array_values($array);
		$count = count($array);
		
		for($i=0; $i<$count-$time_period; ++$i) {
			$sma_data[] = array_sum(array_slice($array, $i, $time_period)) / $time_period;
		}
		
		return $sma_data;
	}

	/*
	 * Returns 'Average Power', 'Kilojoules', 'Normalised Power', 'Variability Index', 'Intensity Factor', and 'Training Stress Score' in an array.
	 * 
	 * Normalised Power (and metrics dependent on it) require the PHP trader extension to be loaded
	 * http://php.net/manual/en/book.trader.php
	 */
	public function power_metrics($functional_threshold_power) {
		if(!isset($this->data_mesgs['record']['power'])) throw new Exception('phpFITFileAnalysis->power_metrics(): power data not present in FIT file!');
		
		$power_metrics['Average Power'] = array_sum($this->data_mesgs['record']['power']) / count($this->data_mesgs['record']['power']);
		$power_metrics['Kilojoules'] = ($power_metrics['Average Power'] * count($this->data_mesgs['record']['power'])) / 1000;
		
		// NP1 capture all values for rolling 30s averages
		$NP_values = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], 30) : $this->sma($this->data_mesgs['record']['power'], 30);
		
		$NormalisedPower = 0.0;
		foreach($NP_values as $value) {  // NP2 Raise all the values obtained in step NP1 to the fourth power
			$NormalisedPower += pow($value,4);
		}
		$NormalisedPower /= count($NP_values);  // NP3 Find the average of the values in NP2
		$power_metrics['Normalised Power'] = pow($NormalisedPower, 1/4);  // NP4 taking the fourth root of the value obtained in step NP3
		
		$power_metrics['Variability Index'] = $power_metrics['Normalised Power'] / $power_metrics['Average Power'];
		$power_metrics['Intensity Factor'] = $power_metrics['Normalised Power'] / $functional_threshold_power;
		$power_metrics['Training Stress Score'] = (count($this->data_mesgs['record']['power']) * $power_metrics['Normalised Power'] * $power_metrics['Intensity Factor']) / ($functional_threshold_power * 36);
		
		// Round the values to make them something sensible.
		$power_metrics['Average Power'] = (int)round($power_metrics['Average Power']);
		$power_metrics['Kilojoules'] = (int)round($power_metrics['Kilojoules']);
		$power_metrics['Normalised Power'] = (int)round($power_metrics['Normalised Power']);
		$power_metrics['Variability Index'] = round($power_metrics['Variability Index'], 2);
		$power_metrics['Intensity Factor'] = round($power_metrics['Intensity Factor'], 2);
		$power_metrics['Training Stress Score'] = (int)round($power_metrics['Training Stress Score']);
		
		return $power_metrics;
	}
	
	/*
	 * Returns Critical Power (Best Efforts) values for supplied time period(s).
	 */
	public function critical_power($time_periods) {
		if(!isset($this->data_mesgs['record']['power'])) throw new Exception('phpFITFileAnalysis->critical_power(): power data not present in FIT file!');
		
		if(is_array($time_periods)) {
			$count = count($this->data_mesgs['record']['power']);
			foreach($time_periods as $time_period) {
				if(!is_numeric($time_period)) throw new Exception('phpFITFileAnalysis->critical_power(): time periods must only contain numeric data!');
				if($time_period < 0) throw new Exception('phpFITFileAnalysis->critical_power(): time periods cannot be negative!');
				if($time_period > $count) break;
				
				$averages = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], $time_period) : $this->sma($this->data_mesgs['record']['power'], $time_period);
				if($averages !== false) {
					$critical_power_values[$time_period] = max($averages);
				}
			}
			
			return $critical_power_values;
		}
		else if(is_numeric($time_periods) && $time_periods > 0) {
			if($time_periods > count($this->data_mesgs['record']['power'])) {
				$critical_power_values[$time_periods] = 0;
			}
			else {
				$averages = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], $time_periods) : $this->sma($this->data_mesgs['record']['power'], $time_periods);
				if($averages !== false) {
					$critical_power_values[$time_periods] = max($averages);
				}
			}
			
			return $critical_power_values;
		}
		else throw new Exception('phpFITFileAnalysis->critical_power(): time periods not valid!');
	}
	
	/*
	 * Outputs tables of information being listened for and found within the processed FIT file.
	 */
	public function show_debug_info() {
		echo '<h3>Types</h3>';
		echo '<table class=\'table table-condensed table-striped\'>';  // Bootstrap classes
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
}
?>