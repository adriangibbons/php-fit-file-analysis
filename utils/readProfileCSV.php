<?php

/**
 * This reads the Profile.xlsx from the Fit SDK Release information and converts it into arrays.
 * xlsx file should be saved as 2 files "Profile-mesg.csv" for the message tab
 * and "Profile-types.csv" for the type tab.
 *
 */
$sdk_version = "21.101";

//
// TYPES - MESSAGES
//
$enum = '$enum_data = [' . "\n\t";
$enum_first = true;

if (($open = fopen("Profile-types.csv", "r")) !== FALSE) {

    $header = fgetcsv($open, 4096, ';', '"');

    while (($data = fgetcsv($open, 1000, ",")) !== FALSE) {
        if (!empty($data[0])) {
            if ($enum_first) {
                $enum .= "'$data[0]' => [\n\t\t'type' => '$data[1]',\n";
                $enum_first = false;
            } else {
                $enum .= "\t],\n\t'$data[0]' => [ \n\t\t'type' => '$data[1]',\n";
            }
        } else {
            $enum .= "\t\t$data[3] => '$data[2]', " . (!empty($data[4]) ? "// $data[4]\n" : "\n");
        }
    }

    $enum .= "\n\t]\n];\n";

    fclose($open);
} else {
    echo "The CSV version of the Profiles.xlsx Types tab needs to be available in the local directory.\n";
    exit(1);
}
$fp = fopen('type-enums.php', 'w');
fwrite($fp, "<?php\n\n// VERSION: $sdk_version\n// Automatically extracted from FIT SDK https://developer.garmin.com/fit/download/");
fwrite($fp, "\n// Extracted from CSV versions of the Profile Type tab in Profile.xlsx\n");
fwrite($fp, "//add private in front of \$enum_data when updating phpFitFileAnaysis.php\n");
fwrite($fp, "\n\n" . $enum);
fclose($fp);

//
// MESSAGES
//  needs 'mesg_num' from $enum_data from previous code
//
require_once('type-enums.php'); // puts $enum_data into memory

$data_msg = '$data_mesg_info = [' . "\n\t";
$data_first = true;

if (($open = fopen("Profile-mesg.csv", "r")) !== FALSE) {

    $header = fgetcsv($open, 4096, ';', '"');

    while (($data = fgetcsv($open, 2000, ",")) !== FALSE) {
        if (str_contains($data[6], ',')) {
            $data[6] = "'$data[6]'"; // string of scale values
        }
        if (!empty($data[0])) {
            if ($data_first) {
                //$data_msg .= "'$data[0]' => [ // $data[13]\n";
                $data_msg .= array_search($data[0], $enum_data['mesg_num']) . " => ['mesg_name' =>'$data[0]', 'field_defns' => [\n\t";
                $data_first = false;
            } else {
                $data_msg .= "]],\n\t " . array_search($data[0], $enum_data['mesg_num']) . " => ['mesg_name' =>'$data[0]', 'field_defns' => [\n";
            }
        } else {
            if (!is_numeric($data[1])) {
                // Start Dynamic section, remove previous closure
                $data_msg = addDynamic($open, $data, $data_msg);
            } else {
                $data_msg .= "\t\t$data[1] => ['field_name' => '$data[2]', 'field_type' => '$data[3]', 'scale' => " . (!empty($data[6]) ? "$data[6]" : "1") . ", "
                        . "'offset' => " . (!empty($data[7]) ? "$data[7]" : "0") . ", "
                        . "'units' => " . (!empty($data[8]) ? "'$data[8]'" : "''") . ", "
                        . "'bits' => " . (!empty($data[9]) ? "'$data[9]'" : "''") . ", "
                        . "'accumulate' => " . (!empty($data[10]) ? "'$data[10]'" : "''") . ", "
                        . (!empty($data[5]) ? "'component'=>'$data[5]', " : "")
                        . "'ref_field_type' => " . (!empty($data[12]) ? "'$data[12]'" : "''") . ", "
                        . "'ref_field_name' => '$data[11]'], " . (!empty($data[13]) ? "// $data[13] (e.g. $data[15])\n" : "\n");
            }
        }
    }

    $data_msg .= "\n\t]\n]];\n";

    fclose($open);
} else {
    echo "The CSV version of the Profiles.xlsx Messages tab needs to be available in the local directory.\n";
    exit(1);
}

/**
 * Assumes $data array is first message in dynamic message, then scans for more and stops
 * @param type $fp
 * @param type $data
 * @param type $data_msg
 */
