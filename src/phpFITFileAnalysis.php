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
 * Eric-11 edits:
 * Updated enum_data/data_mesg_info to FIT Version 21.101 however some features are not yet implemented
 * Added some additional information to fields, like type of value (e.g. enum, uint16, etc for future use)
 *  included comments from Profile.xlsx sheets
 * Added debug option to class to view debugging messages
 * Added dynamic field information, but not implemented yet
 * Added code for timeInZones calculation
 * Added meta data for TRIMPexp/hrIF code
 * Added dynamic field output to showDebug
 * Added checks for cases where no recorded data is found for calculations (can now run on FIT files with no data)
 *
 * https://github.com/adriangibbons/phpFITFileAnalysis
 * https://developer.garmin.com/fit/download/
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

class phpFITFileAnalysis {

    public $data_mesgs = [];  // Used to store the data read from the file in associative arrays.
    public $debug = false; // output debug messages
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
    // VERSION: 21.101
    // Automatically extracted from FIT SDK https://developer.garmin.com/fit/download/
    // Extracted from CSV versions of the Profile Type tab in Profile.xlsx
    private $enum_data = [
        'file' => [
            'type' => 'enum',
            1 => 'device', // Read only, single file. Must be in root directory.
            2 => 'settings', // Read/write, single file. Directory=Settings
            3 => 'sport', // Read/write, multiple files, file number = sport type. Directory=Sports
            4 => 'activity', // Read/erase, multiple files. Directory=Activities
            5 => 'workout', // Read/write/erase, multiple files. Directory=Workouts
            6 => 'course', // Read/write/erase, multiple files. Directory=Courses
            7 => 'schedules', // Read/write, single file. Directory=Schedules
            9 => 'weight', // Read only, single file. Circular buffer. All message definitions at start of file. Directory=Weight
            10 => 'totals', // Read only, single file. Directory=Totals
            11 => 'goals', // Read/write, single file. Directory=Goals
            14 => 'blood_pressure', // Read only. Directory=Blood Pressure
            15 => 'monitoring_a', // Read only. Directory=Monitoring. File number=sub type.
            20 => 'activity_summary', // Read/erase, multiple files. Directory=Activities
            28 => 'monitoring_daily',
            32 => 'monitoring_b', // Read only. Directory=Monitoring. File number=identifier
            34 => 'segment', // Read/write/erase. Multiple Files. Directory=Segments
            35 => 'segment_list', // Read/write/erase. Single File. Directory=Segments
            40 => 'exd_configuration', // Read/write/erase. Single File. Directory=Settings
            0xF7 => 'mfg_range_min', // 0xF7 - 0xFE reserved for manufacturer specific file types
            0xFE => 'mfg_range_max', // 0xF7 - 0xFE reserved for manufacturer specific file types
        ],
        'mesg_num' => [
            'type' => 'uint16',
            0 => 'file_id',
            1 => 'capabilities',
            2 => 'device_settings',
            3 => 'user_profile',
            4 => 'hrm_profile',
            5 => 'sdm_profile',
            6 => 'bike_profile',
            7 => 'zones_target',
            8 => 'hr_zone',
            9 => 'power_zone',
            10 => 'met_zone',
            12 => 'sport',
            15 => 'goal',
            18 => 'session',
            19 => 'lap',
            20 => 'record',
            21 => 'event',
            23 => 'device_info',
            26 => 'workout',
            27 => 'workout_step',
            28 => 'schedule',
            30 => 'weight_scale',
            31 => 'course',
            32 => 'course_point',
            33 => 'totals',
            34 => 'activity',
            35 => 'software',
            37 => 'file_capabilities',
            38 => 'mesg_capabilities',
            39 => 'field_capabilities',
            49 => 'file_creator',
            51 => 'blood_pressure',
            53 => 'speed_zone',
            55 => 'monitoring',
            72 => 'training_file',
            78 => 'hrv',
            80 => 'ant_rx',
            81 => 'ant_tx',
            82 => 'ant_channel_id',
            101 => 'length',
            103 => 'monitoring_info',
            105 => 'pad',
            106 => 'slave_device',
            127 => 'connectivity',
            128 => 'weather_conditions',
            129 => 'weather_alert',
            131 => 'cadence_zone',
            132 => 'hr',
            142 => 'segment_lap',
            145 => 'memo_glob',
            148 => 'segment_id',
            149 => 'segment_leaderboard_entry',
            150 => 'segment_point',
            151 => 'segment_file',
            158 => 'workout_session',
            159 => 'watchface_settings',
            160 => 'gps_metadata',
            161 => 'camera_event',
            162 => 'timestamp_correlation',
            164 => 'gyroscope_data',
            165 => 'accelerometer_data',
            167 => 'three_d_sensor_calibration',
            169 => 'video_frame',
            174 => 'obdii_data',
            177 => 'nmea_sentence',
            178 => 'aviation_attitude',
            184 => 'video',
            185 => 'video_title',
            186 => 'video_description',
            187 => 'video_clip',
            188 => 'ohr_settings',
            200 => 'exd_screen_configuration',
            201 => 'exd_data_field_configuration',
            202 => 'exd_data_concept_configuration',
            206 => 'field_description',
            207 => 'developer_data_id',
            208 => 'magnetometer_data',
            209 => 'barometer_data',
            210 => 'one_d_sensor_calibration',
            216 => 'time_in_zone',
            225 => 'set',
            227 => 'stress_level',
            258 => 'dive_settings',
            259 => 'dive_gas',
            262 => 'dive_alarm',
            264 => 'exercise_title',
            268 => 'dive_summary',
            285 => 'jump',
            312 => 'split',
            317 => 'climb_pro',
            375 => 'device_aux_battery_info',
            0xFF00 => 'mfg_range_min', // 0xFF00 - 0xFFFE reserved for manufacturer specific messages
            0xFFFE => 'mfg_range_max', // 0xFF00 - 0xFFFE reserved for manufacturer specific messages
        ],
        'checksum' => [
            'type' => 'uint8',
            0 => 'clear', // Allows clear of checksum for flash memory where can only write 1 to 0 without erasing sector.
            1 => 'ok', // Set to mark checksum as valid if computes to invalid values 0 or 0xFF. Checksum can also be set to ok to save encoding computation time.
        ],
        'file_flags' => [
            'type' => 'uint8z',
            0x02 => 'read',
            0x04 => 'write',
            0x08 => 'erase',
        ],
        'mesg_count' => [
            'type' => 'enum',
            0 => 'num_per_file',
            1 => 'max_per_file',
            2 => 'max_per_file_type',
        ],
        'date_time' => [
            'type' => 'uint32',
            0x10000000 => 'min', // if date_time is < 0x10000000 then it is system time (seconds from device power on)
        ],
        'local_date_time' => [
            'type' => 'uint32',
            0x10000000 => 'min', // if date_time is < 0x10000000 then it is system time (seconds from device power on)
        ],
        'message_index' => [
            'type' => 'uint16',
            0x8000 => 'selected', // message is selected if set
            0x7000 => 'reserved', // reserved (default 0)
            0x0FFF => 'mask', // index
        ],
        'device_index' => [
            'type' => 'uint8',
            0 => 'creator', // Creator of the file is always device index 0.
        ],
        'gender' => [
            'type' => 'enum',
            0 => 'female',
            1 => 'male',
        ],
        'language' => [
            'type' => 'enum',
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
            26 => 'chinese',
            27 => 'japanese',
            28 => 'korean',
            29 => 'taiwanese',
            30 => 'thai',
            31 => 'hebrew',
            32 => 'brazilian_portuguese',
            33 => 'indonesian',
            34 => 'malaysian',
            35 => 'vietnamese',
            36 => 'burmese',
            37 => 'mongolian',
            254 => 'custom',
        ],
        'language_bits_0' => [
            'type' => 'uint8z',
            0x01 => 'english',
            0x02 => 'french',
            0x04 => 'italian',
            0x08 => 'german',
            0x10 => 'spanish',
            0x20 => 'croatian',
            0x40 => 'czech',
            0x80 => 'danish',
        ],
        'language_bits_1' => [
            'type' => 'uint8z',
            0x01 => 'dutch',
            0x02 => 'finnish',
            0x04 => 'greek',
            0x08 => 'hungarian',
            0x10 => 'norwegian',
            0x20 => 'polish',
            0x40 => 'portuguese',
            0x80 => 'slovakian',
        ],
        'language_bits_2' => [
            'type' => 'uint8z',
            0x01 => 'slovenian',
            0x02 => 'swedish',
            0x04 => 'russian',
            0x08 => 'turkish',
            0x10 => 'latvian',
            0x20 => 'ukrainian',
            0x40 => 'arabic',
            0x80 => 'farsi',
        ],
        'language_bits_3' => [
            'type' => 'uint8z',
            0x01 => 'bulgarian',
            0x02 => 'romanian',
            0x04 => 'chinese',
            0x08 => 'japanese',
            0x10 => 'korean',
            0x20 => 'taiwanese',
            0x40 => 'thai',
            0x80 => 'hebrew',
        ],
        'language_bits_4' => [
            'type' => 'uint8z',
            0x01 => 'brazilian_portuguese',
            0x02 => 'indonesian',
            0x04 => 'malaysian',
            0x08 => 'vietnamese',
            0x10 => 'burmese',
            0x20 => 'mongolian',
        ],
        'time_zone' => [
            'type' => 'enum',
            0 => 'almaty',
            1 => 'bangkok',
            2 => 'bombay',
            3 => 'brasilia',
            4 => 'cairo',
            5 => 'cape_verde_is',
            6 => 'darwin',
            7 => 'eniwetok',
            8 => 'fiji',
            9 => 'hong_kong',
            10 => 'islamabad',
            11 => 'kabul',
            12 => 'magadan',
            13 => 'mid_atlantic',
            14 => 'moscow',
            15 => 'muscat',
            16 => 'newfoundland',
            17 => 'samoa',
            18 => 'sydney',
            19 => 'tehran',
            20 => 'tokyo',
            21 => 'us_alaska',
            22 => 'us_atlantic',
            23 => 'us_central',
            24 => 'us_eastern',
            25 => 'us_hawaii',
            26 => 'us_mountain',
            27 => 'us_pacific',
            28 => 'other',
            29 => 'auckland',
            30 => 'kathmandu',
            31 => 'europe_western_wet',
            32 => 'europe_central_cet',
            33 => 'europe_eastern_eet',
            34 => 'jakarta',
            35 => 'perth',
            36 => 'adelaide',
            37 => 'brisbane',
            38 => 'tasmania',
            39 => 'iceland',
            40 => 'amsterdam',
            41 => 'athens',
            42 => 'barcelona',
            43 => 'berlin',
            44 => 'brussels',
            45 => 'budapest',
            46 => 'copenhagen',
            47 => 'dublin',
            48 => 'helsinki',
            49 => 'lisbon',
            50 => 'london',
            51 => 'madrid',
            52 => 'munich',
            53 => 'oslo',
            54 => 'paris',
            55 => 'prague',
            56 => 'reykjavik',
            57 => 'rome',
            58 => 'stockholm',
            59 => 'vienna',
            60 => 'warsaw',
            61 => 'zurich',
            62 => 'quebec',
            63 => 'ontario',
            64 => 'manitoba',
            65 => 'saskatchewan',
            66 => 'alberta',
            67 => 'british_columbia',
            68 => 'boise',
            69 => 'boston',
            70 => 'chicago',
            71 => 'dallas',
            72 => 'denver',
            73 => 'kansas_city',
            74 => 'las_vegas',
            75 => 'los_angeles',
            76 => 'miami',
            77 => 'minneapolis',
            78 => 'new_york',
            79 => 'new_orleans',
            80 => 'phoenix',
            81 => 'santa_fe',
            82 => 'seattle',
            83 => 'washington_dc',
            84 => 'us_arizona',
            85 => 'chita',
            86 => 'ekaterinburg',
            87 => 'irkutsk',
            88 => 'kaliningrad',
            89 => 'krasnoyarsk',
            90 => 'novosibirsk',
            91 => 'petropavlovsk_kamchatskiy',
            92 => 'samara',
            93 => 'vladivostok',
            94 => 'mexico_central',
            95 => 'mexico_mountain',
            96 => 'mexico_pacific',
            97 => 'cape_town',
            98 => 'winkhoek',
            99 => 'lagos',
            100 => 'riyahd',
            101 => 'venezuela',
            102 => 'australia_lh',
            103 => 'santiago',
            253 => 'manual',
            254 => 'automatic',
        ],
        'display_measure' => [
            'type' => 'enum',
            0 => 'metric',
            1 => 'statute',
            2 => 'nautical',
        ],
        'display_heart' => [
            'type' => 'enum',
            0 => 'bpm',
            1 => 'max',
            2 => 'reserve',
        ],
        'display_power' => [
            'type' => 'enum',
            0 => 'watts',
            1 => 'percent_ftp',
        ],
        'display_position' => [
            'type' => 'enum',
            0 => 'degree', // dd.dddddd
            1 => 'degree_minute', // dddmm.mmm
            2 => 'degree_minute_second', // dddmmss
            3 => 'austrian_grid', // Austrian Grid (BMN)
            4 => 'british_grid', // British National Grid
            5 => 'dutch_grid', // Dutch grid system
            6 => 'hungarian_grid', // Hungarian grid system
            7 => 'finnish_grid', // Finnish grid system Zone3 KKJ27
            8 => 'german_grid', // Gausss Krueger (German)
            9 => 'icelandic_grid', // Icelandic Grid
            10 => 'indonesian_equatorial', // Indonesian Equatorial LCO
            11 => 'indonesian_irian', // Indonesian Irian LCO
            12 => 'indonesian_southern', // Indonesian Southern LCO
            13 => 'india_zone_0', // India zone 0
            14 => 'india_zone_IA', // India zone IA
            15 => 'india_zone_IB', // India zone IB
            16 => 'india_zone_IIA', // India zone IIA
            17 => 'india_zone_IIB', // India zone IIB
            18 => 'india_zone_IIIA', // India zone IIIA
            19 => 'india_zone_IIIB', // India zone IIIB
            20 => 'india_zone_IVA', // India zone IVA
            21 => 'india_zone_IVB', // India zone IVB
            22 => 'irish_transverse', // Irish Transverse Mercator
            23 => 'irish_grid', // Irish Grid
            24 => 'loran', // Loran TD
            25 => 'maidenhead_grid', // Maidenhead grid system
            26 => 'mgrs_grid', // MGRS grid system
            27 => 'new_zealand_grid', // New Zealand grid system
            28 => 'new_zealand_transverse', // New Zealand Transverse Mercator
            29 => 'qatar_grid', // Qatar National Grid
            30 => 'modified_swedish_grid', // Modified RT-90 (Sweden)
            31 => 'swedish_grid', // RT-90 (Sweden)
            32 => 'south_african_grid', // South African Grid
            33 => 'swiss_grid', // Swiss CH-1903 grid
            34 => 'taiwan_grid', // Taiwan Grid
            35 => 'united_states_grid', // United States National Grid
            36 => 'utm_ups_grid', // UTM/UPS grid system
            37 => 'west_malayan', // West Malayan RSO
            38 => 'borneo_rso', // Borneo RSO
            39 => 'estonian_grid', // Estonian grid system
            40 => 'latvian_grid', // Latvian Transverse Mercator
            41 => 'swedish_ref_99_grid', // Reference Grid 99 TM (Swedish)
        ],
        'switch' => [
            'type' => 'enum',
            0 => 'off',
            1 => 'on',
            2 => 'auto',
        ],
        'sport' => [
            'type' => 'enum',
            0 => 'generic',
            1 => 'running',
            2 => 'cycling',
            3 => 'transition', // Mulitsport transition
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
            20 => 'flying',
            21 => 'e_biking',
            22 => 'motorcycling',
            23 => 'boating',
            24 => 'driving',
            25 => 'golf',
            26 => 'hang_gliding',
            27 => 'horseback_riding',
            28 => 'hunting',
            29 => 'fishing',
            30 => 'inline_skating',
            31 => 'rock_climbing',
            32 => 'sailing',
            33 => 'ice_skating',
            34 => 'sky_diving',
            35 => 'snowshoeing',
            36 => 'snowmobiling',
            37 => 'stand_up_paddleboarding',
            38 => 'surfing',
            39 => 'wakeboarding',
            40 => 'water_skiing',
            41 => 'kayaking',
            42 => 'rafting',
            43 => 'windsurfing',
            44 => 'kitesurfing',
            45 => 'tactical',
            46 => 'jumpmaster',
            47 => 'boxing',
            48 => 'floor_climbing',
            53 => 'diving',
            64 => 'racket',
            76 => 'water_tubing',
            77 => 'wakesurfing',
            254 => 'all', // All is for goals only to include all sports.
        ],
        'sport_bits_0' => [
            'type' => 'uint8z',
            0x01 => 'generic',
            0x02 => 'running',
            0x04 => 'cycling',
            0x08 => 'transition', // Mulitsport transition
            0x10 => 'fitness_equipment',
            0x20 => 'swimming',
            0x40 => 'basketball',
            0x80 => 'soccer',
        ],
        'sport_bits_1' => [
            'type' => 'uint8z',
            0x01 => 'tennis',
            0x02 => 'american_football',
            0x04 => 'training',
            0x08 => 'walking',
            0x10 => 'cross_country_skiing',
            0x20 => 'alpine_skiing',
            0x40 => 'snowboarding',
            0x80 => 'rowing',
        ],
        'sport_bits_2' => [
            'type' => 'uint8z',
            0x01 => 'mountaineering',
            0x02 => 'hiking',
            0x04 => 'multisport',
            0x08 => 'paddling',
            0x10 => 'flying',
            0x20 => 'e_biking',
            0x40 => 'motorcycling',
            0x80 => 'boating',
        ],
        'sport_bits_3' => [
            'type' => 'uint8z',
            0x01 => 'driving',
            0x02 => 'golf',
            0x04 => 'hang_gliding',
            0x08 => 'horseback_riding',
            0x10 => 'hunting',
            0x20 => 'fishing',
            0x40 => 'inline_skating',
            0x80 => 'rock_climbing',
        ],
        'sport_bits_4' => [
            'type' => 'uint8z',
            0x01 => 'sailing',
            0x02 => 'ice_skating',
            0x04 => 'sky_diving',
            0x08 => 'snowshoeing',
            0x10 => 'snowmobiling',
            0x20 => 'stand_up_paddleboarding',
            0x40 => 'surfing',
            0x80 => 'wakeboarding',
        ],
        'sport_bits_5' => [
            'type' => 'uint8z',
            0x01 => 'water_skiing',
            0x02 => 'kayaking',
            0x04 => 'rafting',
            0x08 => 'windsurfing',
            0x10 => 'kitesurfing',
            0x20 => 'tactical',
            0x40 => 'jumpmaster',
            0x80 => 'boxing',
        ],
        'sport_bits_6' => [
            'type' => 'uint8z',
            0x01 => 'floor_climbing',
        ],
        'sub_sport' => [
            'type' => 'enum',
            0 => 'generic',
            1 => 'treadmill', // Run/Fitness Equipment
            2 => 'street', // Run
            3 => 'trail', // Run
            4 => 'track', // Run
            5 => 'spin', // Cycling
            6 => 'indoor_cycling', // Cycling/Fitness Equipment
            7 => 'road', // Cycling
            8 => 'mountain', // Cycling
            9 => 'downhill', // Cycling
            10 => 'recumbent', // Cycling
            11 => 'cyclocross', // Cycling
            12 => 'hand_cycling', // Cycling
            13 => 'track_cycling', // Cycling
            14 => 'indoor_rowing', // Fitness Equipment
            15 => 'elliptical', // Fitness Equipment
            16 => 'stair_climbing', // Fitness Equipment
            17 => 'lap_swimming', // Swimming
            18 => 'open_water', // Swimming
            19 => 'flexibility_training', // Training
            20 => 'strength_training', // Training
            21 => 'warm_up', // Tennis
            22 => 'match', // Tennis
            23 => 'exercise', // Tennis
            24 => 'challenge',
            25 => 'indoor_skiing', // Fitness Equipment
            26 => 'cardio_training', // Training
            27 => 'indoor_walking', // Walking/Fitness Equipment
            28 => 'e_bike_fitness', // E-Biking
            29 => 'bmx', // Cycling
            30 => 'casual_walking', // Walking
            31 => 'speed_walking', // Walking
            32 => 'bike_to_run_transition', // Transition
            33 => 'run_to_bike_transition', // Transition
            34 => 'swim_to_bike_transition', // Transition
            35 => 'atv', // Motorcycling
            36 => 'motocross', // Motorcycling
            37 => 'backcountry', // Alpine Skiing/Snowboarding
            38 => 'resort', // Alpine Skiing/Snowboarding
            39 => 'rc_drone', // Flying
            40 => 'wingsuit', // Flying
            41 => 'whitewater', // Kayaking/Rafting
            42 => 'skate_skiing', // Cross Country Skiing
            43 => 'yoga', // Training
            44 => 'pilates', // Fitness Equipment
            45 => 'indoor_running', // Run
            46 => 'gravel_cycling', // Cycling
            47 => 'e_bike_mountain', // Cycling
            48 => 'commuting', // Cycling
            49 => 'mixed_surface', // Cycling
            50 => 'navigate',
            51 => 'track_me',
            52 => 'map',
            53 => 'single_gas_diving', // Diving
            54 => 'multi_gas_diving', // Diving
            55 => 'gauge_diving', // Diving
            56 => 'apnea_diving', // Diving
            57 => 'apnea_hunting', // Diving
            58 => 'virtual_activity',
            59 => 'obstacle', // Used for events where participants run, crawl through mud, climb over walls, etc.
            62 => 'breathing',
            65 => 'sail_race', // Sailing
            67 => 'ultra', // Ultramarathon
            68 => 'indoor_climbing', // Climbing
            69 => 'bouldering', // Climbing
            84 => 'pickleball', // Racket
            85 => 'padel', // Racket
            254 => 'all',
        ],
        'sport_event' => [
            'type' => 'enum',
            0 => 'uncategorized',
            1 => 'geocaching',
            2 => 'fitness',
            3 => 'recreation',
            4 => 'race',
            5 => 'special_event',
            6 => 'training',
            7 => 'transportation',
            8 => 'touring',
        ],
        'activity' => [
            'type' => 'enum',
            0 => 'manual',
            1 => 'auto_multi_sport',
        ],
        'intensity' => [
            'type' => 'enum',
            0 => 'active',
            1 => 'rest',
            2 => 'warmup',
            3 => 'cooldown',
            4 => 'recovery',
            5 => 'interval',
            6 => 'other',
        ],
        'session_trigger' => [
            'type' => 'enum',
            0 => 'activity_end',
            1 => 'manual', // User changed sport.
            2 => 'auto_multi_sport', // Auto multi-sport feature is enabled and user pressed lap button to advance session.
            3 => 'fitness_equipment', // Auto sport change caused by user linking to fitness equipment.
        ],
        'autolap_trigger' => [
            'type' => 'enum',
            0 => 'time',
            1 => 'distance',
            2 => 'position_start',
            3 => 'position_lap',
            4 => 'position_waypoint',
            5 => 'position_marked',
            6 => 'off',
        ],
        'lap_trigger' => [
            'type' => 'enum',
            0 => 'manual',
            1 => 'time',
            2 => 'distance',
            3 => 'position_start',
            4 => 'position_lap',
            5 => 'position_waypoint',
            6 => 'position_marked',
            7 => 'session_end',
            8 => 'fitness_equipment',
        ],
        'time_mode' => [
            'type' => 'enum',
            0 => 'hour12',
            1 => 'hour24', // Does not use a leading zero and has a colon
            2 => 'military', // Uses a leading zero and does not have a colon
            3 => 'hour_12_with_seconds',
            4 => 'hour_24_with_seconds',
            5 => 'utc',
        ],
        'backlight_mode' => [
            'type' => 'enum',
            0 => 'off',
            1 => 'manual',
            2 => 'key_and_messages',
            3 => 'auto_brightness',
            4 => 'smart_notifications',
            5 => 'key_and_messages_night',
            6 => 'key_and_messages_and_smart_notifications',
        ],
        'date_mode' => [
            'type' => 'enum',
            0 => 'day_month',
            1 => 'month_day',
        ],
        'backlight_timeout' => [
            'type' => 'uint8',
            0 => 'infinite', // Backlight stays on forever.
        ],
        'event' => [
            'type' => 'enum',
            0 => 'timer', // Group 0. Start / stop_all
            3 => 'workout', // start / stop
            4 => 'workout_step', // Start at beginning of workout. Stop at end of each step.
            5 => 'power_down', // stop_all group 0
            6 => 'power_up', // stop_all group 0
            7 => 'off_course', // start / stop group 0
            8 => 'session', // Stop at end of each session.
            9 => 'lap', // Stop at end of each lap.
            10 => 'course_point', // marker
            11 => 'battery', // marker
            12 => 'virtual_partner_pace', // Group 1. Start at beginning of activity if VP enabled, when VP pace is changed during activity or VP enabled mid activity. stop_disable when VP disabled.
            13 => 'hr_high_alert', // Group 0. Start / stop when in alert condition.
            14 => 'hr_low_alert', // Group 0. Start / stop when in alert condition.
            15 => 'speed_high_alert', // Group 0. Start / stop when in alert condition.
            16 => 'speed_low_alert', // Group 0. Start / stop when in alert condition.
            17 => 'cad_high_alert', // Group 0. Start / stop when in alert condition.
            18 => 'cad_low_alert', // Group 0. Start / stop when in alert condition.
            19 => 'power_high_alert', // Group 0. Start / stop when in alert condition.
            20 => 'power_low_alert', // Group 0. Start / stop when in alert condition.
            21 => 'recovery_hr', // marker
            22 => 'battery_low', // marker
            23 => 'time_duration_alert', // Group 1. Start if enabled mid activity (not required at start of activity). Stop when duration is reached. stop_disable if disabled.
            24 => 'distance_duration_alert', // Group 1. Start if enabled mid activity (not required at start of activity). Stop when duration is reached. stop_disable if disabled.
            25 => 'calorie_duration_alert', // Group 1. Start if enabled mid activity (not required at start of activity). Stop when duration is reached. stop_disable if disabled.
            26 => 'activity', // Group 1.. Stop at end of activity.
            27 => 'fitness_equipment', // marker
            28 => 'length', // Stop at end of each length.
            32 => 'user_marker', // marker
            33 => 'sport_point', // marker
            36 => 'calibration', // start/stop/marker
            42 => 'front_gear_change', // marker
            43 => 'rear_gear_change', // marker
            44 => 'rider_position_change', // marker
            45 => 'elev_high_alert', // Group 0. Start / stop when in alert condition.
            46 => 'elev_low_alert', // Group 0. Start / stop when in alert condition.
            47 => 'comm_timeout', // marker
            75 => 'radar_threat_alert', // start/stop/marker
        ],
        'event_type' => [
            'type' => 'enum',
            0 => 'start',
            1 => 'stop',
            2 => 'consecutive_depreciated',
            3 => 'marker',
            4 => 'stop_all',
            5 => 'begin_depreciated',
            6 => 'end_depreciated',
            7 => 'end_all_depreciated',
            8 => 'stop_disable',
            9 => 'stop_disable_all',
        ],
        'timer_trigger' => [
            'type' => 'enum',
            0 => 'manual',
            1 => 'auto',
            2 => 'fitness_equipment',
        ],
        'fitness_equipment_state' => [
            'type' => 'enum',
            0 => 'ready',
            1 => 'in_use',
            2 => 'paused',
            3 => 'unknown', // lost connection to fitness equipment
        ],
        'tone' => [
            'type' => 'enum',
            0 => 'off',
            1 => 'tone',
            2 => 'vibrate',
            3 => 'tone_and_vibrate',
        ],
        'autoscroll' => [
            'type' => 'enum',
            0 => 'none',
            1 => 'slow',
            2 => 'medium',
            3 => 'fast',
        ],
        'activity_class' => [
            'type' => 'enum',
            0x7F => 'level', // 0 to 100
            100 => 'level_max',
            0x80 => 'athlete',
        ],
        'hr_zone_calc' => [
            'type' => 'enum',
            0 => 'custom',
            1 => 'percent_max_hr',
            2 => 'percent_hrr',
            3 => 'percent_lthr',
        ],
        'pwr_zone_calc' => [
            'type' => 'enum',
            0 => 'custom',
            1 => 'percent_ftp',
        ],
        'wkt_step_duration' => [
            'type' => 'enum',
            0 => 'time',
            1 => 'distance',
            2 => 'hr_less_than',
            3 => 'hr_greater_than',
            4 => 'calories',
            5 => 'open',
            6 => 'repeat_until_steps_cmplt',
            7 => 'repeat_until_time',
            8 => 'repeat_until_distance',
            9 => 'repeat_until_calories',
            10 => 'repeat_until_hr_less_than',
            11 => 'repeat_until_hr_greater_than',
            12 => 'repeat_until_power_less_than',
            13 => 'repeat_until_power_greater_than',
            14 => 'power_less_than',
            15 => 'power_greater_than',
            16 => 'training_peaks_tss',
            17 => 'repeat_until_power_last_lap_less_than',
            18 => 'repeat_until_max_power_last_lap_less_than',
            19 => 'power_3s_less_than',
            20 => 'power_10s_less_than',
            21 => 'power_30s_less_than',
            22 => 'power_3s_greater_than',
            23 => 'power_10s_greater_than',
            24 => 'power_30s_greater_than',
            25 => 'power_lap_less_than',
            26 => 'power_lap_greater_than',
            27 => 'repeat_until_training_peaks_tss',
            28 => 'repetition_time',
            29 => 'reps',
            31 => 'time_only',
        ],
        'wkt_step_target' => [
            'type' => 'enum',
            0 => 'speed',
            1 => 'heart_rate',
            2 => 'open',
            3 => 'cadence',
            4 => 'power',
            5 => 'grade',
            6 => 'resistance',
            7 => 'power_3s',
            8 => 'power_10s',
            9 => 'power_30s',
            10 => 'power_lap',
            11 => 'swim_stroke',
            12 => 'speed_lap',
            13 => 'heart_rate_lap',
        ],
        'goal' => [
            'type' => 'enum',
            0 => 'time',
            1 => 'distance',
            2 => 'calories',
            3 => 'frequency',
            4 => 'steps',
            5 => 'ascent',
            6 => 'active_minutes',
        ],
        'goal_recurrence' => [
            'type' => 'enum',
            0 => 'off',
            1 => 'daily',
            2 => 'weekly',
            3 => 'monthly',
            4 => 'yearly',
            5 => 'custom',
        ],
        'goal_source' => [
            'type' => 'enum',
            0 => 'auto', // Device generated
            1 => 'community', // Social network sourced goal
            2 => 'user', // Manually generated
        ],
        'schedule' => [
            'type' => 'enum',
            0 => 'workout',
            1 => 'course',
        ],
        'course_point' => [
            'type' => 'enum',
            0 => 'generic',
            1 => 'summit',
            2 => 'valley',
            3 => 'water',
            4 => 'food',
            5 => 'danger',
            6 => 'left',
            7 => 'right',
            8 => 'straight',
            9 => 'first_aid',
            10 => 'fourth_category',
            11 => 'third_category',
            12 => 'second_category',
            13 => 'first_category',
            14 => 'hors_category',
            15 => 'sprint',
            16 => 'left_fork',
            17 => 'right_fork',
            18 => 'middle_fork',
            19 => 'slight_left',
            20 => 'sharp_left',
            21 => 'slight_right',
            22 => 'sharp_right',
            23 => 'u_turn',
            24 => 'segment_start',
            25 => 'segment_end',
            27 => 'campsite',
            28 => 'aid_station',
            29 => 'rest_area',
            30 => 'general_distance', // Used with UpAhead
            31 => 'service',
            32 => 'energy_gel',
            33 => 'sports_drink',
            34 => 'mile_marker',
            35 => 'checkpoint',
            36 => 'shelter',
            37 => 'meeting_spot',
            38 => 'overlook',
            39 => 'toilet',
            40 => 'shower',
            41 => 'gear',
            42 => 'sharp_curve',
            43 => 'steep_incline',
            44 => 'tunnel',
            45 => 'bridge',
            46 => 'obstacle',
            47 => 'crossing',
            48 => 'store',
            49 => 'transition',
            50 => 'navaid',
            51 => 'transport',
            52 => 'alert',
            53 => 'info',
        ],
        'manufacturer' => [
            'type' => 'uint16',
            1 => 'garmin',
            2 => 'garmin_fr405_antfs', // Do not use. Used by FR405 for ANTFS man id.
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
            41 => 'shimano',
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
            94 => 'sound_of_motion',
            95 => 'stryd',
            96 => 'icg', // Indoorcycling Group
            97 => 'MiPulse',
            98 => 'bsx_athletics',
            99 => 'look',
            100 => 'campagnolo_srl',
            101 => 'body_bike_smart',
            102 => 'praxisworks',
            103 => 'limits_technology', // Limits Technology Ltd.
            104 => 'topaction_technology', // TopAction Technology Inc.
            105 => 'cosinuss',
            106 => 'fitcare',
            107 => 'magene',
            108 => 'giant_manufacturing_co',
            109 => 'tigrasport', // Tigrasport
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
            129 => 'coros_byte',
            130 => 'versa_design',
            131 => 'chileaf',
            132 => 'cycplus',
            133 => 'gravaa_byte',
            134 => 'sigeyi',
            135 => 'coospo',
            136 => 'geoid',
            137 => 'bosch',
            138 => 'kyto',
            139 => 'kinetic_sports',
            140 => 'decathlon_byte',
            141 => 'tq_systems',
            142 => 'tag_heuer',
            143 => 'keiser_fitness',
            144 => 'zwift_byte',
            145 => 'porsche_ep',
            255 => 'development',
            257 => 'healthandlife',
            258 => 'lezyne',
            259 => 'scribe_labs',
            260 => 'zwift',
            261 => 'watteam',
            262 => 'recon',
            263 => 'favero_electronics',
            264 => 'dynovelo',
            265 => 'strava',
            266 => 'precor', // Amer Sports
            267 => 'bryton',
            268 => 'sram',
            269 => 'navman', // MiTAC Global Corporation (Mio Technology)
            270 => 'cobi', // COBI GmbH
            271 => 'spivi',
            272 => 'mio_magellan',
            273 => 'evesports',
            274 => 'sensitivus_gauge',
            275 => 'podoon',
            276 => 'life_time_fitness',
            277 => 'falco_e_motors', // Falco eMotors Inc.
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
            300 => 'nurvv',
            301 => 'microprogram',
            302 => 'zone5cloud',
            303 => 'greenteg',
            304 => 'yamaha_motors',
            305 => 'whoop',
            306 => 'gravaa',
            307 => 'onelap',
            308 => 'monark_exercise',
            309 => 'form',
            310 => 'decathlon',
            311 => 'syncros',
            312 => 'heatup',
            313 => 'cannondale',
            314 => 'true_fitness',
            315 => 'RGT_cycling',
            316 => 'vasa',
            317 => 'race_republic',
            318 => 'fazua',
            319 => 'oreka_training',
            320 => 'lsec', // Lishun Electric & Communication
            321 => 'lululemon_studio',
            322 => 'shanyue',
            5759 => 'actigraphcorp',
        ],
        'garmin_product' => [
            'type' => 'uint16',
            1 => 'hrm1',
            2 => 'axh01', // AXH01 HRM chipset
            3 => 'axb01',
            4 => 'axb02',
            5 => 'hrm2ss',
            6 => 'dsi_alf02',
            7 => 'hrm3ss',
            8 => 'hrm_run_single_byte_product_id', // hrm_run model for HRM ANT+ messaging
            9 => 'bsm', // BSM model for ANT+ messaging
            10 => 'bcm', // BCM model for ANT+ messaging
            11 => 'axs01', // AXS01 HRM Bike Chipset model for ANT+ messaging
            12 => 'hrm_tri_single_byte_product_id', // hrm_tri model for HRM ANT+ messaging
            13 => 'hrm4_run_single_byte_product_id', // hrm4 run model for HRM ANT+ messaging
            14 => 'fr225_single_byte_product_id', // fr225 model for HRM ANT+ messaging
            15 => 'gen3_bsm_single_byte_product_id', // gen3_bsm model for Bike Speed ANT+ messaging
            16 => 'gen3_bcm_single_byte_product_id', // gen3_bcm model for Bike Cadence ANT+ messaging
            255 => 'OHR', // Garmin Wearable Optical Heart Rate Sensor for ANT+ HR Profile Broadcasting
            473 => 'fr301_china',
            474 => 'fr301_japan',
            475 => 'fr301_korea',
            494 => 'fr301_taiwan',
            717 => 'fr405', // Forerunner 405
            782 => 'fr50', // Forerunner 50
            987 => 'fr405_japan',
            988 => 'fr60', // Forerunner 60
            1011 => 'dsi_alf01',
            1018 => 'fr310xt', // Forerunner 310
            1036 => 'edge500',
            1124 => 'fr110', // Forerunner 110
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
            1405 => 'approach_g10',
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
            1736 => 'edge_touring', // Also Edge Touring Plus
            1742 => 'edge510_japan',
            1743 => 'hrm_tri', // Also HRM-Swim
            1752 => 'hrm_run',
            1765 => 'fr920xt',
            1821 => 'edge510_asia',
            1822 => 'edge810_china',
            1823 => 'edge810_taiwan',
            1836 => 'edge1000',
            1837 => 'vivo_fit',
            1853 => 'virb_remote',
            1885 => 'vivo_ki',
            1903 => 'fr15',
            1907 => 'vivo_active',
            1918 => 'edge510_korea',
            1928 => 'fr620_japan',
            1929 => 'fr620_china',
            1930 => 'fr220_japan',
            1931 => 'fr220_china',
            1936 => 'approach_s6',
            1956 => 'vivo_smart',
            1967 => 'fenix2',
            1988 => 'epix',
            2050 => 'fenix3',
            2052 => 'edge1000_taiwan',
            2053 => 'edge1000_japan',
            2061 => 'fr15_japan',
            2067 => 'edge520',
            2070 => 'edge1000_china',
            2072 => 'fr620_russia',
            2073 => 'fr220_russia',
            2079 => 'vector_s',
            2100 => 'edge1000_korea',
            2130 => 'fr920xt_taiwan',
            2131 => 'fr920xt_china',
            2132 => 'fr920xt_japan',
            2134 => 'virbx',
            2135 => 'vivo_smart_apac',
            2140 => 'etrex_touch',
            2147 => 'edge25',
            2148 => 'fr25',
            2150 => 'vivo_fit2',
            2153 => 'fr225',
            2156 => 'fr630',
            2157 => 'fr230',
            2158 => 'fr735xt',
            2160 => 'vivo_active_apac',
            2161 => 'vector_2',
            2162 => 'vector_2s',
            2172 => 'virbxe',
            2173 => 'fr620_taiwan',
            2174 => 'fr220_taiwan',
            2175 => 'truswing',
            2187 => 'd2airvenu',
            2188 => 'fenix3_china',
            2189 => 'fenix3_twn',
            2192 => 'varia_headlight',
            2193 => 'varia_taillight_old',
            2204 => 'edge_explore_1000',
            2219 => 'fr225_asia',
            2225 => 'varia_radar_taillight',
            2226 => 'varia_radar_display',
            2238 => 'edge20',
            2260 => 'edge520_asia',
            2261 => 'edge520_japan',
            2262 => 'd2_bravo',
            2266 => 'approach_s20',
            2271 => 'vivo_smart2',
            2274 => 'edge1000_thai',
            2276 => 'varia_remote',
            2288 => 'edge25_asia',
            2289 => 'edge25_jpn',
            2290 => 'edge20_asia',
            2292 => 'approach_x40',
            2293 => 'fenix3_japan',
            2294 => 'vivo_smart_emea',
            2310 => 'fr630_asia',
            2311 => 'fr630_jpn',
            2313 => 'fr230_jpn',
            2327 => 'hrm4_run',
            2332 => 'epix_japan',
            2337 => 'vivo_active_hr',
            2347 => 'vivo_smart_gps_hr',
            2348 => 'vivo_smart_hr',
            2361 => 'vivo_smart_hr_asia',
            2362 => 'vivo_smart_gps_hr_asia',
            2368 => 'vivo_move',
            2379 => 'varia_taillight',
            2396 => 'fr235_asia',
            2397 => 'fr235_japan',
            2398 => 'varia_vision',
            2406 => 'vivo_fit3',
            2407 => 'fenix3_korea',
            2408 => 'fenix3_sea',
            2413 => 'fenix3_hr',
            2417 => 'virb_ultra_30',
            2429 => 'index_smart_scale',
            2431 => 'fr235',
            2432 => 'fenix3_chronos',
            2441 => 'oregon7xx',
            2444 => 'rino7xx',
            2457 => 'epix_korea',
            2473 => 'fenix3_hr_chn',
            2474 => 'fenix3_hr_twn',
            2475 => 'fenix3_hr_jpn',
            2476 => 'fenix3_hr_sea',
            2477 => 'fenix3_hr_kor',
            2496 => 'nautix',
            2497 => 'vivo_active_hr_apac',
            2512 => 'oregon7xx_ww',
            2530 => 'edge_820',
            2531 => 'edge_explore_820',
            2533 => 'fr735xt_apac',
            2534 => 'fr735xt_japan',
            2544 => 'fenix5s',
            2547 => 'd2_bravo_titanium',
            2567 => 'varia_ut800', // Varia UT 800 SW
            2593 => 'running_dynamics_pod',
            2599 => 'edge_820_china',
            2600 => 'edge_820_japan',
            2604 => 'fenix5x',
            2606 => 'vivo_fit_jr',
            2622 => 'vivo_smart3',
            2623 => 'vivo_sport',
            2628 => 'edge_820_taiwan',
            2629 => 'edge_820_korea',
            2630 => 'edge_820_sea',
            2650 => 'fr35_hebrew',
            2656 => 'approach_s60',
            2667 => 'fr35_apac',
            2668 => 'fr35_japan',
            2675 => 'fenix3_chronos_asia',
            2687 => 'virb_360',
            2691 => 'fr935',
            2697 => 'fenix5',
            2700 => 'vivoactive3',
            2733 => 'fr235_china_nfc',
            2769 => 'foretrex_601_701',
            2772 => 'vivo_move_hr',
            2713 => 'edge_1030',
            2727 => 'fr35_sea',
            2787 => 'vector_3',
            2796 => 'fenix5_asia',
            2797 => 'fenix5s_asia',
            2798 => 'fenix5x_asia',
            2806 => 'approach_z80',
            2814 => 'fr35_korea',
            2819 => 'd2charlie',
            2831 => 'vivo_smart3_apac',
            2832 => 'vivo_sport_apac',
            2833 => 'fr935_asia',
            2859 => 'descent',
            2878 => 'vivo_fit4',
            2886 => 'fr645',
            2888 => 'fr645m',
            2891 => 'fr30',
            2900 => 'fenix5s_plus',
            2909 => 'Edge_130',
            2924 => 'edge_1030_asia',
            2927 => 'vivosmart_4',
            2945 => 'vivo_move_hr_asia',
            2962 => 'approach_x10',
            2977 => 'fr30_asia',
            2988 => 'vivoactive3m_w',
            3003 => 'fr645_asia',
            3004 => 'fr645m_asia',
            3011 => 'edge_explore',
            3028 => 'gpsmap66',
            3049 => 'approach_s10',
            3066 => 'vivoactive3m_l',
            3085 => 'approach_g80',
            3092 => 'edge_130_asia',
            3095 => 'edge_1030_bontrager',
            3110 => 'fenix5_plus',
            3111 => 'fenix5x_plus',
            3112 => 'edge_520_plus',
            3113 => 'fr945',
            3121 => 'edge_530',
            3122 => 'edge_830',
            3126 => 'instinct_esports',
            3134 => 'fenix5s_plus_apac',
            3135 => 'fenix5x_plus_apac',
            3142 => 'edge_520_plus_apac',
            3144 => 'fr235l_asia',
            3145 => 'fr245_asia',
            3163 => 'vivo_active3m_apac',
            3192 => 'gen3_bsm', // gen3 bike speed sensor
            3193 => 'gen3_bcm', // gen3 bike cadence sensor
            3218 => 'vivo_smart4_asia',
            3224 => 'vivoactive4_small',
            3225 => 'vivoactive4_large',
            3226 => 'venu',
            3246 => 'marq_driver',
            3247 => 'marq_aviator',
            3248 => 'marq_captain',
            3249 => 'marq_commander',
            3250 => 'marq_expedition',
            3251 => 'marq_athlete',
            3258 => 'descent_mk2',
            3284 => 'gpsmap66i',
            3287 => 'fenix6S_sport',
            3288 => 'fenix6S',
            3289 => 'fenix6_sport',
            3290 => 'fenix6',
            3291 => 'fenix6x',
            3299 => 'hrm_dual', // HRM-Dual
            3300 => 'hrm_pro', // HRM-Pro
            3308 => 'vivo_move3_premium',
            3314 => 'approach_s40',
            3321 => 'fr245m_asia',
            3349 => 'edge_530_apac',
            3350 => 'edge_830_apac',
            3378 => 'vivo_move3',
            3387 => 'vivo_active4_small_asia',
            3388 => 'vivo_active4_large_asia',
            3389 => 'vivo_active4_oled_asia',
            3405 => 'swim2',
            3420 => 'marq_driver_asia',
            3421 => 'marq_aviator_asia',
            3422 => 'vivo_move3_asia',
            3441 => 'fr945_asia',
            3446 => 'vivo_active3t_chn',
            3448 => 'marq_captain_asia',
            3449 => 'marq_commander_asia',
            3450 => 'marq_expedition_asia',
            3451 => 'marq_athlete_asia',
            3466 => 'instinct_solar',
            3469 => 'fr45_asia',
            3473 => 'vivoactive3_daimler',
            3498 => 'legacy_rey',
            3499 => 'legacy_darth_vader',
            3500 => 'legacy_captain_marvel',
            3501 => 'legacy_first_avenger',
            3512 => 'fenix6s_sport_asia',
            3513 => 'fenix6s_asia',
            3514 => 'fenix6_sport_asia',
            3515 => 'fenix6_asia',
            3516 => 'fenix6x_asia',
            3535 => 'legacy_captain_marvel_asia',
            3536 => 'legacy_first_avenger_asia',
            3537 => 'legacy_rey_asia',
            3538 => 'legacy_darth_vader_asia',
            3542 => 'descent_mk2s',
            3558 => 'edge_130_plus',
            3570 => 'edge_1030_plus',
            3578 => 'rally_200', // Rally 100/200 Power Meter Series
            3589 => 'fr745',
            3600 => 'venusq',
            3615 => 'lily',
            3624 => 'marq_adventurer',
            3638 => 'enduro',
            3639 => 'swim2_apac',
            3648 => 'marq_adventurer_asia',
            3652 => 'fr945_lte',
            3702 => 'descent_mk2_asia', // Mk2 and Mk2i
            3703 => 'venu2',
            3704 => 'venu2s',
            3737 => 'venu_daimler_asia',
            3739 => 'marq_golfer',
            3740 => 'venu_daimler',
            3794 => 'fr745_asia',
            3809 => 'lily_asia',
            3812 => 'edge_1030_plus_asia',
            3813 => 'edge_130_plus_asia',
            3823 => 'approach_s12',
            3872 => 'enduro_asia',
            3837 => 'venusq_asia',
            3843 => 'edge_1040',
            3850 => 'marq_golfer_asia',
            3851 => 'venu2_plus',
            3869 => 'fr55',
            3888 => 'instinct_2',
            3905 => 'fenix7s',
            3906 => 'fenix7',
            3907 => 'fenix7x',
            3908 => 'fenix7s_apac',
            3909 => 'fenix7_apac',
            3910 => 'fenix7x_apac',
            3927 => 'approach_g12',
            3930 => 'descent_mk2s_asia',
            3934 => 'approach_s42',
            3943 => 'epix_gen2',
            3944 => 'epix_gen2_apac',
            3949 => 'venu2s_asia',
            3950 => 'venu2_asia',
            3978 => 'fr945_lte_asia',
            3982 => 'vivo_move_sport',
            3986 => 'approach_S12_asia',
            3990 => 'fr255_music',
            3991 => 'fr255_small_music',
            3992 => 'fr255',
            3993 => 'fr255_small',
            4001 => 'approach_g12_asia',
            4002 => 'approach_s42_asia',
            4005 => 'descent_g1',
            4017 => 'venu2_plus_asia',
            4024 => 'fr955',
            4033 => 'fr55_asia',
            4063 => 'vivosmart_5',
            4071 => 'instinct_2_asia',
            4105 => 'marq_gen2', // Adventurer, Athlete, Captain, Golfer
            4115 => 'venusq2',
            4116 => 'venusq2music',
            4124 => 'marq_gen2_aviator',
            4125 => 'd2_air_x10',
            4130 => 'hrm_pro_plus',
            4132 => 'descent_g1_asia',
            4135 => 'tactix7',
            4155 => 'instinct_crossover',
            4169 => 'edge_explore2',
            4265 => 'tacx_neo_smart', // Neo Smart, Tacx
            4266 => 'tacx_neo2_smart', // Neo 2 Smart, Tacx
            4267 => 'tacx_neo2_t_smart', // Neo 2T Smart, Tacx
            4268 => 'tacx_neo_smart_bike', // Neo Smart Bike, Tacx
            4269 => 'tacx_satori_smart', // Satori Smart, Tacx
            4270 => 'tacx_flow_smart', // Flow Smart, Tacx
            4271 => 'tacx_vortex_smart', // Vortex Smart, Tacx
            4272 => 'tacx_bushido_smart', // Bushido Smart, Tacx
            4273 => 'tacx_genius_smart', // Genius Smart, Tacx
            4274 => 'tacx_flux_flux_s_smart', // Flux/Flux S Smart, Tacx
            4275 => 'tacx_flux2_smart', // Flux 2 Smart, Tacx
            4276 => 'tacx_magnum', // Magnum, Tacx
            4305 => 'edge_1040_asia',
            4341 => 'enduro2',
            10007 => 'sdm4', // SDM4 footpod
            10014 => 'edge_remote',
            20533 => 'tacx_training_app_win',
            20534 => 'tacx_training_app_mac',
            20565 => 'tacx_training_app_mac_catalyst',
            20119 => 'training_center',
            30045 => 'tacx_training_app_android',
            30046 => 'tacx_training_app_ios',
            30047 => 'tacx_training_app_legacy',
            65531 => 'connectiq_simulator',
            65532 => 'android_antplus_plugin',
            65534 => 'connect', // Garmin Connect website
        ],
        'antplus_device_type' => [
            'type' => 'uint8',
            1 => 'antfs',
            11 => 'bike_power',
            12 => 'environment_sensor_legacy',
            15 => 'multi_sport_speed_distance',
            16 => 'control',
            17 => 'fitness_equipment',
            18 => 'blood_pressure',
            19 => 'geocache_node',
            20 => 'light_electric_vehicle',
            25 => 'env_sensor',
            26 => 'racquet',
            27 => 'control_hub',
            31 => 'muscle_oxygen',
            34 => 'shifting',
            35 => 'bike_light_main',
            36 => 'bike_light_shared',
            38 => 'exd',
            40 => 'bike_radar',
            46 => 'bike_aero',
            119 => 'weight_scale',
            120 => 'heart_rate',
            121 => 'bike_speed_cadence',
            122 => 'bike_cadence',
            123 => 'bike_speed',
            124 => 'stride_speed_distance',
        ],
        'ant_network' => [
            'type' => 'enum',
            0 => 'public',
            1 => 'antplus',
            2 => 'antfs',
            3 => 'private',
        ],
        'workout_capabilities' => [
            'type' => 'uint32z',
            0x00000001 => 'interval',
            0x00000002 => 'custom',
            0x00000004 => 'fitness_equipment',
            0x00000008 => 'firstbeat',
            0x00000010 => 'new_leaf',
            0x00000020 => 'tcx', // For backwards compatibility. Watch should add missing id fields then clear flag.
            0x00000080 => 'speed', // Speed source required for workout step.
            0x00000100 => 'heart_rate', // Heart rate source required for workout step.
            0x00000200 => 'distance', // Distance source required for workout step.
            0x00000400 => 'cadence', // Cadence source required for workout step.
            0x00000800 => 'power', // Power source required for workout step.
            0x00001000 => 'grade', // Grade source required for workout step.
            0x00002000 => 'resistance', // Resistance source required for workout step.
            0x00004000 => 'protected',
        ],
        'battery_status' => [
            'type' => 'uint8',
            1 => 'new',
            2 => 'good',
            3 => 'ok',
            4 => 'low',
            5 => 'critical',
            6 => 'charging',
            7 => 'unknown',
        ],
        'hr_type' => [
            'type' => 'enum',
            0 => 'normal',
            1 => 'irregular',
        ],
        'course_capabilities' => [
            'type' => 'uint32z',
            0x00000001 => 'processed',
            0x00000002 => 'valid',
            0x00000004 => 'time',
            0x00000008 => 'distance',
            0x00000010 => 'position',
            0x00000020 => 'heart_rate',
            0x00000040 => 'power',
            0x00000080 => 'cadence',
            0x00000100 => 'training',
            0x00000200 => 'navigation',
            0x00000400 => 'bikeway',
            0x00001000 => 'aviation', // Denote course files to be used as flight plans
        ],
        'weight' => [
            'type' => 'uint16',
            0xFFFE => 'calculating',
        ],
        'workout_hr' => [
            'type' => 'uint32',
            100 => 'bpm_offset',
        ],
        'workout_power' => [
            'type' => 'uint32',
            1000 => 'watts_offset',
        ],
        'bp_status' => [
            'type' => 'enum',
            0 => 'no_error',
            1 => 'error_incomplete_data',
            2 => 'error_no_measurement',
            3 => 'error_data_out_of_range',
            4 => 'error_irregular_heart_rate',
        ],
        'user_local_id' => [
            'type' => 'uint16',
            0x0000 => 'local_min',
            0x000F => 'local_max',
            0x0010 => 'stationary_min',
            0x00FF => 'stationary_max',
            0x0100 => 'portable_min',
            0xFFFE => 'portable_max',
        ],
        'swim_stroke' => [
            'type' => 'enum',
            0 => 'freestyle',
            1 => 'backstroke',
            2 => 'breaststroke',
            3 => 'butterfly',
            4 => 'drill',
            5 => 'mixed',
            6 => 'im', // IM is a mixed interval containing the same number of lengths for each of: Butterfly, Backstroke, Breaststroke, Freestyle, swam in that order.
        ],
        'activity_type' => [
            'type' => 'enum',
            0 => 'generic',
            1 => 'running',
            2 => 'cycling',
            3 => 'transition', // Mulitsport transition
            4 => 'fitness_equipment',
            5 => 'swimming',
            6 => 'walking',
            8 => 'sedentary',
            254 => 'all', // All is for goals only to include all sports.
        ],
        'activity_subtype' => [
            'type' => 'enum',
            0 => 'generic',
            1 => 'treadmill', // Run
            2 => 'street', // Run
            3 => 'trail', // Run
            4 => 'track', // Run
            5 => 'spin', // Cycling
            6 => 'indoor_cycling', // Cycling
            7 => 'road', // Cycling
            8 => 'mountain', // Cycling
            9 => 'downhill', // Cycling
            10 => 'recumbent', // Cycling
            11 => 'cyclocross', // Cycling
            12 => 'hand_cycling', // Cycling
            13 => 'track_cycling', // Cycling
            14 => 'indoor_rowing', // Fitness Equipment
            15 => 'elliptical', // Fitness Equipment
            16 => 'stair_climbing', // Fitness Equipment
            17 => 'lap_swimming', // Swimming
            18 => 'open_water', // Swimming
            254 => 'all',
        ],
        'activity_level' => [
            'type' => 'enum',
            0 => 'low',
            1 => 'medium',
            2 => 'high',
        ],
        'side' => [
            'type' => 'enum',
            0 => 'right',
            1 => 'left',
        ],
        'left_right_balance' => [
            'type' => 'uint8',
            0x7F => 'mask', // % contribution
            0x80 => 'right', // data corresponds to right if set, otherwise unknown
        ],
        'left_right_balance_100' => [
            'type' => 'uint16',
            0x3FFF => 'mask', // % contribution scaled by 100
            0x8000 => 'right', // data corresponds to right if set, otherwise unknown
        ],
        'length_type' => [
            'type' => 'enum',
            0 => 'idle', // Rest period. Length with no strokes
            1 => 'active', // Length with strokes.
        ],
        'day_of_week' => [
            'type' => 'enum',
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ],
        'connectivity_capabilities' => [
            'type' => 'uint32z',
            0x00000001 => 'bluetooth',
            0x00000002 => 'bluetooth_le',
            0x00000004 => 'ant',
            0x00000008 => 'activity_upload',
            0x00000010 => 'course_download',
            0x00000020 => 'workout_download',
            0x00000040 => 'live_track',
            0x00000080 => 'weather_conditions',
            0x00000100 => 'weather_alerts',
            0x00000200 => 'gps_ephemeris_download',
            0x00000400 => 'explicit_archive',
            0x00000800 => 'setup_incomplete',
            0x00001000 => 'continue_sync_after_software_update',
            0x00002000 => 'connect_iq_app_download',
            0x00004000 => 'golf_course_download',
            0x00008000 => 'device_initiates_sync', // Indicates device is in control of initiating all syncs
            0x00010000 => 'connect_iq_watch_app_download',
            0x00020000 => 'connect_iq_widget_download',
            0x00040000 => 'connect_iq_watch_face_download',
            0x00080000 => 'connect_iq_data_field_download',
            0x00100000 => 'connect_iq_app_managment', // Device supports delete and reorder of apps via GCM
            0x00200000 => 'swing_sensor',
            0x00400000 => 'swing_sensor_remote',
            0x00800000 => 'incident_detection', // Device supports incident detection
            0x01000000 => 'audio_prompts',
            0x02000000 => 'wifi_verification', // Device supports reporting wifi verification via GCM
            0x04000000 => 'true_up', // Device supports True Up
            0x08000000 => 'find_my_watch', // Device supports Find My Watch
            0x10000000 => 'remote_manual_sync',
            0x20000000 => 'live_track_auto_start', // Device supports LiveTrack auto start
            0x40000000 => 'live_track_messaging', // Device supports LiveTrack Messaging
            0x80000000 => 'instant_input', // Device supports instant input feature
        ],
        'weather_report' => [
            'type' => 'enum',
            0 => 'current',
            1 => 'forecast', // Deprecated use hourly_forecast instead
            1 => 'hourly_forecast',
            2 => 'daily_forecast',
        ],
        'weather_status' => [
            'type' => 'enum',
            0 => 'clear',
            1 => 'partly_cloudy',
            2 => 'mostly_cloudy',
            3 => 'rain',
            4 => 'snow',
            5 => 'windy',
            6 => 'thunderstorms',
            7 => 'wintry_mix',
            8 => 'fog',
            11 => 'hazy',
            12 => 'hail',
            13 => 'scattered_showers',
            14 => 'scattered_thunderstorms',
            15 => 'unknown_precipitation',
            16 => 'light_rain',
            17 => 'heavy_rain',
            18 => 'light_snow',
            19 => 'heavy_snow',
            20 => 'light_rain_snow',
            21 => 'heavy_rain_snow',
            22 => 'cloudy',
        ],
        'weather_severity' => [
            'type' => 'enum',
            0 => 'unknown',
            1 => 'warning',
            2 => 'watch',
            3 => 'advisory',
            4 => 'statement',
        ],
        'weather_severe_type' => [
            'type' => 'enum',
            0 => 'unspecified',
            1 => 'tornado',
            2 => 'tsunami',
            3 => 'hurricane',
            4 => 'extreme_wind',
            5 => 'typhoon',
            6 => 'inland_hurricane',
            7 => 'hurricane_force_wind',
            8 => 'waterspout',
            9 => 'severe_thunderstorm',
            10 => 'wreckhouse_winds',
            11 => 'les_suetes_wind',
            12 => 'avalanche',
            13 => 'flash_flood',
            14 => 'tropical_storm',
            15 => 'inland_tropical_storm',
            16 => 'blizzard',
            17 => 'ice_storm',
            18 => 'freezing_rain',
            19 => 'debris_flow',
            20 => 'flash_freeze',
            21 => 'dust_storm',
            22 => 'high_wind',
            23 => 'winter_storm',
            24 => 'heavy_freezing_spray',
            25 => 'extreme_cold',
            26 => 'wind_chill',
            27 => 'cold_wave',
            28 => 'heavy_snow_alert',
            29 => 'lake_effect_blowing_snow',
            30 => 'snow_squall',
            31 => 'lake_effect_snow',
            32 => 'winter_weather',
            33 => 'sleet',
            34 => 'snowfall',
            35 => 'snow_and_blowing_snow',
            36 => 'blowing_snow',
            37 => 'snow_alert',
            38 => 'arctic_outflow',
            39 => 'freezing_drizzle',
            40 => 'storm',
            41 => 'storm_surge',
            42 => 'rainfall',
            43 => 'areal_flood',
            44 => 'coastal_flood',
            45 => 'lakeshore_flood',
            46 => 'excessive_heat',
            47 => 'heat',
            48 => 'weather',
            49 => 'high_heat_and_humidity',
            50 => 'humidex_and_health',
            51 => 'humidex',
            52 => 'gale',
            53 => 'freezing_spray',
            54 => 'special_marine',
            55 => 'squall',
            56 => 'strong_wind',
            57 => 'lake_wind',
            58 => 'marine_weather',
            59 => 'wind',
            60 => 'small_craft_hazardous_seas',
            61 => 'hazardous_seas',
            62 => 'small_craft',
            63 => 'small_craft_winds',
            64 => 'small_craft_rough_bar',
            65 => 'high_water_level',
            66 => 'ashfall',
            67 => 'freezing_fog',
            68 => 'dense_fog',
            69 => 'dense_smoke',
            70 => 'blowing_dust',
            71 => 'hard_freeze',
            72 => 'freeze',
            73 => 'frost',
            74 => 'fire_weather',
            75 => 'flood',
            76 => 'rip_tide',
            77 => 'high_surf',
            78 => 'smog',
            79 => 'air_quality',
            80 => 'brisk_wind',
            81 => 'air_stagnation',
            82 => 'low_water',
            83 => 'hydrological',
            84 => 'special_weather',
        ],
        'time_into_day' => [
            'type' => 'uint32',
        ],
        'localtime_into_day' => [
            'type' => 'uint32',
        ],
        'stroke_type' => [
            'type' => 'enum',
            0 => 'no_event',
            1 => 'other', // stroke was detected but cannot be identified
            2 => 'serve',
            3 => 'forehand',
            4 => 'backhand',
            5 => 'smash',
        ],
        'body_location' => [
            'type' => 'enum',
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
            26 => 'left_brachioradialis', // Left anterior forearm
            27 => 'left_forearm_extensors', // Left posterior forearm
            28 => 'right_arm',
            29 => 'right_shoulder',
            30 => 'right_bicep',
            31 => 'right_tricep',
            32 => 'right_brachioradialis', // Right anterior forearm
            33 => 'right_forearm_extensors', // Right posterior forearm
            34 => 'neck',
            35 => 'throat',
            36 => 'waist_mid_back',
            37 => 'waist_front',
            38 => 'waist_left',
            39 => 'waist_right',
        ],
        'segment_lap_status' => [
            'type' => 'enum',
            0 => 'end',
            1 => 'fail',
        ],
        'segment_leaderboard_type' => [
            'type' => 'enum',
            0 => 'overall',
            1 => 'personal_best',
            2 => 'connections',
            3 => 'group',
            4 => 'challenger',
            5 => 'kom',
            6 => 'qom',
            7 => 'pr',
            8 => 'goal',
            9 => 'rival',
            10 => 'club_leader',
        ],
        'segment_delete_status' => [
            'type' => 'enum',
            0 => 'do_not_delete',
            1 => 'delete_one',
            2 => 'delete_all',
        ],
        'segment_selection_type' => [
            'type' => 'enum',
            0 => 'starred',
            1 => 'suggested',
        ],
        'source_type' => [
            'type' => 'enum',
            0 => 'ant', // External device connected with ANT
            1 => 'antplus', // External device connected with ANT+
            2 => 'bluetooth', // External device connected with BT
            3 => 'bluetooth_low_energy', // External device connected with BLE
            4 => 'wifi', // External device connected with Wifi
            5 => 'local', // Onboard device
        ],
        'local_device_type' => [
            'type' => 'uint8',
            0 => 'gps', // Onboard gps receiver
            1 => 'glonass', // Onboard glonass receiver
            2 => 'gps_glonass', // Onboard gps glonass receiver
            3 => 'accelerometer', // Onboard sensor
            4 => 'barometer', // Onboard sensor
            5 => 'temperature', // Onboard sensor
            10 => 'whr', // Onboard wrist HR sensor
            12 => 'sensor_hub', // Onboard software package
        ],
        'ble_device_type' => [
            'type' => 'uint8',
            0 => 'connected_gps', // GPS that is provided over a proprietary bluetooth service
            1 => 'heart_rate',
            2 => 'bike_power',
            3 => 'bike_speed_cadence',
            4 => 'bike_speed',
            5 => 'bike_cadence',
            6 => 'footpod',
            7 => 'bike_trainer', // Indoor-Bike FTMS protocol
        ],
        'display_orientation' => [
            'type' => 'enum',
            0 => 'auto', // automatic if the device supports it
            1 => 'portrait',
            2 => 'landscape',
            3 => 'portrait_flipped', // portrait mode but rotated 180 degrees
            4 => 'landscape_flipped', // landscape mode but rotated 180 degrees
        ],
        'workout_equipment' => [
            'type' => 'enum',
            0 => 'none',
            1 => 'swim_fins',
            2 => 'swim_kickboard',
            3 => 'swim_paddles',
            4 => 'swim_pull_buoy',
            5 => 'swim_snorkel',
        ],
        'watchface_mode' => [
            'type' => 'enum',
            0 => 'digital',
            1 => 'analog',
            2 => 'connect_iq',
            3 => 'disabled',
        ],
        'digital_watchface_layout' => [
            'type' => 'enum',
            0 => 'traditional',
            1 => 'modern',
            2 => 'bold',
        ],
        'analog_watchface_layout' => [
            'type' => 'enum',
            0 => 'minimal',
            1 => 'traditional',
            2 => 'modern',
        ],
        'rider_position_type' => [
            'type' => 'enum',
            0 => 'seated',
            1 => 'standing',
            2 => 'transition_to_seated',
            3 => 'transition_to_standing',
        ],
        'power_phase_type' => [
            'type' => 'enum',
            0 => 'power_phase_start_angle',
            1 => 'power_phase_end_angle',
            2 => 'power_phase_arc_length',
            3 => 'power_phase_center',
        ],
        'camera_event_type' => [
            'type' => 'enum',
            0 => 'video_start', // Start of video recording
            1 => 'video_split', // Mark of video file split (end of one file, beginning of the other)
            2 => 'video_end', // End of video recording
            3 => 'photo_taken', // Still photo taken
            4 => 'video_second_stream_start',
            5 => 'video_second_stream_split',
            6 => 'video_second_stream_end',
            7 => 'video_split_start', // Mark of video file split start
            8 => 'video_second_stream_split_start',
            11 => 'video_pause', // Mark when a video recording has been paused
            12 => 'video_second_stream_pause',
            13 => 'video_resume', // Mark when a video recording has been resumed
            14 => 'video_second_stream_resume',
        ],
        'sensor_type' => [
            'type' => 'enum',
            0 => 'accelerometer',
            1 => 'gyroscope',
            2 => 'compass', // Magnetometer
            3 => 'barometer',
        ],
        'bike_light_network_config_type' => [
            'type' => 'enum',
            0 => 'auto',
            4 => 'individual',
            5 => 'high_visibility',
            6 => 'trail',
        ],
        'comm_timeout_type' => [
            'type' => 'uint16',
            0 => 'wildcard_pairing_timeout', // Timeout pairing to any device
            1 => 'pairing_timeout', // Timeout pairing to previously paired device
            2 => 'connection_lost', // Temporary loss of communications
            3 => 'connection_timeout', // Connection closed due to extended bad communications
        ],
        'camera_orientation_type' => [
            'type' => 'enum',
            0 => 'camera_orientation_0',
            1 => 'camera_orientation_90',
            2 => 'camera_orientation_180',
            3 => 'camera_orientation_270',
        ],
        'attitude_stage' => [
            'type' => 'enum',
            0 => 'failed',
            1 => 'aligning',
            2 => 'degraded',
            3 => 'valid',
        ],
        'attitude_validity' => [
            'type' => 'uint16',
            0x0001 => 'track_angle_heading_valid',
            0x0002 => 'pitch_valid',
            0x0004 => 'roll_valid',
            0x0008 => 'lateral_body_accel_valid',
            0x0010 => 'normal_body_accel_valid',
            0x0020 => 'turn_rate_valid',
            0x0040 => 'hw_fail',
            0x0080 => 'mag_invalid',
            0x0100 => 'no_gps',
            0x0200 => 'gps_invalid',
            0x0400 => 'solution_coasting',
            0x0800 => 'true_track_angle',
            0x1000 => 'magnetic_heading',
        ],
        'auto_sync_frequency' => [
            'type' => 'enum',
            0 => 'never',
            1 => 'occasionally',
            2 => 'frequent',
            3 => 'once_a_day',
            4 => 'remote',
        ],
        'exd_layout' => [
            'type' => 'enum',
            0 => 'full_screen',
            1 => 'half_vertical',
            2 => 'half_horizontal',
            3 => 'half_vertical_right_split',
            4 => 'half_horizontal_bottom_split',
            5 => 'full_quarter_split',
            6 => 'half_vertical_left_split',
            7 => 'half_horizontal_top_split',
            8 => 'dynamic', // The EXD may display the configured concepts in any layout it sees fit.
        ],
        'exd_display_type' => [
            'type' => 'enum',
            0 => 'numerical',
            1 => 'simple',
            2 => 'graph',
            3 => 'bar',
            4 => 'circle_graph',
            5 => 'virtual_partner',
            6 => 'balance',
            7 => 'string_list',
            8 => 'string',
            9 => 'simple_dynamic_icon',
            10 => 'gauge',
        ],
        'exd_data_units' => [
            'type' => 'enum',
            0 => 'no_units',
            1 => 'laps',
            2 => 'miles_per_hour',
            3 => 'kilometers_per_hour',
            4 => 'feet_per_hour',
            5 => 'meters_per_hour',
            6 => 'degrees_celsius',
            7 => 'degrees_farenheit',
            8 => 'zone',
            9 => 'gear',
            10 => 'rpm',
            11 => 'bpm',
            12 => 'degrees',
            13 => 'millimeters',
            14 => 'meters',
            15 => 'kilometers',
            16 => 'feet',
            17 => 'yards',
            18 => 'kilofeet',
            19 => 'miles',
            20 => 'time',
            21 => 'enum_turn_type',
            22 => 'percent',
            23 => 'watts',
            24 => 'watts_per_kilogram',
            25 => 'enum_battery_status',
            26 => 'enum_bike_light_beam_angle_mode',
            27 => 'enum_bike_light_battery_status',
            28 => 'enum_bike_light_network_config_type',
            29 => 'lights',
            30 => 'seconds',
            31 => 'minutes',
            32 => 'hours',
            33 => 'calories',
            34 => 'kilojoules',
            35 => 'milliseconds',
            36 => 'second_per_mile',
            37 => 'second_per_kilometer',
            38 => 'centimeter',
            39 => 'enum_course_point',
            40 => 'bradians',
            41 => 'enum_sport',
            42 => 'inches_hg',
            43 => 'mm_hg',
            44 => 'mbars',
            45 => 'hecto_pascals',
            46 => 'feet_per_min',
            47 => 'meters_per_min',
            48 => 'meters_per_sec',
            49 => 'eight_cardinal',
        ],
        'exd_qualifiers' => [
            'type' => 'enum',
            0 => 'no_qualifier',
            1 => 'instantaneous',
            2 => 'average',
            3 => 'lap',
            4 => 'maximum',
            5 => 'maximum_average',
            6 => 'maximum_lap',
            7 => 'last_lap',
            8 => 'average_lap',
            9 => 'to_destination',
            10 => 'to_go',
            11 => 'to_next',
            12 => 'next_course_point',
            13 => 'total',
            14 => 'three_second_average',
            15 => 'ten_second_average',
            16 => 'thirty_second_average',
            17 => 'percent_maximum',
            18 => 'percent_maximum_average',
            19 => 'lap_percent_maximum',
            20 => 'elapsed',
            21 => 'sunrise',
            22 => 'sunset',
            23 => 'compared_to_virtual_partner',
            24 => 'maximum_24h',
            25 => 'minimum_24h',
            26 => 'minimum',
            27 => 'first',
            28 => 'second',
            29 => 'third',
            30 => 'shifter',
            31 => 'last_sport',
            32 => 'moving',
            33 => 'stopped',
            34 => 'estimated_total',
            242 => 'zone_9',
            243 => 'zone_8',
            244 => 'zone_7',
            245 => 'zone_6',
            246 => 'zone_5',
            247 => 'zone_4',
            248 => 'zone_3',
            249 => 'zone_2',
            250 => 'zone_1',
        ],
        'exd_descriptors' => [
            'type' => 'enum',
            0 => 'bike_light_battery_status',
            1 => 'beam_angle_status',
            2 => 'batery_level',
            3 => 'light_network_mode',
            4 => 'number_lights_connected',
            5 => 'cadence',
            6 => 'distance',
            7 => 'estimated_time_of_arrival',
            8 => 'heading',
            9 => 'time',
            10 => 'battery_level',
            11 => 'trainer_resistance',
            12 => 'trainer_target_power',
            13 => 'time_seated',
            14 => 'time_standing',
            15 => 'elevation',
            16 => 'grade',
            17 => 'ascent',
            18 => 'descent',
            19 => 'vertical_speed',
            20 => 'di2_battery_level',
            21 => 'front_gear',
            22 => 'rear_gear',
            23 => 'gear_ratio',
            24 => 'heart_rate',
            25 => 'heart_rate_zone',
            26 => 'time_in_heart_rate_zone',
            27 => 'heart_rate_reserve',
            28 => 'calories',
            29 => 'gps_accuracy',
            30 => 'gps_signal_strength',
            31 => 'temperature',
            32 => 'time_of_day',
            33 => 'balance',
            34 => 'pedal_smoothness',
            35 => 'power',
            36 => 'functional_threshold_power',
            37 => 'intensity_factor',
            38 => 'work',
            39 => 'power_ratio',
            40 => 'normalized_power',
            41 => 'training_stress_Score',
            42 => 'time_on_zone',
            43 => 'speed',
            44 => 'laps',
            45 => 'reps',
            46 => 'workout_step',
            47 => 'course_distance',
            48 => 'navigation_distance',
            49 => 'course_estimated_time_of_arrival',
            50 => 'navigation_estimated_time_of_arrival',
            51 => 'course_time',
            52 => 'navigation_time',
            53 => 'course_heading',
            54 => 'navigation_heading',
            55 => 'power_zone',
            56 => 'torque_effectiveness',
            57 => 'timer_time',
            58 => 'power_weight_ratio',
            59 => 'left_platform_center_offset',
            60 => 'right_platform_center_offset',
            61 => 'left_power_phase_start_angle',
            62 => 'right_power_phase_start_angle',
            63 => 'left_power_phase_finish_angle',
            64 => 'right_power_phase_finish_angle',
            65 => 'gears', // Combined gear information
            66 => 'pace',
            67 => 'training_effect',
            68 => 'vertical_oscillation',
            69 => 'vertical_ratio',
            70 => 'ground_contact_time',
            71 => 'left_ground_contact_time_balance',
            72 => 'right_ground_contact_time_balance',
            73 => 'stride_length',
            74 => 'running_cadence',
            75 => 'performance_condition',
            76 => 'course_type',
            77 => 'time_in_power_zone',
            78 => 'navigation_turn',
            79 => 'course_location',
            80 => 'navigation_location',
            81 => 'compass',
            82 => 'gear_combo',
            83 => 'muscle_oxygen',
            84 => 'icon',
            85 => 'compass_heading',
            86 => 'gps_heading',
            87 => 'gps_elevation',
            88 => 'anaerobic_training_effect',
            89 => 'course',
            90 => 'off_course',
            91 => 'glide_ratio',
            92 => 'vertical_distance',
            93 => 'vmg',
            94 => 'ambient_pressure',
            95 => 'pressure',
            96 => 'vam',
        ],
        'auto_activity_detect' => [
            'type' => 'uint32',
            0x00000000 => 'none',
            0x00000001 => 'running',
            0x00000002 => 'cycling',
            0x00000004 => 'swimming',
            0x00000008 => 'walking',
            0x00000020 => 'elliptical',
            0x00000400 => 'sedentary',
        ],
        'supported_exd_screen_layouts' => [
            'type' => 'uint32z',
            0x00000001 => 'full_screen',
            0x00000002 => 'half_vertical',
            0x00000004 => 'half_horizontal',
            0x00000008 => 'half_vertical_right_split',
            0x00000010 => 'half_horizontal_bottom_split',
            0x00000020 => 'full_quarter_split',
            0x00000040 => 'half_vertical_left_split',
            0x00000080 => 'half_horizontal_top_split',
        ],
        'fit_base_type' => [
            'type' => 'uint8',
            0 => 'enum',
            1 => 'sint8',
            2 => 'uint8',
            131 => 'sint16',
            132 => 'uint16',
            133 => 'sint32',
            134 => 'uint32',
            7 => 'string',
            136 => 'float32',
            137 => 'float64',
            10 => 'uint8z',
            139 => 'uint16z',
            140 => 'uint32z',
            13 => 'byte',
            142 => 'sint64',
            143 => 'uint64',
            144 => 'uint64z',
        ],
        'turn_type' => [
            'type' => 'enum',
            0 => 'arriving_idx',
            1 => 'arriving_left_idx',
            2 => 'arriving_right_idx',
            3 => 'arriving_via_idx',
            4 => 'arriving_via_left_idx',
            5 => 'arriving_via_right_idx',
            6 => 'bear_keep_left_idx',
            7 => 'bear_keep_right_idx',
            8 => 'continue_idx',
            9 => 'exit_left_idx',
            10 => 'exit_right_idx',
            11 => 'ferry_idx',
            12 => 'roundabout_45_idx',
            13 => 'roundabout_90_idx',
            14 => 'roundabout_135_idx',
            15 => 'roundabout_180_idx',
            16 => 'roundabout_225_idx',
            17 => 'roundabout_270_idx',
            18 => 'roundabout_315_idx',
            19 => 'roundabout_360_idx',
            20 => 'roundabout_neg_45_idx',
            21 => 'roundabout_neg_90_idx',
            22 => 'roundabout_neg_135_idx',
            23 => 'roundabout_neg_180_idx',
            24 => 'roundabout_neg_225_idx',
            25 => 'roundabout_neg_270_idx',
            26 => 'roundabout_neg_315_idx',
            27 => 'roundabout_neg_360_idx',
            28 => 'roundabout_generic_idx',
            29 => 'roundabout_neg_generic_idx',
            30 => 'sharp_turn_left_idx',
            31 => 'sharp_turn_right_idx',
            32 => 'turn_left_idx',
            33 => 'turn_right_idx',
            34 => 'uturn_left_idx',
            35 => 'uturn_right_idx',
            36 => 'icon_inv_idx',
            37 => 'icon_idx_cnt',
        ],
        'bike_light_beam_angle_mode' => [
            'type' => 'uint8',
            0 => 'manual',
            1 => 'auto',
        ],
        'fit_base_unit' => [
            'type' => 'uint16',
            0 => 'other',
            1 => 'kilogram',
            2 => 'pound',
        ],
        'set_type' => [
            'type' => 'uint8',
            0 => 'rest',
            1 => 'active',
        ],
        'exercise_category' => [
            'type' => 'uint16',
            0 => 'bench_press',
            1 => 'calf_raise',
            2 => 'cardio',
            3 => 'carry',
            4 => 'chop',
            5 => 'core',
            6 => 'crunch',
            7 => 'curl',
            8 => 'deadlift',
            9 => 'flye',
            10 => 'hip_raise',
            11 => 'hip_stability',
            12 => 'hip_swing',
            13 => 'hyperextension',
            14 => 'lateral_raise',
            15 => 'leg_curl',
            16 => 'leg_raise',
            17 => 'lunge',
            18 => 'olympic_lift',
            19 => 'plank',
            20 => 'plyo',
            21 => 'pull_up',
            22 => 'push_up',
            23 => 'row',
            24 => 'shoulder_press',
            25 => 'shoulder_stability',
            26 => 'shrug',
            27 => 'sit_up',
            28 => 'squat',
            29 => 'total_body',
            30 => 'triceps_extension',
            31 => 'warm_up',
            32 => 'run',
            65534 => 'unknown',
        ],
        'bench_press_exercise_name' => [
            'type' => 'uint16',
            0 => 'alternating_dumbbell_chest_press_on_swiss_ball',
            1 => 'barbell_bench_press',
            2 => 'barbell_board_bench_press',
            3 => 'barbell_floor_press',
            4 => 'close_grip_barbell_bench_press',
            5 => 'decline_dumbbell_bench_press',
            6 => 'dumbbell_bench_press',
            7 => 'dumbbell_floor_press',
            8 => 'incline_barbell_bench_press',
            9 => 'incline_dumbbell_bench_press',
            10 => 'incline_smith_machine_bench_press',
            11 => 'isometric_barbell_bench_press',
            12 => 'kettlebell_chest_press',
            13 => 'neutral_grip_dumbbell_bench_press',
            14 => 'neutral_grip_dumbbell_incline_bench_press',
            15 => 'one_arm_floor_press',
            16 => 'weighted_one_arm_floor_press',
            17 => 'partial_lockout',
            18 => 'reverse_grip_barbell_bench_press',
            19 => 'reverse_grip_incline_bench_press',
            20 => 'single_arm_cable_chest_press',
            21 => 'single_arm_dumbbell_bench_press',
            22 => 'smith_machine_bench_press',
            23 => 'swiss_ball_dumbbell_chest_press',
            24 => 'triple_stop_barbell_bench_press',
            25 => 'wide_grip_barbell_bench_press',
            26 => 'alternating_dumbbell_chest_press',
        ],
        'calf_raise_exercise_name' => [
            'type' => 'uint16',
            0 => '3_way_calf_raise',
            1 => '3_way_weighted_calf_raise',
            2 => '3_way_single_leg_calf_raise',
            3 => '3_way_weighted_single_leg_calf_raise',
            4 => 'donkey_calf_raise',
            5 => 'weighted_donkey_calf_raise',
            6 => 'seated_calf_raise',
            7 => 'weighted_seated_calf_raise',
            8 => 'seated_dumbbell_toe_raise',
            9 => 'single_leg_bent_knee_calf_raise',
            10 => 'weighted_single_leg_bent_knee_calf_raise',
            11 => 'single_leg_decline_push_up',
            12 => 'single_leg_donkey_calf_raise',
            13 => 'weighted_single_leg_donkey_calf_raise',
            14 => 'single_leg_hip_raise_with_knee_hold',
            15 => 'single_leg_standing_calf_raise',
            16 => 'single_leg_standing_dumbbell_calf_raise',
            17 => 'standing_barbell_calf_raise',
            18 => 'standing_calf_raise',
            19 => 'weighted_standing_calf_raise',
            20 => 'standing_dumbbell_calf_raise',
        ],
        'cardio_exercise_name' => [
            'type' => 'uint16',
            0 => 'bob_and_weave_circle',
            1 => 'weighted_bob_and_weave_circle',
            2 => 'cardio_core_crawl',
            3 => 'weighted_cardio_core_crawl',
            4 => 'double_under',
            5 => 'weighted_double_under',
            6 => 'jump_rope',
            7 => 'weighted_jump_rope',
            8 => 'jump_rope_crossover',
            9 => 'weighted_jump_rope_crossover',
            10 => 'jump_rope_jog',
            11 => 'weighted_jump_rope_jog',
            12 => 'jumping_jacks',
            13 => 'weighted_jumping_jacks',
            14 => 'ski_moguls',
            15 => 'weighted_ski_moguls',
            16 => 'split_jacks',
            17 => 'weighted_split_jacks',
            18 => 'squat_jacks',
            19 => 'weighted_squat_jacks',
            20 => 'triple_under',
            21 => 'weighted_triple_under',
        ],
        'carry_exercise_name' => [
            'type' => 'uint16',
            0 => 'bar_holds',
            1 => 'farmers_walk',
            2 => 'farmers_walk_on_toes',
            3 => 'hex_dumbbell_hold',
            4 => 'overhead_carry',
        ],
        'chop_exercise_name' => [
            'type' => 'uint16',
            0 => 'cable_pull_through',
            1 => 'cable_rotational_lift',
            2 => 'cable_woodchop',
            3 => 'cross_chop_to_knee',
            4 => 'weighted_cross_chop_to_knee',
            5 => 'dumbbell_chop',
            6 => 'half_kneeling_rotation',
            7 => 'weighted_half_kneeling_rotation',
            8 => 'half_kneeling_rotational_chop',
            9 => 'half_kneeling_rotational_reverse_chop',
            10 => 'half_kneeling_stability_chop',
            11 => 'half_kneeling_stability_reverse_chop',
            12 => 'kneeling_rotational_chop',
            13 => 'kneeling_rotational_reverse_chop',
            14 => 'kneeling_stability_chop',
            15 => 'kneeling_woodchopper',
            16 => 'medicine_ball_wood_chops',
            17 => 'power_squat_chops',
            18 => 'weighted_power_squat_chops',
            19 => 'standing_rotational_chop',
            20 => 'standing_split_rotational_chop',
            21 => 'standing_split_rotational_reverse_chop',
            22 => 'standing_stability_reverse_chop',
        ],
        'core_exercise_name' => [
            'type' => 'uint16',
            0 => 'abs_jabs',
            1 => 'weighted_abs_jabs',
            2 => 'alternating_plate_reach',
            3 => 'barbell_rollout',
            4 => 'weighted_barbell_rollout',
            5 => 'body_bar_oblique_twist',
            6 => 'cable_core_press',
            7 => 'cable_side_bend',
            8 => 'side_bend',
            9 => 'weighted_side_bend',
            10 => 'crescent_circle',
            11 => 'weighted_crescent_circle',
            12 => 'cycling_russian_twist',
            13 => 'weighted_cycling_russian_twist',
            14 => 'elevated_feet_russian_twist',
            15 => 'weighted_elevated_feet_russian_twist',
            16 => 'half_turkish_get_up',
            17 => 'kettlebell_windmill',
            18 => 'kneeling_ab_wheel',
            19 => 'weighted_kneeling_ab_wheel',
            20 => 'modified_front_lever',
            21 => 'open_knee_tucks',
            22 => 'weighted_open_knee_tucks',
            23 => 'side_abs_leg_lift',
            24 => 'weighted_side_abs_leg_lift',
            25 => 'swiss_ball_jackknife',
            26 => 'weighted_swiss_ball_jackknife',
            27 => 'swiss_ball_pike',
            28 => 'weighted_swiss_ball_pike',
            29 => 'swiss_ball_rollout',
            30 => 'weighted_swiss_ball_rollout',
            31 => 'triangle_hip_press',
            32 => 'weighted_triangle_hip_press',
            33 => 'trx_suspended_jackknife',
            34 => 'weighted_trx_suspended_jackknife',
            35 => 'u_boat',
            36 => 'weighted_u_boat',
            37 => 'windmill_switches',
            38 => 'weighted_windmill_switches',
            39 => 'alternating_slide_out',
            40 => 'weighted_alternating_slide_out',
            41 => 'ghd_back_extensions',
            42 => 'weighted_ghd_back_extensions',
            43 => 'overhead_walk',
            44 => 'inchworm',
            45 => 'weighted_modified_front_lever',
            46 => 'russian_twist',
            47 => 'abdominal_leg_rotations', // Deprecated do not use
            48 => 'arm_and_leg_extension_on_knees',
            49 => 'bicycle',
            50 => 'bicep_curl_with_leg_extension',
            51 => 'cat_cow',
            52 => 'corkscrew',
            53 => 'criss_cross',
            54 => 'criss_cross_with_ball', // Deprecated do not use
            55 => 'double_leg_stretch',
            56 => 'knee_folds',
            57 => 'lower_lift',
            58 => 'neck_pull',
            59 => 'pelvic_clocks',
            60 => 'roll_over',
            61 => 'roll_up',
            62 => 'rolling',
            63 => 'rowing_1',
            64 => 'rowing_2',
            65 => 'scissors',
            66 => 'single_leg_circles',
            67 => 'single_leg_stretch',
            68 => 'snake_twist_1_and_2', // Deprecated do not use
            69 => 'swan',
            70 => 'swimming',
            71 => 'teaser',
            72 => 'the_hundred',
        ],
        'crunch_exercise_name' => [
            'type' => 'uint16',
            0 => 'bicycle_crunch',
            1 => 'cable_crunch',
            2 => 'circular_arm_crunch',
            3 => 'crossed_arms_crunch',
            4 => 'weighted_crossed_arms_crunch',
            5 => 'cross_leg_reverse_crunch',
            6 => 'weighted_cross_leg_reverse_crunch',
            7 => 'crunch_chop',
            8 => 'weighted_crunch_chop',
            9 => 'double_crunch',
            10 => 'weighted_double_crunch',
            11 => 'elbow_to_knee_crunch',
            12 => 'weighted_elbow_to_knee_crunch',
            13 => 'flutter_kicks',
            14 => 'weighted_flutter_kicks',
            15 => 'foam_roller_reverse_crunch_on_bench',
            16 => 'weighted_foam_roller_reverse_crunch_on_bench',
            17 => 'foam_roller_reverse_crunch_with_dumbbell',
            18 => 'foam_roller_reverse_crunch_with_medicine_ball',
            19 => 'frog_press',
            20 => 'hanging_knee_raise_oblique_crunch',
            21 => 'weighted_hanging_knee_raise_oblique_crunch',
            22 => 'hip_crossover',
            23 => 'weighted_hip_crossover',
            24 => 'hollow_rock',
            25 => 'weighted_hollow_rock',
            26 => 'incline_reverse_crunch',
            27 => 'weighted_incline_reverse_crunch',
            28 => 'kneeling_cable_crunch',
            29 => 'kneeling_cross_crunch',
            30 => 'weighted_kneeling_cross_crunch',
            31 => 'kneeling_oblique_cable_crunch',
            32 => 'knees_to_elbow',
            33 => 'leg_extensions',
            34 => 'weighted_leg_extensions',
            35 => 'leg_levers',
            36 => 'mcgill_curl_up',
            37 => 'weighted_mcgill_curl_up',
            38 => 'modified_pilates_roll_up_with_ball',
            39 => 'weighted_modified_pilates_roll_up_with_ball',
            40 => 'pilates_crunch',
            41 => 'weighted_pilates_crunch',
            42 => 'pilates_roll_up_with_ball',
            43 => 'weighted_pilates_roll_up_with_ball',
            44 => 'raised_legs_crunch',
            45 => 'weighted_raised_legs_crunch',
            46 => 'reverse_crunch',
            47 => 'weighted_reverse_crunch',
            48 => 'reverse_crunch_on_a_bench',
            49 => 'weighted_reverse_crunch_on_a_bench',
            50 => 'reverse_curl_and_lift',
            51 => 'weighted_reverse_curl_and_lift',
            52 => 'rotational_lift',
            53 => 'weighted_rotational_lift',
            54 => 'seated_alternating_reverse_crunch',
            55 => 'weighted_seated_alternating_reverse_crunch',
            56 => 'seated_leg_u',
            57 => 'weighted_seated_leg_u',
            58 => 'side_to_side_crunch_and_weave',
            59 => 'weighted_side_to_side_crunch_and_weave',
            60 => 'single_leg_reverse_crunch',
            61 => 'weighted_single_leg_reverse_crunch',
            62 => 'skater_crunch_cross',
            63 => 'weighted_skater_crunch_cross',
            64 => 'standing_cable_crunch',
            65 => 'standing_side_crunch',
            66 => 'step_climb',
            67 => 'weighted_step_climb',
            68 => 'swiss_ball_crunch',
            69 => 'swiss_ball_reverse_crunch',
            70 => 'weighted_swiss_ball_reverse_crunch',
            71 => 'swiss_ball_russian_twist',
            72 => 'weighted_swiss_ball_russian_twist',
            73 => 'swiss_ball_side_crunch',
            74 => 'weighted_swiss_ball_side_crunch',
            75 => 'thoracic_crunches_on_foam_roller',
            76 => 'weighted_thoracic_crunches_on_foam_roller',
            77 => 'triceps_crunch',
            78 => 'weighted_bicycle_crunch',
            79 => 'weighted_crunch',
            80 => 'weighted_swiss_ball_crunch',
            81 => 'toes_to_bar',
            82 => 'weighted_toes_to_bar',
            83 => 'crunch',
            84 => 'straight_leg_crunch_with_ball',
        ],
        'curl_exercise_name' => [
            'type' => 'uint16',
            0 => 'alternating_dumbbell_biceps_curl',
            1 => 'alternating_dumbbell_biceps_curl_on_swiss_ball',
            2 => 'alternating_incline_dumbbell_biceps_curl',
            3 => 'barbell_biceps_curl',
            4 => 'barbell_reverse_wrist_curl',
            5 => 'barbell_wrist_curl',
            6 => 'behind_the_back_barbell_reverse_wrist_curl',
            7 => 'behind_the_back_one_arm_cable_curl',
            8 => 'cable_biceps_curl',
            9 => 'cable_hammer_curl',
            10 => 'cheating_barbell_biceps_curl',
            11 => 'close_grip_ez_bar_biceps_curl',
            12 => 'cross_body_dumbbell_hammer_curl',
            13 => 'dead_hang_biceps_curl',
            14 => 'decline_hammer_curl',
            15 => 'dumbbell_biceps_curl_with_static_hold',
            16 => 'dumbbell_hammer_curl',
            17 => 'dumbbell_reverse_wrist_curl',
            18 => 'dumbbell_wrist_curl',
            19 => 'ez_bar_preacher_curl',
            20 => 'forward_bend_biceps_curl',
            21 => 'hammer_curl_to_press',
            22 => 'incline_dumbbell_biceps_curl',
            23 => 'incline_offset_thumb_dumbbell_curl',
            24 => 'kettlebell_biceps_curl',
            25 => 'lying_concentration_cable_curl',
            26 => 'one_arm_preacher_curl',
            27 => 'plate_pinch_curl',
            28 => 'preacher_curl_with_cable',
            29 => 'reverse_ez_bar_curl',
            30 => 'reverse_grip_wrist_curl',
            31 => 'reverse_grip_barbell_biceps_curl',
            32 => 'seated_alternating_dumbbell_biceps_curl',
            33 => 'seated_dumbbell_biceps_curl',
            34 => 'seated_reverse_dumbbell_curl',
            35 => 'split_stance_offset_pinky_dumbbell_curl',
            36 => 'standing_alternating_dumbbell_curls',
            37 => 'standing_dumbbell_biceps_curl',
            38 => 'standing_ez_bar_biceps_curl',
            39 => 'static_curl',
            40 => 'swiss_ball_dumbbell_overhead_triceps_extension',
            41 => 'swiss_ball_ez_bar_preacher_curl',
            42 => 'twisting_standing_dumbbell_biceps_curl',
            43 => 'wide_grip_ez_bar_biceps_curl',
        ],
        'deadlift_exercise_name' => [
            'type' => 'uint16',
            0 => 'barbell_deadlift',
            1 => 'barbell_straight_leg_deadlift',
            2 => 'dumbbell_deadlift',
            3 => 'dumbbell_single_leg_deadlift_to_row',
            4 => 'dumbbell_straight_leg_deadlift',
            5 => 'kettlebell_floor_to_shelf',
            6 => 'one_arm_one_leg_deadlift',
            7 => 'rack_pull',
            8 => 'rotational_dumbbell_straight_leg_deadlift',
            9 => 'single_arm_deadlift',
            10 => 'single_leg_barbell_deadlift',
            11 => 'single_leg_barbell_straight_leg_deadlift',
            12 => 'single_leg_deadlift_with_barbell',
            13 => 'single_leg_rdl_circuit',
            14 => 'single_leg_romanian_deadlift_with_dumbbell',
            15 => 'sumo_deadlift',
            16 => 'sumo_deadlift_high_pull',
            17 => 'trap_bar_deadlift',
            18 => 'wide_grip_barbell_deadlift',
        ],
        'flye_exercise_name' => [
            'type' => 'uint16',
            0 => 'cable_crossover',
            1 => 'decline_dumbbell_flye',
            2 => 'dumbbell_flye',
            3 => 'incline_dumbbell_flye',
            4 => 'kettlebell_flye',
            5 => 'kneeling_rear_flye',
            6 => 'single_arm_standing_cable_reverse_flye',
            7 => 'swiss_ball_dumbbell_flye',
            8 => 'arm_rotations',
            9 => 'hug_a_tree',
        ],
        'hip_raise_exercise_name' => [
            'type' => 'uint16',
            0 => 'barbell_hip_thrust_on_floor',
            1 => 'barbell_hip_thrust_with_bench',
            2 => 'bent_knee_swiss_ball_reverse_hip_raise',
            3 => 'weighted_bent_knee_swiss_ball_reverse_hip_raise',
            4 => 'bridge_with_leg_extension',
            5 => 'weighted_bridge_with_leg_extension',
            6 => 'clam_bridge',
            7 => 'front_kick_tabletop',
            8 => 'weighted_front_kick_tabletop',
            9 => 'hip_extension_and_cross',
            10 => 'weighted_hip_extension_and_cross',
            11 => 'hip_raise',
            12 => 'weighted_hip_raise',
            13 => 'hip_raise_with_feet_on_swiss_ball',
            14 => 'weighted_hip_raise_with_feet_on_swiss_ball',
            15 => 'hip_raise_with_head_on_bosu_ball',
            16 => 'weighted_hip_raise_with_head_on_bosu_ball',
            17 => 'hip_raise_with_head_on_swiss_ball',
            18 => 'weighted_hip_raise_with_head_on_swiss_ball',
            19 => 'hip_raise_with_knee_squeeze',
            20 => 'weighted_hip_raise_with_knee_squeeze',
            21 => 'incline_rear_leg_extension',
            22 => 'weighted_incline_rear_leg_extension',
            23 => 'kettlebell_swing',
            24 => 'marching_hip_raise',
            25 => 'weighted_marching_hip_raise',
            26 => 'marching_hip_raise_with_feet_on_a_swiss_ball',
            27 => 'weighted_marching_hip_raise_with_feet_on_a_swiss_ball',
            28 => 'reverse_hip_raise',
            29 => 'weighted_reverse_hip_raise',
            30 => 'single_leg_hip_raise',
            31 => 'weighted_single_leg_hip_raise',
            32 => 'single_leg_hip_raise_with_foot_on_bench',
            33 => 'weighted_single_leg_hip_raise_with_foot_on_bench',
            34 => 'single_leg_hip_raise_with_foot_on_bosu_ball',
            35 => 'weighted_single_leg_hip_raise_with_foot_on_bosu_ball',
            36 => 'single_leg_hip_raise_with_foot_on_foam_roller',
            37 => 'weighted_single_leg_hip_raise_with_foot_on_foam_roller',
            38 => 'single_leg_hip_raise_with_foot_on_medicine_ball',
            39 => 'weighted_single_leg_hip_raise_with_foot_on_medicine_ball',
            40 => 'single_leg_hip_raise_with_head_on_bosu_ball',
            41 => 'weighted_single_leg_hip_raise_with_head_on_bosu_ball',
            42 => 'weighted_clam_bridge',
            43 => 'single_leg_swiss_ball_hip_raise_and_leg_curl',
            44 => 'clams',
            45 => 'inner_thigh_circles', // Deprecated do not use
            46 => 'inner_thigh_side_lift', // Deprecated do not use
            47 => 'leg_circles',
            48 => 'leg_lift',
            49 => 'leg_lift_in_external_rotation',
        ],
        'hip_stability_exercise_name' => [
            'type' => 'uint16',
            0 => 'band_side_lying_leg_raise',
            1 => 'dead_bug',
            2 => 'weighted_dead_bug',
            3 => 'external_hip_raise',
            4 => 'weighted_external_hip_raise',
            5 => 'fire_hydrant_kicks',
            6 => 'weighted_fire_hydrant_kicks',
            7 => 'hip_circles',
            8 => 'weighted_hip_circles',
            9 => 'inner_thigh_lift',
            10 => 'weighted_inner_thigh_lift',
            11 => 'lateral_walks_with_band_at_ankles',
            12 => 'pretzel_side_kick',
            13 => 'weighted_pretzel_side_kick',
            14 => 'prone_hip_internal_rotation',
            15 => 'weighted_prone_hip_internal_rotation',
            16 => 'quadruped',
            17 => 'quadruped_hip_extension',
            18 => 'weighted_quadruped_hip_extension',
            19 => 'quadruped_with_leg_lift',
            20 => 'weighted_quadruped_with_leg_lift',
            21 => 'side_lying_leg_raise',
            22 => 'weighted_side_lying_leg_raise',
            23 => 'sliding_hip_adduction',
            24 => 'weighted_sliding_hip_adduction',
            25 => 'standing_adduction',
            26 => 'weighted_standing_adduction',
            27 => 'standing_cable_hip_abduction',
            28 => 'standing_hip_abduction',
            29 => 'weighted_standing_hip_abduction',
            30 => 'standing_rear_leg_raise',
            31 => 'weighted_standing_rear_leg_raise',
            32 => 'supine_hip_internal_rotation',
            33 => 'weighted_supine_hip_internal_rotation',
        ],
        'hip_swing_exercise_name' => [
            'type' => 'uint16',
            0 => 'single_arm_kettlebell_swing',
            1 => 'single_arm_dumbbell_swing',
            2 => 'step_out_swing',
        ],
        'hyperextension_exercise_name' => [
            'type' => 'uint16',
            0 => 'back_extension_with_opposite_arm_and_leg_reach',
            1 => 'weighted_back_extension_with_opposite_arm_and_leg_reach',
            2 => 'base_rotations',
            3 => 'weighted_base_rotations',
            4 => 'bent_knee_reverse_hyperextension',
            5 => 'weighted_bent_knee_reverse_hyperextension',
            6 => 'hollow_hold_and_roll',
            7 => 'weighted_hollow_hold_and_roll',
            8 => 'kicks',
            9 => 'weighted_kicks',
            10 => 'knee_raises',
            11 => 'weighted_knee_raises',
            12 => 'kneeling_superman',
            13 => 'weighted_kneeling_superman',
            14 => 'lat_pull_down_with_row',
            15 => 'medicine_ball_deadlift_to_reach',
            16 => 'one_arm_one_leg_row',
            17 => 'one_arm_row_with_band',
            18 => 'overhead_lunge_with_medicine_ball',
            19 => 'plank_knee_tucks',
            20 => 'weighted_plank_knee_tucks',
            21 => 'side_step',
            22 => 'weighted_side_step',
            23 => 'single_leg_back_extension',
            24 => 'weighted_single_leg_back_extension',
            25 => 'spine_extension',
            26 => 'weighted_spine_extension',
            27 => 'static_back_extension',
            28 => 'weighted_static_back_extension',
            29 => 'superman_from_floor',
            30 => 'weighted_superman_from_floor',
            31 => 'swiss_ball_back_extension',
            32 => 'weighted_swiss_ball_back_extension',
            33 => 'swiss_ball_hyperextension',
            34 => 'weighted_swiss_ball_hyperextension',
            35 => 'swiss_ball_opposite_arm_and_leg_lift',
            36 => 'weighted_swiss_ball_opposite_arm_and_leg_lift',
            37 => 'superman_on_swiss_ball',
            38 => 'cobra',
            39 => 'supine_floor_barre', // Deprecated do not use
        ],
        'lateral_raise_exercise_name' => [
            'type' => 'uint16',
            0 => '45_degree_cable_external_rotation',
            1 => 'alternating_lateral_raise_with_static_hold',
            2 => 'bar_muscle_up',
            3 => 'bent_over_lateral_raise',
            4 => 'cable_diagonal_raise',
            5 => 'cable_front_raise',
            6 => 'calorie_row',
            7 => 'combo_shoulder_raise',
            8 => 'dumbbell_diagonal_raise',
            9 => 'dumbbell_v_raise',
            10 => 'front_raise',
            11 => 'leaning_dumbbell_lateral_raise',
            12 => 'lying_dumbbell_raise',
            13 => 'muscle_up',
            14 => 'one_arm_cable_lateral_raise',
            15 => 'overhand_grip_rear_lateral_raise',
            16 => 'plate_raises',
            17 => 'ring_dip',
            18 => 'weighted_ring_dip',
            19 => 'ring_muscle_up',
            20 => 'weighted_ring_muscle_up',
            21 => 'rope_climb',
            22 => 'weighted_rope_climb',
            23 => 'scaption',
            24 => 'seated_lateral_raise',
            25 => 'seated_rear_lateral_raise',
            26 => 'side_lying_lateral_raise',
            27 => 'standing_lift',
            28 => 'suspended_row',
            29 => 'underhand_grip_rear_lateral_raise',
            30 => 'wall_slide',
            31 => 'weighted_wall_slide',
            32 => 'arm_circles',
            33 => 'shaving_the_head',
        ],
        'leg_curl_exercise_name' => [
            'type' => 'uint16',
            0 => 'leg_curl',
            1 => 'weighted_leg_curl',
            2 => 'good_morning',
            3 => 'seated_barbell_good_morning',
            4 => 'single_leg_barbell_good_morning',
            5 => 'single_leg_sliding_leg_curl',
            6 => 'sliding_leg_curl',
            7 => 'split_barbell_good_morning',
            8 => 'split_stance_extension',
            9 => 'staggered_stance_good_morning',
            10 => 'swiss_ball_hip_raise_and_leg_curl',
            11 => 'zercher_good_morning',
        ],
        'leg_raise_exercise_name' => [
            'type' => 'uint16',
            0 => 'hanging_knee_raise',
            1 => 'hanging_leg_raise',
            2 => 'weighted_hanging_leg_raise',
            3 => 'hanging_single_leg_raise',
            4 => 'weighted_hanging_single_leg_raise',
            5 => 'kettlebell_leg_raises',
            6 => 'leg_lowering_drill',
            7 => 'weighted_leg_lowering_drill',
            8 => 'lying_straight_leg_raise',
            9 => 'weighted_lying_straight_leg_raise',
            10 => 'medicine_ball_leg_drops',
            11 => 'quadruped_leg_raise',
            12 => 'weighted_quadruped_leg_raise',
            13 => 'reverse_leg_raise',
            14 => 'weighted_reverse_leg_raise',
            15 => 'reverse_leg_raise_on_swiss_ball',
            16 => 'weighted_reverse_leg_raise_on_swiss_ball',
            17 => 'single_leg_lowering_drill',
            18 => 'weighted_single_leg_lowering_drill',
            19 => 'weighted_hanging_knee_raise',
            20 => 'lateral_stepover',
            21 => 'weighted_lateral_stepover',
        ],
        'lunge_exercise_name' => [
            'type' => 'uint16',
            0 => 'overhead_lunge',
            1 => 'lunge_matrix',
            2 => 'weighted_lunge_matrix',
            3 => 'alternating_barbell_forward_lunge',
            4 => 'alternating_dumbbell_lunge_with_reach',
            5 => 'back_foot_elevated_dumbbell_split_squat',
            6 => 'barbell_box_lunge',
            7 => 'barbell_bulgarian_split_squat',
            8 => 'barbell_crossover_lunge',
            9 => 'barbell_front_split_squat',
            10 => 'barbell_lunge',
            11 => 'barbell_reverse_lunge',
            12 => 'barbell_side_lunge',
            13 => 'barbell_split_squat',
            14 => 'core_control_rear_lunge',
            15 => 'diagonal_lunge',
            16 => 'drop_lunge',
            17 => 'dumbbell_box_lunge',
            18 => 'dumbbell_bulgarian_split_squat',
            19 => 'dumbbell_crossover_lunge',
            20 => 'dumbbell_diagonal_lunge',
            21 => 'dumbbell_lunge',
            22 => 'dumbbell_lunge_and_rotation',
            23 => 'dumbbell_overhead_bulgarian_split_squat',
            24 => 'dumbbell_reverse_lunge_to_high_knee_and_press',
            25 => 'dumbbell_side_lunge',
            26 => 'elevated_front_foot_barbell_split_squat',
            27 => 'front_foot_elevated_dumbbell_split_squat',
            28 => 'gunslinger_lunge',
            29 => 'lawnmower_lunge',
            30 => 'low_lunge_with_isometric_adduction',
            31 => 'low_side_to_side_lunge',
            32 => 'lunge',
            33 => 'weighted_lunge',
            34 => 'lunge_with_arm_reach',
            35 => 'lunge_with_diagonal_reach',
            36 => 'lunge_with_side_bend',
            37 => 'offset_dumbbell_lunge',
            38 => 'offset_dumbbell_reverse_lunge',
            39 => 'overhead_bulgarian_split_squat',
            40 => 'overhead_dumbbell_reverse_lunge',
            41 => 'overhead_dumbbell_split_squat',
            42 => 'overhead_lunge_with_rotation',
            43 => 'reverse_barbell_box_lunge',
            44 => 'reverse_box_lunge',
            45 => 'reverse_dumbbell_box_lunge',
            46 => 'reverse_dumbbell_crossover_lunge',
            47 => 'reverse_dumbbell_diagonal_lunge',
            48 => 'reverse_lunge_with_reach_back',
            49 => 'weighted_reverse_lunge_with_reach_back',
            50 => 'reverse_lunge_with_twist_and_overhead_reach',
            51 => 'weighted_reverse_lunge_with_twist_and_overhead_reach',
            52 => 'reverse_sliding_box_lunge',
            53 => 'weighted_reverse_sliding_box_lunge',
            54 => 'reverse_sliding_lunge',
            55 => 'weighted_reverse_sliding_lunge',
            56 => 'runners_lunge_to_balance',
            57 => 'weighted_runners_lunge_to_balance',
            58 => 'shifting_side_lunge',
            59 => 'side_and_crossover_lunge',
            60 => 'weighted_side_and_crossover_lunge',
            61 => 'side_lunge',
            62 => 'weighted_side_lunge',
            63 => 'side_lunge_and_press',
            64 => 'side_lunge_jump_off',
            65 => 'side_lunge_sweep',
            66 => 'weighted_side_lunge_sweep',
            67 => 'side_lunge_to_crossover_tap',
            68 => 'weighted_side_lunge_to_crossover_tap',
            69 => 'side_to_side_lunge_chops',
            70 => 'weighted_side_to_side_lunge_chops',
            71 => 'siff_jump_lunge',
            72 => 'weighted_siff_jump_lunge',
            73 => 'single_arm_reverse_lunge_and_press',
            74 => 'sliding_lateral_lunge',
            75 => 'weighted_sliding_lateral_lunge',
            76 => 'walking_barbell_lunge',
            77 => 'walking_dumbbell_lunge',
            78 => 'walking_lunge',
            79 => 'weighted_walking_lunge',
            80 => 'wide_grip_overhead_barbell_split_squat',
        ],
        'olympic_lift_exercise_name' => [
            'type' => 'uint16',
            0 => 'barbell_hang_power_clean',
            1 => 'barbell_hang_squat_clean',
            2 => 'barbell_power_clean',
            3 => 'barbell_power_snatch',
            4 => 'barbell_squat_clean',
            5 => 'clean_and_jerk',
            6 => 'barbell_hang_power_snatch',
            7 => 'barbell_hang_pull',
            8 => 'barbell_high_pull',
            9 => 'barbell_snatch',
            10 => 'barbell_split_jerk',
            11 => 'clean',
            12 => 'dumbbell_clean',
            13 => 'dumbbell_hang_pull',
            14 => 'one_hand_dumbbell_split_snatch',
            15 => 'push_jerk',
            16 => 'single_arm_dumbbell_snatch',
            17 => 'single_arm_hang_snatch',
            18 => 'single_arm_kettlebell_snatch',
            19 => 'split_jerk',
            20 => 'squat_clean_and_jerk',
        ],
        'plank_exercise_name' => [
            'type' => 'uint16',
            0 => '45_degree_plank',
            1 => 'weighted_45_degree_plank',
            2 => '90_degree_static_hold',
            3 => 'weighted_90_degree_static_hold',
            4 => 'bear_crawl',
            5 => 'weighted_bear_crawl',
            6 => 'cross_body_mountain_climber',
            7 => 'weighted_cross_body_mountain_climber',
            8 => 'elbow_plank_pike_jacks',
            9 => 'weighted_elbow_plank_pike_jacks',
            10 => 'elevated_feet_plank',
            11 => 'weighted_elevated_feet_plank',
            12 => 'elevator_abs',
            13 => 'weighted_elevator_abs',
            14 => 'extended_plank',
            15 => 'weighted_extended_plank',
            16 => 'full_plank_passe_twist',
            17 => 'weighted_full_plank_passe_twist',
            18 => 'inching_elbow_plank',
            19 => 'weighted_inching_elbow_plank',
            20 => 'inchworm_to_side_plank',
            21 => 'weighted_inchworm_to_side_plank',
            22 => 'kneeling_plank',
            23 => 'weighted_kneeling_plank',
            24 => 'kneeling_side_plank_with_leg_lift',
            25 => 'weighted_kneeling_side_plank_with_leg_lift',
            26 => 'lateral_roll',
            27 => 'weighted_lateral_roll',
            28 => 'lying_reverse_plank',
            29 => 'weighted_lying_reverse_plank',
            30 => 'medicine_ball_mountain_climber',
            31 => 'weighted_medicine_ball_mountain_climber',
            32 => 'modified_mountain_climber_and_extension',
            33 => 'weighted_modified_mountain_climber_and_extension',
            34 => 'mountain_climber',
            35 => 'weighted_mountain_climber',
            36 => 'mountain_climber_on_sliding_discs',
            37 => 'weighted_mountain_climber_on_sliding_discs',
            38 => 'mountain_climber_with_feet_on_bosu_ball',
            39 => 'weighted_mountain_climber_with_feet_on_bosu_ball',
            40 => 'mountain_climber_with_hands_on_bench',
            41 => 'mountain_climber_with_hands_on_swiss_ball',
            42 => 'weighted_mountain_climber_with_hands_on_swiss_ball',
            43 => 'plank',
            44 => 'plank_jacks_with_feet_on_sliding_discs',
            45 => 'weighted_plank_jacks_with_feet_on_sliding_discs',
            46 => 'plank_knee_twist',
            47 => 'weighted_plank_knee_twist',
            48 => 'plank_pike_jumps',
            49 => 'weighted_plank_pike_jumps',
            50 => 'plank_pikes',
            51 => 'weighted_plank_pikes',
            52 => 'plank_to_stand_up',
            53 => 'weighted_plank_to_stand_up',
            54 => 'plank_with_arm_raise',
            55 => 'weighted_plank_with_arm_raise',
            56 => 'plank_with_knee_to_elbow',
            57 => 'weighted_plank_with_knee_to_elbow',
            58 => 'plank_with_oblique_crunch',
            59 => 'weighted_plank_with_oblique_crunch',
            60 => 'plyometric_side_plank',
            61 => 'weighted_plyometric_side_plank',
            62 => 'rolling_side_plank',
            63 => 'weighted_rolling_side_plank',
            64 => 'side_kick_plank',
            65 => 'weighted_side_kick_plank',
            66 => 'side_plank',
            67 => 'weighted_side_plank',
            68 => 'side_plank_and_row',
            69 => 'weighted_side_plank_and_row',
            70 => 'side_plank_lift',
            71 => 'weighted_side_plank_lift',
            72 => 'side_plank_with_elbow_on_bosu_ball',
            73 => 'weighted_side_plank_with_elbow_on_bosu_ball',
            74 => 'side_plank_with_feet_on_bench',
            75 => 'weighted_side_plank_with_feet_on_bench',
            76 => 'side_plank_with_knee_circle',
            77 => 'weighted_side_plank_with_knee_circle',
            78 => 'side_plank_with_knee_tuck',
            79 => 'weighted_side_plank_with_knee_tuck',
            80 => 'side_plank_with_leg_lift',
            81 => 'weighted_side_plank_with_leg_lift',
            82 => 'side_plank_with_reach_under',
            83 => 'weighted_side_plank_with_reach_under',
            84 => 'single_leg_elevated_feet_plank',
            85 => 'weighted_single_leg_elevated_feet_plank',
            86 => 'single_leg_flex_and_extend',
            87 => 'weighted_single_leg_flex_and_extend',
            88 => 'single_leg_side_plank',
            89 => 'weighted_single_leg_side_plank',
            90 => 'spiderman_plank',
            91 => 'weighted_spiderman_plank',
            92 => 'straight_arm_plank',
            93 => 'weighted_straight_arm_plank',
            94 => 'straight_arm_plank_with_shoulder_touch',
            95 => 'weighted_straight_arm_plank_with_shoulder_touch',
            96 => 'swiss_ball_plank',
            97 => 'weighted_swiss_ball_plank',
            98 => 'swiss_ball_plank_leg_lift',
            99 => 'weighted_swiss_ball_plank_leg_lift',
            100 => 'swiss_ball_plank_leg_lift_and_hold',
            101 => 'swiss_ball_plank_with_feet_on_bench',
            102 => 'weighted_swiss_ball_plank_with_feet_on_bench',
            103 => 'swiss_ball_prone_jackknife',
            104 => 'weighted_swiss_ball_prone_jackknife',
            105 => 'swiss_ball_side_plank',
            106 => 'weighted_swiss_ball_side_plank',
            107 => 'three_way_plank',
            108 => 'weighted_three_way_plank',
            109 => 'towel_plank_and_knee_in',
            110 => 'weighted_towel_plank_and_knee_in',
            111 => 't_stabilization',
            112 => 'weighted_t_stabilization',
            113 => 'turkish_get_up_to_side_plank',
            114 => 'weighted_turkish_get_up_to_side_plank',
            115 => 'two_point_plank',
            116 => 'weighted_two_point_plank',
            117 => 'weighted_plank',
            118 => 'wide_stance_plank_with_diagonal_arm_lift',
            119 => 'weighted_wide_stance_plank_with_diagonal_arm_lift',
            120 => 'wide_stance_plank_with_diagonal_leg_lift',
            121 => 'weighted_wide_stance_plank_with_diagonal_leg_lift',
            122 => 'wide_stance_plank_with_leg_lift',
            123 => 'weighted_wide_stance_plank_with_leg_lift',
            124 => 'wide_stance_plank_with_opposite_arm_and_leg_lift',
            125 => 'weighted_mountain_climber_with_hands_on_bench',
            126 => 'weighted_swiss_ball_plank_leg_lift_and_hold',
            127 => 'weighted_wide_stance_plank_with_opposite_arm_and_leg_lift',
            128 => 'plank_with_feet_on_swiss_ball',
            129 => 'side_plank_to_plank_with_reach_under',
            130 => 'bridge_with_glute_lower_lift',
            131 => 'bridge_one_leg_bridge',
            132 => 'plank_with_arm_variations',
            133 => 'plank_with_leg_lift',
            134 => 'reverse_plank_with_leg_pull',
        ],
        'plyo_exercise_name' => [
            'type' => 'uint16',
            0 => 'alternating_jump_lunge',
            1 => 'weighted_alternating_jump_lunge',
            2 => 'barbell_jump_squat',
            3 => 'body_weight_jump_squat',
            4 => 'weighted_jump_squat',
            5 => 'cross_knee_strike',
            6 => 'weighted_cross_knee_strike',
            7 => 'depth_jump',
            8 => 'weighted_depth_jump',
            9 => 'dumbbell_jump_squat',
            10 => 'dumbbell_split_jump',
            11 => 'front_knee_strike',
            12 => 'weighted_front_knee_strike',
            13 => 'high_box_jump',
            14 => 'weighted_high_box_jump',
            15 => 'isometric_explosive_body_weight_jump_squat',
            16 => 'weighted_isometric_explosive_jump_squat',
            17 => 'lateral_leap_and_hop',
            18 => 'weighted_lateral_leap_and_hop',
            19 => 'lateral_plyo_squats',
            20 => 'weighted_lateral_plyo_squats',
            21 => 'lateral_slide',
            22 => 'weighted_lateral_slide',
            23 => 'medicine_ball_overhead_throws',
            24 => 'medicine_ball_side_throw',
            25 => 'medicine_ball_slam',
            26 => 'side_to_side_medicine_ball_throws',
            27 => 'side_to_side_shuffle_jump',
            28 => 'weighted_side_to_side_shuffle_jump',
            29 => 'squat_jump_onto_box',
            30 => 'weighted_squat_jump_onto_box',
            31 => 'squat_jumps_in_and_out',
            32 => 'weighted_squat_jumps_in_and_out',
        ],
        'pull_up_exercise_name' => [
            'type' => 'uint16',
            0 => 'banded_pull_ups',
            1 => '30_degree_lat_pulldown',
            2 => 'band_assisted_chin_up',
            3 => 'close_grip_chin_up',
            4 => 'weighted_close_grip_chin_up',
            5 => 'close_grip_lat_pulldown',
            6 => 'crossover_chin_up',
            7 => 'weighted_crossover_chin_up',
            8 => 'ez_bar_pullover',
            9 => 'hanging_hurdle',
            10 => 'weighted_hanging_hurdle',
            11 => 'kneeling_lat_pulldown',
            12 => 'kneeling_underhand_grip_lat_pulldown',
            13 => 'lat_pulldown',
            14 => 'mixed_grip_chin_up',
            15 => 'weighted_mixed_grip_chin_up',
            16 => 'mixed_grip_pull_up',
            17 => 'weighted_mixed_grip_pull_up',
            18 => 'reverse_grip_pulldown',
            19 => 'standing_cable_pullover',
            20 => 'straight_arm_pulldown',
            21 => 'swiss_ball_ez_bar_pullover',
            22 => 'towel_pull_up',
            23 => 'weighted_towel_pull_up',
            24 => 'weighted_pull_up',
            25 => 'wide_grip_lat_pulldown',
            26 => 'wide_grip_pull_up',
            27 => 'weighted_wide_grip_pull_up',
            28 => 'burpee_pull_up',
            29 => 'weighted_burpee_pull_up',
            30 => 'jumping_pull_ups',
            31 => 'weighted_jumping_pull_ups',
            32 => 'kipping_pull_up',
            33 => 'weighted_kipping_pull_up',
            34 => 'l_pull_up',
            35 => 'weighted_l_pull_up',
            36 => 'suspended_chin_up',
            37 => 'weighted_suspended_chin_up',
            38 => 'pull_up',
        ],
        'push_up_exercise_name' => [
            'type' => 'uint16',
            0 => 'chest_press_with_band',
            1 => 'alternating_staggered_push_up',
            2 => 'weighted_alternating_staggered_push_up',
            3 => 'alternating_hands_medicine_ball_push_up',
            4 => 'weighted_alternating_hands_medicine_ball_push_up',
            5 => 'bosu_ball_push_up',
            6 => 'weighted_bosu_ball_push_up',
            7 => 'clapping_push_up',
            8 => 'weighted_clapping_push_up',
            9 => 'close_grip_medicine_ball_push_up',
            10 => 'weighted_close_grip_medicine_ball_push_up',
            11 => 'close_hands_push_up',
            12 => 'weighted_close_hands_push_up',
            13 => 'decline_push_up',
            14 => 'weighted_decline_push_up',
            15 => 'diamond_push_up',
            16 => 'weighted_diamond_push_up',
            17 => 'explosive_crossover_push_up',
            18 => 'weighted_explosive_crossover_push_up',
            19 => 'explosive_push_up',
            20 => 'weighted_explosive_push_up',
            21 => 'feet_elevated_side_to_side_push_up',
            22 => 'weighted_feet_elevated_side_to_side_push_up',
            23 => 'hand_release_push_up',
            24 => 'weighted_hand_release_push_up',
            25 => 'handstand_push_up',
            26 => 'weighted_handstand_push_up',
            27 => 'incline_push_up',
            28 => 'weighted_incline_push_up',
            29 => 'isometric_explosive_push_up',
            30 => 'weighted_isometric_explosive_push_up',
            31 => 'judo_push_up',
            32 => 'weighted_judo_push_up',
            33 => 'kneeling_push_up',
            34 => 'weighted_kneeling_push_up',
            35 => 'medicine_ball_chest_pass',
            36 => 'medicine_ball_push_up',
            37 => 'weighted_medicine_ball_push_up',
            38 => 'one_arm_push_up',
            39 => 'weighted_one_arm_push_up',
            40 => 'weighted_push_up',
            41 => 'push_up_and_row',
            42 => 'weighted_push_up_and_row',
            43 => 'push_up_plus',
            44 => 'weighted_push_up_plus',
            45 => 'push_up_with_feet_on_swiss_ball',
            46 => 'weighted_push_up_with_feet_on_swiss_ball',
            47 => 'push_up_with_one_hand_on_medicine_ball',
            48 => 'weighted_push_up_with_one_hand_on_medicine_ball',
            49 => 'shoulder_push_up',
            50 => 'weighted_shoulder_push_up',
            51 => 'single_arm_medicine_ball_push_up',
            52 => 'weighted_single_arm_medicine_ball_push_up',
            53 => 'spiderman_push_up',
            54 => 'weighted_spiderman_push_up',
            55 => 'stacked_feet_push_up',
            56 => 'weighted_stacked_feet_push_up',
            57 => 'staggered_hands_push_up',
            58 => 'weighted_staggered_hands_push_up',
            59 => 'suspended_push_up',
            60 => 'weighted_suspended_push_up',
            61 => 'swiss_ball_push_up',
            62 => 'weighted_swiss_ball_push_up',
            63 => 'swiss_ball_push_up_plus',
            64 => 'weighted_swiss_ball_push_up_plus',
            65 => 't_push_up',
            66 => 'weighted_t_push_up',
            67 => 'triple_stop_push_up',
            68 => 'weighted_triple_stop_push_up',
            69 => 'wide_hands_push_up',
            70 => 'weighted_wide_hands_push_up',
            71 => 'parallette_handstand_push_up',
            72 => 'weighted_parallette_handstand_push_up',
            73 => 'ring_handstand_push_up',
            74 => 'weighted_ring_handstand_push_up',
            75 => 'ring_push_up',
            76 => 'weighted_ring_push_up',
            77 => 'push_up',
            78 => 'pilates_pushup',
        ],
        'row_exercise_name' => [
            'type' => 'uint16',
            0 => 'barbell_straight_leg_deadlift_to_row',
            1 => 'cable_row_standing',
            2 => 'dumbbell_row',
            3 => 'elevated_feet_inverted_row',
            4 => 'weighted_elevated_feet_inverted_row',
            5 => 'face_pull',
            6 => 'face_pull_with_external_rotation',
            7 => 'inverted_row_with_feet_on_swiss_ball',
            8 => 'weighted_inverted_row_with_feet_on_swiss_ball',
            9 => 'kettlebell_row',
            10 => 'modified_inverted_row',
            11 => 'weighted_modified_inverted_row',
            12 => 'neutral_grip_alternating_dumbbell_row',
            13 => 'one_arm_bent_over_row',
            14 => 'one_legged_dumbbell_row',
            15 => 'renegade_row',
            16 => 'reverse_grip_barbell_row',
            17 => 'rope_handle_cable_row',
            18 => 'seated_cable_row',
            19 => 'seated_dumbbell_row',
            20 => 'single_arm_cable_row',
            21 => 'single_arm_cable_row_and_rotation',
            22 => 'single_arm_inverted_row',
            23 => 'weighted_single_arm_inverted_row',
            24 => 'single_arm_neutral_grip_dumbbell_row',
            25 => 'single_arm_neutral_grip_dumbbell_row_and_rotation',
            26 => 'suspended_inverted_row',
            27 => 'weighted_suspended_inverted_row',
            28 => 't_bar_row',
            29 => 'towel_grip_inverted_row',
            30 => 'weighted_towel_grip_inverted_row',
            31 => 'underhand_grip_cable_row',
            32 => 'v_grip_cable_row',
            33 => 'wide_grip_seated_cable_row',
        ],
        'shoulder_press_exercise_name' => [
            'type' => 'uint16',
            0 => 'alternating_dumbbell_shoulder_press',
            1 => 'arnold_press',
            2 => 'barbell_front_squat_to_push_press',
            3 => 'barbell_push_press',
            4 => 'barbell_shoulder_press',
            5 => 'dead_curl_press',
            6 => 'dumbbell_alternating_shoulder_press_and_twist',
            7 => 'dumbbell_hammer_curl_to_lunge_to_press',
            8 => 'dumbbell_push_press',
            9 => 'floor_inverted_shoulder_press',
            10 => 'weighted_floor_inverted_shoulder_press',
            11 => 'inverted_shoulder_press',
            12 => 'weighted_inverted_shoulder_press',
            13 => 'one_arm_push_press',
            14 => 'overhead_barbell_press',
            15 => 'overhead_dumbbell_press',
            16 => 'seated_barbell_shoulder_press',
            17 => 'seated_dumbbell_shoulder_press',
            18 => 'single_arm_dumbbell_shoulder_press',
            19 => 'single_arm_step_up_and_press',
            20 => 'smith_machine_overhead_press',
            21 => 'split_stance_hammer_curl_to_press',
            22 => 'swiss_ball_dumbbell_shoulder_press',
            23 => 'weight_plate_front_raise',
        ],
        'shoulder_stability_exercise_name' => [
            'type' => 'uint16',
            0 => '90_degree_cable_external_rotation',
            1 => 'band_external_rotation',
            2 => 'band_internal_rotation',
            3 => 'bent_arm_lateral_raise_and_external_rotation',
            4 => 'cable_external_rotation',
            5 => 'dumbbell_face_pull_with_external_rotation',
            6 => 'floor_i_raise',
            7 => 'weighted_floor_i_raise',
            8 => 'floor_t_raise',
            9 => 'weighted_floor_t_raise',
            10 => 'floor_y_raise',
            11 => 'weighted_floor_y_raise',
            12 => 'incline_i_raise',
            13 => 'weighted_incline_i_raise',
            14 => 'incline_l_raise',
            15 => 'weighted_incline_l_raise',
            16 => 'incline_t_raise',
            17 => 'weighted_incline_t_raise',
            18 => 'incline_w_raise',
            19 => 'weighted_incline_w_raise',
            20 => 'incline_y_raise',
            21 => 'weighted_incline_y_raise',
            22 => 'lying_external_rotation',
            23 => 'seated_dumbbell_external_rotation',
            24 => 'standing_l_raise',
            25 => 'swiss_ball_i_raise',
            26 => 'weighted_swiss_ball_i_raise',
            27 => 'swiss_ball_t_raise',
            28 => 'weighted_swiss_ball_t_raise',
            29 => 'swiss_ball_w_raise',
            30 => 'weighted_swiss_ball_w_raise',
            31 => 'swiss_ball_y_raise',
            32 => 'weighted_swiss_ball_y_raise',
        ],
        'shrug_exercise_name' => [
            'type' => 'uint16',
            0 => 'barbell_jump_shrug',
            1 => 'barbell_shrug',
            2 => 'barbell_upright_row',
            3 => 'behind_the_back_smith_machine_shrug',
            4 => 'dumbbell_jump_shrug',
            5 => 'dumbbell_shrug',
            6 => 'dumbbell_upright_row',
            7 => 'incline_dumbbell_shrug',
            8 => 'overhead_barbell_shrug',
            9 => 'overhead_dumbbell_shrug',
            10 => 'scaption_and_shrug',
            11 => 'scapular_retraction',
            12 => 'serratus_chair_shrug',
            13 => 'weighted_serratus_chair_shrug',
            14 => 'serratus_shrug',
            15 => 'weighted_serratus_shrug',
            16 => 'wide_grip_jump_shrug',
        ],
        'sit_up_exercise_name' => [
            'type' => 'uint16',
            0 => 'alternating_sit_up',
            1 => 'weighted_alternating_sit_up',
            2 => 'bent_knee_v_up',
            3 => 'weighted_bent_knee_v_up',
            4 => 'butterfly_sit_up',
            5 => 'weighted_butterfly_situp',
            6 => 'cross_punch_roll_up',
            7 => 'weighted_cross_punch_roll_up',
            8 => 'crossed_arms_sit_up',
            9 => 'weighted_crossed_arms_sit_up',
            10 => 'get_up_sit_up',
            11 => 'weighted_get_up_sit_up',
            12 => 'hovering_sit_up',
            13 => 'weighted_hovering_sit_up',
            14 => 'kettlebell_sit_up',
            15 => 'medicine_ball_alternating_v_up',
            16 => 'medicine_ball_sit_up',
            17 => 'medicine_ball_v_up',
            18 => 'modified_sit_up',
            19 => 'negative_sit_up',
            20 => 'one_arm_full_sit_up',
            21 => 'reclining_circle',
            22 => 'weighted_reclining_circle',
            23 => 'reverse_curl_up',
            24 => 'weighted_reverse_curl_up',
            25 => 'single_leg_swiss_ball_jackknife',
            26 => 'weighted_single_leg_swiss_ball_jackknife',
            27 => 'the_teaser',
            28 => 'the_teaser_weighted',
            29 => 'three_part_roll_down',
            30 => 'weighted_three_part_roll_down',
            31 => 'v_up',
            32 => 'weighted_v_up',
            33 => 'weighted_russian_twist_on_swiss_ball',
            34 => 'weighted_sit_up',
            35 => 'x_abs',
            36 => 'weighted_x_abs',
            37 => 'sit_up',
        ],
        'squat_exercise_name' => [
            'type' => 'uint16',
            0 => 'leg_press',
            1 => 'back_squat_with_body_bar',
            2 => 'back_squats',
            3 => 'weighted_back_squats',
            4 => 'balancing_squat',
            5 => 'weighted_balancing_squat',
            6 => 'barbell_back_squat',
            7 => 'barbell_box_squat',
            8 => 'barbell_front_squat',
            9 => 'barbell_hack_squat',
            10 => 'barbell_hang_squat_snatch',
            11 => 'barbell_lateral_step_up',
            12 => 'barbell_quarter_squat',
            13 => 'barbell_siff_squat',
            14 => 'barbell_squat_snatch',
            15 => 'barbell_squat_with_heels_raised',
            16 => 'barbell_stepover',
            17 => 'barbell_step_up',
            18 => 'bench_squat_with_rotational_chop',
            19 => 'weighted_bench_squat_with_rotational_chop',
            20 => 'body_weight_wall_squat',
            21 => 'weighted_wall_squat',
            22 => 'box_step_squat',
            23 => 'weighted_box_step_squat',
            24 => 'braced_squat',
            25 => 'crossed_arm_barbell_front_squat',
            26 => 'crossover_dumbbell_step_up',
            27 => 'dumbbell_front_squat',
            28 => 'dumbbell_split_squat',
            29 => 'dumbbell_squat',
            30 => 'dumbbell_squat_clean',
            31 => 'dumbbell_stepover',
            32 => 'dumbbell_step_up',
            33 => 'elevated_single_leg_squat',
            34 => 'weighted_elevated_single_leg_squat',
            35 => 'figure_four_squats',
            36 => 'weighted_figure_four_squats',
            37 => 'goblet_squat',
            38 => 'kettlebell_squat',
            39 => 'kettlebell_swing_overhead',
            40 => 'kettlebell_swing_with_flip_to_squat',
            41 => 'lateral_dumbbell_step_up',
            42 => 'one_legged_squat',
            43 => 'overhead_dumbbell_squat',
            44 => 'overhead_squat',
            45 => 'partial_single_leg_squat',
            46 => 'weighted_partial_single_leg_squat',
            47 => 'pistol_squat',
            48 => 'weighted_pistol_squat',
            49 => 'plie_slides',
            50 => 'weighted_plie_slides',
            51 => 'plie_squat',
            52 => 'weighted_plie_squat',
            53 => 'prisoner_squat',
            54 => 'weighted_prisoner_squat',
            55 => 'single_leg_bench_get_up',
            56 => 'weighted_single_leg_bench_get_up',
            57 => 'single_leg_bench_squat',
            58 => 'weighted_single_leg_bench_squat',
            59 => 'single_leg_squat_on_swiss_ball',
            60 => 'weighted_single_leg_squat_on_swiss_ball',
            61 => 'squat',
            62 => 'weighted_squat',
            63 => 'squats_with_band',
            64 => 'staggered_squat',
            65 => 'weighted_staggered_squat',
            66 => 'step_up',
            67 => 'weighted_step_up',
            68 => 'suitcase_squats',
            69 => 'sumo_squat',
            70 => 'sumo_squat_slide_in',
            71 => 'weighted_sumo_squat_slide_in',
            72 => 'sumo_squat_to_high_pull',
            73 => 'sumo_squat_to_stand',
            74 => 'weighted_sumo_squat_to_stand',
            75 => 'sumo_squat_with_rotation',
            76 => 'weighted_sumo_squat_with_rotation',
            77 => 'swiss_ball_body_weight_wall_squat',
            78 => 'weighted_swiss_ball_wall_squat',
            79 => 'thrusters',
            80 => 'uneven_squat',
            81 => 'weighted_uneven_squat',
            82 => 'waist_slimming_squat',
            83 => 'wall_ball',
            84 => 'wide_stance_barbell_squat',
            85 => 'wide_stance_goblet_squat',
            86 => 'zercher_squat',
            87 => 'kbs_overhead', // Deprecated do not use
            88 => 'squat_and_side_kick',
            89 => 'squat_jumps_in_n_out',
            90 => 'pilates_plie_squats_parallel_turned_out_flat_and_heels',
            91 => 'releve_straight_leg_and_knee_bent_with_one_leg_variation',
        ],
        'total_body_exercise_name' => [
            'type' => 'uint16',
            0 => 'burpee',
            1 => 'weighted_burpee',
            2 => 'burpee_box_jump',
            3 => 'weighted_burpee_box_jump',
            4 => 'high_pull_burpee',
            5 => 'man_makers',
            6 => 'one_arm_burpee',
            7 => 'squat_thrusts',
            8 => 'weighted_squat_thrusts',
            9 => 'squat_plank_push_up',
            10 => 'weighted_squat_plank_push_up',
            11 => 'standing_t_rotation_balance',
            12 => 'weighted_standing_t_rotation_balance',
        ],
        'triceps_extension_exercise_name' => [
            'type' => 'uint16',
            0 => 'bench_dip',
            1 => 'weighted_bench_dip',
            2 => 'body_weight_dip',
            3 => 'cable_kickback',
            4 => 'cable_lying_triceps_extension',
            5 => 'cable_overhead_triceps_extension',
            6 => 'dumbbell_kickback',
            7 => 'dumbbell_lying_triceps_extension',
            8 => 'ez_bar_overhead_triceps_extension',
            9 => 'incline_dip',
            10 => 'weighted_incline_dip',
            11 => 'incline_ez_bar_lying_triceps_extension',
            12 => 'lying_dumbbell_pullover_to_extension',
            13 => 'lying_ez_bar_triceps_extension',
            14 => 'lying_triceps_extension_to_close_grip_bench_press',
            15 => 'overhead_dumbbell_triceps_extension',
            16 => 'reclining_triceps_press',
            17 => 'reverse_grip_pressdown',
            18 => 'reverse_grip_triceps_pressdown',
            19 => 'rope_pressdown',
            20 => 'seated_barbell_overhead_triceps_extension',
            21 => 'seated_dumbbell_overhead_triceps_extension',
            22 => 'seated_ez_bar_overhead_triceps_extension',
            23 => 'seated_single_arm_overhead_dumbbell_extension',
            24 => 'single_arm_dumbbell_overhead_triceps_extension',
            25 => 'single_dumbbell_seated_overhead_triceps_extension',
            26 => 'single_leg_bench_dip_and_kick',
            27 => 'weighted_single_leg_bench_dip_and_kick',
            28 => 'single_leg_dip',
            29 => 'weighted_single_leg_dip',
            30 => 'static_lying_triceps_extension',
            31 => 'suspended_dip',
            32 => 'weighted_suspended_dip',
            33 => 'swiss_ball_dumbbell_lying_triceps_extension',
            34 => 'swiss_ball_ez_bar_lying_triceps_extension',
            35 => 'swiss_ball_ez_bar_overhead_triceps_extension',
            36 => 'tabletop_dip',
            37 => 'weighted_tabletop_dip',
            38 => 'triceps_extension_on_floor',
            39 => 'triceps_pressdown',
            40 => 'weighted_dip',
        ],
        'warm_up_exercise_name' => [
            'type' => 'uint16',
            0 => 'quadruped_rocking',
            1 => 'neck_tilts',
            2 => 'ankle_circles',
            3 => 'ankle_dorsiflexion_with_band',
            4 => 'ankle_internal_rotation',
            5 => 'arm_circles',
            6 => 'bent_over_reach_to_sky',
            7 => 'cat_camel',
            8 => 'elbow_to_foot_lunge',
            9 => 'forward_and_backward_leg_swings',
            10 => 'groiners',
            11 => 'inverted_hamstring_stretch',
            12 => 'lateral_duck_under',
            13 => 'neck_rotations',
            14 => 'opposite_arm_and_leg_balance',
            15 => 'reach_roll_and_lift',
            16 => 'scorpion', // Deprecated do not use
            17 => 'shoulder_circles',
            18 => 'side_to_side_leg_swings',
            19 => 'sleeper_stretch',
            20 => 'slide_out',
            21 => 'swiss_ball_hip_crossover',
            22 => 'swiss_ball_reach_roll_and_lift',
            23 => 'swiss_ball_windshield_wipers',
            24 => 'thoracic_rotation',
            25 => 'walking_high_kicks',
            26 => 'walking_high_knees',
            27 => 'walking_knee_hugs',
            28 => 'walking_leg_cradles',
            29 => 'walkout',
            30 => 'walkout_from_push_up_position',
        ],
        'run_exercise_name' => [
            'type' => 'uint16',
            0 => 'run',
            1 => 'walk',
            2 => 'jog',
            3 => 'sprint',
        ],
        'water_type' => [
            'type' => 'enum',
            0 => 'fresh',
            1 => 'salt',
            2 => 'en13319',
            3 => 'custom',
        ],
        'tissue_model_type' => [
            'type' => 'enum',
            0 => 'zhl_16c', // Buhlmann's decompression algorithm, version C
        ],
        'dive_gas_status' => [
            'type' => 'enum',
            0 => 'disabled',
            1 => 'enabled',
            2 => 'backup_only',
        ],
        'dive_alarm_type' => [
            'type' => 'enum',
            0 => 'depth', // Alarm when a certain depth is crossed
            1 => 'time', // Alarm when a certain time has transpired
        ],
        'dive_backlight_mode' => [
            'type' => 'enum',
            0 => 'at_depth',
            1 => 'always_on',
        ],
        'favero_product' => [
            'type' => 'uint16',
            10 => 'assioma_uno',
            12 => 'assioma_duo',
        ],
        'split_type' => [
            'type' => 'enum',
            1 => 'ascent_split',
            2 => 'descent_split',
            3 => 'interval_active',
            4 => 'interval_rest',
            5 => 'interval_warmup',
            6 => 'interval_cooldown',
            7 => 'interval_recovery',
            8 => 'interval_other',
            9 => 'climb_active',
            10 => 'climb_rest',
            11 => 'surf_active',
            12 => 'run_active',
            13 => 'run_rest',
            14 => 'workout_round',
            17 => 'rwd_run', // run/walk detection running
            18 => 'rwd_walk', // run/walk detection walking
            21 => 'windsurf_active',
            22 => 'rwd_stand', // run/walk detection standing
            23 => 'transition', // Marks the time going from ascent_split to descent_split/used in backcountry ski
            28 => 'ski_lift_split',
            29 => 'ski_run_split',
        ],
        'climb_pro_event' => [
            'type' => 'enum',
            0 => 'approach',
            1 => 'start',
            2 => 'complete',
        ],
        'tap_sensitivity' => [
            'type' => 'enum',
            0 => 'high',
            1 => 'medium',
            2 => 'low',
        ],
        'radar_threat_level_type' => [
            'type' => 'enum',
            0 => 'threat_unknown',
            1 => 'threat_none',
            2 => 'threat_approaching',
            3 => 'threat_approaching_fast',
        ]
    ];

