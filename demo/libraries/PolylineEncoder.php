<?php

/*!
 * PHP Polyline Encoder
 * 
 */

class PolylineEncoder {
    
    private $points;
    private $encoded;
    
    public function __construct() {
        $this->points = array();
    }
    
    /**
     * Add a point
     * 
     * @param float $lat : lattitude
     * @param float $lng : longitude
     */
    function addPoint($lat, $lng) {
        if (empty($this->points)) {
            $this->points[] = array('x' => $lat, 'y' => $lng);
            $this->encoded = $this->encodeValue($lat) . $this->encodeValue($lng);
        } else {
            $n = count($this->points);
            $prev_p = $this->points[$n-1];
            $this->points[] = array('x' => $lat, 'y' => $lng);
            $this->encoded .= $this->encodeValue($lat-$prev_p['x']) . $this->encodeValue($lng-$prev_p['y']);
        }
    }
    
    /**
     * Return the encoded string generated from the points 
     * 
     * @return string 
     */
    function encodedString() {
        return $this->encoded;
    }
    
    /**
     * Encode a value following Google Maps API v3 algorithm
     * 
     * @param type $value
     * @return type 
     */
    function encodeValue($value) {
        $encoded = "";
        $value = round($value * 100000);
        $r = ($value < 0) ? ~($value << 1) : ($value << 1);
        
        while ($r >= 0x20) {
            $val = (0x20|($r & 0x1f)) + 63;
            $encoded .= chr($val);
            $r >>= 5;
        }
        $lastVal = $r + 63;
        $encoded .= chr($lastVal);
        return $encoded;
    }
    
    /**
     * Decode an encoded polyline string to an array of points
     * 
     * @param type $value
     * @return type 
     */
    static public function decodeValue($value) {
        $index = 0;
        $points = array();
        $lat = 0;
        $lng = 0;

        while ($index < strlen($value)) {
            $b;
            $shift = 0;
            $result = 0;
            do {
                $b = ord(substr($value, $index++, 1)) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b > 31);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;
            do {
                $b = ord(substr($value, $index++, 1)) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b > 31);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = array('x' => $lat/100000, 'y' => $lng/100000);
        }

        return $points;
    }
}