function addDynamic($fp, $data, $data_msg) {

    if (empty($data[0]) && empty($data[1]) && empty($data[2])) {
        // not a message - abort trying to find it
        return $data_msg;
    }

    // Trim off previous array closure
    $lastClose = strrpos($data_msg, "]");
    $data_msg[$lastClose] = ' ';
    $dynamicIdx = 0;
    //
    // Build Dynamic
    //  Where some fields have multiple scales, switch to string format "1,1,1,1" etc.
    //  Also multiple components and ref_field_values will be comma seperated strings
    if (str_contains($data[6], ',')) {
        $data[6] = "'$data[6]'"; // string of scale values
    }

    $data_msg .= "\t\t$dynamicIdx => ['field_name' => '$data[2]', 'field_type' => '$data[3]', "
            . "'scale' => " . (!empty($data[6]) ? "$data[6]" : "1") . ", "
            . "'offset' => " . (!empty($data[7]) ? "$data[7]" : "0") . ", "
            . "'units' => " . (!empty($data[8]) ? "'$data[8]'" : "''") . ", "
            . "'bits' => " . (!empty($data[9]) ? "'$data[9]'" : "''") . ", "
            . "'accumulate' => " . (!empty($data[10]) ? "'$data[10]'" : "''") . ", "
            . (!empty($data[5]) ? "'component'=>'$data[5]', " : "")
            . "'ref_field_type' => " . (!empty($data[12]) ? "'$data[12]'" : "''") . ", "
            . "'ref_field_name' => '$data[11]'], " . (!empty($data[13]) ? "// $data[13] (e.g. $data[15])\n" : "\n");

    while (($data = fgetcsv($fp, 2000, ",")) !== FALSE) {
        $dynamicIdx++;
        if (!is_numeric($data[1]) && empty($data[0])) {
            // still a dynamic field and not a new field
            if (str_contains($data[6], ',')) {
                $data[6] = "'$data[6]'"; // string of scale values
            }
            $data_msg .= "\t\t$dynamicIdx => ['field_name' => '$data[2]', 'field_type' => '$data[3]', "
                    . "'scale' => " . (!empty($data[6]) ? "$data[6]" : "1") . ", "
                    . "'offset' => " . (!empty($data[7]) ? "$data[7]" : "0") . ", "
                    . "'units' => " . (!empty($data[8]) ? "'$data[8]'" : "''") . ", "
                    . "'bits' => " . (!empty($data[9]) ? "'$data[9]'" : "''") . ", "
                    . "'accumulate' => " . (!empty($data[10]) ? "'$data[10]'" : "''") . ", "
                    . (!empty($data[5]) ? "'component'=>'$data[5]', " : "")
                    . "'ref_field_type' => " . (!empty($data[12]) ? "'$data[12]'" : "''") . ", "
                    . "'ref_field_name' => '$data[11]'], " . (!empty($data[13]) ? "// $data[13] (e.g. $data[15])\n" : "\n");
        } else {
            // no long dynamic message -> new field or more data
            // close previous array
            $data_msg .= "\t\t],\n";

            if (!empty($data[0])) {
                // new field
                $data_msg .= "],\n\t'$data[0]' => [ " . (!empty($data[13]) ? "// $data[13]\n" : "\n");
                return $data_msg;
            }
            // new message
            $data_msg .= "\t\t$data[1] => ['field_name' => '$data[2]', 'field_type' => '$data[3]', 'scale' => " . (!empty($data[6]) ? "$data[6]" : "1") . ", "
                    . "'offset' => " . (!empty($data[7]) ? "$data[7]" : "0") . ", "
                    . "'units' => " . (!empty($data[8]) ? "'$data[8]'" : "''") . ", "
                    . "'bits' => " . (!empty($data[9]) ? "'$data[9]'" : "''") . ", "
                    . "'accumulate' => " . (!empty($data[10]) ? "'$data[10]'" : "''") . ", "
                    . (!empty($data[5]) ? "'component'=>'$data[5]', " : "")
                    . "'ref_field_type' => " . (!empty($data[12]) ? "'$data[12]'" : "''") . ", "
                    . "'ref_field_name' => '$data[11]'], " . (!empty($data[13]) ? "// $data[13] (e.g. $data[15])\n" : "\n");
            return $data_msg;
        }
    }
    // file data stream ends with dynamic message as last element
    // close previous array
    $data_msg .= "\t\t],\n";
    return $data_msg;
}

$fp = fopen('type-messages.php', 'w');
fwrite($fp, "<?php\n\n// VERSION: $sdk_version\n// Automatically extracted from FIT SDK https://developer.garmin.com/fit/download/");
fwrite($fp, "\n// Extracted from CSV versions of the Profile Message tab in Profile.xlsx\n");
fwrite($fp, "//add private in front of \$data_mesg_info when updating phpFitFileAnaysis.php\n");
fwrite($fp, "\n\n" . $data_msg);
fclose($fp);