    /**
     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 2.2.pdf
     * Table 4-6. FIT Base Types and Invalid Values
     *
     * $types array holds a string used by the PHP unpack() function to format binary data.
     * 'tmp' is the name of the (single element) array created.
     */
    private $endianness = [
        0 => [// Little Endianness
            0 => ['format' => 'Ctmp', 'bytes' => 1], // enum
            1 => ['format' => 'ctmp', 'bytes' => 1], // sint8
            2 => ['format' => 'Ctmp', 'bytes' => 1], // uint8
            131 => ['format' => 'vtmp', 'bytes' => 2], // sint16 - manually convert uint16 to sint16 in fixData()
            132 => ['format' => 'vtmp', 'bytes' => 2], // uint16
            133 => ['format' => 'Vtmp', 'bytes' => 4], // sint32 - manually convert uint32 to sint32 in fixData()
            134 => ['format' => 'Vtmp', 'bytes' => 4], // uint32
            7 => ['format' => 'a*tmp', 'bytes' => 1], // string
            136 => ['format' => 'ftmp', 'bytes' => 4], // float32
            137 => ['format' => 'dtmp', 'bytes' => 8], // float64
            10 => ['format' => 'Ctmp', 'bytes' => 1], // uint8z
            139 => ['format' => 'vtmp', 'bytes' => 2], // uint16z
            140 => ['format' => 'Vtmp', 'bytes' => 4], // uint32z
            13 => ['format' => 'Ctmp', 'bytes' => 1], // byte
            142 => ['format' => 'Ptmp', 'bytes' => 8], // sint64 - manually convert uint64 to sint64 in fixData()
            143 => ['format' => 'Ptmp', 'bytes' => 8], // uint64
            144 => ['format' => 'Ptmp', 'bytes' => 8]   // uint64z
        ],
        1 => [// Big Endianness
            0 => ['format' => 'Ctmp', 'bytes' => 1], // enum
            1 => ['format' => 'ctmp', 'bytes' => 1], // sint8
            2 => ['format' => 'Ctmp', 'bytes' => 1], // uint8
            131 => ['format' => 'ntmp', 'bytes' => 2], // sint16 - manually convert uint16 to sint16 in fixData()
            132 => ['format' => 'ntmp', 'bytes' => 2], // uint16
            133 => ['format' => 'Ntmp', 'bytes' => 4], // sint32 - manually convert uint32 to sint32 in fixData()
            134 => ['format' => 'Ntmp', 'bytes' => 4], // uint32
            7 => ['format' => 'a*tmp', 'bytes' => 1], // string
            136 => ['format' => 'ftmp', 'bytes' => 4], // float32
            137 => ['format' => 'dtmp', 'bytes' => 8], // float64
            10 => ['format' => 'Ctmp', 'bytes' => 1], // uint8z
            139 => ['format' => 'ntmp', 'bytes' => 2], // uint16z
            140 => ['format' => 'Ntmp', 'bytes' => 4], // uint32z
            13 => ['format' => 'Ctmp', 'bytes' => 1], // byte
            142 => ['format' => 'Jtmp', 'bytes' => 8], // sint64 - manually convert uint64 to sint64 in fixData()
            143 => ['format' => 'Jtmp', 'bytes' => 8], // uint64
            144 => ['format' => 'Jtmp', 'bytes' => 8]   // uint64z
        ]
    ];
    private $invalid_values = [
        0 => 255, // 0xFF
        1 => 127, // 0x7F
        2 => 255, // 0xFF
        131 => 32767, // 0x7FFF
        132 => 65535, // 0xFFFF
        133 => 2147483647, // 0x7FFFFFFF
        134 => 4294967295, // 0xFFFFFFFF
        7 => 0, // 0x00
        136 => 4294967295, // 0xFFFFFFFF
        137 => 9223372036854775807, // 0xFFFFFFFFFFFFFFFF
        10 => 0, // 0x00
        139 => 0, // 0x0000
        140 => 0, // 0x00000000
        13 => 255, // 0xFF
        142 => 9223372036854775807, // 0x7FFFFFFFFFFFFFFF
        143 => 18446744073709551615, // 0xFFFFFFFFFFFFFFFF
        144 => 0                     // 0x0000000000000000
    ];
    // VERSION: 21.101
    // Automatically extracted from FIT SDK https://developer.garmin.com/fit/download/
    // Extracted from CSV versions of the Profile Message tab in Profile.xlsx
    private $data_mesg_info = [
        0 => ['mesg_name' => 'file_id', 'field_defns' => [
                0 => ['field_name' => 'type', 'field_type' => 'file', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'product', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'favero_product', 'field_type' => 'favero_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'favero_electronics', 'ref_field_name' => 'manufacturer'],
                    1 => ['field_name' => 'garmin_product', 'field_type' => 'garmin_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'garmin,dynastream,dynastream_oem,tacx', 'ref_field_name' => 'manufacturer,manufacturer,manufacturer,manufacturer'],
                ],
                3 => ['field_name' => 'serial_number', 'field_type' => 'uint32z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'time_created', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Only set for files that are can be created/erased. (e.g. 1)
                5 => ['field_name' => 'number', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Only set for files that are not created/erased. (e.g. 1)
                8 => ['field_name' => 'product_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Optional free form string to indicate the devices name or model (e.g. 20)
            ]],
        49 => ['mesg_name' => 'file_creator', 'field_defns' => [
                0 => ['field_name' => 'software_version', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'hardware_version', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        162 => ['mesg_name' => 'timestamp_correlation', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of UTC timestamp at the time the system timestamp was recorded. (e.g. )
                0 => ['field_name' => 'fractional_timestamp', 'field_type' => 'uint16', 'scale' => 32768, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of the UTC timestamp at the time the system timestamp was recorded. (e.g. )
                1 => ['field_name' => 'system_timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the system timestamp (e.g. )
                2 => ['field_name' => 'fractional_system_timestamp', 'field_type' => 'uint16', 'scale' => 32768, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of the system timestamp (e.g. )
                3 => ['field_name' => 'local_timestamp', 'field_type' => 'local_date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // timestamp epoch expressed in local time used to convert timestamps to local time (e.g. )
                4 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the UTC timestamp at the time the system timestamp was recorded. (e.g. )
                5 => ['field_name' => 'system_timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the system timestamp (e.g. )
            ]],
        35 => ['mesg_name' => 'software', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'version', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'part_number', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        106 => ['mesg_name' => 'slave_device', 'field_defns' => [
                0 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'product', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'favero_product', 'field_type' => 'favero_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'favero_electronics', 'ref_field_name' => 'manufacturer'],
                    1 => ['field_name' => 'garmin_product', 'field_type' => 'garmin_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'garmin,dynastream,dynastream_oem,tacx', 'ref_field_name' => 'manufacturer,manufacturer,manufacturer,manufacturer'],
                ],
            ],
            'capabilities' => [
                0 => ['field_name' => 'languages', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use language_bits_x types where x is index of array. (e.g. 4)
                1 => ['field_name' => 'sports', 'field_type' => 'sport_bits_0', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use sport_bits_x types where x is index of array. (e.g. 1)
                21 => ['field_name' => 'workouts_supported', 'field_type' => 'workout_capabilities', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'connectivity_supported', 'field_type' => 'connectivity_capabilities', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        37 => ['mesg_name' => 'file_capabilities', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'type', 'field_type' => 'file', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'flags', 'field_type' => 'file_flags', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'directory', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'max_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'max_size', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'bytes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        38 => ['mesg_name' => 'mesg_capabilities', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'file', 'field_type' => 'file', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'mesg_num', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'count_type', 'field_type' => 'mesg_count', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'num_per_file', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'num_per_file', 'ref_field_name' => 'count_type'],
                    1 => ['field_name' => 'max_per_file', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'max_per_file', 'ref_field_name' => 'count_type'],
                    2 => ['field_name' => 'max_per_file_type', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'max_per_file_type', 'ref_field_name' => 'count_type'],
                ],
            ],
            'field_capabilities' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'file', 'field_type' => 'file', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'mesg_num', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'field_num', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        2 => ['mesg_name' => 'device_settings', 'field_defns' => [
                0 => ['field_name' => 'active_time_zone', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Index into time zone arrays. (e.g. 1)
                1 => ['field_name' => 'utc_offset', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Offset from system time. Required to convert timestamp from system time to UTC. (e.g. 1)
                2 => ['field_name' => 'time_offset', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Offset from system time. (e.g. 2)
                4 => ['field_name' => 'time_mode', 'field_type' => 'time_mode', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Display mode for the time (e.g. 2)
                5 => ['field_name' => 'time_zone_offset', 'field_type' => 'sint8', 'scale' => 4, 'offset' => 0, 'units' => 'hr', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // timezone offset in 1/4 hour increments (e.g. 2)
                12 => ['field_name' => 'backlight_mode', 'field_type' => 'backlight_mode', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Mode for backlight (e.g. 1)
                36 => ['field_name' => 'activity_tracker_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Enabled state of the activity tracker functionality (e.g. 1)
                39 => ['field_name' => 'clock_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // UTC timestamp used to set the devices clock and date (e.g. 1)
                40 => ['field_name' => 'pages_enabled', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Bitfield to configure enabled screens for each supported loop (e.g. 1)
                46 => ['field_name' => 'move_alert_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Enabled state of the move alert (e.g. 1)
                47 => ['field_name' => 'date_mode', 'field_type' => 'date_mode', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Display mode for the date (e.g. 1)
                55 => ['field_name' => 'display_orientation', 'field_type' => 'display_orientation', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                56 => ['field_name' => 'mounting_side', 'field_type' => 'side', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                57 => ['field_name' => 'default_page', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Bitfield to indicate one page as default for each supported loop (e.g. 1)
                58 => ['field_name' => 'autosync_min_steps', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'steps', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Minimum steps before an autosync can occur (e.g. 1)
                59 => ['field_name' => 'autosync_min_time', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'minutes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Minimum minutes before an autosync can occur (e.g. 1)
                80 => ['field_name' => 'lactate_threshold_autodetect_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Enable auto-detect setting for the lactate threshold feature. (e.g. )
                86 => ['field_name' => 'ble_auto_upload_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Automatically upload using BLE (e.g. )
                89 => ['field_name' => 'auto_sync_frequency', 'field_type' => 'auto_sync_frequency', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Helps to conserve battery by changing modes (e.g. )
                90 => ['field_name' => 'auto_activity_detect', 'field_type' => 'auto_activity_detect', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Allows setting specific activities auto-activity detect enabled/disabled settings (e.g. )
                94 => ['field_name' => 'number_of_screens', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of screens configured to display (e.g. )
                95 => ['field_name' => 'smart_notification_display_orientation', 'field_type' => 'display_orientation', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Smart Notification display orientation (e.g. )
                134 => ['field_name' => 'tap_interface', 'field_type' => 'switch', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                174 => ['field_name' => 'tap_sensitivity', 'field_type' => 'tap_sensitivity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Used to hold the tap threshold setting (e.g. 1)
            ]],
        3 => ['mesg_name' => 'user_profile', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'friendly_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'gender', 'field_type' => 'gender', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'age', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'years', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'height', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'weight', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'language', 'field_type' => 'language', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'elev_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'weight_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'resting_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'default_max_running_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'default_max_biking_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'default_max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'hr_setting', 'field_type' => 'display_heart', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'speed_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'dist_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'power_setting', 'field_type' => 'display_power', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'activity_class', 'field_type' => 'activity_class', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                18 => ['field_name' => 'position_setting', 'field_type' => 'display_position', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'temperature_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'local_id', 'field_type' => 'user_local_id', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'global_id', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                28 => ['field_name' => 'wake_time', 'field_type' => 'localtime_into_day', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Typical wake time (e.g. )
                29 => ['field_name' => 'sleep_time', 'field_type' => 'localtime_into_day', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Typical bed time (e.g. )
                30 => ['field_name' => 'height_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                31 => ['field_name' => 'user_running_step_length', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // User defined running step length set to 0 for auto length (e.g. 1)
                32 => ['field_name' => 'user_walking_step_length', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // User defined walking step length set to 0 for auto length (e.g. 1)
                47 => ['field_name' => 'depth_setting', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                49 => ['field_name' => 'dive_count', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        4 => ['mesg_name' => 'hrm_profile', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'hrm_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'log_hrv', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'hrm_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        5 => ['mesg_name' => 'sdm_profile', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'sdm_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'sdm_cal_factor', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'odometer', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'speed_source', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use footpod for speed source instead of GPS (e.g. 1)
                5 => ['field_name' => 'sdm_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'odometer_rollover', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Rollover counter that can be used to extend the odometer (e.g. 1)
            ]],
        6 => ['mesg_name' => 'bike_profile', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'odometer', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'bike_spd_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'bike_cad_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'bike_spdcad_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'bike_power_ant_id', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'custom_wheelsize', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'auto_wheelsize', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'bike_weight', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'power_cal_factor', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'auto_wheel_cal', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'auto_power_zero', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'id', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'spd_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'cad_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'spdcad_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                18 => ['field_name' => 'power_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                19 => ['field_name' => 'crank_length', 'field_type' => 'uint8', 'scale' => 2, 'offset' => -110, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                20 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'bike_spd_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'bike_cad_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'bike_spdcad_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'bike_power_ant_id_trans_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                37 => ['field_name' => 'odometer_rollover', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Rollover counter that can be used to extend the odometer (e.g. 1)
                38 => ['field_name' => 'front_gear_num', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of front gears (e.g. 1)
                39 => ['field_name' => 'front_gear', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of teeth on each gear 0 is innermost (e.g. 1)
                40 => ['field_name' => 'rear_gear_num', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of rear gears (e.g. 1)
                41 => ['field_name' => 'rear_gear', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of teeth on each gear 0 is innermost (e.g. 1)
                44 => ['field_name' => 'shimano_di2_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        127 => ['mesg_name' => 'connectivity', 'field_defns' => [
                0 => ['field_name' => 'bluetooth_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use Bluetooth for connectivity features (e.g. 1)
                1 => ['field_name' => 'bluetooth_le_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use Bluetooth Low Energy for connectivity features (e.g. 1)
                2 => ['field_name' => 'ant_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use ANT for connectivity features (e.g. 1)
                3 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'live_tracking_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'weather_conditions_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'weather_alerts_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'auto_activity_upload_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'course_download_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'workout_download_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'gps_ephemeris_download_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'incident_detection_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'grouptrack_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        159 => ['mesg_name' => 'watchface_settings', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'mode', 'field_type' => 'watchface_mode', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'layout', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'digital_layout', 'field_type' => 'digital_watchface_layout', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'digital', 'ref_field_name' => 'mode'],
                    1 => ['field_name' => 'analog_layout', 'field_type' => 'analog_watchface_layout', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'analog', 'ref_field_name' => 'mode'],
                ],
            ],
            'ohr_settings' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'enabled', 'field_type' => 'switch', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        216 => ['mesg_name' => 'time_in_zone', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'reference_mesg', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'reference_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'time_in_hr_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'time_in_speed_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'time_in_cadence_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'time_in_power_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'hr_zone_high_boundary', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'speed_zone_high_boundary', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'cadence_zone_high_bondary', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'power_zone_high_boundary', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'hr_calc_type', 'field_type' => 'hr_zone_calc', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'resting_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'threshold_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'pwr_calc_type', 'field_type' => 'pwr_zone_calc', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'functional_threshold_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        7 => ['mesg_name' => 'zones_target', 'field_defns' => [
                1 => ['field_name' => 'max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'threshold_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'functional_threshold_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'hr_calc_type', 'field_type' => 'hr_zone_calc', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'pwr_calc_type', 'field_type' => 'pwr_zone_calc', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        12 => ['mesg_name' => 'sport', 'field_defns' => [
                0 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        8 => ['mesg_name' => 'hr_zone', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'high_bpm', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        53 => ['mesg_name' => 'speed_zone', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'high_value', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        131 => ['mesg_name' => 'cadence_zone', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'high_value', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        9 => ['mesg_name' => 'power_zone', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'high_value', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        10 => ['mesg_name' => 'met_zone', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'high_bpm', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'calories', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'kcal / min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'fat_calories', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => 'kcal / min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        258 => ['mesg_name' => 'dive_settings', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'model', 'field_type' => 'tissue_model_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'gf_low', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'gf_high', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'water_type', 'field_type' => 'water_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'water_density', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kg/m^3', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fresh water is usually 1000; salt water is usually 1025 (e.g. )
                6 => ['field_name' => 'po2_warn', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Typically 1.40 (e.g. )
                7 => ['field_name' => 'po2_critical', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Typically 1.60 (e.g. )
                8 => ['field_name' => 'po2_deco', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'safety_stop_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'bottom_depth', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'bottom_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'apnea_countdown_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'apnea_countdown_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'backlight_mode', 'field_type' => 'dive_backlight_mode', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'backlight_brightness', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'backlight_timeout', 'field_type' => 'backlight_timeout', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'repeat_dive_interval', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time between surfacing and ending the activity (e.g. )
                18 => ['field_name' => 'safety_stop_time', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time at safety stop (if enabled) (e.g. )
                19 => ['field_name' => 'heart_rate_source_type', 'field_type' => 'source_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                20 => ['field_name' => 'heart_rate_source', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'heart_rate_antplus_device_type', 'field_type' => 'antplus_device_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'antplus', 'ref_field_name' => 'heart_rate_source_type'],
                    1 => ['field_name' => 'heart_rate_local_device_type', 'field_type' => 'local_device_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'local', 'ref_field_name' => 'heart_rate_source_type'],
                ],
            ],
            'dive_alarm' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Index of the alarm (e.g. )
                0 => ['field_name' => 'depth', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Depth setting (m) for depth type alarms (e.g. )
                1 => ['field_name' => 'time', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time setting (s) for time type alarms (e.g. )
                2 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Enablement flag (e.g. )
                3 => ['field_name' => 'alarm_type', 'field_type' => 'dive_alarm_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Alarm type setting (e.g. )
                4 => ['field_name' => 'sound', 'field_type' => 'tone', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Tone and Vibe setting for the alarm (e.g. )
                5 => ['field_name' => 'dive_types', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Dive types the alarm will trigger on (e.g. )
            ]],
        259 => ['mesg_name' => 'dive_gas', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'helium_content', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'oxygen_content', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'status', 'field_type' => 'dive_gas_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        15 => ['mesg_name' => 'goal', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'start_date', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'end_date', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'type', 'field_type' => 'goal', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'value', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'repeat', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'target_value', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'recurrence', 'field_type' => 'goal_recurrence', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'recurrence_value', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'source', 'field_type' => 'goal_source', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        34 => ['mesg_name' => 'activity', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Exclude pauses (e.g. 1)
                1 => ['field_name' => 'num_sessions', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'type', 'field_type' => 'activity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'local_timestamp', 'field_type' => 'local_date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // timestamp epoch expressed in local time, used to convert activity timestamps to local time (e.g. 1)
                6 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        18 => ['mesg_name' => 'session', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Selected bit is set for the current session. (e.g. 1)
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Sesson end time. (e.g. 1)
                0 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // session (e.g. 1)
                1 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // stop (e.g. 1)
                2 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'start_position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'start_position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'total_elapsed_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time (includes pauses) (e.g. 1)
                8 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timer Time (excludes pauses) (e.g. 1)
                9 => ['field_name' => 'total_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'total_cycles', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'total_strides', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'strides', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running,walking', 'ref_field_name' => 'sport,sport'],
                    1 => ['field_name' => 'total_strokes', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'strokes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cycling,swimming,rowing,stand_up_paddleboarding', 'ref_field_name' => 'sport,sport,sport,sport'],
                ],
                11 => ['field_name' => 'total_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'total_fat_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'avg_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_avg_speed', 'ref_field_type' => '', 'ref_field_name' => ''], // total_distance / total_timer_time (e.g. 1)
                15 => ['field_name' => 'max_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_max_speed', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'avg_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // average heart rate (excludes pause time) (e.g. 1)
                17 => ['field_name' => 'max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                18 => ['field_name' => 'avg_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // total_cycles / total_timer_time if non_zero_avg_cadence otherwise total_cycles / total_elapsed_time (e.g. 1)
                    0 => ['field_name' => 'avg_running_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'strides/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running', 'ref_field_name' => 'sport'],
                ],
                19 => ['field_name' => 'max_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'max_running_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'strides/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running', 'ref_field_name' => 'sport'],
                ],
                20 => ['field_name' => 'avg_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // total_power / total_timer_time if non_zero_avg_power otherwise total_power / total_elapsed_time (e.g. 1)
                21 => ['field_name' => 'max_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'total_ascent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'total_descent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'total_training_effect', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                25 => ['field_name' => 'first_lap_index', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                26 => ['field_name' => 'num_laps', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                27 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                28 => ['field_name' => 'trigger', 'field_type' => 'session_trigger', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                29 => ['field_name' => 'nec_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // North east corner latitude (e.g. 1)
                30 => ['field_name' => 'nec_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // North east corner longitude (e.g. 1)
                31 => ['field_name' => 'swc_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // South west corner latitude (e.g. 1)
                32 => ['field_name' => 'swc_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // South west corner longitude (e.g. 1)
                33 => ['field_name' => 'num_lengths', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'lengths', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // # of lengths of swim pool (e.g. 1)
                34 => ['field_name' => 'normalized_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                35 => ['field_name' => 'training_stress_score', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'tss', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                36 => ['field_name' => 'intensity_factor', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'if', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                37 => ['field_name' => 'left_right_balance', 'field_type' => 'left_right_balance_100', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                41 => ['field_name' => 'avg_stroke_count', 'field_type' => 'uint32', 'scale' => 10, 'offset' => 0, 'units' => 'strokes/lap', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                42 => ['field_name' => 'avg_stroke_distance', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                43 => ['field_name' => 'swim_stroke', 'field_type' => 'swim_stroke', 'scale' => 1, 'offset' => 0, 'units' => 'swim_stroke', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                44 => ['field_name' => 'pool_length', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                45 => ['field_name' => 'threshold_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                46 => ['field_name' => 'pool_length_unit', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                47 => ['field_name' => 'num_active_lengths', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'lengths', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // # of active lengths of swim pool (e.g. 1)
                48 => ['field_name' => 'total_work', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'J', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                49 => ['field_name' => 'avg_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_avg_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                50 => ['field_name' => 'max_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_max_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                51 => ['field_name' => 'gps_accuracy', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                52 => ['field_name' => 'avg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                53 => ['field_name' => 'avg_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                54 => ['field_name' => 'avg_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                55 => ['field_name' => 'max_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                56 => ['field_name' => 'max_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                57 => ['field_name' => 'avg_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                58 => ['field_name' => 'max_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                59 => ['field_name' => 'total_moving_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                60 => ['field_name' => 'avg_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                61 => ['field_name' => 'avg_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                62 => ['field_name' => 'max_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                63 => ['field_name' => 'max_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                64 => ['field_name' => 'min_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                65 => ['field_name' => 'time_in_hr_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                66 => ['field_name' => 'time_in_speed_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                67 => ['field_name' => 'time_in_cadence_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                68 => ['field_name' => 'time_in_power_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                69 => ['field_name' => 'avg_lap_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                70 => ['field_name' => 'best_lap_index', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                71 => ['field_name' => 'min_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_min_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                82 => ['field_name' => 'player_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                83 => ['field_name' => 'opponent_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                84 => ['field_name' => 'opponent_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                85 => ['field_name' => 'stroke_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // stroke_type enum used as the index (e.g. 1)
                86 => ['field_name' => 'zone_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // zone number used as the index (e.g. 1)
                87 => ['field_name' => 'max_ball_speed', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                88 => ['field_name' => 'avg_ball_speed', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                89 => ['field_name' => 'avg_vertical_oscillation', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                90 => ['field_name' => 'avg_stance_time_percent', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                91 => ['field_name' => 'avg_stance_time', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                92 => ['field_name' => 'avg_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the avg_cadence (e.g. 1)
                93 => ['field_name' => 'max_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the max_cadence (e.g. 1)
                94 => ['field_name' => 'total_fractional_cycles', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the total_cycles (e.g. 1)
                95 => ['field_name' => 'avg_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Avg saturated and unsaturated hemoglobin (e.g. )
                96 => ['field_name' => 'min_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min saturated and unsaturated hemoglobin (e.g. )
                97 => ['field_name' => 'max_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max saturated and unsaturated hemoglobin (e.g. )
                98 => ['field_name' => 'avg_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Avg percentage of hemoglobin saturated with oxygen (e.g. )
                99 => ['field_name' => 'min_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min percentage of hemoglobin saturated with oxygen (e.g. )
                100 => ['field_name' => 'max_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max percentage of hemoglobin saturated with oxygen (e.g. )
                101 => ['field_name' => 'avg_left_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                102 => ['field_name' => 'avg_right_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                103 => ['field_name' => 'avg_left_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                104 => ['field_name' => 'avg_right_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                105 => ['field_name' => 'avg_combined_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                111 => ['field_name' => 'sport_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                112 => ['field_name' => 'time_standing', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total time spend in the standing position (e.g. )
                113 => ['field_name' => 'stand_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of transitions to the standing state (e.g. )
                114 => ['field_name' => 'avg_left_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average platform center offset Left (e.g. )
                115 => ['field_name' => 'avg_right_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average platform center offset Right (e.g. )
                116 => ['field_name' => 'avg_left_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase angles. Indexes defined by power_phase_type. (e.g. )
                117 => ['field_name' => 'avg_left_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                118 => ['field_name' => 'avg_right_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                119 => ['field_name' => 'avg_right_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase peak angles data value indexes defined by power_phase_type. (e.g. )
                120 => ['field_name' => 'avg_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average power by position. Data value indexes defined by rider_position_type. (e.g. )
                121 => ['field_name' => 'max_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum power by position. Data value indexes defined by rider_position_type. (e.g. )
                122 => ['field_name' => 'avg_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                123 => ['field_name' => 'max_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                124 => ['field_name' => 'enhanced_avg_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // total_distance / total_timer_time (e.g. 1)
                125 => ['field_name' => 'enhanced_max_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                126 => ['field_name' => 'enhanced_avg_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                127 => ['field_name' => 'enhanced_min_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                128 => ['field_name' => 'enhanced_max_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                129 => ['field_name' => 'avg_lev_motor_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev average motor power during session (e.g. )
                130 => ['field_name' => 'max_lev_motor_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev maximum motor power during session (e.g. )
                131 => ['field_name' => 'lev_battery_consumption', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev battery consumption during session (e.g. )
                132 => ['field_name' => 'avg_vertical_ratio', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                133 => ['field_name' => 'avg_stance_time_balance', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                134 => ['field_name' => 'avg_step_length', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                137 => ['field_name' => 'total_anaerobic_training_effect', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                139 => ['field_name' => 'avg_vam', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                147 => ['field_name' => 'avg_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_avg_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                148 => ['field_name' => 'max_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_max_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                149 => ['field_name' => 'min_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_min_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                168 => ['field_name' => 'training_load_peak', 'field_type' => 'sint32', 'scale' => 65536, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                169 => ['field_name' => 'enhanced_avg_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                170 => ['field_name' => 'enhanced_max_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                180 => ['field_name' => 'enhanced_min_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                181 => ['field_name' => 'total_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                182 => ['field_name' => 'total_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                183 => ['field_name' => 'jump_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                186 => ['field_name' => 'avg_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                187 => ['field_name' => 'avg_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                199 => ['field_name' => 'total_fractional_ascent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_ascent (e.g. )
                200 => ['field_name' => 'total_fractional_descent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_descent (e.g. )
                208 => ['field_name' => 'avg_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                209 => ['field_name' => 'min_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                210 => ['field_name' => 'max_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        19 => ['mesg_name' => 'lap', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Lap end time. (e.g. 1)
                0 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'start_position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'start_position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'end_position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'end_position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'total_elapsed_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time (includes pauses) (e.g. 1)
                8 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timer Time (excludes pauses) (e.g. 1)
                9 => ['field_name' => 'total_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'total_cycles', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'total_strides', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'strides', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running,walking', 'ref_field_name' => 'sport,sport'],
                    1 => ['field_name' => 'total_strokes', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'strokes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cycling,swimming,rowing,stand_up_paddleboarding', 'ref_field_name' => 'sport,sport,sport,sport'],
                ],
                11 => ['field_name' => 'total_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'total_fat_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // If New Leaf (e.g. 1)
                13 => ['field_name' => 'avg_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_avg_speed', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'max_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_max_speed', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'avg_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'avg_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // total_cycles / total_timer_time if non_zero_avg_cadence otherwise total_cycles / total_elapsed_time (e.g. 1)
                    0 => ['field_name' => 'avg_running_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'strides/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running', 'ref_field_name' => 'sport'],
                ],
                18 => ['field_name' => 'max_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'max_running_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'strides/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'running', 'ref_field_name' => 'sport'],
                ],
                19 => ['field_name' => 'avg_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // total_power / total_timer_time if non_zero_avg_power otherwise total_power / total_elapsed_time (e.g. 1)
                20 => ['field_name' => 'max_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'total_ascent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'total_descent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'intensity', 'field_type' => 'intensity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'lap_trigger', 'field_type' => 'lap_trigger', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                25 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                26 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                32 => ['field_name' => 'num_lengths', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'lengths', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // # of lengths of swim pool (e.g. 1)
                33 => ['field_name' => 'normalized_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                34 => ['field_name' => 'left_right_balance', 'field_type' => 'left_right_balance_100', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                35 => ['field_name' => 'first_length_index', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                37 => ['field_name' => 'avg_stroke_distance', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                38 => ['field_name' => 'swim_stroke', 'field_type' => 'swim_stroke', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                39 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                40 => ['field_name' => 'num_active_lengths', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'lengths', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // # of active lengths of swim pool (e.g. 1)
                41 => ['field_name' => 'total_work', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'J', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                42 => ['field_name' => 'avg_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_avg_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                43 => ['field_name' => 'max_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_max_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                44 => ['field_name' => 'gps_accuracy', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                45 => ['field_name' => 'avg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                46 => ['field_name' => 'avg_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                47 => ['field_name' => 'avg_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                48 => ['field_name' => 'max_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                49 => ['field_name' => 'max_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                50 => ['field_name' => 'avg_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                51 => ['field_name' => 'max_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                52 => ['field_name' => 'total_moving_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                53 => ['field_name' => 'avg_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                54 => ['field_name' => 'avg_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                55 => ['field_name' => 'max_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                56 => ['field_name' => 'max_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                57 => ['field_name' => 'time_in_hr_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                58 => ['field_name' => 'time_in_speed_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                59 => ['field_name' => 'time_in_cadence_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                60 => ['field_name' => 'time_in_power_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                61 => ['field_name' => 'repetition_num', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                62 => ['field_name' => 'min_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_min_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                63 => ['field_name' => 'min_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                71 => ['field_name' => 'wkt_step_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                74 => ['field_name' => 'opponent_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                75 => ['field_name' => 'stroke_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // stroke_type enum used as the index (e.g. 1)
                76 => ['field_name' => 'zone_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // zone number used as the index (e.g. 1)
                77 => ['field_name' => 'avg_vertical_oscillation', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                78 => ['field_name' => 'avg_stance_time_percent', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                79 => ['field_name' => 'avg_stance_time', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                80 => ['field_name' => 'avg_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the avg_cadence (e.g. 1)
                81 => ['field_name' => 'max_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the max_cadence (e.g. 1)
                82 => ['field_name' => 'total_fractional_cycles', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the total_cycles (e.g. 1)
                83 => ['field_name' => 'player_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                84 => ['field_name' => 'avg_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Avg saturated and unsaturated hemoglobin (e.g. 1)
                85 => ['field_name' => 'min_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min saturated and unsaturated hemoglobin (e.g. 1)
                86 => ['field_name' => 'max_total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max saturated and unsaturated hemoglobin (e.g. 1)
                87 => ['field_name' => 'avg_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Avg percentage of hemoglobin saturated with oxygen (e.g. 1)
                88 => ['field_name' => 'min_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min percentage of hemoglobin saturated with oxygen (e.g. 1)
                89 => ['field_name' => 'max_saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max percentage of hemoglobin saturated with oxygen (e.g. 1)
                91 => ['field_name' => 'avg_left_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                92 => ['field_name' => 'avg_right_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                93 => ['field_name' => 'avg_left_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                94 => ['field_name' => 'avg_right_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                95 => ['field_name' => 'avg_combined_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                98 => ['field_name' => 'time_standing', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total time spent in the standing position (e.g. )
                99 => ['field_name' => 'stand_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of transitions to the standing state (e.g. )
                100 => ['field_name' => 'avg_left_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left platform center offset (e.g. )
                101 => ['field_name' => 'avg_right_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right platform center offset (e.g. )
                102 => ['field_name' => 'avg_left_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                103 => ['field_name' => 'avg_left_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                104 => ['field_name' => 'avg_right_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                105 => ['field_name' => 'avg_right_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                106 => ['field_name' => 'avg_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average power by position. Data value indexes defined by rider_position_type. (e.g. )
                107 => ['field_name' => 'max_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum power by position. Data value indexes defined by rider_position_type. (e.g. )
                108 => ['field_name' => 'avg_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                109 => ['field_name' => 'max_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                110 => ['field_name' => 'enhanced_avg_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                111 => ['field_name' => 'enhanced_max_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                112 => ['field_name' => 'enhanced_avg_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                113 => ['field_name' => 'enhanced_min_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                114 => ['field_name' => 'enhanced_max_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                115 => ['field_name' => 'avg_lev_motor_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev average motor power during lap (e.g. )
                116 => ['field_name' => 'max_lev_motor_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev maximum motor power during lap (e.g. )
                117 => ['field_name' => 'lev_battery_consumption', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev battery consumption during lap (e.g. )
                118 => ['field_name' => 'avg_vertical_ratio', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                119 => ['field_name' => 'avg_stance_time_balance', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                120 => ['field_name' => 'avg_step_length', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                121 => ['field_name' => 'avg_vam', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                136 => ['field_name' => 'enhanced_avg_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                137 => ['field_name' => 'enhanced_max_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                147 => ['field_name' => 'avg_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_avg_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                148 => ['field_name' => 'max_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_max_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                149 => ['field_name' => 'total_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                150 => ['field_name' => 'total_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                151 => ['field_name' => 'jump_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                153 => ['field_name' => 'avg_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                154 => ['field_name' => 'avg_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                156 => ['field_name' => 'total_fractional_ascent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_ascent (e.g. )
                157 => ['field_name' => 'total_fractional_descent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_descent (e.g. )
                158 => ['field_name' => 'avg_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                159 => ['field_name' => 'min_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                160 => ['field_name' => 'max_core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        101 => ['mesg_name' => 'length', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'total_elapsed_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'total_strokes', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'strokes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'avg_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'swim_stroke', 'field_type' => 'swim_stroke', 'scale' => 1, 'offset' => 0, 'units' => 'swim_stroke', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'avg_swimming_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'strokes/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'total_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'length_type', 'field_type' => 'length_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                18 => ['field_name' => 'player_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                19 => ['field_name' => 'opponent_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                20 => ['field_name' => 'stroke_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // stroke_type enum used as the index (e.g. 1)
                21 => ['field_name' => 'zone_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // zone number used as the index (e.g. 1)
                22 => ['field_name' => 'enhanced_avg_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'enhanced_max_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'avg_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_avg_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                25 => ['field_name' => 'max_respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_max_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        20 => ['mesg_name' => 'record', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_speed', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'compressed_speed_distance', 'field_type' => 'byte', 'scale' => '100,16', 'offset' => 0, 'units' => 'm/s,m', 'bits' => '12,12', 'accumulate' => '0,1', 'component' => 'speed,distance', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'resistance', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Relative. 0 is none 254 is Max. (e.g. 1)
                11 => ['field_name' => 'time_from_course', 'field_type' => 'sint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'cycle_length', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'speed_1s', 'field_type' => 'uint8', 'scale' => 16, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Speed at 1s intervals. Timestamp field indicates time of last array element. (e.g. 5)
                18 => ['field_name' => 'cycles', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'cycles', 'bits' => '8', 'accumulate' => '1', 'component' => 'total_cycles', 'ref_field_type' => '', 'ref_field_name' => ''],
                19 => ['field_name' => 'total_cycles', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                28 => ['field_name' => 'compressed_accumulated_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '16', 'accumulate' => '1', 'component' => 'accumulated_power', 'ref_field_type' => '', 'ref_field_name' => ''],
                29 => ['field_name' => 'accumulated_power', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                30 => ['field_name' => 'left_right_balance', 'field_type' => 'left_right_balance', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                31 => ['field_name' => 'gps_accuracy', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                32 => ['field_name' => 'vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                33 => ['field_name' => 'calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                39 => ['field_name' => 'vertical_oscillation', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                40 => ['field_name' => 'stance_time_percent', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                41 => ['field_name' => 'stance_time', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                42 => ['field_name' => 'activity_type', 'field_type' => 'activity_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                43 => ['field_name' => 'left_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                44 => ['field_name' => 'right_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                45 => ['field_name' => 'left_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                46 => ['field_name' => 'right_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                47 => ['field_name' => 'combined_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                48 => ['field_name' => 'time128', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                49 => ['field_name' => 'stroke_type', 'field_type' => 'stroke_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                50 => ['field_name' => 'zone', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                51 => ['field_name' => 'ball_speed', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                52 => ['field_name' => 'cadence256', 'field_type' => 'uint16', 'scale' => 256, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Log cadence and fractional cadence for backwards compatability (e.g. 1)
                53 => ['field_name' => 'fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                54 => ['field_name' => 'total_hemoglobin_conc', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total saturated and unsaturated hemoglobin (e.g. 1)
                55 => ['field_name' => 'total_hemoglobin_conc_min', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min saturated and unsaturated hemoglobin (e.g. 1)
                56 => ['field_name' => 'total_hemoglobin_conc_max', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'g/dL', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max saturated and unsaturated hemoglobin (e.g. 1)
                57 => ['field_name' => 'saturated_hemoglobin_percent', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Percentage of hemoglobin saturated with oxygen (e.g. 1)
                58 => ['field_name' => 'saturated_hemoglobin_percent_min', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min percentage of hemoglobin saturated with oxygen (e.g. 1)
                59 => ['field_name' => 'saturated_hemoglobin_percent_max', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max percentage of hemoglobin saturated with oxygen (e.g. 1)
                62 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                67 => ['field_name' => 'left_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Left platform center offset (e.g. )
                68 => ['field_name' => 'right_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Right platform center offset (e.g. )
                69 => ['field_name' => 'left_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Left power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                70 => ['field_name' => 'left_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Left power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                71 => ['field_name' => 'right_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Right power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                72 => ['field_name' => 'right_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Right power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                73 => ['field_name' => 'enhanced_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                78 => ['field_name' => 'enhanced_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                81 => ['field_name' => 'battery_soc', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev battery state of charge (e.g. )
                82 => ['field_name' => 'motor_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // lev motor power (e.g. )
                83 => ['field_name' => 'vertical_ratio', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                84 => ['field_name' => 'stance_time_balance', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                85 => ['field_name' => 'step_length', 'field_type' => 'uint16', 'scale' => 10, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                91 => ['field_name' => 'absolute_pressure', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'Pa', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Includes atmospheric pressure (e.g. )
                92 => ['field_name' => 'depth', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // 0 if above water (e.g. )
                93 => ['field_name' => 'next_stop_depth', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // 0 if above water (e.g. )
                94 => ['field_name' => 'next_stop_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                95 => ['field_name' => 'time_to_surface', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                96 => ['field_name' => 'ndl_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                97 => ['field_name' => 'cns_load', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                98 => ['field_name' => 'n2_load', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                99 => ['field_name' => 'respiration_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '8', 'accumulate' => '', 'component' => 'enhanced_respiration_rate', 'ref_field_type' => '', 'ref_field_name' => ''],
                108 => ['field_name' => 'enhanced_respiration_rate', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'Breaths/min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                114 => ['field_name' => 'grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                115 => ['field_name' => 'flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                117 => ['field_name' => 'ebike_travel_range', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'km', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                118 => ['field_name' => 'ebike_battery_level', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                119 => ['field_name' => 'ebike_assist_mode', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'depends on sensor', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                120 => ['field_name' => 'ebike_assist_level_percent', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                139 => ['field_name' => 'core_temperature', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        21 => ['mesg_name' => 'event', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'data16', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '16', 'accumulate' => '', 'component' => 'data', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'data', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'timer_trigger', 'field_type' => 'timer_trigger', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'timer', 'ref_field_name' => 'event'],
                    1 => ['field_name' => 'course_point_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'course_point', 'ref_field_name' => 'event'],
                    2 => ['field_name' => 'battery_level', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'V', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'battery', 'ref_field_name' => 'event'],
                    3 => ['field_name' => 'virtual_partner_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'virtual_partner_pace', 'ref_field_name' => 'event'],
                    4 => ['field_name' => 'hr_high_alert', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'hr_high_alert', 'ref_field_name' => 'event'],
                    5 => ['field_name' => 'hr_low_alert', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'hr_low_alert', 'ref_field_name' => 'event'],
                    6 => ['field_name' => 'speed_high_alert', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed_high_alert', 'ref_field_name' => 'event'],
                    7 => ['field_name' => 'speed_low_alert', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed_low_alert', 'ref_field_name' => 'event'],
                    8 => ['field_name' => 'cad_high_alert', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cad_high_alert', 'ref_field_name' => 'event'],
                    9 => ['field_name' => 'cad_low_alert', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cad_low_alert', 'ref_field_name' => 'event'],
                    10 => ['field_name' => 'power_high_alert', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power_high_alert', 'ref_field_name' => 'event'],
                    11 => ['field_name' => 'power_low_alert', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power_low_alert', 'ref_field_name' => 'event'],
                    12 => ['field_name' => 'time_duration_alert', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'time_duration_alert', 'ref_field_name' => 'event'],
                    13 => ['field_name' => 'distance_duration_alert', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'distance_duration_alert', 'ref_field_name' => 'event'],
                    14 => ['field_name' => 'calorie_duration_alert', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'calories', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'calorie_duration_alert', 'ref_field_name' => 'event'],
                    15 => ['field_name' => 'fitness_equipment_state', 'field_type' => 'fitness_equipment_state', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'fitness_equipment', 'ref_field_name' => 'event'],
                    16 => ['field_name' => 'sport_point', 'field_type' => 'uint32', 'scale' => '1,1', 'offset' => 0, 'units' => '', 'bits' => '16,16', 'accumulate' => '', 'component' => 'score,opponent_score', 'ref_field_type' => 'sport_point', 'ref_field_name' => 'event'],
                    17 => ['field_name' => 'gear_change_data', 'field_type' => 'uint32', 'scale' => '1,1,1,1', 'offset' => 0, 'units' => '', 'bits' => '8,8,8,8', 'accumulate' => '', 'component' => 'rear_gear_num,rear_gear,front_gear_num,front_gear', 'ref_field_type' => 'front_gear_change,rear_gear_change', 'ref_field_name' => 'event,event'],
                    18 => ['field_name' => 'rider_position', 'field_type' => 'rider_position_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'rider_position_change', 'ref_field_name' => 'event'], // Indicates the rider position value. (e.g. )
                    19 => ['field_name' => 'comm_timeout', 'field_type' => 'comm_timeout_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'comm_timeout', 'ref_field_name' => 'event'],
                    20 => ['field_name' => 'radar_threat_alert', 'field_type' => 'uint32', 'scale' => '1,1,10,10', 'offset' => 0, 'units' => '', 'bits' => '8,8,8,8', 'accumulate' => '', 'component' => 'radar_threat_level_max,radar_threat_count,radar_threat_avg_approach_speed,radar_threat_max_approach_speed', 'ref_field_type' => 'radar_threat_alert', 'ref_field_name' => 'event'], // The first byte is the radar_threat_level_max, the second byte is the radar_threat_count, third bytes is the average approach speed, and the 4th byte is the max approach speed (e.g. )
                ],
                4 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for sport_point subfield components (e.g. 1)
                8 => ['field_name' => 'opponent_score', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for sport_point subfield components (e.g. 1)
                9 => ['field_name' => 'front_gear_num', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for gear_change subfield components. Front gear number. 1 is innermost. (e.g. 1)
                10 => ['field_name' => 'front_gear', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for gear_change subfield components. Number of front teeth. (e.g. 1)
                11 => ['field_name' => 'rear_gear_num', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for gear_change subfield components. Rear gear number. 1 is innermost. (e.g. 1)
                12 => ['field_name' => 'rear_gear', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for gear_change subfield components. Number of rear teeth. (e.g. 1)
                13 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'radar_threat_level_max', 'field_type' => 'radar_threat_level_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for threat_alert subfield components. (e.g. 1)
                22 => ['field_name' => 'radar_threat_count', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for threat_alert subfield components. (e.g. 1)
                23 => ['field_name' => 'radar_threat_avg_approach_speed', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for radar_threat_alert subfield components (e.g. )
                24 => ['field_name' => 'radar_threat_max_approach_speed', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Do not populate directly. Autogenerated by decoder for radar_threat_alert subfield components (e.g. )
            ]],
        23 => ['mesg_name' => 'device_info', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'device_type', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'ble_device_type', 'field_type' => 'ble_device_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'bluetooth_low_energy', 'ref_field_name' => 'source_type'],
                    1 => ['field_name' => 'antplus_device_type', 'field_type' => 'antplus_device_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'antplus', 'ref_field_name' => 'source_type'],
                    2 => ['field_name' => 'ant_device_type', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'ant', 'ref_field_name' => 'source_type'],
                    3 => ['field_name' => 'local_device_type', 'field_type' => 'local_device_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'local', 'ref_field_name' => 'source_type'],
                ],
                2 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'serial_number', 'field_type' => 'uint32z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'product', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'favero_product', 'field_type' => 'favero_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'favero_electronics', 'ref_field_name' => 'manufacturer'],
                    1 => ['field_name' => 'garmin_product', 'field_type' => 'garmin_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'garmin,dynastream,dynastream_oem,tacx', 'ref_field_name' => 'manufacturer,manufacturer,manufacturer,manufacturer'],
                ],
                5 => ['field_name' => 'software_version', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'hardware_version', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'cum_operating_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Reset by new battery or charge. (e.g. 1)
                10 => ['field_name' => 'battery_voltage', 'field_type' => 'uint16', 'scale' => 256, 'offset' => 0, 'units' => 'V', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'battery_status', 'field_type' => 'battery_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                18 => ['field_name' => 'sensor_position', 'field_type' => 'body_location', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates the location of the sensor (e.g. 1)
                19 => ['field_name' => 'descriptor', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Used to describe the sensor or location (e.g. 1)
                20 => ['field_name' => 'ant_transmission_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'ant_device_number', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'ant_network', 'field_type' => 'ant_network', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                25 => ['field_name' => 'source_type', 'field_type' => 'source_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                27 => ['field_name' => 'product_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Optional free form string to indicate the devices name or model (e.g. 20)
                32 => ['field_name' => 'battery_level', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        375 => ['mesg_name' => 'device_aux_battery_info', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'battery_voltage', 'field_type' => 'uint16', 'scale' => 256, 'offset' => 0, 'units' => 'V', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'battery_status', 'field_type' => 'battery_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'battery_identifier', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        72 => ['mesg_name' => 'training_file', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'type', 'field_type' => 'file', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'product', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'favero_product', 'field_type' => 'favero_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'favero_electronics', 'ref_field_name' => 'manufacturer'],
                    1 => ['field_name' => 'garmin_product', 'field_type' => 'garmin_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'garmin,dynastream,dynastream_oem,tacx', 'ref_field_name' => 'manufacturer,manufacturer,manufacturer,manufacturer'],
                ],
                3 => ['field_name' => 'serial_number', 'field_type' => 'uint32z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'time_created', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        128 => ['mesg_name' => 'weather_conditions', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // time of update for current conditions, else forecast time (e.g. 1)
                0 => ['field_name' => 'weather_report', 'field_type' => 'weather_report', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Current or forecast (e.g. 1)
                1 => ['field_name' => 'temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'condition', 'field_type' => 'weather_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Corresponds to GSC Response weatherIcon field (e.g. 1)
                3 => ['field_name' => 'wind_direction', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'wind_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'precipitation_probability', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // range 0-100 (e.g. 1)
                6 => ['field_name' => 'temperature_feels_like', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Heat Index if GCS heatIdx above or equal to 90F or wind chill if GCS windChill below or equal to 32F (e.g. 1)
                7 => ['field_name' => 'relative_humidity', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'location', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // string corresponding to GCS response location string (e.g. 64)
                9 => ['field_name' => 'observed_at_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'observed_location_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'observed_location_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'day_of_week', 'field_type' => 'day_of_week', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'high_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'low_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        129 => ['mesg_name' => 'weather_alert', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'report_id', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Unique identifier from GCS report ID string, length is 12 (e.g. 12)
                1 => ['field_name' => 'issue_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time alert was issued (e.g. 1)
                2 => ['field_name' => 'expire_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time alert expires (e.g. 1)
                3 => ['field_name' => 'severity', 'field_type' => 'weather_severity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Warning, Watch, Advisory, Statement (e.g. 1)
                4 => ['field_name' => 'type', 'field_type' => 'weather_severe_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Tornado, Severe Thunderstorm, etc. (e.g. 1)
            ]],
        160 => ['mesg_name' => 'gps_metadata', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp. (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'enhanced_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'enhanced_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'heading', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'utc_timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Used to correlate UTC to system time if the timestamp of the message is in system time. This UTC time is derived from the GPS data. (e.g. )
                7 => ['field_name' => 'velocity', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // velocity[0] is lon velocity. Velocity[1] is lat velocity. Velocity[2] is altitude velocity. (e.g. )
            ]],
        161 => ['mesg_name' => 'camera_event', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp. (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'camera_event_type', 'field_type' => 'camera_event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'camera_file_uuid', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'camera_orientation', 'field_type' => 'camera_orientation_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        164 => ['mesg_name' => 'gyroscope_data', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'sample_time_offset', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Each time in the array describes the time at which the gyro sample with the corrosponding index was taken. Limited to 30 samples in each message. The samples may span across seconds. Array size must match the number of samples in gyro_x and gyro_y and gyro_z (e.g. )
                2 => ['field_name' => 'gyro_x', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                3 => ['field_name' => 'gyro_y', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                4 => ['field_name' => 'gyro_z', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                5 => ['field_name' => 'calibrated_gyro_x', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'deg/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated gyro reading (e.g. )
                6 => ['field_name' => 'calibrated_gyro_y', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'deg/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated gyro reading (e.g. )
                7 => ['field_name' => 'calibrated_gyro_z', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'deg/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated gyro reading (e.g. )
            ]],
        165 => ['mesg_name' => 'accelerometer_data', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'sample_time_offset', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Each time in the array describes the time at which the accelerometer sample with the corrosponding index was taken. Limited to 30 samples in each message. The samples may span across seconds. Array size must match the number of samples in accel_x and accel_y and accel_z (e.g. )
                2 => ['field_name' => 'accel_x', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                3 => ['field_name' => 'accel_y', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                4 => ['field_name' => 'accel_z', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                5 => ['field_name' => 'calibrated_accel_x', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'g', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
                6 => ['field_name' => 'calibrated_accel_y', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'g', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
                7 => ['field_name' => 'calibrated_accel_z', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'g', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
                8 => ['field_name' => 'compressed_calibrated_accel_x', 'field_type' => 'sint16', 'scale' => 1, 'offset' => 0, 'units' => 'mG', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
                9 => ['field_name' => 'compressed_calibrated_accel_y', 'field_type' => 'sint16', 'scale' => 1, 'offset' => 0, 'units' => 'mG', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
                10 => ['field_name' => 'compressed_calibrated_accel_z', 'field_type' => 'sint16', 'scale' => 1, 'offset' => 0, 'units' => 'mG', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated accel reading (e.g. )
            ]],
        208 => ['mesg_name' => 'magnetometer_data', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'sample_time_offset', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Each time in the array describes the time at which the compass sample with the corrosponding index was taken. Limited to 30 samples in each message. The samples may span across seconds. Array size must match the number of samples in cmps_x and cmps_y and cmps_z (e.g. )
                2 => ['field_name' => 'mag_x', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                3 => ['field_name' => 'mag_y', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                4 => ['field_name' => 'mag_z', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. Maximum number of samples is 30 in each message. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
                5 => ['field_name' => 'calibrated_mag_x', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'G', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated Magnetometer reading (e.g. )
                6 => ['field_name' => 'calibrated_mag_y', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'G', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated Magnetometer reading (e.g. )
                7 => ['field_name' => 'calibrated_mag_z', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'G', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibrated Magnetometer reading (e.g. )
            ]],
        209 => ['mesg_name' => 'barometer_data', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'sample_time_offset', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Each time in the array describes the time at which the barometer sample with the corrosponding index was taken. The samples may span across seconds. Array size must match the number of samples in baro_cal (e.g. )
                2 => ['field_name' => 'baro_pres', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'Pa', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // These are the raw ADC reading. The samples may span across seconds. A conversion will need to be done on this data once read. (e.g. )
            ]],
        167 => ['mesg_name' => 'three_d_sensor_calibration', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'sensor_type', 'field_type' => 'sensor_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates which sensor the calibration is for (e.g. )
                1 => ['field_name' => 'calibration_factor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // Calibration factor used to convert from raw ADC value to degrees, g, etc. (e.g. )
                    0 => ['field_name' => 'accel_cal_factor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'g', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'accelerometer', 'ref_field_name' => 'sensor_type'], // Accelerometer calibration factor (e.g. )
                    1 => ['field_name' => 'gyro_cal_factor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'deg/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'gyroscope', 'ref_field_name' => 'sensor_type'], // Gyro calibration factor (e.g. )
                ],
                2 => ['field_name' => 'calibration_divisor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibration factor divisor (e.g. )
                3 => ['field_name' => 'level_shift', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Level shift value used to shift the ADC value back into range (e.g. )
                4 => ['field_name' => 'offset_cal', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Internal calibration factors, one for each: xy, yx, zx (e.g. )
                5 => ['field_name' => 'orientation_matrix', 'field_type' => 'sint32', 'scale' => 65535, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // 3 x 3 rotation matrix (row major) (e.g. )
            ]],
        210 => ['mesg_name' => 'one_d_sensor_calibration', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'sensor_type', 'field_type' => 'sensor_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates which sensor the calibration is for (e.g. )
                1 => ['field_name' => 'calibration_factor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // Calibration factor used to convert from raw ADC value to degrees, g, etc. (e.g. )
                    0 => ['field_name' => 'baro_cal_factor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'Pa', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'barometer', 'ref_field_name' => 'sensor_type'], // Barometer calibration factor (e.g. )
                ],
                2 => ['field_name' => 'calibration_divisor', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'counts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Calibration factor divisor (e.g. )
                3 => ['field_name' => 'level_shift', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Level shift value used to shift the ADC value back into range (e.g. )
                4 => ['field_name' => 'offset_cal', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Internal Calibration factor (e.g. )
            ]],
        169 => ['mesg_name' => 'video_frame', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Whole second part of the timestamp (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Millisecond part of the timestamp. (e.g. )
                1 => ['field_name' => 'frame_number', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of the frame that the timestamp and timestamp_ms correlate to (e.g. )
            ]],
        174 => ['mesg_name' => 'obdii_data', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timestamp message was output (e.g. )
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of timestamp, added to timestamp (e.g. )
                1 => ['field_name' => 'time_offset', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Offset of PID reading [i] from start_timestamp+start_timestamp_ms. Readings may span accross seconds. (e.g. )
                2 => ['field_name' => 'pid', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Parameter ID (e.g. )
                3 => ['field_name' => 'raw_data', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Raw parameter data (e.g. )
                4 => ['field_name' => 'pid_data_size', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Optional, data size of PID[i]. If not specified refer to SAE J1979. (e.g. )
                5 => ['field_name' => 'system_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // System time associated with sample expressed in ms, can be used instead of time_offset. There will be a system_time value for each raw_data element. For multibyte pids the system_time is repeated. (e.g. )
                6 => ['field_name' => 'start_timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timestamp of first sample recorded in the message. Used with time_offset to generate time of each sample (e.g. )
                7 => ['field_name' => 'start_timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of start_timestamp (e.g. )
            ]],
        177 => ['mesg_name' => 'nmea_sentence', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timestamp message was output (e.g. 1)
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of timestamp, added to timestamp (e.g. 1)
                1 => ['field_name' => 'sentence', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // NMEA sentence (e.g. 83)
            ]],
        178 => ['mesg_name' => 'aviation_attitude', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timestamp message was output (e.g. 1)
                0 => ['field_name' => 'timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Fractional part of timestamp, added to timestamp (e.g. 1)
                1 => ['field_name' => 'system_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // System time associated with sample expressed in ms. (e.g. 1)
                2 => ['field_name' => 'pitch', 'field_type' => 'sint16', 'scale' => 10430.38, 'offset' => 0, 'units' => 'radians', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Range -PI/2 to +PI/2 (e.g. 1)
                3 => ['field_name' => 'roll', 'field_type' => 'sint16', 'scale' => 10430.38, 'offset' => 0, 'units' => 'radians', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Range -PI to +PI (e.g. 1)
                4 => ['field_name' => 'accel_lateral', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s^2', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Range -78.4 to +78.4 (-8 Gs to 8 Gs) (e.g. 1)
                5 => ['field_name' => 'accel_normal', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'm/s^2', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Range -78.4 to +78.4 (-8 Gs to 8 Gs) (e.g. 1)
                6 => ['field_name' => 'turn_rate', 'field_type' => 'sint16', 'scale' => 1024, 'offset' => 0, 'units' => 'radians/second', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Range -8.727 to +8.727 (-500 degs/sec to +500 degs/sec) (e.g. 1)
                7 => ['field_name' => 'stage', 'field_type' => 'attitude_stage', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'attitude_stage_complete', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The percent complete of the current attitude stage. Set to 0 for attitude stages 0, 1 and 2 and to 100 for attitude stage 3 by AHRS modules that do not support it. Range - 100 (e.g. 1)
                9 => ['field_name' => 'track', 'field_type' => 'uint16', 'scale' => 10430.38, 'offset' => 0, 'units' => 'radians', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Track Angle/Heading Range 0 - 2pi (e.g. 1)
                10 => ['field_name' => 'validity', 'field_type' => 'attitude_validity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        184 => ['mesg_name' => 'video', 'field_defns' => [
                0 => ['field_name' => 'url', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'hosting_provider', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'duration', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Playback time of video (e.g. )
            ]],
        185 => ['mesg_name' => 'video_title', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Long titles will be split into multiple parts (e.g. 1)
                0 => ['field_name' => 'message_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total number of title parts (e.g. 1)
                1 => ['field_name' => 'text', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        186 => ['mesg_name' => 'video_description', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Long descriptions will be split into multiple parts (e.g. 1)
                0 => ['field_name' => 'message_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total number of description parts (e.g. 1)
                1 => ['field_name' => 'text', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        187 => ['mesg_name' => 'video_clip', 'field_defns' => [
                0 => ['field_name' => 'clip_number', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'start_timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'start_timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'end_timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'end_timestamp_ms', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'clip_start', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Start of clip in video time (e.g. )
                7 => ['field_name' => 'clip_end', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'ms', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // End of clip in video time (e.g. )
            ]],
        225 => ['mesg_name' => 'set', 'field_defns' => [
                254 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timestamp of the set (e.g. )
                0 => ['field_name' => 'duration', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'repetitions', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // # of repitions of the movement (e.g. )
                4 => ['field_name' => 'weight', 'field_type' => 'uint16', 'scale' => 16, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Amount of weight applied for the set (e.g. )
                5 => ['field_name' => 'set_type', 'field_type' => 'set_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Start time of the set (e.g. )
                7 => ['field_name' => 'category', 'field_type' => 'exercise_category', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'category_subtype', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Based on the associated category, see [category]_exercise_names (e.g. )
                9 => ['field_name' => 'weight_display_unit', 'field_type' => 'fit_base_unit', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'wkt_step_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        285 => ['mesg_name' => 'jump', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'distance', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'height', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'rotations', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'hang_time', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'score', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // A score for a jump calculated based on hang time, rotations, and distance. (e.g. )
                5 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_speed', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'enhanced_speed', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        312 => ['mesg_name' => 'split', 'field_defns' => [
                0 => ['field_name' => 'split_type', 'field_type' => 'split_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'total_elapsed_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'total_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        317 => ['mesg_name' => 'climb_pro', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'climb_pro_event', 'field_type' => 'climb_pro_event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'climb_number', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'climb_category', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'current_dist', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        206 => ['mesg_name' => 'field_description', 'field_defns' => [
                0 => ['field_name' => 'developer_data_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'field_definition_number', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'fit_base_type_id', 'field_type' => 'fit_base_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'field_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'array', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'components', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'scale', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'offset', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'units', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'bits', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'accumulate', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'fit_base_unit_id', 'field_type' => 'fit_base_unit', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'native_mesg_num', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'native_field_num', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        207 => ['mesg_name' => 'developer_data_id', 'field_defns' => [
                0 => ['field_name' => 'developer_id', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'application_id', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'manufacturer_id', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'developer_data_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'application_version', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        31 => ['mesg_name' => 'course', 'field_defns' => [
                4 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'capabilities', 'field_type' => 'course_capabilities', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        32 => ['mesg_name' => 'course_point', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'type', 'field_type' => 'course_point', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'favorite', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        148 => ['mesg_name' => 'segment_id', 'field_defns' => [
                0 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Friendly name assigned to segment (e.g. 1)
                1 => ['field_name' => 'uuid', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // UUID of the segment (e.g. 1)
                2 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Sport associated with the segment (e.g. 1)
                3 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Segment enabled for evaluation (e.g. 1)
                4 => ['field_name' => 'user_profile_primary_key', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Primary key of the user that created the segment (e.g. 1)
                5 => ['field_name' => 'device_id', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // ID of the device that created the segment (e.g. 1)
                6 => ['field_name' => 'default_race_leader', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Index for the Leader Board entry selected as the default race participant (e.g. 1)
                7 => ['field_name' => 'delete_status', 'field_type' => 'segment_delete_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates if any segments should be deleted (e.g. 1)
                8 => ['field_name' => 'selection_type', 'field_type' => 'segment_selection_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates how the segment was selected to be sent to the device (e.g. 1)
            ]],
        149 => ['mesg_name' => 'segment_leaderboard_entry', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Friendly name assigned to leader (e.g. 1)
                1 => ['field_name' => 'type', 'field_type' => 'segment_leaderboard_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Leader classification (e.g. 1)
                2 => ['field_name' => 'group_primary_key', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Primary user ID of this leader (e.g. 1)
                3 => ['field_name' => 'activity_id', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // ID of the activity associated with this leader time (e.g. 1)
                4 => ['field_name' => 'segment_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Segment Time (includes pauses) (e.g. 1)
                5 => ['field_name' => 'activity_id_string', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // String version of the activity_id. 21 characters long, express in decimal (e.g. )
            ]],
        150 => ['mesg_name' => 'segment_point', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumulated distance along the segment at the described point (e.g. 1)
                4 => ['field_name' => 'altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_altitude', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumulated altitude along the segment at the described point (e.g. 1)
                5 => ['field_name' => 'leader_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumualted time each leader board member required to reach the described point. This value is zero for all leader board members at the starting point of the segment. (e.g. 1)
                6 => ['field_name' => 'enhanced_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumulated altitude along the segment at the described point (e.g. )
            ]],
        142 => ['mesg_name' => 'segment_lap', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Lap end time. (e.g. 1)
                0 => ['field_name' => 'event', 'field_type' => 'event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'event_type', 'field_type' => 'event_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'start_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'start_position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'start_position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'end_position_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'end_position_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'total_elapsed_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time (includes pauses) (e.g. 1)
                8 => ['field_name' => 'total_timer_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Timer Time (excludes pauses) (e.g. 1)
                9 => ['field_name' => 'total_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'total_cycles', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'total_strokes', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'strokes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cycling', 'ref_field_name' => 'sport'],
                ],
                11 => ['field_name' => 'total_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'total_fat_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // If New Leaf (e.g. 1)
                13 => ['field_name' => 'avg_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'max_speed', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'avg_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                16 => ['field_name' => 'max_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'avg_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // total_cycles / total_timer_time if non_zero_avg_cadence otherwise total_cycles / total_elapsed_time (e.g. 1)
                18 => ['field_name' => 'max_cadence', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                19 => ['field_name' => 'avg_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // total_power / total_timer_time if non_zero_avg_power otherwise total_power / total_elapsed_time (e.g. 1)
                20 => ['field_name' => 'max_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                21 => ['field_name' => 'total_ascent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                22 => ['field_name' => 'total_descent', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                23 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'event_group', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                25 => ['field_name' => 'nec_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // North east corner latitude. (e.g. 1)
                26 => ['field_name' => 'nec_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // North east corner longitude. (e.g. 1)
                27 => ['field_name' => 'swc_lat', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // South west corner latitude. (e.g. 1)
                28 => ['field_name' => 'swc_long', 'field_type' => 'sint32', 'scale' => 1, 'offset' => 0, 'units' => 'semicircles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // South west corner latitude. (e.g. 1)
                29 => ['field_name' => 'name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                30 => ['field_name' => 'normalized_power', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                31 => ['field_name' => 'left_right_balance', 'field_type' => 'left_right_balance_100', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                32 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                33 => ['field_name' => 'total_work', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'J', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                34 => ['field_name' => 'avg_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_avg_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                35 => ['field_name' => 'max_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_max_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                36 => ['field_name' => 'gps_accuracy', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                37 => ['field_name' => 'avg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                38 => ['field_name' => 'avg_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                39 => ['field_name' => 'avg_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                40 => ['field_name' => 'max_pos_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                41 => ['field_name' => 'max_neg_grade', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                42 => ['field_name' => 'avg_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                43 => ['field_name' => 'max_temperature', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                44 => ['field_name' => 'total_moving_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                45 => ['field_name' => 'avg_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                46 => ['field_name' => 'avg_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                47 => ['field_name' => 'max_pos_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                48 => ['field_name' => 'max_neg_vertical_speed', 'field_type' => 'sint16', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                49 => ['field_name' => 'time_in_hr_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                50 => ['field_name' => 'time_in_speed_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                51 => ['field_name' => 'time_in_cadence_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                52 => ['field_name' => 'time_in_power_zone', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                53 => ['field_name' => 'repetition_num', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                54 => ['field_name' => 'min_altitude', 'field_type' => 'uint16', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '16', 'accumulate' => '', 'component' => 'enhanced_min_altitude', 'ref_field_type' => '', 'ref_field_name' => ''],
                55 => ['field_name' => 'min_heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                56 => ['field_name' => 'active_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                57 => ['field_name' => 'wkt_step_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                58 => ['field_name' => 'sport_event', 'field_type' => 'sport_event', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                59 => ['field_name' => 'avg_left_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                60 => ['field_name' => 'avg_right_torque_effectiveness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                61 => ['field_name' => 'avg_left_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                62 => ['field_name' => 'avg_right_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                63 => ['field_name' => 'avg_combined_pedal_smoothness', 'field_type' => 'uint8', 'scale' => 2, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                64 => ['field_name' => 'status', 'field_type' => 'segment_lap_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                65 => ['field_name' => 'uuid', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                66 => ['field_name' => 'avg_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the avg_cadence (e.g. 1)
                67 => ['field_name' => 'max_fractional_cadence', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the max_cadence (e.g. 1)
                68 => ['field_name' => 'total_fractional_cycles', 'field_type' => 'uint8', 'scale' => 128, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of the total_cycles (e.g. 1)
                69 => ['field_name' => 'front_gear_shift_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                70 => ['field_name' => 'rear_gear_shift_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                71 => ['field_name' => 'time_standing', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Total time spent in the standing position (e.g. )
                72 => ['field_name' => 'stand_count', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Number of transitions to the standing state (e.g. )
                73 => ['field_name' => 'avg_left_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left platform center offset (e.g. )
                74 => ['field_name' => 'avg_right_pco', 'field_type' => 'sint8', 'scale' => 1, 'offset' => 0, 'units' => 'mm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right platform center offset (e.g. )
                75 => ['field_name' => 'avg_left_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                76 => ['field_name' => 'avg_left_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average left power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                77 => ['field_name' => 'avg_right_power_phase', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase angles. Data value indexes defined by power_phase_type. (e.g. )
                78 => ['field_name' => 'avg_right_power_phase_peak', 'field_type' => 'uint8', 'scale' => 0.7111111, 'offset' => 0, 'units' => 'degrees', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average right power phase peak angles. Data value indexes defined by power_phase_type. (e.g. )
                79 => ['field_name' => 'avg_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average power by position. Data value indexes defined by rider_position_type. (e.g. )
                80 => ['field_name' => 'max_power_position', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum power by position. Data value indexes defined by rider_position_type. (e.g. )
                81 => ['field_name' => 'avg_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                82 => ['field_name' => 'max_cadence_position', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum cadence by position. Data value indexes defined by rider_position_type. (e.g. )
                83 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Manufacturer that produced the segment (e.g. )
                84 => ['field_name' => 'total_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                85 => ['field_name' => 'total_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                86 => ['field_name' => 'avg_grit', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'kGrit', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The grit score estimates how challenging a route could be for a cyclist in terms of time spent going over sharp turns or large grade slopes. (e.g. )
                87 => ['field_name' => 'avg_flow', 'field_type' => 'float32', 'scale' => 1, 'offset' => 0, 'units' => 'Flow', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // The flow score estimates how long distance wise a cyclist deaccelerates over intervals where deacceleration is unnecessary such as smooth turns or small grade angle intervals. (e.g. )
                89 => ['field_name' => 'total_fractional_ascent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_ascent (e.g. )
                90 => ['field_name' => 'total_fractional_descent', 'field_type' => 'uint8', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // fractional part of total_descent (e.g. )
                91 => ['field_name' => 'enhanced_avg_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                92 => ['field_name' => 'enhanced_max_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                93 => ['field_name' => 'enhanced_min_altitude', 'field_type' => 'uint32', 'scale' => 5, 'offset' => 500, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        151 => ['mesg_name' => 'segment_file', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'file_uuid', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // UUID of the segment file (e.g. 1)
                3 => ['field_name' => 'enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Enabled state of the segment file (e.g. 1)
                4 => ['field_name' => 'user_profile_primary_key', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Primary key of the user that created the segment file (e.g. 1)
                7 => ['field_name' => 'leader_type', 'field_type' => 'segment_leaderboard_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Leader type of each leader in the segment file (e.g. 1)
                8 => ['field_name' => 'leader_group_primary_key', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Group primary key of each leader in the segment file (e.g. 1)
                9 => ['field_name' => 'leader_activity_id', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Activity ID of each leader in the segment file (e.g. 1)
                10 => ['field_name' => 'leader_activity_id_string', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // String version of the activity ID of each leader in the segment file. 21 characters long for each ID, express in decimal (e.g. )
                11 => ['field_name' => 'default_race_leader', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Index for the Leader Board entry selected as the default race participant (e.g. )
            ]],
        26 => ['mesg_name' => 'workout', 'field_defns' => [
                4 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'capabilities', 'field_type' => 'workout_capabilities', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'num_valid_steps', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // number of valid steps (e.g. 1)
                8 => ['field_name' => 'wkt_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                14 => ['field_name' => 'pool_length', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                15 => ['field_name' => 'pool_length_unit', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        158 => ['mesg_name' => 'workout_session', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'sub_sport', 'field_type' => 'sub_sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'num_valid_steps', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'first_step_index', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'pool_length', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'pool_length_unit', 'field_type' => 'display_measure', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        27 => ['mesg_name' => 'workout_step', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'wkt_step_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'duration_type', 'field_type' => 'wkt_step_duration', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'duration_value', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'duration_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'time,repetition_time', 'ref_field_name' => 'duration_type,duration_type'],
                    1 => ['field_name' => 'duration_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'distance', 'ref_field_name' => 'duration_type'],
                    2 => ['field_name' => 'duration_hr', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'hr_less_than,hr_greater_than', 'ref_field_name' => 'duration_type,duration_type'],
                    3 => ['field_name' => 'duration_calories', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'calories', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'calories', 'ref_field_name' => 'duration_type'],
                    4 => ['field_name' => 'duration_step', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_steps_cmplt,repeat_until_time,repeat_until_distance,repeat_until_calories,repeat_until_hr_less_than,repeat_until_hr_greater_than,repeat_until_power_less_than,repeat_until_power_greater_than', 'ref_field_name' => 'duration_type,duration_type,duration_type,duration_type,duration_type,duration_type,duration_type,duration_type'], // message_index of step to loop back to. Steps are assumed to be in the order by message_index. custom_name and intensity members are undefined for this duration type. (e.g. 1)
                    5 => ['field_name' => 'duration_power', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power_less_than,power_greater_than', 'ref_field_name' => 'duration_type,duration_type'],
                    6 => ['field_name' => 'duration_reps', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'reps', 'ref_field_name' => 'duration_type'],
                ],
                3 => ['field_name' => 'target_type', 'field_type' => 'wkt_step_target', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'target_value', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'target_speed_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'target_type'], // speed zone (1-10);Custom =0; (e.g. 1)
                    1 => ['field_name' => 'target_hr_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'target_type'], // hr zone (1-5);Custom =0; (e.g. 1)
                    2 => ['field_name' => 'target_cadence_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'target_type'], // Zone (1-?); Custom = 0; (e.g. 1)
                    3 => ['field_name' => 'target_power_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'target_type'], // Power Zone ( 1-7); Custom = 0; (e.g. 1)
                    4 => ['field_name' => 'repeat_steps', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_steps_cmplt', 'ref_field_name' => 'duration_type'], // # of repetitions (e.g. 1)
                    5 => ['field_name' => 'repeat_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_time', 'ref_field_name' => 'duration_type'],
                    6 => ['field_name' => 'repeat_distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_distance', 'ref_field_name' => 'duration_type'],
                    7 => ['field_name' => 'repeat_calories', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'calories', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_calories', 'ref_field_name' => 'duration_type'],
                    8 => ['field_name' => 'repeat_hr', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_hr_less_than,repeat_until_hr_greater_than', 'ref_field_name' => 'duration_type,duration_type'],
                    9 => ['field_name' => 'repeat_power', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'repeat_until_power_less_than,repeat_until_power_greater_than', 'ref_field_name' => 'duration_type,duration_type'],
                    10 => ['field_name' => 'target_stroke_type', 'field_type' => 'swim_stroke', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'swim_stroke', 'ref_field_name' => 'target_type'],
                ],
                5 => ['field_name' => 'custom_target_value_low', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'custom_target_speed_low', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'target_type'],
                    1 => ['field_name' => 'custom_target_heart_rate_low', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'target_type'],
                    2 => ['field_name' => 'custom_target_cadence_low', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'target_type'],
                    3 => ['field_name' => 'custom_target_power_low', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'target_type'],
                ],
                6 => ['field_name' => 'custom_target_value_high', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'custom_target_speed_high', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'target_type'],
                    1 => ['field_name' => 'custom_target_heart_rate_high', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'target_type'],
                    2 => ['field_name' => 'custom_target_cadence_high', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'target_type'],
                    3 => ['field_name' => 'custom_target_power_high', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'target_type'],
                ],
                7 => ['field_name' => 'intensity', 'field_type' => 'intensity', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'notes', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'equipment', 'field_type' => 'workout_equipment', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'exercise_category', 'field_type' => 'exercise_category', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'exercise_name', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'exercise_weight', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                13 => ['field_name' => 'weight_display_unit', 'field_type' => 'fit_base_unit', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                19 => ['field_name' => 'secondary_target_type', 'field_type' => 'wkt_step_target', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                20 => ['field_name' => 'secondary_target_value', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'secondary_target_speed_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'secondary_target_type'], // speed zone (1-10);Custom =0; (e.g. 1)
                    1 => ['field_name' => 'secondary_target_hr_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'secondary_target_type'], // hr zone (1-5);Custom =0; (e.g. 1)
                    2 => ['field_name' => 'secondary_target_cadence_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'secondary_target_type'], // Zone (1-?); Custom = 0; (e.g. 1)
                    3 => ['field_name' => 'secondary_target_power_zone', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'secondary_target_type'], // Power Zone ( 1-7); Custom = 0; (e.g. 1)
                    4 => ['field_name' => 'secondary_target_stroke_type', 'field_type' => 'swim_stroke', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'swim_stroke', 'ref_field_name' => 'secondary_target_type'],
                ],
                21 => ['field_name' => 'secondary_custom_target_value_low', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'secondary_custom_target_speed_low', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'secondary_target_type'],
                    1 => ['field_name' => 'secondary_custom_target_heart_rate_low', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'secondary_target_type'],
                    2 => ['field_name' => 'secondary_custom_target_cadence_low', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'secondary_target_type'],
                    3 => ['field_name' => 'secondary_custom_target_power_low', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'secondary_target_type'],
                ],
                22 => ['field_name' => 'secondary_custom_target_value_high', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '',
                    0 => ['field_name' => 'secondary_custom_target_speed_high', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'speed', 'ref_field_name' => 'secondary_target_type'],
                    1 => ['field_name' => 'secondary_custom_target_heart_rate_high', 'field_type' => 'workout_hr', 'scale' => 1, 'offset' => 0, 'units' => '% or bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'heart_rate', 'ref_field_name' => 'secondary_target_type'],
                    2 => ['field_name' => 'secondary_custom_target_cadence_high', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'rpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cadence', 'ref_field_name' => 'secondary_target_type'],
                    3 => ['field_name' => 'secondary_custom_target_power_high', 'field_type' => 'workout_power', 'scale' => 1, 'offset' => 0, 'units' => '% or watts', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'power', 'ref_field_name' => 'secondary_target_type'],
                ],
            ],
            'exercise_title' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'exercise_category', 'field_type' => 'exercise_category', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'exercise_name', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'wkt_step_name', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        28 => ['mesg_name' => 'schedule', 'field_defns' => [
                0 => ['field_name' => 'manufacturer', 'field_type' => 'manufacturer', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Corresponds to file_id of scheduled workout / course. (e.g. 1)
                1 => ['field_name' => 'product', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // Corresponds to file_id of scheduled workout / course. (e.g. 1)
                    0 => ['field_name' => 'favero_product', 'field_type' => 'favero_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'favero_electronics', 'ref_field_name' => 'manufacturer'],
                    1 => ['field_name' => 'garmin_product', 'field_type' => 'garmin_product', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'garmin,dynastream,dynastream_oem,tacx', 'ref_field_name' => 'manufacturer,manufacturer,manufacturer,manufacturer'],
                ],
                2 => ['field_name' => 'serial_number', 'field_type' => 'uint32z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Corresponds to file_id of scheduled workout / course. (e.g. 1)
                3 => ['field_name' => 'time_created', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Corresponds to file_id of scheduled workout / course. (e.g. 1)
                4 => ['field_name' => 'completed', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // TRUE if this activity has been started (e.g. 1)
                5 => ['field_name' => 'type', 'field_type' => 'schedule', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'scheduled_time', 'field_type' => 'local_date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        33 => ['mesg_name' => 'totals', 'field_defns' => [
                254 => ['field_name' => 'message_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'timer_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Excludes pauses (e.g. 1)
                1 => ['field_name' => 'distance', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'calories', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'sport', 'field_type' => 'sport', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'elapsed_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Includes pauses (e.g. 1)
                5 => ['field_name' => 'sessions', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'active_time', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'sport_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        30 => ['mesg_name' => 'weight_scale', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'weight', 'field_type' => 'weight', 'scale' => 100, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'percent_fat', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'percent_hydration', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => '%', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'visceral_fat_mass', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'bone_mass', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'muscle_mass', 'field_type' => 'uint16', 'scale' => 100, 'offset' => 0, 'units' => 'kg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'basal_met', 'field_type' => 'uint16', 'scale' => 4, 'offset' => 0, 'units' => 'kcal/day', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'physique_rating', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'active_met', 'field_type' => 'uint16', 'scale' => 4, 'offset' => 0, 'units' => 'kcal/day', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // ~4kJ per kcal, 0.25 allows max 16384 kcal (e.g. 1)
                10 => ['field_name' => 'metabolic_age', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'years', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'visceral_fat_rating', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                12 => ['field_name' => 'user_profile_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Associates this weight scale message to a user. This corresponds to the index of the user profile message in the weight scale file. (e.g. 1)
            ]],
        51 => ['mesg_name' => 'blood_pressure', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'systolic_pressure', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'diastolic_pressure', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'mean_arterial_pressure', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'map_3_sample_mean', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'map_morning_values', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'map_evening_values', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'mmHg', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'heart_rate_type', 'field_type' => 'hr_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'status', 'field_type' => 'bp_status', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'user_profile_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Associates this blood pressure message to a user. This corresponds to the index of the user profile message in the blood pressure file. (e.g. 1)
            ]],
        103 => ['mesg_name' => 'monitoring_info', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'local_timestamp', 'field_type' => 'local_date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Use to convert activity timestamps to local time if device does not support time zone and daylight savings time correction. (e.g. 1)
                1 => ['field_name' => 'activity_type', 'field_type' => 'activity_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'cycles_to_distance', 'field_type' => 'uint16', 'scale' => 5000, 'offset' => 0, 'units' => 'm/cycle', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indexed by activity_type (e.g. )
                4 => ['field_name' => 'cycles_to_calories', 'field_type' => 'uint16', 'scale' => 5000, 'offset' => 0, 'units' => 'kcal/cycle', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indexed by activity_type (e.g. )
                5 => ['field_name' => 'resting_metabolic_rate', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal / day', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        55 => ['mesg_name' => 'monitoring', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Must align to logging interval, for example, time must be 00:00:00 for daily log. (e.g. 1)
                0 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Associates this data to device_info message. Not required for file with single device (sensor). (e.g. 1)
                1 => ['field_name' => 'calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumulated total calories. Maintained by MonitoringReader for each activity_type. See SDK documentation (e.g. 1)
                2 => ['field_name' => 'distance', 'field_type' => 'uint32', 'scale' => 100, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Accumulated distance. Maintained by MonitoringReader for each activity_type. See SDK documentation. (e.g. 1)
                3 => ['field_name' => 'cycles', 'field_type' => 'uint32', 'scale' => 2, 'offset' => 0, 'units' => 'cycles', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => '', // Accumulated cycles. Maintained by MonitoringReader for each activity_type. See SDK documentation. (e.g. 1)
                    0 => ['field_name' => 'steps', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 'steps', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'walking,running', 'ref_field_name' => 'activity_type,activity_type'],
                    1 => ['field_name' => 'strokes', 'field_type' => 'uint32', 'scale' => 2, 'offset' => 0, 'units' => 'strokes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => 'cycling,swimming', 'ref_field_name' => 'activity_type,activity_type'],
                ],
                4 => ['field_name' => 'active_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'activity_type', 'field_type' => 'activity_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'activity_subtype', 'field_type' => 'activity_subtype', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'activity_level', 'field_type' => 'activity_level', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'distance_16', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '100 * m', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'cycles_16', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => '2 * cycles (steps)', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'active_time_16', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'local_timestamp', 'field_type' => 'local_date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Must align to logging interval, for example, time must be 00:00:00 for daily log. (e.g. 1)
                12 => ['field_name' => 'temperature', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Avg temperature during the logging interval ended at timestamp (e.g. )
                14 => ['field_name' => 'temperature_min', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Min temperature during the logging interval ended at timestamp (e.g. )
                15 => ['field_name' => 'temperature_max', 'field_type' => 'sint16', 'scale' => 100, 'offset' => 0, 'units' => 'C', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Max temperature during the logging interval ended at timestamp (e.g. )
                16 => ['field_name' => 'activity_time', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'minutes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Indexed using minute_activity_level enum (e.g. )
                19 => ['field_name' => 'active_calories', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'kcal', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                24 => ['field_name' => 'current_activity_type_intensity', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '5,3', 'accumulate' => '', 'component' => 'activity_type,intensity', 'ref_field_type' => '', 'ref_field_name' => ''], // Indicates single type / intensity for duration since last monitoring message. (e.g. )
                25 => ['field_name' => 'timestamp_min_8', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                26 => ['field_name' => 'timestamp_16', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                27 => ['field_name' => 'heart_rate', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                28 => ['field_name' => 'intensity', 'field_type' => 'uint8', 'scale' => 10, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                29 => ['field_name' => 'duration_min', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'min', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                30 => ['field_name' => 'duration', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                31 => ['field_name' => 'ascent', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                32 => ['field_name' => 'descent', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                33 => ['field_name' => 'moderate_activity_minutes', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'minutes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                34 => ['field_name' => 'vigorous_activity_minutes', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'minutes', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        132 => ['mesg_name' => 'hr', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'fractional_timestamp', 'field_type' => 'uint16', 'scale' => 32768, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'time256', 'field_type' => 'uint8', 'scale' => 256, 'offset' => 0, 'units' => 's', 'bits' => '8', 'accumulate' => '', 'component' => 'fractional_timestamp', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'filtered_bpm', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'bpm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'event_timestamp', 'field_type' => 'uint32', 'scale' => 1024, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'event_timestamp_12', 'field_type' => 'byte', 'scale' => '1024,1024,1024,1024,1024,1024,1024,1024,1024,1024', 'offset' => 0, 'units' => 's', 'bits' => '12,12,12,12,12,12,12,12,12,12', 'accumulate' => '1,1,1,1,1,1,1,1,1,1', 'component' => 'event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp,event_timestamp', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        227 => ['mesg_name' => 'stress_level', 'field_defns' => [
                0 => ['field_name' => 'stress_level_value', 'field_type' => 'sint16', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'stress_level_time', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time stress score was calculated (e.g. )
            ]],
        145 => ['mesg_name' => 'memo_glob', 'field_defns' => [
                250 => ['field_name' => 'part_index', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Sequence number of memo blocks (e.g. )
                0 => ['field_name' => 'memo', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Deprecated. Use data field. (e.g. )
                1 => ['field_name' => 'mesg_num', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Message Number of the parent message (e.g. )
                2 => ['field_name' => 'parent_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Index of mesg that this glob is associated with. (e.g. )
                3 => ['field_name' => 'field_num', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Field within the parent that this glob is associated with (e.g. )
                4 => ['field_name' => 'data', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Block of utf8 bytes. Note, mutltibyte characters may be split across adjoining memo_glob messages. (e.g. )
            ]],
        82 => ['mesg_name' => 'ant_channel_id', 'field_defns' => [
                0 => ['field_name' => 'channel_number', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'device_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'device_number', 'field_type' => 'uint16z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'transmission_type', 'field_type' => 'uint8z', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'device_index', 'field_type' => 'device_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        80 => ['mesg_name' => 'ant_rx', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'fractional_timestamp', 'field_type' => 'uint16', 'scale' => 32768, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'mesg_id', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'mesg_data', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8,8,8,8,8,8,8,8,8', 'accumulate' => '', 'component' => 'channel_number,data,data,data,data,data,data,data,data', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'channel_number', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'data', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        81 => ['mesg_name' => 'ant_tx', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'fractional_timestamp', 'field_type' => 'uint16', 'scale' => 32768, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'mesg_id', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'mesg_data', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '8,8,8,8,8,8,8,8,8', 'accumulate' => '', 'component' => 'channel_number,data,data,data,data,data,data,data,data', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'channel_number', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'data', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        200 => ['mesg_name' => 'exd_screen_configuration', 'field_defns' => [
                0 => ['field_name' => 'screen_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'field_count', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // number of fields in screen (e.g. 1)
                2 => ['field_name' => 'layout', 'field_type' => 'exd_layout', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'screen_enabled', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        201 => ['mesg_name' => 'exd_data_field_configuration', 'field_defns' => [
                0 => ['field_name' => 'screen_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'concept_field', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '4,4', 'accumulate' => '', 'component' => 'field_id,concept_count', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'field_id', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'concept_count', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'display_type', 'field_type' => 'exd_display_type', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'title', 'field_type' => 'string', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        202 => ['mesg_name' => 'exd_data_concept_configuration', 'field_defns' => [
                0 => ['field_name' => 'screen_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'concept_field', 'field_type' => 'byte', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '4,4', 'accumulate' => '', 'component' => 'field_id,concept_index', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'field_id', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                3 => ['field_name' => 'concept_index', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                4 => ['field_name' => 'data_page', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                5 => ['field_name' => 'concept_key', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'scaling', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'data_units', 'field_type' => 'exd_data_units', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'qualifier', 'field_type' => 'exd_qualifiers', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'descriptor', 'field_type' => 'exd_descriptors', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'is_signed', 'field_type' => 'bool', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
            ]],
        268 => ['mesg_name' => 'dive_summary', 'field_defns' => [
                253 => ['field_name' => 'timestamp', 'field_type' => 'date_time', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                0 => ['field_name' => 'reference_mesg', 'field_type' => 'mesg_num', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                1 => ['field_name' => 'reference_index', 'field_type' => 'message_index', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                2 => ['field_name' => 'avg_depth', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // 0 if above water (e.g. )
                3 => ['field_name' => 'max_depth', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // 0 if above water (e.g. )
                4 => ['field_name' => 'surface_interval', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time since end of last dive (e.g. )
                5 => ['field_name' => 'start_cns', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                6 => ['field_name' => 'end_cns', 'field_type' => 'uint8', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                7 => ['field_name' => 'start_n2', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                8 => ['field_name' => 'end_n2', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'percent', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                9 => ['field_name' => 'o2_toxicity', 'field_type' => 'uint16', 'scale' => 1, 'offset' => 0, 'units' => 'OTUs', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                10 => ['field_name' => 'dive_number', 'field_type' => 'uint32', 'scale' => 1, 'offset' => 0, 'units' => '', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                11 => ['field_name' => 'bottom_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''],
                17 => ['field_name' => 'avg_ascent_rate', 'field_type' => 'sint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average ascent rate, not including descents or stops (e.g. )
                22 => ['field_name' => 'avg_descent_rate', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Average descent rate, not including ascents or stops (e.g. )
                23 => ['field_name' => 'max_ascent_rate', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum ascent rate (e.g. )
                24 => ['field_name' => 'max_descent_rate', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 'm/s', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Maximum descent rate (e.g. )
                25 => ['field_name' => 'hang_time', 'field_type' => 'uint32', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time spent neither ascending nor descending (e.g. )
            ]],
        78 => ['mesg_name' => 'hrv', 'field_defns' => [
                0 => ['field_name' => 'time', 'field_type' => 'uint16', 'scale' => 1000, 'offset' => 0, 'units' => 's', 'bits' => '', 'accumulate' => '', 'ref_field_type' => '', 'ref_field_name' => ''], // Time between beats (e.g. 1)
            ]
    ]];

    // PHP Constructor - called when an object of the class is instantiated.
    public function __construct($file_path_or_data, $options = null) {
        if (isset($options['input_is_data'])) {
            $this->file_contents = $file_path_or_data;
        } else {
            if (empty($file_path_or_data)) {
                throw new \Exception('phpFITFileAnalysis->__construct(): file_path is empty!');
            }
            if (!file_exists($file_path_or_data)) {
                throw new \Exception('phpFITFileAnalysis->__construct(): file \'' . $file_path_or_data . '\' does not exist!');
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
    private function readHeader() {
        $header_size = unpack('C1header_size', $this->getString($this->file_pointer, 1))['header_size'];
        $this->writeDebug("\nHEADER\n[{$this->file_pointer}-" . ($this->file_pointer + 13) . "]: header size: $header_size\n");
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
        $this->file_header = unpack($header_fields, $this->getString($this->file_pointer, $header_size - 1));
        $this->file_header['header_size'] = $header_size;

        //$this->writeDebug($this->hex_dump($this->getString($this->file_pointer, $header_size - 1)));
        $this->writeDebug("[0-" . ($header_size - 1) . "]: header: " . print_r($this->file_header, true) . "\n");

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
    private function readDataRecords() {
        $record_header_byte = 0;
        $message_type = 0;
        $developer_data_flag = 0;
        $local_mesg_type = 0;
        $previousTS = 0;

        $this->writeDebug("\nDATA RECORDS\n");
        while ($this->file_header['header_size'] + $this->file_header['data_size'] > $this->file_pointer) {
            $record_header_byte = $this->getByteValue();
            $this->writeDebug("[" . $this->file_pointer . "]: Header Byte: $record_header_byte\n");
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
                $this->writeDebug("Header with compressed timestamp\n");
                $this->writeDebug("$message_type, $developer_data_flag, $tsOffset, Message Type, Developer Data Flag, TS Offset\n");
            } else {
                //Normal header
                $message_type = ($record_header_byte >> 6) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
                $developer_data_flag = ($record_header_byte >> 5) & 1;  // 1: DEFINITION_MESSAGE; 0: DATA_MESSAGE
                $local_mesg_type = $record_header_byte & 15;  // bindec('1111') == 15
                $this->writeDebug("Normal Header $message_type, $developer_data_flag, $local_mesg_type Message Type, Developer Data Flag, Local Message Type\n");
            }

            switch ($message_type) {
                case DEFINITION_MESSAGE:
                    /**
                     * D00001275 Flexible & Interoperable Data Transfer (FIT) Protocol Rev 1.7.pdf
                     * Table 4-1. Normal Header Bit Field Description
                     *
                     * Definition Messages: these define the upcoming data messages. A definition message will
                     * define a local message type and associate it to a specific FIT message, and then designate
                     * the byte alignment and field contents of the upcoming data message.
                     */
                    if ($this->debug) {
                        $this->writeDebug("DEFINITION MESSAGE\n");
                        $block_start = $this->file_pointer - 1; // get header too
                        $block_end = $block_start + 6 + $this->getBytesValues($this->file_pointer + 6, 1) * 3;
                        if ($developer_data_flag) {
                            $block_end = $block_end + $this->getBytesValues($block_end + 1, 1) * 3;
                        }
                        ob_start();
                        echo sprintf("[%04d-%04d]:\n", $block_start, $block_end);
                        $this->hex_dump($this->getString($block_start, ($block_end - $block_start)));
                        $contents = ob_get_clean();
                        echo $contents;
                    }
                    $this->writeDebug("[" . $this->file_pointer . "]: Reserved-Ignored\n");
                    $this->file_pointer++;  // Reserved - IGNORED
                    $architecture = $this->getByteValue();  // Architecture
                    $this->writeDebug("[$this->file_pointer]:$architecture Architecture\n");
                    $this->file_pointer++;

                    $this->types = $this->endianness[$architecture];

                    $global_mesg_num = ($architecture === 0) ? unpack('v1tmp', $this->getString($this->file_pointer, 2))['tmp'] : unpack('n1tmp', $this->getString($this->file_pointer, 2))['tmp'];
                    $this->writeDebug("[$this->file_pointer-" . ($this->file_pointer + 1) . "]:$global_mesg_num Global Message Number\n");
                    $this->file_pointer += 2;

                    $num_fields = $this->getByteValue();
                    $this->writeDebug("[$this->file_pointer]:$num_fields Number of Fields\n");
                    $this->file_pointer++;

                    $field_definitions = [];
                    $total_size = 0;
                    for ($i = 0;
                            $i < $num_fields;
                            ++$i) {
                        $field_definition_number = $this->getByteValue();
                        $this->file_pointer++;
                        $size = $this->getByteValue();
                        $this->file_pointer++;
                        $base_type = $this->getByteValue();
                        $this->file_pointer++;
                        $this->writeDebug("[" . ($this->file_pointer - 2) . "-$this->file_pointer]:$field_definition_number, $size, $base_type Field Def., Size, Base Type\n");
                        //
                        // FIND SUBFIELD
                        //
                        $name = '';
                        if (isset($this->data_mesg_info[$global_mesg_num]['field_defns'][$field_definition_number]['field_name'])) {
                            $name = $this->data_mesg_info[$global_mesg_num]['field_defns'][$field_definition_number]['field_name'];
                        } else {
                            $name = "unknown";
                        }
                        $field_definitions[] = ['field_name' => $name, 'field_definition_number' => $field_definition_number, 'size' => $size, 'base_type' => $base_type,];
                        $total_size += $size;
                    }

                    $num_dev_fields = 0;
                    $dev_field_definitions = [];
                    if ($developer_data_flag === 1) {
                        $num_dev_fields = $this->getByteValue();
                        $this->writeDebug("[$this->file_pointer]:$num_dev_fields Number of Dev Fields\n");
                        $this->file_pointer++;

                        for ($i = 0;
                                $i < $num_dev_fields;
                                ++$i) {
                            $field_definition_number = $this->getByteValue();
                            $this->file_pointer++;
                            $size = $this->getByteValue();
                            $this->file_pointer++;
                            $developer_data_index = $this->getByteValue();
                            $this->file_pointer++;
                            $this->writeDebug("[" . ($this->file_pointer - 2) . "-$this->file_pointer]:$field_definition_number, $size, $developer_data_index Field Def., Size, Developer Index\n");

                            $name = '';
                            if (isset($this->data_mesg_info[$global_mesg_num]['field_defns'][$field_definition_number]['field_name'])) {
                                $name = $this->data_mesg_info[$global_mesg_num]['field_defns'][$field_definition_number]['field_name'];
                            } else {
                                $name = "Unknown";
                            }
                            $dev_field_definitions[] = ['field_name' => $name, 'field_definition_number' => $field_definition_number, 'size' => $size, 'developer_data_index' => $developer_data_index];
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
                    $this->writeDebug("DEFINITION MESSAGE TYPE: $local_mesg_type: " . print_r($this->defn_mesgs[$local_mesg_type], true));
                    break;

                case DATA_MESSAGE:
                    /**
                     * Data Messages: these contain a local message type and populated data fields in the format
                     * described by the preceding definition message. The definition message and its associated
                     * data messages will have matching local message types. There are two types of data message:
                     *      Normal Data Message
                     *      Compressed Timestamp Data Message
                     *
                     * Check that we have information on the Data Message.
                     */
                    if ($this->debug) {
                        // trying to map out data messages
                        // not working so far - count is wrong for ending of block
                        $this->writeDebug("DATA MESSAGE\n");
                        $size = 0;
                        foreach ($this->defn_mesgs[$local_mesg_type]['field_defns'] as $key => $value) {
                            if (isset($value['size'])) {
                                $size = $size + $value['size'] + 1;
                            }
                        }
                        $block_start = $this->file_pointer - 1; // get header too
                        $block_size = $size;
                        ob_start();
                        echo sprintf("[%04d-%04d]:\n", $block_start, $block_start + $block_size);
                        $this->hex_dump($this->getString($block_start, $block_size));
                        $contents = ob_get_clean();
                        echo $contents;
                    }

                    if (isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']])) {
                        $tmp_record_array = [];  // Temporary array to store Record data message pieces
                        $tmp_value = null;  // Placeholder for value for checking before inserting into the tmp_record_array

                        foreach ($this->defn_mesgs[$local_mesg_type]['field_defns'] as $field_defn) {
                            // Check that we have information on the Field Definition and a valid base type exists.
                            if (isset($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]) && isset($this->types[$field_defn['base_type']])) {
                                // Check if it's an invalid value for the type
                                $tmp_value = unpack($this->types[$field_defn['base_type']]['format'], $this->getString($this->file_pointer, $field_defn['size']))['tmp'];
                                $this->writeDebug("[$this->file_pointer-" . ($this->file_pointer + $field_defn['size']) . "]=$tmp_value\n");

                                if ($tmp_value !== $this->invalid_values[$field_defn['base_type']] ||
                                        $this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 132) {
                                    // If it's a timestamp, compensate between different in FIT and Unix timestamp epochs
                                    if ($field_defn['field_definition_number'] === 253 && !$this->garmin_timestamps) {
                                        $tmp_value += FIT_UNIX_TS_DIFF;
                                    }

                                    // If it's a Record data message, store all the pieces in the temporary array as the timestamp may not be first...
                                    if ($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 20) {
                                        $tmp_record_array[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']] = $tmp_value / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                        $this->writeDebug("Global Mesg 20: " . print_r($tmp_record_array, true));
                                    } elseif ($this->defn_mesgs[$local_mesg_type]['global_mesg_num'] === 206) {  // Developer Data
                                        $tmp_record_array[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']] = $tmp_value;
                                        $this->writeDebug("Global Mesg 206: " . print_r($tmp_record_array, true));
                                    } else {
                                        if ($field_defn['base_type'] === 7) {  // Handle strings appropriately
                                            $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = filter_var($tmp_value, FILTER_SANITIZE_STRING);
                                            $this->writeDebug("RAW String: " . $tmp_value . "\n");
                                            $this->writeDebug("String: " . filter_var($tmp_value, FILTER_SANITIZE_STRING) . "\n");
                                        } else {
                                            // Handle arrays
                                            if ($field_defn['size'] !== $this->types[$field_defn['base_type']]['bytes']) {
                                                $tmp_array = [];
                                                $num_vals = $field_defn['size'] / $this->types[$field_defn['base_type']]['bytes'];
                                                for ($i = 0;
                                                        $i < $num_vals;
                                                        ++$i) {
                                                    $tmp_array[] = unpack($this->types[$field_defn['base_type']]['format'], $this->getString($this->file_pointer + ($i * $this->types[$field_defn['base_type']]['bytes']), $field_defn['size']))['tmp'] / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                                    $this->writeDebug("[" . ($this->file_pointer + ($i * $this->types[$field_defn['base_type']]['bytes'])) . "-" . (($this->file_pointer + ($i * $this->types[$field_defn['base_type']]['bytes'])) + $field_defn['size']) . "]: " . print_r($tmp_array, true));
                                                }
                                                $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = $tmp_array;
                                                $this->writeDebug($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name'] . " -> " .
                                                        $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name'] . " -> " . (print_r($tmp_array, true)) . " \n");
                                            } else {
                                                $this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']][$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name']][] = $tmp_value / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset'];
                                                $this->writeDebug($this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name'] . " -> " .
                                                        $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['field_name'] . " -> " . ($tmp_value / $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['scale'] - $this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['field_defns'][$field_defn['field_definition_number']]['offset']) . " \n");
                                            }
                                        }
                                    }
                                }
                            }
                            $this->file_pointer += $field_defn['size'];
                        }
                        if (isset($this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']])) {
                            $this->writeDebug("DATA MESSAGE: " . print_r($this->data_mesgs[$this->data_mesg_info[$this->defn_mesgs[$local_mesg_type]['global_mesg_num']]['mesg_name']], true));
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
                            $this->data_mesgs['developer_data'][$this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['field_name']]['data'][] = unpack($this->types[$this->dev_field_descriptions[$field_defn['developer_data_index']][$field_defn['field_definition_number']]['fit_base_type_id']]['format'], $this->getString($this->file_pointer, $field_defn['size']))['tmp'];

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
                                        $timestamp = $previousTS - $fiveLsb + $tsOffset + 32;
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
                                    $this->writeDebug("$key | $timestamp => $value\n");
                                }
                            }
                        }
                    } else {
                        $this->file_pointer += $this->defn_mesgs[$local_mesg_type]['total_size'];
                    }
            }
        }
//        print_r($this->data_mesgs);
//        exit;
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
    private function fixData($options) {
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
                                if ($this->data_mesgs[$mesg_name][$field_name] > 0x7FFFFFFF) {
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
            for ($i = $min_ts;
                    $i <= $max_ts;
                    ++$i) {
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
                    $bLatitudeLongitude = (count($this->data_mesgs['record']['position_lat']) === $count_timestamp && count($this->data_mesgs['record']['position_long']) === $count_timestamp) ? false : in_array('lat_lon', $options['fix_data']);
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

        if (!isset($this->data_mesgs['record'])) {
            $this->writeDebug("No recorded data.\n");
            return;
        }

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
    private function interpolateMissingData(&$missing_keys, &$array) {
        if (!is_array($array)) {
            return;  // Can't interpolate if not an array
        }

        $num_points = 2;

        $min_key = min(array_keys($array));
        $max_key = max(array_keys($array));
        $count = count($missing_keys);

        for ($i = 0;
                $i < $count;
                ++$i) {
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
                for ($j = $i + 1;
                        $j < $count;
                        ++$j) {
                    if ($missing_keys[$j] < key($array)) {
                        $num_points++;
                    } else {
                        break;
                    }
                }

                $gap = ($next_value - $prev_value) / $num_points;

                for ($k = 0;
                        $k <= $num_points - 2;
                        ++$k) {
                    $array[$missing_keys[$i + $k]] = $prev_value + ($gap * ($k + 1));
                }
                for ($k = 0;
                        $k <= $num_points - 2;
                        ++$k) {
                    $missing_keys[$i + $k] = 0;
                }

                $num_points = 2;
            }
        }

        ksort($array);  // sort using keys
    }

    /**
     * Change arrays that contain only one element into non-arrays so you can use $variable rather than $variable[0] to access.
     */
    private function oneElementArrays() {
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
    public function enumData($type, $value) {
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
    public function manufacturer() {
        if (!isset($this->data_mesgs['device_info'])) {
            return; // no recorded device information
        }
        $tmp = $this->enumData('manufacturer', $this->data_mesgs['device_info']['manufacturer']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }

    public function product() {
        if (!isset($this->data_mesgs['device_info'])) {
            return; // no recorded device information
        }
        $tmp = $this->enumData('product', $this->data_mesgs['device_info']['product']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }

    public function sport() {
        if (!isset($this->data_mesgs['session'])) {
            return; // no data records
        }
        $tmp = $this->enumData('sport', $this->data_mesgs['session']['sport']);
        return is_array($tmp) ? $tmp[0] : $tmp;
    }

    /**
     * Transform the values read from the FIT file into the units requested by the user.
     */
    private function setUnits($options) {
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
     * @param type $hr_maximum - max heart rate
     * @param type $percentages_array - upper percentage limit lists
     * @return type - array with heart rates for each zone based on HR max provided
     * @throws \Exception
     */
    public function hrZonesMax($hr_maximum, $percentages_array = [0.60, 0.75, 0.85, 0.95]) {
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
     * @param type $hr_resting - resting heart rate
     * @param type $hr_maximum - maximum heart rate
     * @param type $percentages_array - upper percentage limit lists
     * @return type - array with HRR for each zone
     * @throws \Exception
     */
    public function hrZonesReserve($hr_resting, $hr_maximum, $percentages_array = [0.60, 0.65, 0.75, 0.82, 0.89, 0.94]) {
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
    public function powerZones($functional_threshold_power, $percentages_array = [0.55, 0.75, 0.90, 1.05, 1.20, 1.50]) {
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
    public function partitionData($record_field = '', $thresholds = null, $percentages = true, $labels_for_keys = true) {
        if (!isset($this->data_mesgs['record'][$record_field])) {
            throw new \Exception('phpFITFileAnalysis->partitionData(): ' . $record_field . ' data not present in FIT file!');
        }
        if (!is_array($thresholds)) {
            throw new \Exception('phpFITFileAnalysis->partitionData(): thresholds must be an array e.g. [10,20,30,40,50]!');
        }

        foreach ($thresholds as $threshold) {
            if (!is_numeric($threshold) || $threshold < 0) {
                throw new \Exception('phpFITFileAnalysis->partitionData(): ' . $threshold . ' not valid in thresholds!');
            }
            if (isset($last_threshold) && $last_threshold >= $threshold) {
                throw new \Exception('phpFITFileAnalysis->partitionData(): error near ..., ' . $last_threshold . ', ' . $threshold . ', ... - each element in thresholds array must be greater than previous element!');
            }
            $last_threshold = $threshold;
        }

        $result = array_fill(0, count($thresholds) + 1, 0);

        foreach ($this->data_mesgs['record'][$record_field] as $value) {
            $key = 0;
            $count = count($thresholds);
            for ($key;
                    $key < $count;
                    ++$key) {
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
            for ($i = 0;
                    $i < $count;
                    ++$i) {
                $keys[] = $thresholds[$i] . (isset($thresholds[$i + 1]) ? '-' . ($thresholds[$i + 1] - 1) : '+');
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
     * Split data into buckets/bins using a Counting Sort algorithm
     * (http://en.wikipedia.org/wiki/Counting_sort) to generate data for a histogram plot.
     */
    public function histogram($bucket_width = 25, $record_field = '') {
        if (!isset($this->data_mesgs['record'][$record_field])) {
            trigger_error("->histogram: '$record_field' data not present in FIT file.", E_USER_NOTICE);
            return;
            //throw new \Exception('phpFITFileAnalysis->histogram(): ' . $record_field . ' data not present in FIT file!');
        }
        if (!is_numeric($bucket_width) || $bucket_width <= 0) {
            throw new \Exception('phpFITFileAnalysis->histogram(): bucket width is not valid!');
        }

        foreach ($this->data_mesgs['record'][$record_field] as $value) {
            $key = round($value / $bucket_width) * $bucket_width;
            isset($result[$key]) ? $result[$key]++ : $result[$key] = 1;
        }

        for ($i = 0;
                $i < max(array_keys($result)) / $bucket_width;
                ++$i) {
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
    public function hrPartionedHRmaximum($hr_maximum) {
        return $this->partitionData('heart_rate', $this->hrZonesMax($hr_maximum));
    }

    public function hrPartionedHRreserve($hr_resting, $hr_maximum) {
        return $this->partitionData('heart_rate', $this->hrZonesReserve($hr_resting, $hr_maximum));
    }

    public function powerPartioned($functional_threshold_power) {
        return $this->partitionData('power', $this->powerZones($functional_threshold_power));
    }

    public function powerHistogram($bucket_width = 25) {
        return $this->histogram($bucket_width, 'power');
    }

    /**
     * Simple moving average algorithm
     */
    private function sma($array, $time_period) {
        $sma_data = [];
        $data = array_values($array);
        $count = count($array);

        for ($i = 0;
                $i < $count - $time_period;
                ++$i) {
            $sma_data[] = array_sum(array_slice($data, $i, $time_period)) / $time_period;
        }

        return $sma_data;
    }

    /**
     * Calculate TRIMP (TRaining IMPulse) and an Intensity Factor using HR data. Useful if power data not available.
     * hr_FT is heart rate at Functional Threshold, or Lactate Threshold Heart Rate (LTHR)
     *
     * Returns:
     * TRIMPexp (TRaining IMPulse) measure of effort 0-100 with 100 for a 1 hr maximum sustained effort
     * Intensity Factor - <0.65 recovery, 0.65-0.85 Endurance, 0.85 and 0.95 Tempo where fat burning is maximal
     * and a lot of energy is generated from carbohydrates)
     * 0.95-1.05 ‘sweet spot zone’, >1.05 intensity workouts
     * see: https://www.trainingpeaks.com/learn/articles/normalized-power-intensity-factor-training-stress/
     */
    public function hrMetrics($hr_resting, $hr_maximum, $hr_FT, $gender) {
        if (!isset($this->data_mesgs['record']['heart_rate'])) {
            trigger_error("->hrMetrics: 'heart_rate' data not present in FIT file.", E_USER_NOTICE);
            return;
            //throw new \Exception('phpFITFileAnalysis->hrMetrics(): heart rate data not present in FIT file!');
        }
        $meta = [
            'Recovery Pace' => 0.75,
            'Endurance Pace' => 0.85,
            'Tempo or aerobic and anaerobic interval' => 0.90,
            'Anaerobice threshold' => 1.05,
            'Time Trials/Race' => 1.15,
            'Intensive' => 3
        ];
        $hr_metrics = [// array to hold HR analysis data
            'TRIMPexp' => 0.0,
            'hrIF' => 0.0,
            'Meta' => '',
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
            $hr_metrics['TRIMPexp'] += ((1 / 60) * $temp_heart_rate * 0.64 * (exp($gender_coeff * $temp_heart_rate)));
        }
        $hr_metrics['TRIMPexp'] = round($hr_metrics['TRIMPexp']);
        $hr_metrics['hrIF'] = round((array_sum($this->data_mesgs['record']['heart_rate']) / (count($this->data_mesgs['record']['heart_rate']))) / $hr_FT, 2);
        foreach ($meta as $state => $upperBounds) {
            if ($hr_metrics['hrIF'] < $upperBounds) {
                $hr_metrics['Meta'] = $state;
                break;
            }
        }
        return $hr_metrics;
    }

    /**
     * Compute the time spent in each heart rate zone in minutes
     * Standard garmin zones:
     * 1 50–60%
     * 2 60–70%
     * 3 70–80%
     * 4	 80–90%
     * 5 90–100+%
     * @param int/flot $hr_maximum - max heart rate
     * @param array percentages_zone - list of percentages for each upper bound of the zone
     * @return type array with time in each zone 0-5.
     */
    public function timeInZones($hr_maximum, $percentages_zones = [0.60, 0.70, 0.80, 0.90, 1]) {
        if (!isset($this->data_mesgs['record']['heart_rate'])) {
            trigger_error("->timeInZones: 'heart_rate' data not present in FIT file.", E_USER_NOTICE);
            return;
            //throw new \Exception('phpFITFileAnalysis->timeInZones(): heart rate data not present in FIT file!');
        }
        if (count($percentages_zones) < 2) {
            throw new \Exception('phpFITFileAnalysis->timeInZones(): requires at least 2 zones to be set.');
        }
        $hr_metrics = [];
        foreach ($percentages_zones as $key => $value) {
            $hr_metrics[$key + 1] = 0.0;
        }

        $prev_time = key(array_slice($this->data_mesgs['record']['heart_rate'], 0, 1, true));
        foreach ($this->data_mesgs['record']['heart_rate'] as $sec => $hr) {
            $elapsed = round(($sec - $prev_time) / 60, 2);

            foreach ($percentages_zones as $zone => $upperBounds) {
                if ($hr < ($hr_maximum * $upperBounds)) {
                    $hr_metrics[$zone + 1] += $elapsed;
                    break;
                }
            }
            $prev_time = $sec;
        }
        return $hr_metrics;
    }

    /**
     * Returns 'Average Power', 'Kilojoules', 'Normalised Power', 'Variability Index', 'Intensity Factor', and 'Training Stress Score' in an array.
     *
     * Normalised Power (and metrics dependent on it) require the PHP trader extension to be loaded
     * http://php.net/manual/en/book.trader.php
     */
    public function powerMetrics($functional_threshold_power) {
        if (!isset($this->data_mesgs['record']['power'])) {
            trigger_error("->powerMetrics: power data not present in FIT file.", E_USER_NOTICE);
            return;
            //throw new \Exception('phpFITFileAnalysis->powerMetrics(): power data not present in FIT file!');
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
        $power_metrics['Normalised Power'] = pow($NormalisedPower, 1 / 4);  // NP4 taking the fourth root of the value obtained in step NP3

        $power_metrics['Variability Index'] = $power_metrics['Normalised Power'] / $power_metrics['Average Power'];
        $power_metrics['Intensity Factor'] = $power_metrics['Normalised Power'] / $functional_threshold_power;
        $power_metrics['Training Stress Score'] = (count($this->data_mesgs['record']['power']) * $power_metrics['Normalised Power'] * $power_metrics['Intensity Factor']) / ($functional_threshold_power * 36);

        // Round the values to make them something sensible.
        $power_metrics['Average Power'] = (int) round($power_metrics['Average Power']);
        $power_metrics['Kilojoules'] = (int) round($power_metrics['Kilojoules']);
        $power_metrics['Normalised Power'] = (int) round($power_metrics['Normalised Power']);
        $power_metrics['Variability Index'] = round($power_metrics['Variability Index'], 2);
        $power_metrics['Intensity Factor'] = round($power_metrics['Intensity Factor'], 2);
        $power_metrics['Training Stress Score'] = (int) round($power_metrics['Training Stress Score']);

        return $power_metrics;
    }

    /**
     * Returns Critical Power (Best Efforts) values for supplied time period(s).
     */
    public function criticalPower($time_periods) {
        if (!isset($this->data_mesgs['record']['power'])) {
            trigger_error("->criticalPower: power data not present in FIT file.", E_USER_NOTICE);
            return;
            //throw new \Exception('phpFITFileAnalysis->criticalPower(): power data not present in FIT file!');
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
    public function isPaused() {
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

        for ($i = $first_ts;
                $i < $last_ts;
                ++$i) {
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
    public function quadrantAnalysis($crank_length, $ftp, $selected_cadence = 90, $use_timestamps = false) {
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
        for ($c = 20;
                $c <= 150;
                $c += 5) {
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
    public function gearChanges($bIgnoreTimerPaused = true) {
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
                'timestamp' => $this->data_mesgs['event']['timestamp'][$k],
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
                'timestamp' => $this->data_mesgs['event']['timestamp'][$k],
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
        for ($i = $first_ts;
                $i < $last_ts;
                ++$i) {
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
    public function getJSON($crank_length = null, $ftp = null, $data_required = ['all'], $selected_cadence = 90) {
        if (!is_array($data_required)) {
            $data_required = [$data_required];
        }
        foreach ($data_required as &$datum) {
            $datum = strtolower($datum);
        }

        $all = in_array('all', $data_required);
        $timestamp = ($all || in_array('timestamp', $data_required));
        $paused = ($all || in_array('paused', $data_required));
        $temperature = ($all || in_array('temperature', $data_required));
        $lap = ($all || in_array('lap', $data_required));
        $position_lat = ($all || in_array('position_lat', $data_required));
        $position_long = ($all || in_array('position_long', $data_required));
        $distance = ($all || in_array('distance', $data_required));
        $altitude = ($all || in_array('altitude', $data_required));
        $speed = ($all || in_array('speed', $data_required));
        $heart_rate = ($all || in_array('heart_rate', $data_required));
        $cadence = ($all || in_array('cadence', $data_required));
        $power = ($all || in_array('power', $data_required));
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
    public function getJSONLap() {
        $for_json = [];
        $for_json['fix_data'] = isset($this->options['fix_data']) ? $this->options['fix_data'] : null;
        $for_json['units'] = isset($this->options['units']) ? $this->options['units'] : null;
        $for_json['pace'] = isset($this->options['pace']) ? $this->options['pace'] : null;
        $for_json['num_laps'] = count($this->data_mesgs['lap']['timestamp']);
        $data = [];

        for ($i = 0;
                $i < $for_json['num_laps'];
                $i++) {
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
    public function showDebugInfo() {
        asort($this->defn_mesgs_all);  // Sort the definition messages
        // bootstrap v5.2.3 cdn for appearence
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>';

        echo '<h3>Types</h3>';
        echo '<table class=\'table table-condensed table-striped\'>';  // Bootstrap classes
        echo '<thead>';
        echo '<th>key</th>';
        echo '<th>PHP unpack() format</th>';
        echo '<th>Bytes</th>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($this->types as $key => $val) {
            echo '<tr><td>' . $key . '</td><td>' . $val['format'] . '</td><td>' . $val['bytes'] . '</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';

        echo '<br><hr><br>';

        echo '<h3>Messages and Fields being listened for</h3>';
        foreach ($this->data_mesg_info as $key => $val) {
            echo '<h4>MESSAGE NAME: ' . $val['mesg_name'] . ' [' . $key . ']</h4>';
            echo '<table class=\'table table-condensed table-striped\'>';
            echo '<thead><th>ID</th><th>Name</th><th>Scale</th><th>Offset</th><th>Units</th></thead><tbody>';
            foreach ($val['field_defns'] as $key2 => $val2) {
                echo '<tr><td>' . $key2 . '</td><td>' . $val2['field_name'] . '</td><td>' . $val2['scale'] . '</td><td>' . $val2['offset'] . '</td><td>' . $val2['units'] . '</td></tr>';
                if (isset($val2[0])) {
                    // dynamic fields found
                    foreach ($val2 as $key3 => $val3) {
                        if (!is_array($val3)) {
                            continue; // skip non array elements
                        }
                        echo '<tr><td><i>' . $val3['field_name'] . '</i></td><td>' . $val3['field_type'] . '</td><td>' . $val3['scale'] . '</td><td>' . $val3['offset'] . '</td><td>' . $val2['units'] . '</td></tr>';
                    }
                }
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
            echo '<tr><td>' . $val['global_mesg_num'] . (isset($this->data_mesg_info[$val['global_mesg_num']]) ? ' (' . $this->data_mesg_info[$val['global_mesg_num']]['mesg_name'] . ')' : ' (unknown)') . '</td><td>' . $val['num_fields'] . '</td><td>';
            foreach ($val['field_defns'] as $defn) {
                echo 'defn: ' . $defn['field_definition_number'] . '; size: ' . $defn['size'] . '; type: ' . $defn['base_type'];
                echo ' (' . (isset($this->data_mesg_info[$val['global_mesg_num']]['field_defns'][$defn['field_definition_number']]) ? $this->data_mesg_info[$val['global_mesg_num']]['field_defns'][$defn['field_definition_number']]['field_name'] : 'unknown') . ')<br>';
            }
            echo '</td><td>' . $val['total_size'] . '</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';

        echo '<br><hr><br>';

        echo '<h3>Messages found in file</h3>';
        foreach ($this->data_mesgs as $mesg_key => $mesg) {
            echo '<table class=\'table table-condensed table-striped\'>';
            echo '<thead><th>' . $mesg_key . '</th><th>count()</th></thead><tbody>';
            foreach ($mesg as $field_key => $field) {
                if (is_countable($field)) {
                    echo '<tr><td>' . $field_key . '</td><td>' . count($field) . '</td></tr>';
                } else {
                    echo '<tr><td>' . $field_key . '</td><td>' . print_r($field, true) . '</td></tr>';
                }
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

    private function processHrMessages() {
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
            $j = 0;
            for ($i = 0;
                    $i < 11;
                    $i++) {
                $last_event_timestamp12 = $last_event_timestamp & 0xFFF;
                $next_event_timestamp12;

                if ($j % 2 === 0) {
                    $next_event_timestamp12 = $event_timestamp_12_val[$i] + (($event_timestamp_12_val[$i + 1] & 0xF) << 8);
                    $last_event_timestamp = ($last_event_timestamp & 0xFFFFF000) + $next_event_timestamp12;
                } else {
                    $next_event_timestamp12 = 16 * $event_timestamp_12_val[$i + 1] + (($event_timestamp_12_val[$i] & 0xF0) >> 4);
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
            $this->data_mesgs['record']['heart_rate'][$idx] = (int) round($arr[0] / $arr[1]);
        }
    }

    private function writeDebug($message) {
        if (!$this->debug) {
            return;
        }
        echo $message;
    }

    /**
     * Get single byte value at current file pointer from file buffer
     * @return type INT
     */
    private function getByteValue() {
        return ord(substr($this->file_contents, $this->file_pointer, 1));
    }

    /**
     * Get $length byte values at $start from file buffer
     * @param type $start int
     * @param type $length int
     * @return type int
     */
    private function getBytesValues($start, $length) {
        return ord(substr($this->file_contents, $start, $length));
    }

    /**
     * Get a string from the file buffer
     * @param type $start int
     * @param type $length int
     * @return type string
     */
    private function getString($start, $length) {
        return substr($this->file_contents, $start, $length);
    }

    /**
     * Converts binary data to a hex dump with ascii values
     *
     * @staticvar string $from
     * @staticvar string $to
     * @staticvar int $width
     * @staticvar string $pad
     * @param type $data binary data
     * @param type $newline ending character
     */
    private function hex_dump($data, $newline = "\n") {
        static $from = '';
        static $to = '';
        static $width = 16; # number of bytes per line
        static $pad = '.'; # for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width * 2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        echo "         x0 x1 x2 x3 x4 x5 x6 x7 x8 x9 xa xb xc xd xe xf\n";
        foreach ($hex as $i => $line) {
            echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width;
        }
    }

}
