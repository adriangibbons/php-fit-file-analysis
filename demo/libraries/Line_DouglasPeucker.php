<?php
/*
 * A Ramer-Douglas-Peucker implementation to simplify lines in PHP
 * Unlike the one by Pol Dell'Aiera this one is built to operate on an array of arrays and in a non-OO manner,
 * making it suitable for smaller apps which must consume input from ArcGIS or Leaflet, without the luxury of GeoPHP/GEOS
 * 
 * Usage:
 * $verts     = array( array(0,1), array(1,2), array(2,1), array(3,5), array(4,6), array(5,5) );
 * $tolerance = 1.0;
 * $newverts  = simplify_RDP($verts,$tolerance);
 *
 * Bonus: It does not trim off extra ordinates from each vertex, so it's agnostic as to whether your data are 2D or 3D
 * and will return the kept vertices unchanged.
 * 
 * This operates on a single set of vertices, aka a single linestring.
 * If used on a multilinestring you will want to run it on each component linestring separately.
 * 
 * No license, use as you will, credits appreciated but not required, etc.
 * Greg Allensworth, GreenInfo Network   <gregor@greeninfo.org>
 *
 * My invaluable references:
 * https://github.com/Polzme/geoPHP/commit/56c9072f69ed1cec2fdd36da76fa595792b4aa24
 * http://en.wikipedia.org/wiki/Ramer%E2%80%93Douglas%E2%80%93Peucker_algorithm
 * http://math.ucsd.edu/~wgarner/math4c/derivations/distance/distptline.htm
 */

function simplify_RDP($vertices, $tolerance) {
    // if this is a multilinestring, then we call ourselves one each segment individually, collect the list, and return that list of simplified lists
    if (is_array($vertices[0][0])) {
        $multi = array();
        foreach ($vertices as $subvertices) $multi[] = simplify_RDP($subvertices,$tolerance);
        return $multi;
    }

    $tolerance2 = $tolerance * $tolerance;

    // okay, so this is a single linestring and we simplify it individually
    return _segment_RDP($vertices,$tolerance2);
}

function _segment_RDP($segment, $tolerance_squared) {
    if (sizeof($segment) <= 2) return $segment; // segment is too small to simplify, hand it back as-is

    // find the maximum distance (squared) between this line $segment and each vertex
    // distance is solved as described at UCSD page linked above
    // cheat: vertical lines (directly north-south) have no slope so we fudge it with a very tiny nudge to one vertex; can't imagine any units where this will matter
    $startx = (float) $segment[0][0];
    $starty = (float) $segment[0][1];
    $endx   = (float) $segment[ sizeof($segment)-1 ][0];
    $endy   = (float) $segment[ sizeof($segment)-1 ][1];
    if ($endx == $startx) $startx += 0.00001;
    $m = ($endy - $starty) / ($endx - $startx); // slope, as in y = mx + b
    $b = $starty - ($m * $startx);              // y-intercept, as in y = mx + b

    $max_distance_squared = 0;
    $max_distance_index   = null;
    for ($i=1, $l=sizeof($segment); $i<=$l-2; $i++) {
        $x1 = $segment[$i][0];
        $y1 = $segment[$i][1];

        $closestx = ( ($m*$y1) + ($x1) - ($m*$b) ) / ( ($m*$m)+1);
        $closesty = ($m * $closestx) + $b;
        $distsqr  = ($closestx-$x1)*($closestx-$x1) + ($closesty-$y1)*($closesty-$y1);

        if ($distsqr > $max_distance_squared) {
            $max_distance_squared = $distsqr;
            $max_distance_index   = $i;
        }
    }

    // cleanup and disposition
    // if the max distance is below tolerance, we can bail, giving a straight line between the start vertex and end vertex   (all points are so close to the straight line)
    if ($max_distance_squared <= $tolerance_squared) {
        return array($segment[0], $segment[ sizeof($segment)-1 ]);
    }
    // but if we got here then a vertex falls outside the tolerance
    // split the line segment into two smaller segments at that "maximum error vertex" and simplify those
    $slice1 = array_slice($segment, 0, $max_distance_index);
    $slice2 = array_slice($segment, $max_distance_index);
    $segs1 = _segment_RDP($slice1, $tolerance_squared);
    $segs2 = _segment_RDP($slice2, $tolerance_squared);
    return array_merge($segs1,$segs2);
}