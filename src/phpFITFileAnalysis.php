<?php
namespace adriangibbons;

/**
 * phpFITFileAnalysis
 * =====================
 * A PHP class for Analysing FIT files created by Garmin GPS devices.
 * Adrian Gibbons, 2015
 * Adrian.GitHub@gmail.com
 *
 * G Frogley edits:
 * Added code to generate TRIMPexp and hrIF (Intensity Factor) value to measure session if Power is not present (June 2015).
 * Added code to generate Quadrant Analysis data (September 2015).
 *
 * Rafael Nájera edits:
 * Added code to support compressed timestamps (March 2017).
 *
 * https://github.com/adriangibbons/phpFITFileAnalysis
 * http://www.thisisant.com/resources/fit
 */

if (!defined('DEFINITION_MESSAGE')) {
    define('DEFINITION_MESSAGE', 1);
}
if (!defined('DATA_MESSAGE')) {
    define('DATA_MESSAGE', 0);
}

/*
 * This is the number of seconds difference between FIT and Unix timestamps.
 * FIT timestamps are seconds since UTC 00:00:00 Dec 31 1989 (source FIT SDK)
 * Unix time is the number of seconds since UTC 00:00:00 Jan 01 1970
 */
if (!defined('FIT_UNIX_TS_DIFF')) {
    define('FIT_UNIX_TS_DIFF', 631065600);
}

class phpFITFileAnalysis
{
    public $data_mesgs = [];  // Used to store the data read from the file in associative arrays.
    private $dev_field_descriptions = [];
    private $options = null;                 // Options provided to __construct().
    private $file_contents = '';             // FIT file is read-in to memory as a string, split into an array, and reversed. See __construct().
    private $file_pointer = 0;               // Points to the location in the file that shall be read next.
    private $defn_mesgs = [];                // Array of FIT 'Definition Messages', which describe the architecture, format, and fields of 'Data Messages'.
    private $defn_mesgs_all = [];            // Keeps a record of all Definition Messages as index ($local_mesg_type) of $defn_mesgs may be reused in file.
    private $file_header = [];               // Contains information about the FIT file such as the Protocol version, Profile version, and Data Size.
    private $php_trader_ext_loaded = false;  // Is the PHP Trader extension loaded? Use $this->sma() algorithm if not available.
    private $types = null;                   // Set by $endianness depending on architecture in Definition Message.
    private $garmin_timestamps = false;      // By default the constant FIT_UNIX_TS_DIFF will be added to timestamps.
    
    // Enumerated data looked up by enumData().
    // Values from 'Profile.xls' contained within the FIT SDK.
    private $enum_data = [
        'activity' => [0 => 'manual', 1 => 'auto_multi_sport'],
        'ant_network' => [0 => 'public', 1 => 'antplus', 2 => 'antfs', 3 => 'private'],
        'battery_status' => [1 => 'new', 2 => 'good', 3 => 'ok', 4 => 'low', 5 => 'critical', 7 => 'unknown'],
        'body_location' => [
            0 => 'left_leg',
            1 => 'left_calf',
            2 => 'left_shin',
            3 => 'left_hamstring',
            4 => 'left_quad',
            5 => 'left_glute',
            6 => 'right_leg',
            7 => 'right_calf',
            8 => 'right_shin',
            9 => 'right_hamstring',
            10 => 'right_quad',
            11 => 'right_glute',
            12 => 'torso_back',
            13 => 'left_lower_back',
            14 => 'left_upper_back',
            15 => 'right_lower_back',
            16 => 'right_upper_back',
            17 => 'torso_front',
            18 => 'left_abdomen',
            19 => 'left_chest',
            20 => 'right_abdomen',
            21 => 'right_chest',
            22 => 'left_arm',
            23 => 'left_shoulder',
            24 => 'left_bicep',
            25 => 'left_tricep',
            26 => 'left_brachioradialis',
            27 => 'left_forearm_extensors',
            28 => 'right_arm',
            29 => 'right_shoulder',
            30 => 'right_bicep',
            31 => 'right_tricep',
            32 => 'right_brachioradialis',
            33 => 'right_forearm_extensors',
            34 => 'neck',
            35 => 'throat'
        ],
        'display_heart' => [0 => 'bpm', 1 => 'max', 2 => 'reserve'],
        'display_measure' => [0 => 'metric', 1 => 'statute'],
        'display_position' => [
            0 => 'degree',                //dd.dddddd
            1 => 'degree_minute',         //dddmm.mmm
            2 => 'degree_minute_second',  //dddmmss
            3 => 'austrian_grid',   //Austrian Grid (BMN)
            4 => 'british_grid',    //British National Grid
            5 => 'dutch_grid',      //Dutch grid system
            6 => 'hungarian_grid',  //Hungarian grid system
            7 => 'finnish_grid',    //Finnish grid system Zone3 KKJ27
            8 => 'german_grid',     //Gausss Krueger (German)
            9 => 'icelandic_grid',  //Icelandic Grid
            10 => 'indonesian_equatorial',  //Indonesian Equatorial LCO
            11 => 'indonesian_irian',       //Indonesian Irian LCO
            12 => 'indonesian_southern',    //Indonesian Southern LCO
            13 => 'india_zone_0',      //India zone 0
            14 => 'india_zone_IA',     //India zone IA
            15 => 'india_zone_IB',     //India zone IB
            16 => 'india_zone_IIA',    //India zone IIA
            17 => 'india_zone_IIB',    //India zone IIB
            18 => 'india_zone_IIIA',   //India zone IIIA
            19 => 'india_zone_IIIB',   //India zone IIIB
            20 => 'india_zone_IVA',    //India zone IVA
            21 => 'india_zone_IVB',    //India zone IVB
            22 => 'irish_transverse',  //Irish Transverse Mercator
            23 => 'irish_grid',        //Irish Grid
            24 => 'loran',             //Loran TD
            25 => 'maidenhead_grid',   //Maidenhead grid system
            26 => 'mgrs_grid',         //MGRS grid system
            27 => 'new_zealand_grid',  //New Zealand grid system
            28 => 'new_zealand_transverse',  //New Zealand Transverse Mercator
            29 => 'qatar_grid',              //Qatar National Grid
            30 => 'modified_swedish_grid',   //Modified RT-90 (Sweden)
            31 => 'swedish_grid',            //RT-90 (Sweden)
            32 => 'south_african_grid',      //South African Grid
            33 => 'swiss_grid',              //Swiss CH-1903 grid
            34 => 'taiwan_grid',             //Taiwan Grid
            35 => 'united_states_grid',      //United States National Grid
            36 => 'utm_ups_grid',            //UTM/UPS grid system
            37 => 'west_malayan',            //West Malayan RSO
            38 => 'borneo_rso',              //Borneo RSO
            39 => 'estonian_grid',           //Estonian grid system
            40 => 'latvian_grid',            //Latvian Transverse Mercator
            41 => 'swedish_ref_99_grid',     //Reference Grid 99 TM (Swedish)
        ],
        'display_power' => [0 => 'watts', 1 => 'percent_ftp'],
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
            44 => 'rider_position_change',
            45 => 'elev_high_alert',
            46 => 'elev_low_alert',
            47 => 'comm_timeout'
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
        'gender' => [0 => 'female', 1 => 'male'],
        'hr_zone_calc' => [0 => 'custom', 1 => 'percent_max_hr', 2 => 'percent_hrr'],
        'intensity' => [0 => 'active', 1 => 'rest', 2 => 'warmup', 3 => 'cooldown'],
        'language' => [
            0 => 'english',
            1 => 'french',
            2 => 'italian',
            3 => 'german',
            4 => 'spanish',
            5 => 'croatian',
            6 => 'czech',
            7 => 'danish',
            8 => 'dutch',
            9 => 'finnish',
            10 => 'greek',
            11 => 'hungarian',
            12 => 'norwegian',
            13 => 'polish',
            14 => 'portuguese',
            15 => 'slovakian',
            16 => 'slovenian',
            17 => 'swedish',
            18 => 'russian',
            19 => 'turkish',
            20 => 'latvian',
            21 => 'ukrainian',
            22 => 'arabic',
            23 => 'farsi',
            24 => 'bulgarian',
            25 => 'romanian',
            254 => 'custom'
        ],
        'length_type' => [0 => 'idle', 1 => 'active'],
        'manufacturer' => [  // Have capitalised select manufacturers
            1 => 'Garmin',
            2 => 'garmin_fr405_antfs',
            3 => 'zephyr',
            4 => 'dayton',
            5 => 'idt',
            6 => 'SRM',
            7 => 'Quarq',
            8 => 'iBike',
            9 => 'saris',
            10 => 'spark_hk',
            11 => 'Tanita',
            12 => 'Echowell',
            13 => 'dynastream_oem',
            14 => 'nautilus',
            15 => 'dynastream',
            16 => 'Timex',
            17 => 'metrigear',
            18 => 'xelic',
            19 => 'beurer',
            20 => 'cardiosport',
            21 => 'a_and_d',
            22 => 'hmm',
            23 => 'Suunto',
            24 => 'thita_elektronik',
            25 => 'gpulse',
            26 => 'clean_mobile',
            27 => 'pedal_brain',
            28 => 'peaksware',
            29 => 'saxonar',
            30 => 'lemond_fitness',
            31 => 'dexcom',
            32 => 'Wahoo Fitness',
            33 => 'octane_fitness',
            34 => 'archinoetics',
            35 => 'the_hurt_box',
            36 => 'citizen_systems',
            37 => 'Magellan',
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
            60 => 'Rotor',
            61 => 'geonaute',
            62 => 'id_bike',
            63 => 'Specialized',
            64 => 'wtek',
            65 => 'physical_enterprises',
            66 => 'north_pole_engineering',
            67 => 'BKOOL',
            68 => 'Cateye',
            69 => 'Stages Cycling',
            70 => 'Sigmasport',
            71 => 'TomTom',
            72 => 'peripedal',
            73 => 'Wattbike',
            76 => 'moxy',
            77 => 'ciclosport',
            78 => 'powerbahn',
            79 => 'acorn_projects_aps',
            80 => 'lifebeam',
            81 => 'Bontrager',
            82 => 'wellgo',
            83 => 'scosche',
            84 => 'magura',
            85 => 'woodway',
            86 => 'elite',
            87 => 'nielsen_kellerman',
            88 => 'dk_city',
            89 => 'Tacx',
            90 => 'direction_technology',
            91 => 'magtonic',
            92 => '1partcarbon',
            93 => 'inside_ride_technologies',
            94 => 'sound_of_motion',
            95 => 'stryd',
            96 => 'icg',
            97 => 'MiPulse',
            98 => 'bsx_athletics',
            99 => 'look',
            100 => 'campagnolo_srl',
            101 => 'body_bike_smart',
            102 => 'praxisworks',
            103 => 'limits_technology',
            104 => 'topaction_technology',
            105 => 'cosinuss',
            106 => 'fitcare',
            107 => 'magene',
            108 => 'giant_manufacturing_co',
            109 => 'tigrasport',
            110 => 'salutron',
            111 => 'technogym',
            112 => 'bryton_sensors',
            113 => 'latitude_limited',
            114 => 'soaring_technology',
            115 => 'igpsport',
            116 => 'thinkrider',
            117 => 'gopher_sport',
            118 => 'waterrower',
            119 => 'orangetheory',
            120 => 'inpeak',
            121 => 'kinetic',
            122 => 'johnson_health_tech',
            123 => 'polar_electro',
            124 => 'seesense',
            125 => 'nci_technology',
            126 => 'iqsquare',
            127 => 'leomo',
            128 => 'ifit_com',
            255 => 'development',
            257 => 'healthandlife',
            258 => 'Lezyne',
            259 => 'scribe_labs',
            260 => 'Zwift',
            261 => 'watteam',
            262 => 'recon',
            263 => 'favero_electronics',
            264 => 'dynovelo',
            265 => 'Strava',
            266 => 'precor',
            267 => 'Bryton',
            268 => 'sram',
            269 => 'navman',
            270 => 'cobi',
            271 => 'spivi',
            272 => 'mio_magellan',
            273 => 'evesports',
            274 => 'sensitivus_gauge',
            275 => 'podoon',
            276 => 'life_time_fitness',
            277 => 'falco_e_motors',
            278 => 'minoura',
            279 => 'cycliq',
            280 => 'luxottica',
            281 => 'trainer_road',
            282 => 'the_sufferfest',
            283 => 'fullspeedahead',
            284 => 'virtualtraining',
            285 => 'feedbacksports',
            286 => 'omata',
            287 => 'vdo',
            288 => 'magneticdays',
            289 => 'hammerhead',
            290 => 'kinetic_by_kurt',
            291 => 'shapelog',
            292 => 'dabuziduo',
            293 => 'jetblack',
            294 => 'coros',
            295 => 'virtugo',
            296 => 'velosense',
            297 => 'cycligentinc',
            298 => 'trailforks',
            299 => 'mahle_ebikemotion',
            5759 => 'actigraphcorp'
        ],
        'pwr_zone_calc' => [0 => 'custom', 1 => 'percent_ftp'],
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
            11 => 'axs01',
            12 => 'HRM-Tri',                    // 'hrm_tri_single_byte_product_id',
            14 => 'Forerunner 225',             // 'fr225_single_byte_product_id',
            473 => 'Forerunner 301',            // 'fr301_china',
            474 => 'Forerunner 301',            // 'fr301_japan',
            475 => 'Forerunner 301',            // 'fr301_korea',
            494 => 'Forerunner 301',            // 'fr301_taiwan',
            717 => 'Forerunner 405',            // 'fr405',
            782 => 'Forerunner 50',             // 'fr50',
            987 => 'Forerunner 405',            // 'fr405_japan',
            988 => 'Forerunner 60',             // 'fr60',
            1011 => 'dsi_alf01',
            1018 => 'Forerunner 310XT',         // 'fr310xt',
            1036 => 'Edge 500',                 // 'edge500',
            1124 => 'Forerunner 110',           // 'fr110',
            1169 => 'Edge 800',                 // 'edge800',
            1199 => 'Edge 500',                 // 'edge500_taiwan',
            1213 => 'Edge 500',                 // 'edge500_japan',
            1253 => 'chirp',
            1274 => 'Forerunner 110',           // 'fr110_japan',
            1325 => 'edge200',
            1328 => 'Forerunner 910XT',         // 'fr910xt',
            1333 => 'Edge 800',                 // 'edge800_taiwan',
            1334 => 'Edge 800',                 // 'edge800_japan',
            1341 => 'alf04',
            1345 => 'Forerunner 610',           // 'fr610',
            1360 => 'Forerunner 210',           // 'fr210_japan',
            1380 => 'Vector 2S',                // vector_ss
            1381 => 'Vector 2',                 // vector_cp
            1386 => 'Edge 800',                 // 'edge800_china',
            1387 => 'Edge 500',                 // 'edge500_china',
            1410 => 'Forerunner 610',           // 'fr610_japan',
            1422 => 'Edge 500',                 // 'edge500_korea',
            1436 => 'Forerunner 70',            // 'fr70',
            1446 => 'Forerunner 310XT',         // 'fr310xt_4t',
            1461 => 'amx',
            1482 => 'Forerunner 10',            // 'fr10',
            1497 => 'Edge 800',                 // 'edge800_korea',
            1499 => 'swim',
            1537 => 'Forerunner 910XT',         // 'fr910xt_china',
            1551 => 'Fenix',                    // fenix
            1555 => 'edge200_taiwan',
            1561 => 'Edge 510',                 // 'edge510',
            1567 => 'Edge 810',                 // 'edge810',
            1570 => 'tempe',
            1600 => 'Forerunner 910XT',         // 'fr910xt_japan',
            1623 => 'Forerunner 620',           // 'fr620',
            1632 => 'Forerunner 220',           // 'fr220',
            1664 => 'Forerunner 910XT',         // 'fr910xt_korea',
            1688 => 'Forerunner 10',            // 'fr10_japan',
            1721 => 'Edge 810',                 // 'edge810_japan',
            1735 => 'virb_elite',
            1736 => 'edge_touring',
            1742 => 'Edge 510',                 // 'edge510_japan',
            1743 => 'HRM-Tri',                  // 'hrm_tri',
            1752 => 'hrm_run',
            1765 => 'Forerunner 920XT',         // 'fr920xt',
            1821 => 'Edge 510',                 // 'edge510_asia',
            1822 => 'Edge 810',                 // 'edge810_china',
            1823 => 'Edge 810',                 // 'edge810_taiwan',
            1836 => 'Edge 1000',                // 'edge1000',
            1837 => 'vivo_fit',
            1853 => 'virb_remote',
            1885 => 'vivo_ki',
            1903 => 'Forerunner 15',            // 'fr15',
            1907 => 'vivoactive',               // 'vivo_active',
            1918 => 'Edge 510',                 // 'edge510_korea',
            1928 => 'Forerunner 620',           // 'fr620_japan',
            1929 => 'Forerunner 620',           // 'fr620_china',
            1930 => 'Forerunner 220',           // 'fr220_japan',
            1931 => 'Forerunner 220',           // 'fr220_china',
            1936 => 'Approach S6',              // 'approach_s6'
            1956 => 'vívosmart',                // 'vivo_smart',
            1967 => 'Fenix 2',                  // fenix2
            1988 => 'epix',
            2050 => 'Fenix 3',                  // fenix3
            2052 => 'Edge 1000',                // edge1000_taiwan
            2053 => 'Edge 1000',                // edge1000_japan
            2061 => 'Forerunner 15',            // fr15_japan
            2067 => 'Edge 520',                 // edge520
            2070 => 'Edge 1000',                // edge1000_china
            2072 => 'Forerunner 620',           // fr620_russia
            2073 => 'Forerunner 220',           // fr220_russia
            2079 => 'Vector S',                 // vector_s
            2100 => 'Edge 1000',                // edge1000_korea
            2130 => 'Forerunner 920',           // fr920xt_taiwan
            2131 => 'Forerunner 920',           // fr920xt_china
            2132 => 'Forerunner 920',           // fr920xt_japan
            2134 => 'virbx',
            2135 => 'vívosmart',                // vivo_smart_apac',
            2140 => 'etrex_touch',
            2147 => 'Edge 25',                  // edge25
            2148 => 'Forerunner 25',            // fr25
            2150 => 'vivo_fit2',
            2153 => 'Forerunner 225',           // fr225
            2156 => 'Forerunner 630',           // fr630
            2157 => 'Forerunner 230',           // fr230
            2160 => 'vivo_active_apac',
            2161 => 'Vector 2',                 // vector_2
            2162 => 'Vector 2S',                // vector_2s
            2172 => 'virbxe',
            2173 => 'Forerunner 620',           // fr620_taiwan
            2174 => 'Forerunner 220',           // fr220_taiwan
            2175 => 'truswing',
            2188 => 'Fenix 3',                  // fenix3_china
            2189 => 'Fenix 3',                  // fenix3_twn
            2192 => 'varia_headlight',
            2193 => 'varia_taillight_old',
            2204 => 'Edge Explore 1000',        // edge_explore_1000
            2219 => 'Forerunner 225',           // fr225_asia
            2225 => 'varia_radar_taillight',
            2226 => 'varia_radar_display',
            2238 => 'Edge 20',                  // edge20
            2262 => 'D2 Bravo',                 // d2_bravo
            2266 => 'approach_s20',
            2276 => 'varia_remote',
            2327 => 'hrm4_run',
            2337 => 'vivo_active_hr',
            2347 => 'vivo_smart_gps_hr',
            2348 => 'vivo_smart_hr',
            2368 => 'vivo_move',
            2398 => 'varia_vision',
            2406 => 'vivo_fit3',
            2413 => 'Fenix 3 HR',               // fenix3_hr
            2417 => 'Virb Ultra 30',            // virb_ultra_30
            2429 => 'index_smart_scale',
            2431 => 'Forerunner 235',           // fr235
            2432 => 'Fenix 3 Chronos',          // fenix3_chronos
            2441 => 'oregon7xx',
            2444 => 'rino7xx',
            2496 => 'nautix',
            2530 => 'Edge 820',                 // edge_820
            2531 => 'Edge Explore 820',         // edge_explore_820
            2544 => 'fenix5s',
            2547 => 'D2 Bravo Titanium',        // d2_bravo_titanium
            2593 => 'Running Dynamics Pod',     // running_dynamics_pod
            2604 => 'Fenix 5x',                 // fenix5x
            2606 => 'vivofit jr',               // vivo_fit_jr
            2691 => 'Forerunner 935',           // fr935
            2697 => 'Fenix 5',                  // fenix5
            2700 => 'vivoactive3',
            2769 => 'foretrex_601_701',
            2772 => 'vivo_move_hr',
            2713 => 'Edge 1030',                // edge_1030
            2806 => 'approach_z80',
            2831 => 'vivo_smart3_apac',
            2832 => 'vivo_sport_apac',
            2859 => 'descent',
            2886 => 'Forerunner 645',           // fr645
            2888 => 'Forerunner 645',           // fr645m
            2900 => 'Fenix 5S Plus',            // fenix5s_plus
            2909 => 'Edge 130',                 // Edge_130
            2927 => 'vivosmart_4',
            2962 => 'approach_x10',
            2988 => 'vivoactive3m_w',
            3011 => 'edge_explore',
            3028 => 'gpsmap66',
            3049 => 'approach_s10',
            3066 => 'vivoactive3m_l',
            3085 => 'approach_g80',
            3110 => 'Fenix 5 Plus',             // fenix5_plus
            3111 => 'Fenix 5X Plus',            // fenix5x_plus
            3112 => 'Edge 520 Plus',            // edge_520_plus
            3299 => 'hrm_dual',
            3314 => 'approach_s40',
            10007 => 'SDM4 footpod',            // sdm4
            10014 => 'edge_remote',
            20119 => 'training_center',
            65531 => 'connectiq_simulator',
            65532 => 'android_antplus_plugin',
            65534 => 'Garmin Connect website'   // connect
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
            28 => 'E-Bike Fitness',
            254 => 'All'
        ],
        'session_trigger' => [0 => 'activity_end', 1 => 'manual', 2 => 'auto_multi_sport', 3 => 'fitness_equipment'],
        'source_type' => [
            0 => 'ant',  //External device connected with ANT
            1 => 'antplus',  //External device connected with ANT+
            2 => 'bluetooth',  //External device connected with BT
            3 => 'bluetooth_low_energy',  //External device connected with BLE
            4 => 'wifi',  //External device connected with Wifi
            5 => 'local',  //Onboard device
        ],
        'swim_stroke' => [0 => 'Freestyle', 1 => 'Backstroke', 2 => 'Breaststroke', 3 => 'Butterfly', 4 => 'Drill', 5 => 'Mixed', 6 => 'IM'],  // Have capitalised.
        'water_type' => [0 => 'fresh', 1 => 'salt', 2 => 'en13319', 3 => 'custom'],
        'tissue_model_type' => [0 => 'zhl_16c'],
        'dive_gas_status' => [0 => 'disabled', 1 => 'enabled', 2 => 'backup_only'],
        'dive_alarm_type' => [0 => 'depth', 1 => 'time'],
        'dive_backlight_mode' => [0 => 'at_depth', 1 => 'always_on'],
    ];
    
    /**
     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 2.2.pdf
     * Table 4-6. FIT Base Types and Invalid Values
     *
     * $types array holds a string used by the PHP unpack() function to format binary data.
     * 'tmp' is the name of the (single element) array created.
     */
    private $endianness = [
        0 => [  // Little Endianness
            0   => ['format' => 'Ctmp', 'bytes' => 1],  // enum
            1   => ['format' => 'ctmp', 'bytes' => 1],  // sint8
            2   => ['format' => 'Ctmp', 'bytes' => 1],  // uint8
            131 => ['format' => 'vtmp', 'bytes' => 2],  // sint16 - manually convert uint16 to sint16 in fixData()
            132 => ['format' => 'vtmp', 'bytes' => 2],  // uint16
            133 => ['format' => 'Vtmp', 'bytes' => 4],  // sint32 - manually convert uint32 to sint32 in fixData()
            134 => ['format' => 'Vtmp', 'bytes' => 4],  // uint32
            7   => ['format' => 'a*tmp', 'bytes' => 1], // string
            136 => ['format' => 'ftmp', 'bytes' => 4],  // float32
            137 => ['format' => 'dtmp', 'bytes' => 8],  // float64
            10  => ['format' => 'Ctmp', 'bytes' => 1],  // uint8z
            139 => ['format' => 'vtmp', 'bytes' => 2],  // uint16z
            140 => ['format' => 'Vtmp', 'bytes' => 4],  // uint32z
            13  => ['format' => 'Ctmp', 'bytes' => 1],  // byte
            142 => ['format' => 'Ptmp', 'bytes' => 8],  // sint64 - manually convert uint64 to sint64 in fixData()
            143 => ['format' => 'Ptmp', 'bytes' => 8],  // uint64
            144 => ['format' => 'Ptmp', 'bytes' => 8]   // uint64z
        ],
        1 => [  // Big Endianness
            0   => ['format' => 'Ctmp', 'bytes' => 1],  // enum
            1   => ['format' => 'ctmp', 'bytes' => 1],  // sint8
            2   => ['format' => 'Ctmp', 'bytes' => 1],  // uint8
            131 => ['format' => 'ntmp', 'bytes' => 2],  // sint16 - manually convert uint16 to sint16 in fixData()
            132 => ['format' => 'ntmp', 'bytes' => 2],  // uint16
            133 => ['format' => 'Ntmp', 'bytes' => 4],  // sint32 - manually convert uint32 to sint32 in fixData()
            134 => ['format' => 'Ntmp', 'bytes' => 4],  // uint32
            7   => ['format' => 'a*tmp', 'bytes' => 1], // string
            136 => ['format' => 'ftmp', 'bytes' => 4],  // float32
            137 => ['format' => 'dtmp', 'bytes' => 8],  // float64
            10  => ['format' => 'Ctmp', 'bytes' => 1],  // uint8z
            139 => ['format' => 'ntmp', 'bytes' => 2],  // uint16z
            140 => ['format' => 'Ntmp', 'bytes' => 4],  // uint32z
            13  => ['format' => 'Ctmp', 'bytes' => 1],  // byte
            142 => ['format' => 'Jtmp', 'bytes' => 8],  // sint64 - manually convert uint64 to sint64 in fixData()
            143 => ['format' => 'Jtmp', 'bytes' => 8],  // uint64
            144 => ['format' => 'Jtmp', 'bytes' => 8]   // uint64z
        ]
    ];
    
    private $invalid_values = [
        0   => 255,                  // 0xFF
        1   => 127,                  // 0x7F
        2   => 255,                  // 0xFF
        131 => 32767,                // 0x7FFF
        132 => 65535,                // 0xFFFF
        133 => 2147483647,           // 0x7FFFFFFF
        134 => 4294967295,           // 0xFFFFFFFF
        7   => 0,                    // 0x00
        136 => 4294967295,           // 0xFFFFFFFF
        137 => 9223372036854775807,  // 0xFFFFFFFFFFFFFFFF
        10  => 0,                    // 0x00
        139 => 0,                    // 0x0000
        140 => 0,                    // 0x00000000
        13  => 255,                  // 0xFF
        142 => 9223372036854775807,  // 0x7FFFFFFFFFFFFFFF
        143 => 18446744073709551615, // 0xFFFFFFFFFFFFFFFF
        144 => 0                     // 0x0000000000000000
    ];
    
    /**
     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
     * 4.4 Scale/Offset
     * When specified, the binary quantity is divided by the scale factor and then the offset is subtracted, yielding a floating point quantity.
     */
    private $data_mesg_info = [
        0 => [
            'mesg_name' => 'file_id', 'field_defns' => [
                0 => ['field_name' => 'type',           'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'manufacturer',   'scale' => 1, 'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'product',        'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'serial_number',  'scale' => 1, 'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'time_created',   'scale' => 1, 'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'number',         'scale' => 1, 'offset' => 0, 'units' => ''],
            ]
        ],
        
        2 => [
            'mesg_name' => 'device_settings', 'field_defns' => [
                0 => ['field_name' => 'active_time_zone', 'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'utc_offset', 'scale' => 1, 'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'time_zone_offset', 'scale' => 4, 'offset' => 0, 'units' => 'hr'],
            ]
        ],
        
        3 => [
            'mesg_name' => 'user_profile', 'field_defns' => [
                0 => ['field_name' => 'friendly_name',                  'scale' => 1,   'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'gender',                         'scale' => 1,   'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'age',                            'scale' => 1,   'offset' => 0, 'units' => 'years'],
                3 => ['field_name' => 'height',                         'scale' => 100, 'offset' => 0, 'units' => 'm'],
                4 => ['field_name' => 'weight',                         'scale' => 10,  'offset' => 0, 'units' => 'kg'],
                5 => ['field_name' => 'language',                       'scale' => 1,   'offset' => 0, 'units' => ''],
                6 => ['field_name' => 'elev_setting',                   'scale' => 1,   'offset' => 0, 'units' => ''],
                7 => ['field_name' => 'weight_setting',                 'scale' => 1,   'offset' => 0, 'units' => ''],
                8 => ['field_name' => 'resting_heart_rate',             'scale' => 1,   'offset' => 0, 'units' => 'bpm'],
                10 => ['field_name' => 'default_max_biking_heart_rate', 'scale' => 1,   'offset' => 0, 'units' => 'bpm'],
                11 => ['field_name' => 'default_max_heart_rate',        'scale' => 1,   'offset' => 0, 'units' => 'bpm'],
                12 => ['field_name' => 'hr_setting',                    'scale' => 1,   'offset' => 0, 'units' => ''],
                13 => ['field_name' => 'speed_setting',                 'scale' => 1,   'offset' => 0, 'units' => ''],
                14 => ['field_name' => 'dist_setting',                  'scale' => 1,   'offset' => 0, 'units' => ''],
                16 => ['field_name' => 'power_setting',                 'scale' => 1,   'offset' => 0, 'units' => ''],
                17 => ['field_name' => 'activity_class',                'scale' => 1,   'offset' => 0, 'units' => ''],
                18 => ['field_name' => 'position_setting',              'scale' => 1,   'offset' => 0, 'units' => ''],
                21 => ['field_name' => 'temperature_setting',           'scale' => 1,   'offset' => 0, 'units' => ''],
            ]
        ],
        
        7 => [
            'mesg_name' => 'zones_target', 'field_defns' => [
                1 => ['field_name' => 'max_heart_rate',             'scale' => 1, 'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'threshold_heart_rate',       'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'functional_threshold_power', 'scale' => 1, 'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'hr_calc_type',               'scale' => 1, 'offset' => 0, 'units' => ''],
                7 => ['field_name' => 'pwr_calc_type',              'scale' => 1, 'offset' => 0, 'units' => ''],
            ]
        ],
        
        12 => [
            'mesg_name' => 'sport', 'field_defns' => [
                0 => ['field_name' => 'sport',     'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'name',      'scale' => 1, 'offset' => 0, 'units' => ''],
            ]
        ],
        
        18 => [
            'mesg_name' => 'session', 'field_defns' => [
                0 => ['field_name' => 'event',                            'scale' => 1,         'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'event_type',                       'scale' => 1,         'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'start_time',                       'scale' => 1,         'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'start_position_lat',               'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                4 => ['field_name' => 'start_position_long',              'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                5 => ['field_name' => 'sport',                            'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                6 => ['field_name' => 'sub_sport',                        'scale' => 1,         'offset' => 0, 'units' => ''],
                7 => ['field_name' => 'total_elapsed_time',               'scale' => 1000,      'offset' => 0, 'units' => 's'],
                8 => ['field_name' => 'total_timer_time',                 'scale' => 1000,      'offset' => 0, 'units' => 's'],
                9 => ['field_name' => 'total_distance',                   'scale' => 100,       'offset' => 0, 'units' => 'm'],
                10 => ['field_name' => 'total_cycles',                    'scale' => 1,         'offset' => 0, 'units' => 'cycles'],
                11 => ['field_name' => 'total_calories',                  'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                13 => ['field_name' => 'total_fat_calories',              'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                14 => ['field_name' => 'avg_speed',                       'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                15 => ['field_name' => 'max_speed',                       'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                16 => ['field_name' => 'avg_heart_rate',                  'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                17 => ['field_name' => 'max_heart_rate',                  'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                18 => ['field_name' => 'avg_cadence',                     'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                19 => ['field_name' => 'max_cadence',                     'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                20 => ['field_name' => 'avg_power',                       'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                21 => ['field_name' => 'max_power',                       'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                22 => ['field_name' => 'total_ascent',                    'scale' => 1,         'offset' => 0, 'units' => 'm'],
                23 => ['field_name' => 'total_descent',                   'scale' => 1,         'offset' => 0, 'units' => 'm'],
                24 => ['field_name' => 'total_training_effect',           'scale' => 10,        'offset' => 0, 'units' => ''],
                25 => ['field_name' => 'first_lap_index',                 'scale' => 1,         'offset' => 0, 'units' => ''],
                26 => ['field_name' => 'num_laps',                        'scale' => 1,         'offset' => 0, 'units' => ''],
                27 => ['field_name' => 'event_group',                     'scale' => 1,         'offset' => 0, 'units' => ''],
                28 => ['field_name' => 'trigger',                         'scale' => 1,         'offset' => 0, 'units' => ''],
                29 => ['field_name' => 'nec_lat',                         'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                30 => ['field_name' => 'nec_long',                        'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                31 => ['field_name' => 'swc_lat',                         'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                32 => ['field_name' => 'swc_long',                        'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                34 => ['field_name' => 'normalized_power',                'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                35 => ['field_name' => 'training_stress_score',           'scale' => 10,        'offset' => 0, 'units' => 'tss'],
                36 => ['field_name' => 'intensity_factor',                'scale' => 1000,      'offset' => 0, 'units' => 'if'],
                37 => ['field_name' => 'left_right_balance',              'scale' => 1,         'offset' => 0, 'units' => ''],
                41 => ['field_name' => 'avg_stroke_count',                'scale' => 10,        'offset' => 0, 'units' => 'strokes/lap'],
                42 => ['field_name' => 'avg_stroke_distance',             'scale' => 100,       'offset' => 0, 'units' => 'm'],
                43 => ['field_name' => 'swim_stroke',                     'scale' => 1,         'offset' => 0, 'units' => 'swim_stroke'],
                44 => ['field_name' => 'pool_length',                     'scale' => 100,       'offset' => 0, 'units' => 'm'],
                45 => ['field_name' => 'threshold_power',                 'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                46 => ['field_name' => 'pool_length_unit',                'scale' => 1,         'offset' => 0, 'units' => ''],
                47 => ['field_name' => 'num_active_lengths',              'scale' => 1,         'offset' => 0, 'units' => 'lengths'],
                48 => ['field_name' => 'total_work',                      'scale' => 1,         'offset' => 0, 'units' => 'J'],
                65 => ['field_name' => 'time_in_hr_zone',                 'scale' => 1000,      'offset' => 0, 'units' => 's'],
                68 => ['field_name' => 'time_in_power_zone',              'scale' => 1000,      'offset' => 0, 'units' => 's'],
                89 => ['field_name' => 'avg_vertical_oscillation',        'scale' => 10,        'offset' => 0, 'units' => 'mm'],
                90 => ['field_name' => 'avg_stance_time_percent',         'scale' => 100,       'offset' => 0, 'units' => 'percent'],
                91 => ['field_name' => 'avg_stance_time',                 'scale' => 10,        'offset' => 0, 'units' => 'ms'],
                92 => ['field_name' => 'avg_fractional_cadence',          'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                93 => ['field_name' => 'max_fractional_cadence',          'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                94 => ['field_name' => 'total_fractional_cycles',         'scale' => 128,       'offset' => 0, 'units' => 'cycles'],
                101 => ['field_name' => 'avg_left_torque_effectiveness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                102 => ['field_name' => 'avg_right_torque_effectiveness', 'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                103 => ['field_name' => 'avg_left_pedal_smoothness',      'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                104 => ['field_name' => 'avg_right_pedal_smoothness',     'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                105 => ['field_name' => 'avg_combined_pedal_smoothness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                111 => ['field_name' => 'sport_index',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                112 => ['field_name' => 'time_standing',                  'scale' => 1000,      'offset' => 0, 'units' => 's'],
                113 => ['field_name' => 'stand_count',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                114 => ['field_name' => 'avg_left_pco',                   'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                115 => ['field_name' => 'avg_right_pco',                  'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                116 => ['field_name' => 'avg_left_power_phase',           'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                117 => ['field_name' => 'avg_left_power_phase_peak',      'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                118 => ['field_name' => 'avg_right_power_phase',          'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                119 => ['field_name' => 'avg_right_power_phase_peak',     'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                120 => ['field_name' => 'avg_power_position',             'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                121 => ['field_name' => 'max_power_position',             'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                122 => ['field_name' => 'avg_cadence_position',           'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                123 => ['field_name' => 'max_cadence_position',           'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                253 => ['field_name' => 'timestamp',                      'scale' => 1,         'offset' => 0, 'units' => 's'],
                254 => ['field_name' => 'message_index',                  'scale' => 1,         'offset' => 0, 'units' => ''],
            ]
        ],
        
        19 => [
            'mesg_name' => 'lap', 'field_defns' => [
                0 => ['field_name' => 'event',                           'scale' => 1,         'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'event_type',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'start_time',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'start_position_lat',              'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                4 => ['field_name' => 'start_position_long',             'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                5 => ['field_name' => 'end_position_lat',                'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                6 => ['field_name' => 'end_position_long',               'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                7 => ['field_name' => 'total_elapsed_time',              'scale' => 1000,      'offset' => 0, 'units' => 's'],
                8 => ['field_name' => 'total_timer_time',                'scale' => 1000,      'offset' => 0, 'units' => 's'],
                9 => ['field_name' => 'total_distance',                  'scale' => 100,       'offset' => 0, 'units' => 'm'],
                10 => ['field_name' => 'total_cycles',                   'scale' => 1,         'offset' => 0, 'units' => 'cycles'],
                11 => ['field_name' => 'total_calories',                 'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                12 => ['field_name' => 'total_fat_calories',             'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                13 => ['field_name' => 'avg_speed',                      'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                14 => ['field_name' => 'max_speed',                      'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                15 => ['field_name' => 'avg_heart_rate',                 'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                16 => ['field_name' => 'max_heart_rate',                 'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                17 => ['field_name' => 'avg_cadence',                    'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                18 => ['field_name' => 'max_cadence',                    'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                19 => ['field_name' => 'avg_power',                      'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                20 => ['field_name' => 'max_power',                      'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                21 => ['field_name' => 'total_ascent',                   'scale' => 1,         'offset' => 0, 'units' => 'm'],
                22 => ['field_name' => 'total_descent',                  'scale' => 1,         'offset' => 0, 'units' => 'm'],
                23 => ['field_name' => 'intensity',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                24 => ['field_name' => 'lap_trigger',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                25 => ['field_name' => 'sport',                          'scale' => 1,         'offset' => 0, 'units' => ''],
                26 => ['field_name' => 'event_group',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                32 => ['field_name' => 'num_lengths',                    'scale' => 1,         'offset' => 0, 'units' => 'lengths'],
                33 => ['field_name' => 'normalized_power',               'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                34 => ['field_name' => 'left_right_balance',             'scale' => 1,         'offset' => 0, 'units' => ''],
                35 => ['field_name' => 'first_length_index',             'scale' => 1,         'offset' => 0, 'units' => ''],
                37 => ['field_name' => 'avg_stroke_distance',            'scale' => 100,       'offset' => 0, 'units' => 'm'],
                38 => ['field_name' => 'swim_stroke',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                39 => ['field_name' => 'sub_sport',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                40 => ['field_name' => 'num_active_lengths',             'scale' => 1,         'offset' => 0, 'units' => 'lengths'],
                41 => ['field_name' => 'total_work',                     'scale' => 1,         'offset' => 0, 'units' => 'J'],
                57 => ['field_name' => 'time_in_hr_zone',                'scale' => 1000,      'offset' => 0, 'units' => 's'],
                60 => ['field_name' => 'time_in_power_zone',             'scale' => 1000,      'offset' => 0, 'units' => 's'],
                71 => ['field_name' => 'wkt_step_index',                 'scale' => 1,         'offset' => 0, 'units' => ''],
                77 => ['field_name' => 'avg_vertical_oscillation',       'scale' => 10,        'offset' => 0, 'units' => 'mm'],
                78 => ['field_name' => 'avg_stance_time_percent',        'scale' => 100,       'offset' => 0, 'units' => 'percent'],
                79 => ['field_name' => 'avg_stance_time',                'scale' => 10,        'offset' => 0, 'units' => 'ms'],
                80 => ['field_name' => 'avg_fractional_cadence',         'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                81 => ['field_name' => 'max_fractional_cadence',         'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                82 => ['field_name' => 'total_fractional_cycles',        'scale' => 128,       'offset' => 0, 'units' => 'cycles'],
                91 => ['field_name' => 'avg_left_torque_effectiveness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                92 => ['field_name' => 'avg_right_torque_effectiveness', 'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                93 => ['field_name' => 'avg_left_pedal_smoothness',      'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                94 => ['field_name' => 'avg_right_pedal_smoothness',     'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                95 => ['field_name' => 'avg_combined_pedal_smoothness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                98 => ['field_name' => 'time_standing',                  'scale' => 1000,      'offset' => 0, 'units' => 's'],
                99 => ['field_name' => 'stand_count',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                100 => ['field_name' => 'avg_left_pco',                  'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                101 => ['field_name' => 'avg_right_pco',                 'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                102 => ['field_name' => 'avg_left_power_phase',          'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                103 => ['field_name' => 'avg_left_power_phase_peak',     'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                104 => ['field_name' => 'avg_right_power_phase',         'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                105 => ['field_name' => 'avg_right_power_phase_peak',    'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                106 => ['field_name' => 'avg_power_position',            'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                107 => ['field_name' => 'max_power_position',            'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                108 => ['field_name' => 'avg_cadence_position',          'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                109 => ['field_name' => 'max_cadence_position',          'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                253 => ['field_name' => 'timestamp',                     'scale' => 1,         'offset' => 0, 'units' => 's'],
                254 => ['field_name' => 'message_index',                 'scale' => 1,         'offset' => 0, 'units' => '']
            ]
        ],
        
        20 => [
            'mesg_name' => 'record', 'field_defns' => [
                0 => ['field_name' => 'position_lat',                      'scale' => 1,         'offset' => 0,   'units' => 'semicircles'],
                1 => ['field_name' => 'position_long',                     'scale' => 1,         'offset' => 0,   'units' => 'semicircles'],
                2 => ['field_name' => 'altitude',                          'scale' => 5,         'offset' => 500, 'units' => 'm'],
                3 => ['field_name' => 'heart_rate',                        'scale' => 1,         'offset' => 0,   'units' => 'bpm'],
                4 => ['field_name' => 'cadence',                           'scale' => 1,         'offset' => 0,   'units' => 'rpm'],
                5 => ['field_name' => 'distance',                          'scale' => 100,       'offset' => 0,   'units' => 'm'],
                6 => ['field_name' => 'speed',                             'scale' => 1000,      'offset' => 0,   'units' => 'm/s'],
                7 => ['field_name' => 'power',                             'scale' => 1,         'offset' => 0,   'units' => 'watts'],
                8 => ['field_name' => 'compressed_speed_distance',         'scale' => 100,       'offset' => 0,   'units' => 'm/s,m'],
                9 => ['field_name' => 'grade',                             'scale' => 100,       'offset' => 0,   'units' => 'percent'],
                10 => ['field_name' => 'resistance',                       'scale' => 1,         'offset' => 0,   'units' => ''],
                11 => ['field_name' => 'time_from_course',                 'scale' => 1000,      'offset' => 0,   'units' => 's'],
                12 => ['field_name' => 'cycle_length',                     'scale' => 100,       'offset' => 0,   'units' => 'm'],
                13 => ['field_name' => 'temperature',                      'scale' => 1,         'offset' => 0,   'units' => 'C'],
                17 => ['field_name' => 'speed_1s',                         'scale' => 16,        'offset' => 0,   'units' => 'm/s'],
                18 => ['field_name' => 'cycles',                           'scale' => 1,         'offset' => 0,   'units' => 'cycles'],
                19 => ['field_name' => 'total_cycles',                     'scale' => 1,         'offset' => 0,   'units' => 'cycles'],
                28 => ['field_name' => 'compressed_accumulated_power',     'scale' => 1,         'offset' => 0,   'units' => 'watts'],
                29 => ['field_name' => 'accumulated_power',                'scale' => 1,         'offset' => 0,   'units' => 'watts'],
                30 => ['field_name' => 'left_right_balance',               'scale' => 1,         'offset' => 0,   'units' => ''],
                31 => ['field_name' => 'gps_accuracy',                     'scale' => 1,         'offset' => 0,   'units' => 'm'],
                32 => ['field_name' => 'vertical_speed',                   'scale' => 1000,      'offset' => 0,   'units' => 'm/s'],
                33 => ['field_name' => 'calories',                         'scale' => 1,         'offset' => 0,   'units' => 'kcal'],
                39 => ['field_name' => 'vertical_oscillation',             'scale' => 10,        'offset' => 0,   'units' => 'mm'],
                40 => ['field_name' => 'stance_time_percent',              'scale' => 100,       'offset' => 0,   'units' => 'percent'],
                41 => ['field_name' => 'stance_time',                      'scale' => 10,        'offset' => 0,   'units' => 'ms'],
                42 => ['field_name' => 'activity_type',                    'scale' => 1,         'offset' => 0,   'units' => ''],
                43 => ['field_name' => 'left_torque_effectiveness',        'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                44 => ['field_name' => 'right_torque_effectiveness',       'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                45 => ['field_name' => 'left_pedal_smoothness',            'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                46 => ['field_name' => 'right_pedal_smoothness',           'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                47 => ['field_name' => 'combined_pedal_smoothness',        'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                48 => ['field_name' => 'time128',                          'scale' => 128,       'offset' => 0,   'units' => 's'],
                49 => ['field_name' => 'stroke_type',                      'scale' => 1,         'offset' => 0,   'units' => ''],
                50 => ['field_name' => 'zone',                             'scale' => 1,         'offset' => 0,   'units' => ''],
                51 => ['field_name' => 'ball_speed',                       'scale' => 100,       'offset' => 0,   'units' => 'm/s'],
                52 => ['field_name' => 'cadence256',                       'scale' => 256,       'offset' => 0,   'units' => 'rpm'],
                53 => ['field_name' => 'fractional_cadence',               'scale' => 128,       'offset' => 0,   'units' => 'rpm'],
                54 => ['field_name' => 'total_hemoglobin_conc',            'scale' => 100,       'offset' => 0,   'units' => 'g/dL'],
                55 => ['field_name' => 'total_hemoglobin_conc_min',        'scale' => 100,       'offset' => 0,   'units' => 'g/dL'],
                56 => ['field_name' => 'total_hemoglobin_conc_max',        'scale' => 100,       'offset' => 0,   'units' => 'g/dL'],
                57 => ['field_name' => 'saturated_hemoglobin_percent',     'scale' => 10,        'offset' => 0,   'units' => '%'],
                58 => ['field_name' => 'saturated_hemoglobin_percent_min', 'scale' => 10,        'offset' => 0,   'units' => '%'],
                59 => ['field_name' => 'saturated_hemoglobin_percent_max', 'scale' => 10,        'offset' => 0,   'units' => '%'],
                62 => ['field_name' => 'device_index',                     'scale' => 1,         'offset' => 0,   'units' => ''],
                67 => ['field_name' => 'left_pco',                         'scale' => 1,         'offset' => 0,   'units' => 'mm'],
                68 => ['field_name' => 'right_pco',                        'scale' => 1,         'offset' => 0,   'units' => 'mm'],
                69 => ['field_name' => 'left_power_phase',                 'scale' => 0.7111111, 'offset' => 0,   'units' => 'degrees'],
                70 => ['field_name' => 'left_power_phase_peak',            'scale' => 0.7111111, 'offset' => 0,   'units' => 'degrees'],
                71 => ['field_name' => 'right_power_phase',                'scale' => 0.7111111, 'offset' => 0,   'units' => 'degrees'],
                72 => ['field_name' => 'right_power_phase_peak',           'scale' => 0.7111111, 'offset' => 0,   'units' => 'degrees'],
                73 => ['field_name' => 'enhanced_speed',                   'scale' => 1000,      'offset' => 0,   'units' => 'm/s'],
                78 => ['field_name' => 'enhanced_altitude',                'scale' => 5,         'offset' => 500, 'units' => 'm'],
                81 => ['field_name' => 'battery_soc',                      'scale' => 2,         'offset' => 0,   'units' => 'percent'],
                82 => ['field_name' => 'motor_power',                      'scale' => 1,         'offset' => 0,   'units' => 'watts'],
                83 => ['field_name' => 'vertical_ratio',                   'scale' => 100,       'offset' => 0,   'units' => 'percent'],
                84 => ['field_name' => 'stance_time_balance',              'scale' => 100,       'offset' => 0,   'units' => 'percent'],
                85 => ['field_name' => 'step_length',                      'scale' => 10,        'offset' => 0,   'units' => 'mm'],
                253 => ['field_name' => 'timestamp',                       'scale' => 1,         'offset' => 0,   'units' => 's']
            ]
        ],
        
        21 => [
            'mesg_name' => 'event', 'field_defns' => [
                0 => ['field_name' => 'event',       'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'event_type',  'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'data',        'scale' => 1, 'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'event_group', 'scale' => 1, 'offset' => 0, 'units' => ''],
                253 => ['field_name' => 'timestamp', 'scale' => 1, 'offset' => 0, 'units' => 's']
            ]
        ],
        
        23 => [
            'mesg_name' => 'device_info', 'field_defns' => [
                0 => ['field_name' => 'device_index',           'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'device_type',            'scale' => 1, 'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'manufacturer',           'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'serial_number',          'scale' => 1, 'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'product',                'scale' => 1, 'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'software_version',       'scale' => 1, 'offset' => 0, 'units' => ''],
                6 => ['field_name' => 'hardware_version',       'scale' => 1, 'offset' => 0, 'units' => ''],
                7 => ['field_name' => 'cum_operating_time',     'scale' => 1, 'offset' => 0, 'units' => ''],
                10 => ['field_name' => 'battery_voltage',       'scale' => 1, 'offset' => 0, 'units' => ''],
                11 => ['field_name' => 'battery_status',        'scale' => 1, 'offset' => 0, 'units' => ''],
                20 => ['field_name' => 'ant_transmission_type', 'scale' => 1, 'offset' => 0, 'units' => ''],
                21 => ['field_name' => 'ant_device_number',     'scale' => 1, 'offset' => 0, 'units' => ''],
                22 => ['field_name' => 'ant_network',           'scale' => 1, 'offset' => 0, 'units' => ''],
                25 => ['field_name' => 'source_type',           'scale' => 1, 'offset' => 0, 'units' => ''],
                253 => ['field_name' => 'timestamp',            'scale' => 1, 'offset' => 0, 'units' => 's']
            ]
        ],
        
        34 => [
            'mesg_name' => 'activity', 'field_defns' => [
                0 => ['field_name' => 'total_timer_time', 'scale' => 1000, 'offset' => 0, 'units' => 's'],
                1 => ['field_name' => 'num_sessions',     'scale' => 1,    'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'type',             'scale' => 1,    'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'event',            'scale' => 1,    'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'event_type',       'scale' => 1,    'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'local_timestamp',  'scale' => 1,    'offset' => 0, 'units' => ''],
                6 => ['field_name' => 'event_group',      'scale' => 1,    'offset' => 0, 'units' => ''],
                253 => ['field_name' => 'timestamp',      'scale' => 1,    'offset' => 0, 'units' => 's']
            ]
        ],
        
        49 => [
            'mesg_name' => 'file_creator', 'field_defns' => [
                0 => ['field_name' => 'software_version', 'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'hardware_version', 'scale' => 1, 'offset' => 0, 'units' => '']
            ]
        ],
        
        78 => [
            'mesg_name' => 'hrv', 'field_defns' => [
                0 => ['field_name' => 'time', 'scale' => 1000, 'offset' => 0, 'units' => 's']
            ]
        ],
        
        101 => [
            'mesg_name' => 'length', 'field_defns' => [
                0 => ['field_name' => 'event',                'scale' => 1,    'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'event_type',           'scale' => 1,    'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'start_time',           'scale' => 1,    'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'total_elapsed_time',   'scale' => 1000, 'offset' => 0, 'units' => 's'],
                4 => ['field_name' => 'total_timer_time',     'scale' => 1000, 'offset' => 0, 'units' => 's'],
                5 => ['field_name' => 'total_strokes',        'scale' => 1,    'offset' => 0, 'units' => 'strokes'],
                6 => ['field_name' => 'avg_speed',            'scale' => 1000, 'offset' => 0, 'units' => 'm/s'],
                7 => ['field_name' => 'swim_stroke',          'scale' => 1,    'offset' => 0, 'units' => 'swim_stroke'],
                9 => ['field_name' => 'avg_swimming_cadence', 'scale' => 1,    'offset' => 0, 'units' => 'strokes/min'],
                10 => ['field_name' => 'event_group',         'scale' => 1,    'offset' => 0, 'units' => ''],
                11 => ['field_name' => 'total_calories',      'scale' => 1,    'offset' => 0, 'units' => 'kcal'],
                12 => ['field_name' => 'length_type',         'scale' => 1,    'offset' => 0, 'units' => ''],
                253 => ['field_name' => 'timestamp',          'scale' => 1,    'offset' => 0, 'units' => 's'],
                254 => ['field_name' => 'message_index',      'scale' => 1,    'offset' => 0, 'units' => '']
            ]
        ],
        
        // 'event_timestamp' and 'event_timestamp_12' should have scale of 1024 but due to floating point rounding errors.
        // These are manually divided by 1024 later in the processHrMessages() function.
        132 => [
            'mesg_name' => 'hr', 'field_defns' => [
                0 => ['field_name' => 'fractional_timestamp', 'scale' => 32768, 'offset' => 0, 'units' => 's'],
                1 => ['field_name' => 'time256',              'scale' => 256,   'offset' => 0, 'units' => 's'],
                6 => ['field_name' => 'filtered_bpm',         'scale' => 1,     'offset' => 0, 'units' => 'bpm'],
                9 => ['field_name' => 'event_timestamp',      'scale' => 1,     'offset' => 0, 'units' => 's'],
                10 => ['field_name' => 'event_timestamp_12',  'scale' => 1,     'offset' => 0, 'units' => 's'],
                253 => ['field_name' => 'timestamp',          'scale' => 1,     'offset' => 0, 'units' => 's']
            ]
        ],
        
        142 => [
            'mesg_name' => 'segment_lap', 'field_defns' => [
                0 => ['field_name' => 'event',                           'scale' => 1,         'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'event_type',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'start_time',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'start_position_lat',              'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                4 => ['field_name' => 'start_position_long',             'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                5 => ['field_name' => 'end_position_lat',                'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                6 => ['field_name' => 'end_position_long',               'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                7 => ['field_name' => 'total_elapsed_time',              'scale' => 1000,      'offset' => 0, 'units' => 's'],
                8 => ['field_name' => 'total_timer_time',                'scale' => 1000,      'offset' => 0, 'units' => 's'],
                9 => ['field_name' => 'total_distance',                  'scale' => 100,       'offset' => 0, 'units' => 'm'],
                10 => ['field_name' => 'total_cycles',                   'scale' => 1,         'offset' => 0, 'units' => 'cycles'],
                11 => ['field_name' => 'total_calories',                 'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                12 => ['field_name' => 'total_fat_calories',             'scale' => 1,         'offset' => 0, 'units' => 'kcal'],
                13 => ['field_name' => 'avg_speed',                      'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                14 => ['field_name' => 'max_speed',                      'scale' => 1000,      'offset' => 0, 'units' => 'm/s'],
                15 => ['field_name' => 'avg_heart_rate',                 'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                16 => ['field_name' => 'max_heart_rate',                 'scale' => 1,         'offset' => 0, 'units' => 'bpm'],
                17 => ['field_name' => 'avg_cadence',                    'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                18 => ['field_name' => 'max_cadence',                    'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                19 => ['field_name' => 'avg_power',                      'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                20 => ['field_name' => 'max_power',                      'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                21 => ['field_name' => 'total_ascent',                   'scale' => 1,         'offset' => 0, 'units' => 'm'],
                22 => ['field_name' => 'total_descent',                  'scale' => 1,         'offset' => 0, 'units' => 'm'],
                23 => ['field_name' => 'sport',                          'scale' => 1,         'offset' => 0, 'units' => ''],
                24 => ['field_name' => 'event_group',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                25 => ['field_name' => 'nec_lat',                        'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                26 => ['field_name' => 'nec_long',                       'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                27 => ['field_name' => 'swc_lat',                        'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                28 => ['field_name' => 'swc_long',                       'scale' => 1,         'offset' => 0, 'units' => 'semicircles'],
                29 => ['field_name' => 'name',                           'scale' => 1,         'offset' => 0, 'units' => ''],
                30 => ['field_name' => 'normalized_power',               'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                31 => ['field_name' => 'left_right_balance',             'scale' => 1,         'offset' => 0, 'units' => ''],
                32 => ['field_name' => 'sub_sport',                      'scale' => 1,         'offset' => 0, 'units' => ''],
                33 => ['field_name' => 'total_work',                     'scale' => 1,         'offset' => 0, 'units' => 'J'],
                58 => ['field_name' => 'sport_event',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                59 => ['field_name' => 'avg_left_torque_effectiveness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                60 => ['field_name' => 'avg_right_torque_effectiveness', 'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                61 => ['field_name' => 'avg_left_pedal_smoothness',      'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                62 => ['field_name' => 'avg_right_pedal_smoothness',     'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                63 => ['field_name' => 'avg_combined_pedal_smoothness',  'scale' => 2,         'offset' => 0, 'units' => 'percent'],
                64 => ['field_name' => 'status',                         'scale' => 1,         'offset' => 0, 'units' => ''],
                65 => ['field_name' => 'uuid',                           'scale' => 1,         'offset' => 0, 'units' => ''],
                66 => ['field_name' => 'avg_fractional_cadence',         'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                67 => ['field_name' => 'max_fractional_cadence',         'scale' => 128,       'offset' => 0, 'units' => 'rpm'],
                68 => ['field_name' => 'total_fractional_cycles',        'scale' => 128,       'offset' => 0, 'units' => 'cycles'],
                69 => ['field_name' => 'front_gear_shift_count',         'scale' => 1,         'offset' => 0, 'units' => ''],
                70 => ['field_name' => 'rear_gear_shift_count',          'scale' => 1,         'offset' => 0, 'units' => ''],
                71 => ['field_name' => 'time_standing',                  'scale' => 1000,      'offset' => 0, 'units' => 's'],
                72 => ['field_name' => 'stand_count',                    'scale' => 1,         'offset' => 0, 'units' => ''],
                73 => ['field_name' => 'avg_left_pco',                   'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                74 => ['field_name' => 'avg_right_pco',                  'scale' => 1,         'offset' => 0, 'units' => 'mm'],
                75 => ['field_name' => 'avg_left_power_phase',           'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                76 => ['field_name' => 'avg_left_power_phase_peak',      'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                77 => ['field_name' => 'avg_right_power_phase',          'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                78 => ['field_name' => 'avg_right_power_phase_peak',     'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees'],
                79 => ['field_name' => 'avg_power_position',             'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                80 => ['field_name' => 'max_power_position',             'scale' => 1,         'offset' => 0, 'units' => 'watts'],
                81 => ['field_name' => 'avg_cadence_position',           'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                82 => ['field_name' => 'max_cadence_position',           'scale' => 1,         'offset' => 0, 'units' => 'rpm'],
                253 => ['field_name' => 'timestamp',                     'scale' => 1,         'offset' => 0, 'units' => 's'],
                254 => ['field_name' => 'message_index',                 'scale' => 1,         'offset' => 0, 'units' => '']
            ]
        ],
        
        206 => [
            'mesg_name' => 'field_description', 'field_defns' => [
                0 => ['field_name' => 'developer_data_index',    'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'field_definition_number', 'scale' => 1, 'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'fit_base_type_id',        'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'field_name',              'scale' => 1, 'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'array',                   'scale' => 1, 'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'components',              'scale' => 1, 'offset' => 0, 'units' => ''],
                6 => ['field_name' => 'scale',                   'scale' => 1, 'offset' => 0, 'units' => ''],
                7 => ['field_name' => 'offset',                  'scale' => 1, 'offset' => 0, 'units' => ''],
                8 => ['field_name' => 'units',                   'scale' => 1, 'offset' => 0, 'units' => ''],
                9 => ['field_name' => 'bits',                    'scale' => 1, 'offset' => 0, 'units' => ''],
                10 => ['field_name' => 'accumulate',             'scale' => 1, 'offset' => 0, 'units' => ''],
                13 => ['field_name' => 'fit_base_unit_id',       'scale' => 1, 'offset' => 0, 'units' => ''],
                14 => ['field_name' => 'native_mesg_num',        'scale' => 1, 'offset' => 0, 'units' => ''],
                15 => ['field_name' => 'native_field_num',       'scale' => 1, 'offset' => 0, 'units' => '']
            ]
        ],
        
        207 => [
            'mesg_name' => 'developer_data_id', 'field_defns' => [
                0 => ['field_name' => 'developer_id',         'scale' => 1, 'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'application_id',       'scale' => 1, 'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'manufacturer_id',      'scale' => 1, 'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'developer_data_index', 'scale' => 1, 'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'application_version',  'scale' => 1, 'offset' => 0, 'units' => '']
            ]
        ],
        
        258 => [
            'mesg_name' => 'dive_settings', 'field_defns' => [
                0 => ['field_name' => 'name',                     'scale' => 1,   'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'model',                    'scale' => 1,   'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'gf_low',                   'scale' => 1,   'offset' => 0, 'units' => 'percent'],
                3 => ['field_name' => 'gf_high',                  'scale' => 1,   'offset' => 0, 'units' => 'percent'],
                4 => ['field_name' => 'water_type',               'scale' => 1,   'offset' => 0, 'units' => ''],
                5 => ['field_name' => 'water_density',            'scale' => 1,   'offset' => 0, 'units' => 'kg/m^3'],
                6 => ['field_name' => 'po2_warn',                 'scale' => 100, 'offset' => 0, 'units' => 'percent'],
                7 => ['field_name' => 'po2_critical',             'scale' => 100, 'offset' => 0, 'units' => 'percent'],
                8 => ['field_name' => 'po2_deco',                 'scale' => 100, 'offset' => 0, 'units' => 'percent'],
                9 => ['field_name' => 'safety_stop_enabled',      'scale' => 1,   'offset' => 0, 'units' => ''],
                10 => ['field_name' => 'bottom_depth',            'scale' => 1,   'offset' => 0, 'units' => ''],
                11 => ['field_name' => 'bottom_time',             'scale' => 1,   'offset' => 0, 'units' => ''],
                12 => ['field_name' => 'apnea_countdown_enabled', 'scale' => 1,   'offset' => 0, 'units' => ''],
                13 => ['field_name' => 'apnea_countdown_time',    'scale' => 1,   'offset' => 0, 'units' => ''],
                14 => ['field_name' => 'backlight_mode',          'scale' => 1,   'offset' => 0, 'units' => ''],
                15 => ['field_name' => 'backlight_brightness',    'scale' => 1,   'offset' => 0, 'units' => ''],
                16 => ['field_name' => 'backlight_timeout',       'scale' => 1,   'offset' => 0, 'units' => ''],
                17 => ['field_name' => 'repeat_dive_interval',    'scale' => 1,   'offset' => 0, 'units' => 's'],
                18 => ['field_name' => 'safety_stop_time',        'scale' => 1,   'offset' => 0, 'units' => 's'],
                19 => ['field_name' => 'heart_rate_source_type',  'scale' => 1,   'offset' => 0, 'units' => ''],
                20 => ['field_name' => 'heart_rate_source',       'scale' => 1,   'offset' => 0, 'units' => ''],
                254 => ['field_name' => 'message_index',          'scale' => 1,   'offset' => 0, 'units' => '']
              ]
          ],
        
        259 => [
            'mesg_name' => 'dive_gas', 'field_defns' => [
                0 => ['field_name' => 'helium_content',  'scale' => 1, 'offset' => 0, 'units' => 'percent'],
                1 => ['field_name' => 'oxygen_content',  'scale' => 1, 'offset' => 0, 'units' => 'percent'],
                2 => ['field_name' => 'status',          'scale' => 1, 'offset' => 0, 'units' => ''],
                254 => ['field_name' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '']
              ]
          ],
        
        262 => [
            'mesg_name' => 'dive_alarm', 'field_defns' => [
                0 => ['field_name' => 'depth',           'scale' => 1000, 'offset' => 0, 'units' => 'm'],
                1 => ['field_name' => 'time',            'scale' => 1,    'offset' => 0, 'units' => 's'],
                2 => ['field_name' => 'enabled',         'scale' => 1,    'offset' => 0, 'units' => ''],
                3 => ['field_name' => 'alarm_type',      'scale' => 1,    'offset' => 0, 'units' => ''],
                4 => ['field_name' => 'sound',           'scale' => 1,    'offset' => 0, 'units' => ''],
                254 => ['field_name' => 'message_index', 'scale' => 1,    'offset' => 0, 'units' => '']
              ]
          ],
        
        268 => [
            'mesg_name' => 'dive_summary', 'field_defns' => [
                0 => ['field_name' => 'reference_mesg',   'scale' => 1,    'offset' => 0, 'units' => ''],
                1 => ['field_name' => 'reference_index',  'scale' => 1,    'offset' => 0, 'units' => ''],
                2 => ['field_name' => 'avg_depth',        'scale' => 1000, 'offset' => 0, 'units' => 'm'],
                3 => ['field_name' => 'max_depth',        'scale' => 1000, 'offset' => 0, 'units' => 'm'],
                4 => ['field_name' => 'surface_interval', 'scale' => 1,    'offset' => 0, 'units' => 's'],
                5 => ['field_name' => 'start_cns',        'scale' => 1,    'offset' => 0, 'units' => 'percent'],
                6 => ['field_name' => 'end_cns',          'scale' => 1,    'offset' => 0, 'units' => 'percent'],
                7 => ['field_name' => 'start_n2',         'scale' => 1,    'offset' => 0, 'units' => 'percent'],
                8 => ['field_name' => 'end_n2',           'scale' => 1,    'offset' => 0, 'units' => 'percent'],
                9 => ['field_name' => 'o2_toxicity',      'scale' => 1,    'offset' => 0, 'units' => 'OTUs'],
                10 => ['field_name' => 'dive_number',     'scale' => 1,    'offset' => 0, 'units' => ''],
                11 => ['field_name' => 'bottom_time',     'scale' => 1000, 'offset' => 0, 'units' => 's'],
                253 => ['field_name' => 'timestamp',      'scale' => 1,    'offset' => 0, 'units' => 's']
              ]
          ]
    ];

    // PHP Constructor - called when an object of the class is instantiated.
    public function __construct($file_path_or_data, $options = null)
    {
        if( isset( $options['input_is_data'] ) ){
            $this->file_contents = $file_path_or_data;
        }else{
            if (empty($file_path_or_data)) {
                throw new \Exception('phpFITFileAnalysis->__construct(): file_path is empty!');
            }
            if (!file_exists($file_path_or_data)) {
                throw new \Exception('phpFITFileAnalysis->__construct(): file \''.$file_path_or_data.'\' does not exist!');
            }
            /**
             * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
             * 3.3 FIT File Structure
             * Header . Data Records . CRC
             */
            $this->file_contents = file_get_contents($file_path_or_data);  // Read the entire file into a string

        }

        $this->options = $options;
        if (isset($options['garmin_timestamps']) && $options['garmin_timestamps'] == true) {
            $this->garmin_timestamps = true;
        }
        $this->options['overwrite_with_dev_data'] = false;
        if (isset($this->options['overwrite_with_dev_data']) && $this->options['overwrite_with_dev_data'] == true) {
            $this->options['overwrite_with_dev_data'] = true;
        }
        $this->php_trader_ext_loaded = extension_loaded('trader');
        
        // Process the file contents.
        $this->readHeader();
        $this->readDataRecords();
        $this->oneElementArrays();
        
        // Process HR messages
        $this->processHrMessages();
        
        // Handle options.
        $this->fixData($this->options);
        $this->setUnits($this->options);
    }
    
    /**
     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
     * Table 3-1. Byte Description of File Header
     */
    private function readHeader()
    {
        $header_size = unpack('C1header_size', substr($this->file_contents, $this->file_pointer, 1))['header_size'];
        $this->file_pointer++;
        
        if ($header_size != 12 && $header_size != 14) {
            throw new \Exception('phpFITFileAnalysis->readHeader(): not a valid header size!');
        }
        
        $header_fields = 'C1protocol_version/' .
            'v1profile_version/' .
            'V1data_size/' .
            'C4data_type';
        if ($header_size > 12) {
            $header_fields .= '/v1crc';
        }
        $this->file_header = unpack($header_fields, substr($this->file_contents, $this->file_pointer, $header_size - 1));
        $this->file_header['header_size'] = $header_size;
            
        $this->file_pointer += $this->file_header['header_size'] - 1;
        
        $file_extension = sprintf('%c%c%c%c', $this->file_header['data_type1'], $this->file_header['data_type2'], $this->file_header['data_type3'], $this->file_header['data_type4']);
        
        if ($file_extension != '.FIT' || $this->file_header['data_size'] <= 0) {
            throw new \Exception('phpFITFileAnalysis->readHeader(): not a valid FIT file!');
        }
        
        if (strlen($this->file_contents) - $header_size - 2 !== $this->file_header['data_size']) {
            // Overwrite the data_size. Seems to be incorrect if there are buffered messages e.g. HR records.
            $this->file_header['data_size'] = $this->file_header['crc'] - $header_size + 2;
        }
    }
    
    /**
     * Reads the remainder of $this->file_contents and store the data in the $this->data_mesgs array.
     */
    private function readDataRecords()
    {
        $record_header_byte = 0;
        $message_type = 0;
        $developer_data_flag = 0;
        $local_mesg_type = 0;
        $previousTS = 0;
        
        while ($this->file_header['header_size'] + $this->file_header['data_size'] > $this->file_pointer) {
            $record_header_byte = ord(substr($this->file_contents, $this->file_pointer, 1));
            $this->file_pointer++;
            
            $compressedTimestamp = false;
            $tsOffset = 0;
            /**
             * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 2.2.pdf
             * Table 4-1. Normal Header Bit Field Description
             */
            if (($record_header_byte >> 7) & 1) {  // Check that it's a normal header
                // Header with compressed timestamp
                $message_type = 0;  //always 0: DATA_MESSAGE
                $developer_data_flag = 0;  // always 0: DATA_MESSAGE
                $local_mesg_type = ($record_header_byte >> 5) & 3;  // bindec('0011') == 3
                $tsOffset = $record_header_byte & 31;
                $compressedTimestamp = true;
            } else {
                //Normal header
                $message_type = ($record_header_byte >> 6) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
                $developer_data_flag = ($record_header_byte >> 5) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
                $local_mesg_type = $record_header_byte & 15;  // bindec('1111') == 15
            }
            
            switch ($message_type) {
                case DEFINITION_MESSAGE:
                    /**
                     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
                     * Table 4-1. Normal Header Bit Field Description
                     */
                    $this->file_pointer++;  // Reserved - IGNORED
                    $architecture = ord(substr($this->file_contents, $this->file_pointer, 1));  // Architecture
                    $this->file_pointer++;
                    
                    $this->types = $this->endianness[$architecture];
                    
                    $global_mesg_num = ($architecture === 0) ? unpack('v1tmp', substr($this->file_contents, $this->file_pointer, 2))['tmp'] : unpack('n1tmp', substr($this->file_contents, $this->file_pointer, 2))['tmp'];
                    $this->file_pointer += 2;
                    
                    $num_fields = ord(substr($this->file_contents, $this->file_pointer, 1));
                    $this->file_pointer++;
                    
                    $field_definitions = [];
                    $total_size = 0;
                    for ($i=0; $i<$num_fields; ++$i) {
                        $field_definition_number = ord(substr($this->file_contents, $this->file_pointer, 1));
                        $this->file_pointer++;
                        $size = ord(substr($this->file_contents, $this->file_pointer, 1));
                        $this->file_pointer++;
                        $base_type = ord(substr($this->file_contents, $this->file_pointer, 1));
                        $this->file_pointer++;
                        
                        $field_definitions[] = ['field_definition_number' => $field_definition_number, 'size' => $size, 'base_type' => $base_type];
                        $total_size += $size;
                    }
                    
                    $num_dev_fields = 0;
                    $dev_field_definitions = [];
                    if ($developer_data_flag === 1) {
                        $num_dev_fields = ord(substr($this->file_contents, $this->file_pointer, 1));
                        $this->file_pointer++;
                        
                        for ($i=0; $i<$num_dev_fields; ++$i) {
                            $field_definition_number = ord(substr($this->file_contents, $this->file_pointer, 1));
                            $this->file_pointer++;
                            $size = ord(substr($this->file_contents, $this->file_pointer, 1));
                            $this->file_pointer++;
                            $developer_data_index = ord(substr($this->file_contents, $this->file_pointer, 1));
                            $this->file_pointer++;
                            
                            $dev_field_definitions[] = ['field_definition_number' => $field_definition_number, 'size' => $size, 'developer_data_index' => $developer_data_index];
                            $total_size += $size;
                        }
                    }
                    
                    $this->defn_mesgs[$local_mesg_type] = [
                            'global_mesg_num' => $global_mesg_num,
                            'num_fields' => $num_fields,
                            'field_defns' => $field_definitions,
                            'num_dev_fields' => $num_dev_fields,
                            'dev_field_definitions' => $dev_field_definitions,
                            'total_size' => $total_size
                        ];
                    $this->defn_mesgs_all[] = [
                            'global_mesg_num' => $global_mesg_num,
                            'num_fields' => $num_fields,
                            'field_defns' => $field_definitions,
                            'num_dev_fields' => $num_dev_fields,
                            'dev_field_definitions' => $dev_field_definitions,
                            'total_size' => $total_size
                        ];
                    break;
                
                case DATA_MESSAGE:
                    // Check that we have information on the Data Message.
                    if (isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']])) {
                        $tmp_record_array = [];  // Temporary array to store Record data message pieces
                        $tmp_value = null;  // Placeholder for value for checking before inserting into the tmp_record_array
                        
                        foreach ($this->defn_mesgs[$local_mesg_type]['field_defns'] as $field_defn) {
                            // Check that we have information on the Field Definition and a valid base type exists.
                            if (isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]) && isset($this->types[$field_defn['base_type']])) {
                                // Check if it's an invalid value for the type
                                $tmp_value = unpack($this->types[$field_defn['base_type']]['format'], substr($this->file_contents, $this->file_pointer, $field_defn['size']))['tmp'];
                                if ($tmp_value !== $this->invalid_values[$field_defn['base_type']] ||
                                   $this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 132) {
                                    // If it's a timestamp, compensate between different in FIT and Unix timestamp epochs
                                    if ($field_defn['field_definition_number'] === 253 && !$this->garmin_timestamps) {
                                        $tmp_value += FIT_UNIX_TS_DIFF;
                                    }
                                    
                                    // If it's a Record data message, store all the pieces in the temporary array as the timestamp may not be first...
                                    if ($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20) {
                                        $tmp_record_array[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']] = $tmp_value / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                    } elseif ($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 206) {  // Developer Data
                                        $tmp_record_array[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']] = $tmp_value;
                                    } else {
                                        if ($field_defn['base_type'] === 7) {  // Handle strings appropriately
                                            $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = filter_var($tmp_value, FILTER_SANITIZE_STRING);
                                        } else {
                                            // Handle arrays
                                            if ($field_defn['size'] !== $this->types[$field_defn['base_type']]['bytes']) {
                                                $tmp_array = [];
                                                $num_vals = $field_defn['size'] / $this->types[$field_defn['base_type']]['bytes'];
                                                for ($i=0; $i<$num_vals; ++$i) {
                                                    $tmp_array[] = unpack($this->types[$field_defn['base_type']]['format'], substr($this->file_contents, $this->file_pointer + ($i * $this->types[$field_defn['base_type']]['bytes']), $field_defn['size']))['tmp']/ $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                                }
                                                $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = $tmp_array;
                                            } else {
                                                $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = $tmp_value / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                            }
                                        }
                                    }
                                }
                            }
                            $this->file_pointer += $field_defn['size'];
                        }
                        
                        // Handle Developer Data
                        if ($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 206) {
                            $developer_data_index = $tmp_record_array['developer_data_index'];
                            $field_definition_number = $tmp_record_array['field_definition_number'];
                            unset($tmp_record_array['developer_data_index']);
                            unset($tmp_record_array['field_definition_number']);
                            if (isset($tmp_record_array['field_name'])) {  // Get rid of special/invalid characters after the null terminated string
                                $tmp_record_array['field_name'] = strtolower(implode('', explode("\0", $tmp_record_array['field_name'])));
                            }
                            if (isset($tmp_record_array['units'])) {
                                $tmp_record_array['units'] = strtolower(implode('', explode("\0", $tmp_record_array['units'])));
                            }
                            $this->dev_field_descriptions[$developer_data_index][$field_definition_number] = $tmp_record_array;
                            unset($tmp_record_array);
                        }
                        foreach ($this->defn_mesgs[$local_mesg_type]['dev_field_definitions'] as $field_defn) {
                            // Units
                            $this->data_mesgs['developer_data'][$this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['field_name']]['units'] = $this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['units'];
                            
                            // Data
                            $this->data_mesgs['developer_data'][$this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['field_name']]['data'][] = unpack($this->types[$this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['fit_base_type_id']]['format'], substr($this->file_contents, $this->file_pointer, $field_defn['size']))['tmp'];
                            
                            $this->file_pointer += $field_defn['size'];
                        }
                        
                        // Process the temporary array and load values into the public data messages array
                        if (!empty($tmp_record_array)) {
                            $timestamp = isset($this->data_mesgs['record']['timestamp']) ? max($this->data_mesgs['record']['timestamp']) + 1 : 0;
                            if ($compressedTimestamp) {
                                if ($previousTS === 0) {
                                    // This should not happen! Throw exception?
                                } else {
                                    $previousTS -= FIT_UNIX_TS_DIFF; // back to FIT timestamps epoch
                                    $fiveLsb = $previousTS & 0x1F;
                                    if ($tsOffset >= $fiveLsb) {
                                        // No rollover
                                        $timestamp = $previousTS - $fiveLsb + $tsOffset;
                                    } else {
                                        // Rollover
                                        $timestamp = $previousTS - $fiveLsb  + $tsOffset + 32;
                                    }
                                    $timestamp += FIT_UNIX_TS_DIFF; // back to Unix timestamps epoch
                                    $previousTS += FIT_UNIX_TS_DIFF;
                                }
                            } else {
                                if (isset($tmp_record_array['timestamp'])) {
                                    if ($tmp_record_array['timestamp'] > 0) {
                                        $timestamp = $tmp_record_array['timestamp'];
                                        $previousTS = $timestamp;
                                    }
                                    unset($tmp_record_array['timestamp']);
                                }
                            }
                            
                            $this->data_mesgs['record']['timestamp'][] = $timestamp;
                            
                            foreach ($tmp_record_array as $key => $value) {
                                if ($value !== null) {
                                    $this->data_mesgs['record'][$key][$timestamp] = $value;
                                }
                            }
                        }
                    } else {
                        $this->file_pointer += $this->defn_mesgs[$local_mesg_type]['total_size'];
                    }
            }
        }
        // Overwrite native FIT fields (e.g. Power, HR, Cadence, etc) with developer data by default
        if (!empty($this->dev_field_descriptions)) {
            foreach ($this->dev_field_descriptions as $developer_data_index) {
                foreach ($developer_data_index as $field_definition_number) {
                    if (isset($field_definition_number['native_field_num'])) {
                        if (isset($this->data_mesgs['record'][$field_definition_number['field_name']]) && !$this->options['overwrite_with_dev_data']) {
                            continue;
                        }
                        $this->data_mesgs['record'][$field_definition_number['field_name']] = $this->data_mesgs['developer_data'][$field_definition_number['field_name']]['data'];
                    }
                }
            }
        }
    }
    
    /**
     * If the user has requested for the data to be fixed, identify the missing keys for that data.
     */
    private function fixData($options)
    {
        // By default the constant FIT_UNIX_TS_DIFF will be added to timestamps, which have field type of date_time (or local_date_time).
        // Timestamp fields (field number == 253) converted after being unpacked in $this->readDataRecords().
        if (!$this->garmin_timestamps) {
            $date_times = [
                    ['message_name' => 'activity', 'field_name' => 'local_timestamp'],
                    ['message_name' => 'course_point', 'field_name' => 'timestamp'],
                    ['message_name' => 'file_id', 'field_name' => 'time_created'],
                    ['message_name' => 'goal', 'field_name' => 'end_date'],
                    ['message_name' => 'goal', 'field_name' => 'start_date'],
                    ['message_name' => 'lap', 'field_name' => 'start_time'],
                    ['message_name' => 'length', 'field_name' => 'start_time'],
                    ['message_name' => 'monitoring', 'field_name' => 'local_timestamp'],
                    ['message_name' => 'monitoring_info', 'field_name' => 'local_timestamp'],
                    ['message_name' => 'obdii_data', 'field_name' => 'start_timestamp'],
                    ['message_name' => 'schedule', 'field_name' => 'scheduled_time'],
                    ['message_name' => 'schedule', 'field_name' => 'time_created'],
                    ['message_name' => 'segment_lap', 'field_name' => 'start_time'],
                    ['message_name' => 'session', 'field_name' => 'start_time'],
                    ['message_name' => 'timestamp_correlation', 'field_name' => 'local_timestamp'],
                    ['message_name' => 'timestamp_correlation', 'field_name' => 'system_timestamp'],
                    ['message_name' => 'training_file', 'field_name' => 'time_created'],
                    ['message_name' => 'video_clip', 'field_name' => 'end_timestamp'],
                    ['message_name' => 'video_clip', 'field_name' => 'start_timestamp']
                ];
            
            foreach ($date_times as $date_time) {
                if (isset($this->data_mesgs[$date_time['message_name']][$date_time['field_name']])) {
                    if (is_array($this->data_mesgs[$date_time['message_name']][$date_time['field_name']])) {
                        foreach ($this->data_mesgs[$date_time['message_name']][$date_time['field_name']] as &$element) {
                            $element += FIT_UNIX_TS_DIFF;
                        }
                    } else {
                        $this->data_mesgs[$date_time['message_name']][$date_time['field_name']] += FIT_UNIX_TS_DIFF;
                    }
                }
            }
        }

        
        // Find messages that have been unpacked as unsigned integers that should be signed integers.
        // http://php.net/manual/en/function.pack.php - signed integers endianness is always machine dependent.
        // 131    s    signed short (always 16 bit, machine byte order)
        // 133    l    signed long (always 32 bit, machine byte order)
        // 142    q    signed long long (always 64 bit, machine byte order)
        foreach ($this->defn_mesgs_all as $mesg) {
            if (isset($this->data_mesg_info[$mesg['global_mesg_num']])) {
                $mesg_name = $this->data_mesg_info[$mesg['global_mesg_num']]['mesg_name'];
                

                foreach ($mesg['field_defns'] as $field) {
                    // Convert uint16 to sint16
                    if ($field['base_type'] === 131 && isset($this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'])) {
                        $field_name = $this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'];
                        if (isset($this->data_mesgs[$mesg_name][$field_name])) {
                            if (is_array($this->data_mesgs[$mesg_name][$field_name])) {
                                foreach ($this->data_mesgs[$mesg_name][$field_name] as &$v) {
                                    if (PHP_INT_SIZE === 8 && $v > 0x7FFF) {
                                        $v -= 0x10000;
                                    }
                                    if ($v > 0x7FFF) {
                                        $v = -1 * ($v - 0x7FFF);
                                    }
                                }
                            } elseif ($this->data_mesgs[$mesg_name][$field_name] > 0x7FFF) {
                                if (PHP_INT_SIZE === 8) {
                                    $this->data_mesgs[$mesg_name][$field_name] -= 0x10000;
                                }
                                $this->data_mesgs[$mesg_name][$field_name] = -1 * ($this->data_mesgs[$mesg_name][$field_name] - 0x7FFF);
                            }
                        }
                    } // Convert uint32 to sint32
                    elseif ($field['base_type'] === 133 && isset($this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'])) {
                        $field_name = $this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'];
                        if (isset($this->data_mesgs[$mesg_name][$field_name])) {
                            if (is_array($this->data_mesgs[$mesg_name][$field_name])) {
                                foreach ($this->data_mesgs[$mesg_name][$field_name] as &$v) {
                                    if (PHP_INT_SIZE === 8 && $v > 0x7FFFFFFF) {
                                        $v -= 0x100000000;
                                    }
                                    if ($v > 0x7FFFFFFF) {
                                        $v = -1 * ($v - 0x7FFFFFFF);
                                    }
                                }
                            } elseif ($this->data_mesgs[$mesg_name][$field_name] > 0x7FFFFFFF) {
                                if (PHP_INT_SIZE === 8) {
                                    $this->data_mesgs[$mesg_name][$field_name] -= 0x100000000;

                                }
                                if( $this->data_mesgs[$mesg_name][$field_name] > 0x7FFFFFFF ){
                                    $this->data_mesgs[$mesg_name][$field_name] = -1 * ($this->data_mesgs[$mesg_name][$field_name] - 0x7FFFFFFF);
                                }
                            }
                        }
                    } // Convert uint64 to sint64
                    elseif ($field['base_type'] === 142 && isset($this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'])) {
                        $field_name = $this->data_mesg_info[$mesg['global_mesg_num']]['field_defns'][$field['field_definition_number']]['field_name'];
                        if (isset($this->data_mesgs[$mesg_name][$field_name])) {
                            if (is_array($this->data_mesgs[$mesg_name][$field_name])) {
                                foreach ($this->data_mesgs[$mesg_name][$field_name] as &$v) {
                                    if (PHP_INT_SIZE === 8 && $v > 0x7FFFFFFFFFFFFFFF) {
                                        $v -= 0x10000000000000000;
                                    }
                                    if ($v > 0x7FFFFFFFFFFFFFFF) {
                                        $v = -1 * ($v - 0x7FFFFFFFFFFFFFFF);
                                    }
                                }
                            } elseif ($this->data_mesgs[$mesg_name][$field_name] > 0x7FFFFFFFFFFFFFFF) {
                                if (PHP_INT_SIZE === 8) {
                                    $this->data_mesgs[$mesg_name][$field_name] -= 0x10000000000000000;
                                }
                                $this->data_mesgs[$mesg_name][$field_name] = -1 * ($this->data_mesgs[$mesg_name][$field_name] - 0x7FFFFFFFFFFFFFFF);
                            }
                        }
                    }
                }
            }
        }
        
        // Remove duplicate timestamps
        if (isset($this->data_mesgs['record']['timestamp']) && is_array($this->data_mesgs['record']['timestamp'])) {
            $this->data_mesgs['record']['timestamp'] = array_unique($this->data_mesgs['record']['timestamp']);
        }
        
        // Return if no option set
        if (empty($options['fix_data']) && empty($options['data_every_second'])) {
            return;
        }
        
        // If $options['data_every_second'], then create timestamp array for every second from min to max
        if (!empty($options['data_every_second']) && !(is_string($options['data_every_second']) && strtolower($options['data_every_second']) === 'false')) {
            // If user has not specified the data to be fixed, assume all
            if (empty($options['fix_data'])) {
                $options['fix_data'] = ['all'];
            }
            
            $min_ts = min($this->data_mesgs['record']['timestamp']);
            $max_ts = max($this->data_mesgs['record']['timestamp']);
            unset($this->data_mesgs['record']['timestamp']);
            for ($i=$min_ts; $i<=$max_ts; ++$i) {
                $this->data_mesgs['record']['timestamp'][] = $i;
            }
        }
        
        // Check if valid option(s) provided
        array_walk($options['fix_data'], function (&$value) {
            $value = strtolower($value);
        });  // Make all lower-case.
        if (count(array_intersect(['all', 'cadence', 'distance', 'heart_rate', 'lat_lon', 'speed', 'power'], $options['fix_data'])) === 0) {
            throw new \Exception('phpFITFileAnalysis->fixData(): option not valid!');
        }
        
        $bCadence = $bDistance = $bHeartRate = $bLatitudeLongitude = $bSpeed = $bPower = false;
        if (in_array('all', $options['fix_data'])) {
            $bCadence = isset($this->data_mesgs['record']['cadence']);
            $bDistance = isset($this->data_mesgs['record']['distance']);
            $bHeartRate = isset($this->data_mesgs['record']['heart_rate']);
            $bLatitudeLongitude = isset($this->data_mesgs['record']['position_lat']) && isset($this->data_mesgs['record']['position_long']);
            $bSpeed = isset($this->data_mesgs['record']['speed']);
            $bPower = isset($this->data_mesgs['record']['power']);
        } else {
            if (isset($this->data_mesgs['record']['timestamp'])) {
                $count_timestamp = count($this->data_mesgs['record']['timestamp']);  // No point try to insert missing values if we know there aren't any.
                if (isset($this->data_mesgs['record']['cadence'])) {
                    $bCadence = (count($this->data_mesgs['record']['cadence']) === $count_timestamp) ? false : in_array('cadence', $options['fix_data']);
                }
                if (isset($this->data_mesgs['record']['distance'])) {
                    $bDistance = (count($this->data_mesgs['record']['distance']) === $count_timestamp) ? false : in_array('distance', $options['fix_data']);
                }
                if (isset($this->data_mesgs['record']['heart_rate'])) {
                    $bHeartRate = (count($this->data_mesgs['record']['heart_rate']) === $count_timestamp) ? false : in_array('heart_rate', $options['fix_data']);
                }
                if (isset($this->data_mesgs['record']['position_lat']) && isset($this->data_mesgs['record']['position_long'])) {
                    $bLatitudeLongitude = (count($this->data_mesgs['record']['position_lat']) === $count_timestamp
                        && count($this->data_mesgs['record']['position_long']) === $count_timestamp) ? false : in_array('lat_lon', $options['fix_data']);
                }
                if (isset($this->data_mesgs['record']['speed'])) {
                    $bSpeed = (count($this->data_mesgs['record']['speed']) === $count_timestamp) ? false : in_array('speed', $options['fix_data']);
                }
                if (isset($this->data_mesgs['record']['power'])) {
                    $bPower = (count($this->data_mesgs['record']['power']) === $count_timestamp) ? false : in_array('power', $options['fix_data']);
                }
            }
        }
        
        $missing_distance_keys = [];
        $missing_hr_keys = [];
        $missing_lat_keys = [];
        $missing_lon_keys = [];
        $missing_speed_keys = [];
        $missing_power_keys = [];
        
        foreach ($this->data_mesgs['record']['timestamp'] as $timestamp) {
            if ($bCadence) {  // Assumes all missing cadence values are zeros
                if (!isset($this->data_mesgs['record']['cadence'][$timestamp])) {
                    $this->data_mesgs['record']['cadence'][$timestamp] = 0;
                }
            }
            if ($bDistance) {
                if (!isset($this->data_mesgs['record']['distance'][$timestamp])) {
                    $missing_distance_keys[] = $timestamp;
                }
            }
            if ($bHeartRate) {
                if (!isset($this->data_mesgs['record']['heart_rate'][$timestamp])) {
                    $missing_hr_keys[] = $timestamp;
                }
            }
            if ($bLatitudeLongitude) {
                if (!isset($this->data_mesgs['record']['position_lat'][$timestamp])) {
                    $missing_lat_keys[] = $timestamp;
                }
                if (!isset($this->data_mesgs['record']['position_long'][$timestamp])) {
                    $missing_lon_keys[] = $timestamp;
                }
            }
            if ($bSpeed) {
                if (!isset($this->data_mesgs['record']['speed'][$timestamp])) {
                    $missing_speed_keys[] = $timestamp;
                }
            }
            if ($bPower) {
                if (!isset($this->data_mesgs['record']['power'][$timestamp])) {
                    $missing_power_keys[] = $timestamp;
                }
            }
        }
        
        if ($bCadence) {
            ksort($this->data_mesgs['record']['cadence']);  // no interpolation; zeros added earlier
        }
        if ($bDistance) {
            $this->interpolateMissingData($missing_distance_keys, $this->data_mesgs['record']['distance']);
        }
        if ($bHeartRate) {
            $this->interpolateMissingData($missing_hr_keys, $this->data_mesgs['record']['heart_rate']);
        }
        if ($bLatitudeLongitude) {
            $this->interpolateMissingData($missing_lat_keys, $this->data_mesgs['record']['position_lat']);
            $this->interpolateMissingData($missing_lon_keys, $this->data_mesgs['record']['position_long']);
        }
        if ($bSpeed) {
            $this->interpolateMissingData($missing_speed_keys, $this->data_mesgs['record']['speed']);
        }
        if ($bPower) {
            $this->interpolateMissingData($missing_power_keys, $this->data_mesgs['record']['power']);
        }
    }
    
    /**
     * For the missing keys in the data, interpolate using values either side and insert as necessary.
     */
    private function interpolateMissingData(&$missing_keys, &$array)
    {
        if (!is_array($array)) {
            return;  // Can't interpolate if not an array
        }
        
        $num_points = 2;
        
        $min_key = min(array_keys($array));
        $max_key = max(array_keys($array));
        $count = count($missing_keys);
        
        for ($i=0; $i<$count; ++$i) {
            if ($missing_keys[$i] !== 0) {
                // Interpolating outside recorded range is impossible - use edge values instead
                if ($missing_keys[$i] > $max_key) {
                    $array[$missing_keys[$i]] = $array[$max_key];
                    continue;
                } elseif ($missing_keys[$i] < $min_key) {
                    $array[$missing_keys[$i]] = $array[$min_key];
                    continue;
                }
                
                $prev_value = $next_value = reset($array);
                
                while ($missing_keys[$i] > key($array)) {
                    $prev_value = current($array);
                    $next_value = next($array);
                }
                for ($j=$i+1; $j<$count; ++$j) {
                    if ($missing_keys[$j] < key($array)) {
                        $num_points++;
                    } else {
                        break;
                    }
                }
                
                $gap = ($next_value - $prev_value) / $num_points;
                
                for ($k=0; $k<=$num_points-2; ++$k) {
                    $array[$missing_keys[$i+$k]] = $prev_value + ($gap * ($k+1));
                }
                for ($k=0; $k<=$num_points-2; ++$k) {
                    $missing_keys[$i+$k] = 0;
                }
                
                $num_points = 2;
            }
        }
        
        ksort($array);  // sort using keys
    }
    
    /**
     * Change arrays that contain only one element into non-arrays so you can use $variable rather than $variable[0] to access.
     */
    private function oneElementArrays()
    {
        foreach ($this->data_mesgs as $mesg_key => $mesg) {
            if ($mesg_key === 'developer_data') {
                continue;
            }
            foreach ($mesg as $field_key => $field) {
                if (count($field) === 1) {
                    $first_key = key($field);
                    $this->data_mesgs[$mesg_key][$field_key] = $field[$first_key];
                }
            }
        }
    }
    
    /**
     * The FIT protocol makes use of enumerated data types.
     * Where these values have been identified in the FIT SDK, they have been included in $this->enum_data
     * This function returns the enumerated value for a given message type.
     */
    public function enumData($type, $value)
    {
        if (is_array($value)) {
            $tmp = [];
            foreach ($value as $element) {
                if (isset($this->enum_data[$type][$element])) {
                    $tmp[] = $this->enum_data[$type][$element];
                } else {
                    $tmp[] = 'unknown';
                }
            }
            return $tmp;
        } else {
            return isset($this->enum_data[$type][$value]) ? $this->enum_data[$type][$value] : 'unknown';
        }
    }
    
    /**
     * Short-hand access to commonly used enumerated data.
     */
    public function manufacturer()
    {
        $tmp = $this->enumData('manufacturer', $this->data_mesgs['device_info']['manufacturer']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }
    public function product()
    {
        $tmp = $this->enumData('product', $this->data_mesgs['device_info']['product']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }
    public function sport()
    {
        $tmp = $this->enumData('sport', $this->data_mesgs['session']['sport']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }
    
    /**
     * Transform the values read from the FIT file into the units requested by the user.
     */
    private function setUnits($options)
    {
        if (!empty($options['units'])) {
            // Handle $options['units'] not being passed as array and/or not in lowercase.
            $units = strtolower((is_array($options['units'])) ? $options['units'][0] : $options['units']);
        } else {
            $units = 'metric';
        }
        
        //  Handle $options['pace'] being pass as array and/or boolean vs string and/or lowercase.
        $bPace = false;
        if (isset($options['pace'])) {
            $pace = is_array($options['pace']) ? $options['pace'][0] : $options['pace'];
            if (is_bool($pace)) {
                $bPace = $pace;
            } elseif (is_string($pace)) {
                $pace = strtolower($pace);
                if ($pace === 'true' || $pace === 'false') {
                    $bPace = $pace;
                } else {
                    throw new \Exception('phpFITFileAnalysis->setUnits(): pace option not valid!');
                }
            } else {
                throw new \Exception('phpFITFileAnalysis->setUnits(): pace option not valid!');
            }
        }
        
        // Set units for all messages
        $messages = ['session', 'lap', 'record', 'segment_lap'];
        $c_fields = [
                'avg_temperature',
                'max_temperature',
                'temperature'
            ];
        $m_fields = [
                'distance',
                'total_distance'
            ];
        $m_ft_fields = [
                'altitude',
                'avg_altitude',
                'enhanced_avg_altitude',
                'enhanced_max_altitude',
                'enhanced_min_altitude',
                'max_altitude',
                'min_altitude',
                'total_ascent',
                'total_descent'
            ];
        $ms_fields = [
                'avg_neg_vertical_speed',
                'avg_pos_vertical_speed',
                'avg_speed',
                'enhanced_avg_speed',
                'enhanced_max_speed',
                'enhanced_speed',
                'max_neg_vertical_speed',
                'max_pos_vertical_speed',
                'max_speed',
                'speed'
            ];
        $semi_fields = [
                'end_position_lat',
                'end_position_long',
                'nec_lat',
                'nec_long',
                'position_lat',
                'position_long',
                'start_position_lat',
                'start_position_long',
                'swc_lat',
                'swc_long'
            ];
        
        foreach ($messages as $message) {
            switch ($units) {
                case 'statute':
                    // convert from celsius to fahrenheit
                    foreach ($c_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round((($value * 9) / 5) + 32, 2);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round((($this->data_mesgs[$message][$field] * 9) / 5) + 32, 2);
                            }
                        }
                    }
                    
                    // convert from meters to miles
                    foreach ($m_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round($value * 0.000621371192, 2);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * 0.000621371192, 2);
                            }
                        }
                    }
                    
                    // convert from meters to feet
                    foreach ($m_ft_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round($value * 3.2808399, 1);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * 3.2808399, 1);
                            }
                        }
                    }
                    
                    // convert  meters per second to miles per hour
                    foreach ($ms_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    if ($bPace) {
                                        $value = round(60 / 2.23693629 / $value, 3);
                                    } else {
                                        $value = round($value * 2.23693629, 3);
                                    }
                                }
                            } else {
                                if ($bPace) {
                                    $this->data_mesgs[$message][$field] = round(60 / 2.23693629 / $this->data_mesgs[$message][$field], 3);
                                } else {
                                    $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * 2.23693629, 3);
                                }
                            }
                        }
                    }
                    
                    // convert from semicircles to degress
                    foreach ($semi_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round($value * (180.0 / pow(2, 31)), 5);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * (180.0 / pow(2, 31)), 5);
                            }
                        }
                    }
                    
                    break;
                    
                case 'raw':
                    // Do nothing - leave values as read from file.
                    break;
                case 'metric':
                    // convert from meters to kilometers
                    foreach ($m_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round($value * 0.001, 2);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * 0.001, 2);
                            }
                        }
                    }
                    
                    // convert  meters per second to kilometers per hour
                    foreach ($ms_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    if ($bPace) {
                                        $value = ($value != 0) ? round(60 / 3.6 / $value, 3) : 0;
                                    } else {
                                        $value = round($value * 3.6, 3);
                                    }
                                }
                            } else {
                                if ($this->data_mesgs[$message][$field] === 0) {  // Prevent divide by zero error
                                    continue;
                                }
                                if ($bPace) {
                                    $this->data_mesgs[$message][$field] = round(60 / 3.6 / $this->data_mesgs[$message][$field], 3);
                                } else {
                                    $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * 3.6, 3);
                                }
                            }
                        }
                    }
                    
                    // convert from semicircles to degress
                    foreach ($semi_fields as $field) {
                        if (isset($this->data_mesgs[$message][$field])) {
                            if (is_array($this->data_mesgs[$message][$field])) {
                                foreach ($this->data_mesgs[$message][$field] as &$value) {
                                    $value = round($value * (180.0 / pow(2, 31)), 5);
                                }
                            } else {
                                $this->data_mesgs[$message][$field] = round($this->data_mesgs[$message][$field] * (180.0 / pow(2, 31)), 5);
                            }
                        }
                    }
                    
                    break;
                default:
                    throw new \Exception('phpFITFileAnalysis->setUnits(): units option not valid!');
                    break;
            }
        }
    }
    
    /**
     * Calculate HR zones using HRmax formula: zone = HRmax * percentage.
     */
    public function hrZonesMax($hr_maximum, $percentages_array = [0.60, 0.75, 0.85, 0.95])
    {
        if (array_walk($percentages_array, function (&$value, $key, $hr_maximum) {
            $value = round($value * $hr_maximum);
        }, $hr_maximum)) {
            return $percentages_array;
        } else {
            throw new \Exception('phpFITFileAnalysis->hrZonesMax(): cannot calculate zones, please check inputs!');
        }
    }
    
    /**
     * Calculate HR zones using HRreserve formula: zone = HRresting + ((HRmax - HRresting) * percentage).
     */
    public function hrZonesReserve($hr_resting, $hr_maximum, $percentages_array = [0.60, 0.65, 0.75, 0.82, 0.89, 0.94 ])
    {
        if (array_walk($percentages_array, function (&$value, $key, $params) {
            $value = round($params[0] + ($value * $params[1]));
        }, [$hr_resting, $hr_maximum - $hr_resting])) {
            return $percentages_array;
        } else {
            throw new \Exception('phpFITFileAnalysis->hrZonesReserve(): cannot calculate zones, please check inputs!');
        }
    }
    
    /**
     * Calculate power zones using Functional Threshold Power value: zone = FTP * percentage.
     */
    public function powerZones($functional_threshold_power, $percentages_array = [0.55, 0.75, 0.90, 1.05, 1.20, 1.50])
    {
        if (array_walk($percentages_array, function (&$value, $key, $functional_threshold_power) {
            $value = round($value * $functional_threshold_power) + 1;
        }, $functional_threshold_power)) {
            return $percentages_array;
        } else {
            throw new \Exception('phpFITFileAnalysis->powerZones(): cannot calculate zones, please check inputs!');
        }
    }
    
    /**
     * Partition the data (e.g. cadence, heart_rate, power, speed) using thresholds provided as an array.
     */
    public function partitionData($record_field = '', $thresholds = null, $percentages = true, $labels_for_keys = true)
    {
        if (!isset($this->data_mesgs['record'][$record_field])) {
            throw new \Exception('phpFITFileAnalysis->partitionData(): '.$record_field.' data not present in FIT file!');
        }
        if (!is_array($thresholds)) {
            throw new \Exception('phpFITFileAnalysis->partitionData(): thresholds must be an array e.g. [10,20,30,40,50]!');
        }
        
        foreach ($thresholds as $threshold) {
            if (!is_numeric($threshold) || $threshold < 0) {
                throw new \Exception('phpFITFileAnalysis->partitionData(): '.$threshold.' not valid in thresholds!');
            }
            if (isset($last_threshold) && $last_threshold >= $threshold) {
                throw new \Exception('phpFITFileAnalysis->partitionData(): error near ..., '.$last_threshold.', '.$threshold.', ... - each element in thresholds array must be greater than previous element!');
            }
            $last_threshold = $threshold;
        }
        
        $result = array_fill(0, count($thresholds)+1, 0);
        
        foreach ($this->data_mesgs['record'][$record_field] as $value) {
            $key = 0;
            $count = count($thresholds);
            for ($key; $key<$count; ++$key) {
                if ($value < $thresholds[$key]) {
                    break;
                }
            }
            $result[$key]++;
        }
        
        array_unshift($thresholds, 0);
        $keys = [];
        
        if ($labels_for_keys === true) {
            $count = count($thresholds);
            for ($i=0; $i<$count; ++$i) {
                $keys[] = $thresholds[$i] . (isset($thresholds[$i+1]) ? '-'.($thresholds[$i+1] - 1) : '+');
            }
            $result = array_combine($keys, $result);
        }
        
        if ($percentages === true) {
            $total = array_sum($result);
            array_walk($result, function (&$value, $key, $total) {
                $value = round($value / $total * 100, 1);
            }, $total);
        }
        
        return $result;
    }
    
    /**
     * Split data into buckets/bins using a Counting Sort algorithm (http://en.wikipedia.org/wiki/Counting_sort) to generate data for a histogram plot.
     */
    public function histogram($bucket_width = 25, $record_field = '')
    {
        if (!isset($this->data_mesgs['record'][$record_field])) {
            throw new \Exception('phpFITFileAnalysis->histogram(): '.$record_field.' data not present in FIT file!');
        }
        if (!is_numeric($bucket_width) || $bucket_width <= 0) {
            throw new \Exception('phpFITFileAnalysis->histogram(): bucket width is not valid!');
        }
        
        foreach ($this->data_mesgs['record'][$record_field] as $value) {
            $key = round($value / $bucket_width) * $bucket_width;
            isset($result[$key]) ? $result[$key]++ : $result[$key] = 1;
        }
        
        for ($i=0; $i<max(array_keys($result)) / $bucket_width; ++$i) {
            if (!isset($result[$i * $bucket_width])) {
                $result[$i * $bucket_width] = 0;
            }
        }
        
        ksort($result);
        return $result;
    }
    
    /**
     * Helper functions / shortcuts.
     */
    public function hrPartionedHRmaximum($hr_maximum)
    {
        return $this->partitionData('heart_rate', $this->hrZonesMax($hr_maximum));
    }
    public function hrPartionedHRreserve($hr_resting, $hr_maximum)
    {
        return $this->partitionData('heart_rate', $this->hrZonesReserve($hr_resting, $hr_maximum));
    }
    public function powerPartioned($functional_threshold_power)
    {
        return $this->partitionData('power', $this->powerZones($functional_threshold_power));
    }
    public function powerHistogram($bucket_width = 25)
    {
        return $this->histogram($bucket_width, 'power');
    }
    
    /**
     * Simple moving average algorithm
     */
    private function sma($array, $time_period)
    {
        $sma_data = [];
        $data = array_values($array);
        $count = count($array);
        
        for ($i=0; $i<$count-$time_period; ++$i) {
            $sma_data[] = array_sum(array_slice($data, $i, $time_period)) / $time_period;
        }
        
        return $sma_data;
    }
    
    /**
     * Calculate TRIMP (TRaining IMPulse) and an Intensity Factor using HR data. Useful if power data not available.
     * hr_FT is heart rate at Functional Threshold, or Lactate Threshold Heart Rate (LTHR)
     */
    public function hrMetrics($hr_resting, $hr_maximum, $hr_FT, $gender)
    {
        $hr_metrics = [  // array to hold HR analysis data
            'TRIMPexp' => 0.0,
            'hrIF' => 0.0,
        ];
        if (in_array($gender, ['F', 'f', 'Female', 'female'])) {
            $gender_coeff = 1.67;
        } else {
            $gender_coeff = 1.92;
        }
        foreach ($this->data_mesgs['record']['heart_rate'] as $hr) {
            // TRIMPexp formula from http://fellrnr.com/wiki/TRIMP
            // TRIMPexp = sum(D x HRr x 0.64ey)
            $temp_heart_rate = ($hr - $hr_resting) / ($hr_maximum - $hr_resting);
            $hr_metrics['TRIMPexp'] += ((1/60) * $temp_heart_rate * 0.64 * (exp($gender_coeff * $temp_heart_rate)));
        }
        $hr_metrics['TRIMPexp'] = round($hr_metrics['TRIMPexp']);
        $hr_metrics['hrIF'] = round((array_sum($this->data_mesgs['record']['heart_rate'])/(count($this->data_mesgs['record']['heart_rate']))) / $hr_FT, 2);
        
        return $hr_metrics;
    }

    /**
     * Returns 'Average Power', 'Kilojoules', 'Normalised Power', 'Variability Index', 'Intensity Factor', and 'Training Stress Score' in an array.
     *
     * Normalised Power (and metrics dependent on it) require the PHP trader extension to be loaded
     * http://php.net/manual/en/book.trader.php
     */
    public function powerMetrics($functional_threshold_power)
    {
        if (!isset($this->data_mesgs['record']['power'])) {
            throw new \Exception('phpFITFileAnalysis->powerMetrics(): power data not present in FIT file!');
        }
        
        $power_metrics['Average Power'] = array_sum($this->data_mesgs['record']['power']) / count($this->data_mesgs['record']['power']);
        $power_metrics['Kilojoules'] = ($power_metrics['Average Power'] * count($this->data_mesgs['record']['power'])) / 1000;
        
        // NP1 capture all values for rolling 30s averages
        $NP_values = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], 30) : $this->sma($this->data_mesgs['record']['power'], 30);
        
        $NormalisedPower = 0.0;
        foreach ($NP_values as $value) {  // NP2 Raise all the values obtained in step NP1 to the fourth power
            $NormalisedPower += pow($value, 4);
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
    
    /**
     * Returns Critical Power (Best Efforts) values for supplied time period(s).
     */
    public function criticalPower($time_periods)
    {
        if (!isset($this->data_mesgs['record']['power'])) {
            throw new \Exception('phpFITFileAnalysis->criticalPower(): power data not present in FIT file!');
        }
        
        if (is_array($time_periods)) {
            $count = count($this->data_mesgs['record']['power']);
            foreach ($time_periods as $time_period) {
                if (!is_numeric($time_period)) {
                    throw new \Exception('phpFITFileAnalysis->criticalPower(): time periods must only contain numeric data!');
                }
                if ($time_period < 0) {
                    throw new \Exception('phpFITFileAnalysis->criticalPower(): time periods cannot be negative!');
                }
                if ($time_period > $count) {
                    break;
                }
                
                $averages = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], $time_period) : $this->sma($this->data_mesgs['record']['power'], $time_period);
                if ($averages !== false) {
                    $criticalPower_values[$time_period] = max($averages);
                }
            }
            
            return $criticalPower_values;
        } elseif (is_numeric($time_periods) && $time_periods > 0) {
            if ($time_periods > count($this->data_mesgs['record']['power'])) {
                $criticalPower_values[$time_periods] = 0;
            } else {
                $averages = ($this->php_trader_ext_loaded) ? trader_sma($this->data_mesgs['record']['power'], $time_periods) : $this->sma($this->data_mesgs['record']['power'], $time_periods);
                if ($averages !== false) {
                    $criticalPower_values[$time_periods] = max($averages);
                }
            }
            
            return $criticalPower_values;
        } else {
            throw new \Exception('phpFITFileAnalysis->criticalPower(): time periods not valid!');
        }
    }
    
    /**
     * Returns array of booleans using timestamp as key.
     * true == timer paused (e.g. autopause)
     */
    public function isPaused()
    {
        /**
         * Event enumerated values of interest
         * 0 = timer
         */
        $tek = array_keys($this->data_mesgs['event']['event'], 0);  // timer event keys
        
        $timer_start = [];
        $timer_stop = [];
        foreach ($tek as $v) {
            if ($this->data_mesgs['event']['event_type'][$v] === 0) {
                $timer_start[$v] = $this->data_mesgs['event']['timestamp'][$v];
            } elseif ($this->data_mesgs['event']['event_type'][$v] === 4) {
                $timer_stop[$v] = $this->data_mesgs['event']['timestamp'][$v];
            }
        }
        
        $first_ts = min($this->data_mesgs['record']['timestamp']);  // first timestamp
        $last_ts = max($this->data_mesgs['record']['timestamp']);  // last timestamp
        
        reset($timer_start);
        $cur_start = next($timer_start);
        $cur_stop = reset($timer_stop);
        
        $is_paused = [];
        $bPaused = false;
        
        for ($i = $first_ts; $i < $last_ts; ++$i) {
            if ($i == $cur_stop) {
                $bPaused = true;
                $cur_stop = next($timer_stop);
            } elseif ($i == $cur_start) {
                $bPaused = false;
                $cur_start = next($timer_start);
            }
            $is_paused[$i] = $bPaused;
        }
        $is_paused[$last_ts] = end($this->data_mesgs['record']['speed']) == 0 ? true : false;
        
        return $is_paused;
    }
    
    /**
     * Returns an array that can be used to plot Circumferential Pedal Velocity (x-axis) vs Average Effective Pedal Force (y-axis).
     * NB Crank length is in metres.
     */
    public function quadrantAnalysis($crank_length, $ftp, $selected_cadence = 90, $use_timestamps = false)
    {
        if ($crank_length === null || $ftp === null) {
            return [];
        }
        if (empty($this->data_mesgs['record']['power']) || empty($this->data_mesgs['record']['cadence'])) {
            return [];
        }
        
        $quadrant_plot = [];
        $quadrant_plot['selected_cadence'] = $selected_cadence;
        $quadrant_plot['aepf_threshold'] = round(($ftp * 60) / ($selected_cadence * 2 * pi() * $crank_length), 3);
        $quadrant_plot['cpv_threshold'] = round(($selected_cadence * $crank_length * 2 * pi()) / 60, 3);
        
        // Used to calculate percentage of points in each quadrant
        $quad_percent = ['hf_hv' => 0, 'hf_lv' => 0, 'lf_lv' => 0, 'lf_hv' => 0];
        
        // Filter zeroes from cadence array (otherwise !div/0 error for AEPF)
        $cadence = array_filter($this->data_mesgs['record']['cadence']);
        $cpv = $aepf = 0.0;
        
        foreach ($cadence as $k => $c) {
            $p = isset($this->data_mesgs['record']['power'][$k]) ? $this->data_mesgs['record']['power'][$k] : 0;
            
            // Circumferential Pedal Velocity (CPV, m/s) = (Cadence × Crank Length × 2 × Pi) / 60
            $cpv = round(($c * $crank_length * 2 * pi()) / 60, 3);
            
            // Average Effective Pedal Force (AEPF, N) = (Power × 60) / (Cadence × 2 × Pi × Crank Length)
            $aepf = round(($p * 60) / ($c * 2 * pi() * $crank_length), 3);
            
            if ($use_timestamps === true) {
                $quadrant_plot['plot'][$k] = [$cpv, $aepf];
            } else {
                $quadrant_plot['plot'][] = [$cpv, $aepf];
            }
            
            if ($aepf > $quadrant_plot['aepf_threshold']) {  // high force
                if ($cpv > $quadrant_plot['cpv_threshold']) {  // high velocity
                    $quad_percent['hf_hv']++;
                } else {
                    $quad_percent['hf_lv']++;
                }
            } else {  // low force
                if ($cpv > $quadrant_plot['cpv_threshold']) {  // high velocity
                    $quad_percent['lf_hv']++;
                } else {
                    $quad_percent['lf_lv']++;
                }
            }
        }
        
        // Convert to percentages and add to array that will be returned by the function
        $sum = array_sum($quad_percent);
        foreach ($quad_percent as $k => $v) {
            $quad_percent[$k] = round($v / $sum * 100, 2);
        }
        $quadrant_plot['quad_percent'] = $quad_percent;
        
        // Calculate CPV and AEPF for cadences between 20 and 150rpm at and near to FTP
        for ($c = 20; $c <= 150; $c += 5) {
            $cpv = round((($c * $crank_length * 2 * pi()) / 60), 3);
            $quadrant_plot['ftp-25w'][] = [$cpv, round((($ftp - 25) * 60) / ($c * 2 * pi() * $crank_length), 3)];
            $quadrant_plot['ftp'][] = [$cpv, round(($ftp * 60) / ($c * 2 * pi() * $crank_length), 3)];
            $quadrant_plot['ftp+25w'][] = [$cpv, round((($ftp + 25) * 60) / ($c * 2 * pi() * $crank_length), 3)];
        }
        
        return $quadrant_plot;
    }
        
    /**
     * Returns array of gear change information.
     */
    public function gearChanges($bIgnoreTimerPaused = true)
    {
        /**
         * Event enumerated values of interest
         * 42 = front_gear_change
         * 43 = rear_gear_change
         */
        $fgcek = array_keys($this->data_mesgs['event']['event'], 42);  // front gear change event keys
        $rgcek = array_keys($this->data_mesgs['event']['event'], 43);  // rear gear change event keys
        
        /**
         * gear_change_data (uint32)
         * components:
         *     rear_gear_num  00000000 00000000 00000000 11111111
         *     rear_gear      00000000 00000000 11111111 00000000
         *     front_gear_num 00000000 11111111 00000000 00000000
         *     front_gear     11111111 00000000 00000000 00000000
         * scale: 1, 1, 1, 1
         * bits: 8, 8, 8, 8
         */
        
        $fgc = [];  // front gear components
        $front_gears = [];
        foreach ($fgcek as $k) {
            $fgc_tmp = [
                'timestamp'   => $this->data_mesgs['event']['timestamp'][$k],
                // 'data'        => $this->data_mesgs['event']['data'][$k],
                // 'event_type'  => $this->data_mesgs['event']['event_type'][$k],
                // 'event_group' => $this->data_mesgs['event']['event_group'][$k],
                'rear_gear_num' => $this->data_mesgs['event']['data'][$k] & 255,
                'rear_gear' => ($this->data_mesgs['event']['data'][$k] >> 8) & 255,
                'front_gear_num' => ($this->data_mesgs['event']['data'][$k] >> 16) & 255,
                'front_gear' => ($this->data_mesgs['event']['data'][$k] >> 24) & 255
            ];
            
            $fgc[] = $fgc_tmp;
            
            if (!array_key_exists($fgc_tmp['front_gear_num'], $front_gears)) {
                $front_gears[$fgc_tmp['front_gear_num']] = $fgc_tmp['front_gear'];
            }
        }
        ksort($front_gears);
        
        $rgc = [];  // rear gear components
        $rear_gears = [];
        foreach ($rgcek as $k) {
            $rgc_tmp = [
                'timestamp'   => $this->data_mesgs['event']['timestamp'][$k],
                // 'data'        => $this->data_mesgs['event']['data'][$k],
                // 'event_type'  => $this->data_mesgs['event']['event_type'][$k],
                // 'event_group' => $this->data_mesgs['event']['event_group'][$k],
                'rear_gear_num' => $this->data_mesgs['event']['data'][$k] & 255,
                'rear_gear' => ($this->data_mesgs['event']['data'][$k] >> 8) & 255,
                'front_gear_num' => ($this->data_mesgs['event']['data'][$k] >> 16) & 255,
                'front_gear' => ($this->data_mesgs['event']['data'][$k] >> 24) & 255
            ];
            
            $rgc[] = $rgc_tmp;
            
            if (!array_key_exists($rgc_tmp['rear_gear_num'], $rear_gears)) {
                $rear_gears[$rgc_tmp['rear_gear_num']] = $rgc_tmp['rear_gear'];
            }
        }
        ksort($rear_gears);
        
        $timestamps = $this->data_mesgs['record']['timestamp'];
        $first_ts = min($timestamps);  // first timestamp
        $last_ts = max($timestamps);   // last timestamp
        
        $fg = 0;  // front gear at start of ride
        $rg = 0;  // rear gear at start of ride
        
        if (isset($fgc[0]['timestamp'])) {
            if ($first_ts == $fgc[0]['timestamp']) {
                $fg = $fgc[0]['front_gear'];
            } else {
                $fg = $fgc[0]['front_gear_num'] == 1 ? $front_gears[2] : $front_gears[1];
            }
        }
        
        if (isset($rgc[0]['timestamp'])) {
            if ($first_ts == $rgc[0]['timestamp']) {
                $rg = $rgc[0]['rear_gear'];
            } else {
                $rg = $rgc[0]['rear_gear_num'] == min($rear_gears) ? $rear_gears[$rgc[0]['rear_gear_num'] + 1] : $rear_gears[$rgc[0]['rear_gear_num'] - 1];
            }
        }
        
        $fg_summary = [];
        $rg_summary = [];
        $combined = [];
        $gears_array = [];
        
        if ($bIgnoreTimerPaused === true) {
            $is_paused = $this->isPaused();
        }
        
        reset($fgc);
        reset($rgc);
        for ($i = $first_ts; $i < $last_ts; ++$i) {
            if ($bIgnoreTimerPaused === true && $is_paused[$i] === true) {
                continue;
            }
            
            $fgc_tmp = current($fgc);
            $rgc_tmp = current($rgc);
            
            if ($i > $fgc_tmp['timestamp']) {
                if (next($fgc) !== false) {
                    $fg = $fgc_tmp['front_gear'];
                }
            }
            $fg_summary[$fg] = isset($fg_summary[$fg]) ? $fg_summary[$fg] + 1 : 1;
            
            if ($i > $rgc_tmp['timestamp']) {
                if (next($rgc) !== false) {
                    $rg = $rgc_tmp['rear_gear'];
                }
            }
            $rg_summary[$rg] = isset($rg_summary[$rg]) ? $rg_summary[$rg] + 1 : 1;
            
            $combined[$fg][$rg] = isset($combined[$fg][$rg]) ? $combined[$fg][$rg] + 1 : 1;
            
            $gears_array[$i] = ['front_gear' => $fg, 'rear_gear' => $rg];
        }
        
        krsort($fg_summary);
        krsort($rg_summary);
        krsort($combined);
        
        $output = ['front_gear_summary' => $fg_summary, 'rear_gear_summary' => $rg_summary, 'combined_summary' => $combined, 'gears_array' => $gears_array];
        
        return $output;
    }
    
    /**
     * Create a JSON object that contains available record message information and CPV/AEPF if requested/available.
     */
    public function getJSON($crank_length = null, $ftp = null, $data_required = ['all'], $selected_cadence = 90)
    {
        if (!is_array($data_required)) {
            $data_required = [$data_required];
        }
        foreach ($data_required as &$datum) {
            $datum = strtolower($datum);
        }
        
        $all = in_array('all', $data_required);
        $timestamp         = ($all || in_array('timestamp', $data_required));
        $paused            = ($all || in_array('paused', $data_required));
        $temperature       = ($all || in_array('temperature', $data_required));
        $lap               = ($all || in_array('lap', $data_required));
        $position_lat      = ($all || in_array('position_lat', $data_required));
        $position_long     = ($all || in_array('position_long', $data_required));
        $distance          = ($all || in_array('distance', $data_required));
        $altitude          = ($all || in_array('altitude', $data_required));
        $speed             = ($all || in_array('speed', $data_required));
        $heart_rate        = ($all || in_array('heart_rate', $data_required));
        $cadence           = ($all || in_array('cadence', $data_required));
        $power             = ($all || in_array('power', $data_required));
        $quadrant_analysis = ($all || in_array('quadrant-analysis', $data_required));
        
        $for_json = [];
        $for_json['fix_data'] = isset($this->options['fix_data']) ? $this->options['fix_data'] : null;
        $for_json['units'] = isset($this->options['units']) ? $this->options['units'] : null;
        $for_json['pace'] = isset($this->options['pace']) ? $this->options['pace'] : null;
        
        $lap_count = 1;
        $data = [];
        if ($quadrant_analysis) {
            $quadrant_plot = $this->quadrantAnalysis($crank_length, $ftp, $selected_cadence, true);
            if (!empty($quadrant_plot)) {
                $for_json['aepf_threshold'] = $quadrant_plot['aepf_threshold'];
                $for_json['cpv_threshold'] = $quadrant_plot['cpv_threshold'];
            }
        }
        if ($paused) {
            $is_paused = $this->isPaused();
        }
                
        foreach ($this->data_mesgs['record']['timestamp'] as $ts) {
            if ($lap && is_array($this->data_mesgs['lap']['timestamp']) && $ts >= $this->data_mesgs['lap']['timestamp'][$lap_count - 1]) {
                $lap_count++;
            }
            $tmp = [];
            if ($timestamp) {
                $tmp['timestamp'] = $ts;
            }
            if ($lap) {
                $tmp['lap'] = $lap_count;
            }
            
            foreach ($this->data_mesgs['record'] as $key => $value) {
                if ($key !== 'timestamp') {
                    if ($$key) {
                        $tmp[$key] = isset($value[$ts]) ? $value[$ts] : null;
                    }
                }
            }
            
            if ($quadrant_analysis) {
                if (!empty($quadrant_plot)) {
                    $tmp['cpv'] = isset($quadrant_plot['plot'][$ts]) ? $quadrant_plot['plot'][$ts][0] : null;
                    $tmp['aepf'] = isset($quadrant_plot['plot'][$ts]) ? $quadrant_plot['plot'][$ts][1] : null;
                }
            }
            
            if ($paused) {
                $tmp['paused'] = $is_paused[$ts];
            }
            
            $data[] = $tmp;
            unset($tmp);
        }
        
        $for_json['data'] = $data;
        
        return json_encode($for_json);
    }
    
    /**
     * Create a JSON object that contains available lap message information.
     */
    public function getJSONLap()
    {
        $for_json = [];
        $for_json['fix_data'] = isset($this->options['fix_data']) ? $this->options['fix_data'] : null;
        $for_json['units'] = isset($this->options['units']) ? $this->options['units'] : null;
        $for_json['pace'] = isset($this->options['pace']) ? $this->options['pace'] : null;
        $for_json['num_laps'] = count($this->data_mesgs['lap']['timestamp']);
        $data = [];
        
        for ($i=0; $i<$for_json['num_laps']; $i++) {
            $data[$i]['lap'] = $i;
            foreach ($this->data_mesgs['lap'] as $key => $value) {
                $data[$i][$key] = $value[$i];
            }
        }
        
        $for_json['data'] = $data;
        
        return json_encode($for_json);
    }
    
    /**
     * Outputs tables of information being listened for and found within the processed FIT file.
     */
    public function showDebugInfo()
    {
        asort($this->defn_mesgs_all);  // Sort the definition messages
        
        echo '<h3>Types</h3>';
        echo '<table class=\'table table-condensed table-striped\'>';  // Bootstrap classes
        echo '<thead>';
        echo '<th>key</th>';
        echo '<th>PHP unpack() format</th>';
        echo '<th>Bytes</th>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($this->types as $key => $val) {
            echo '<tr><td>'.$key.'</td><td>'.$val['format'].'</td><td>'.$val['bytes'].'</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';
        
        echo '<br><hr><br>';
        
        echo '<h3>Messages and Fields being listened for</h3>';
        foreach ($this->data_mesg_info as $key => $val) {
            echo '<h4>'.$val['mesg_name'].' ('.$key.')</h4>';
            echo '<table class=\'table table-condensed table-striped\'>';
            echo '<thead><th>ID</th><th>Name</th><th>Scale</th><th>Offset</th><th>Units</th></thead><tbody>';
            foreach ($val['field_defns'] as $key2 => $val2) {
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
        foreach ($this->defn_mesgs_all as $key => $val) {
            echo  '<tr><td>'.$val['global_mesg_num'].(isset($this->data_mesg_info[$val['global_mesg_num']]) ? ' ('.$this->data_mesg_info[$val['global_mesg_num']]['mesg_name'].')' : ' (unknown)').'</td><td>'.$val['num_fields'].'</td><td>';
            foreach ($val['field_defns'] as $defn) {
                echo 'defn: '.$defn['field_definition_number'].'; size: '.$defn['size'].'; type: '.$defn['base_type'];
                echo ' (' . (isset($this->data_mesg_info[$val['global_mesg_num']]['field_defns'][$defn['field_definition_number']]) ? $this->data_mesg_info[$val['global_mesg_num']]['field_defns'][$defn['field_definition_number']]['field_name'] : 'unknown') . ')<br>';
            }
            echo  '</td><td>'.$val['total_size'].'</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';
        
        echo '<br><hr><br>';
        
        echo '<h3>Messages found in file</h3>';
        foreach ($this->data_mesgs as $mesg_key => $mesg) {
            echo '<table class=\'table table-condensed table-striped\'>';
            echo '<thead><th>'.$mesg_key.'</th><th>count()</th></thead><tbody>';
            foreach ($mesg as $field_key => $field) {
                echo '<tr><td>'.$field_key.'</td><td>'.count($field).'</td></tr>';
            }
            echo '</tbody></table><br><br>';
        }
    }
    
    /*
     * Process HR messages
     * 
     * Based heavily on logic in commit:
     * https://github.com/GoldenCheetah/GoldenCheetah/commit/957ae470999b9a57b5b8ec57e75512d4baede1ec
     * Particularly the decodeHr() method
     */
    private function processHrMessages()
    {
        // Check that we have received HR messages
        if (empty($this->data_mesgs['hr'])) {
            return;
        }
        
        $hr = [];
        $timestamps = [];
        
        // Load all filtered_bpm values into the $hr array
        foreach ($this->data_mesgs['hr']['filtered_bpm'] as $hr_val) {
            if (is_array($hr_val)) {
                foreach ($hr_val as $sub_hr_val) {
                    $hr[] = $sub_hr_val;
                }
            } else {
                $hr[] = $hr_val;
            }
        }
        
        // Manually scale timestamps (i.e. divide by 1024)
        $last_event_timestamp = $this->data_mesgs['hr']['event_timestamp'];
        if (is_array($last_event_timestamp)) {
            $last_event_timestamp = $last_event_timestamp[0];
        }
        $start_timestamp = $this->data_mesgs['hr']['timestamp'] - $last_event_timestamp / 1024.0;
        $timestamps[] = $last_event_timestamp / 1024.0;
        
        // Determine timestamps (similar to compressed timestamps)
        foreach ($this->data_mesgs['hr']['event_timestamp_12'] as $event_timestamp_12_val) {
            $j=0;
            for ($i=0; $i<11; $i++) {
                $last_event_timestamp12 = $last_event_timestamp & 0xFFF;
                $next_event_timestamp12;
                
                if ($j % 2 === 0) {
                    $next_event_timestamp12 = $event_timestamp_12_val[$i] + (($event_timestamp_12_val[$i+1] & 0xF) << 8);
                    $last_event_timestamp = ($last_event_timestamp & 0xFFFFF000) + $next_event_timestamp12;
                } else {
                    $next_event_timestamp12 = 16 * $event_timestamp_12_val[$i+1] + (($event_timestamp_12_val[$i] & 0xF0) >> 4);
                    $last_event_timestamp = ($last_event_timestamp & 0xFFFFF000) + $next_event_timestamp12;
                    $i++;
                }
                if ($next_event_timestamp12 < $last_event_timestamp12) {
                    $last_event_timestamp += 0x1000;
                }
                
                $timestamps[] = $last_event_timestamp / 1024.0;
                $j++;
            }
        }
        
        // Map HR values to timestamps
        $filtered_bpm_arr = [];
        $secs = 0;
        $min_record_ts = min($this->data_mesgs['record']['timestamp']);
        $max_record_ts = max($this->data_mesgs['record']['timestamp']);
        foreach ($timestamps as $idx => $timestamp) {
            $ts_secs = round($timestamp + $start_timestamp);
            
            // Skip timestamps outside of the range we're interested in
            if ($ts_secs >= $min_record_ts && $ts_secs <= $max_record_ts) {
                if (isset($filtered_bpm_arr[$ts_secs])) {
                    $filtered_bpm_arr[$ts_secs][0] += $hr[$idx];
                    $filtered_bpm_arr[$ts_secs][1]++;
                } else {
                    $filtered_bpm_arr[$ts_secs] = [$hr[$idx], 1];
                }
            }
        }
        
        // Populate the heart_rate fields for record messages
        foreach ($filtered_bpm_arr as $idx => $arr) {
            $this->data_mesgs['record']['heart_rate'][$idx] = (int)round($arr[0] / $arr[1]);
        }
    }
}
