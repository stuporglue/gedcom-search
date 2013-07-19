<?php

/* 
 * GEDCOM Specific Search Engine
 *
 * Given a string as input, searches a GEDCOM and returns ranked results
 *
 * Uses the PHP-Gedcom library for GEDCOM parsing
 *
 * Searches: 
 *  Names (exact match and soundex)
 *  Events (Individual and Family)
 *  Place Names (exact match and soundex)
 *  Dates
 *  Notes
 *  Sources
 *  Media
 *
 */


spl_autoload_register(function ($class) {
    $pathToPhpGedcom = __DIR__ . '/php-gedcom/library/'; 

    if (!substr(ltrim($class, '\\'), 0, 7) == 'PhpGedcom\\') {
        return;
    }

    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($pathToPhpGedcom . $class)) {
        require_once($pathToPhpGedcom . $class);
    }
});


class GedcomSearch {

    var $gedcom;

    /*
     * Weighting is done by multiplying the scores of the found objects
     * $score = $typeWeight * ($objectWeight['type'] * $matchWeight + $objectWeight['type'] * $matchWeight)
     *
     * $typeWeight and $matchWeight are single depth, so their scores only occur if the specific key value type is matched
     *
     * $objectWeight is more than one layer deep. Scores found in objectWeight are cumulative. So an indi with a 
     */
    var $typeWeight;
    var $matchWeight;
    var $objectWeight;

    function __construct($gedcomFile,$weighting = Array()){
        $this->matchWeight   = Array(

            // match weight should be permutations of : Exact, exact out of order, alpha only, soundex

            'exact' => 10,
            'exactAlpha' => 9,
            'exactSoundex' => 8,
            'outOfOrderExact' => 6,
            'outOfOrderSoundex' => 5,
            'partialExact' => 3,
            'partialSoundex' => 3,
        );

        $this->typeWeight = Array(
            'indi' => 10,
            'fam' => 8,
            'sour' => 6,
            'obje' => 6
        );

        $this->objectWeight= Array(
            'indi' = Array(
                'name' => 10,
                'note' => 8,
                'event' => Array(
                    'note' => 6,
                    'place' => Array(
                        'name' => 3
                    ),
                    'date' => 2
                ),
                'ordinance' => Array(
                    'note' => 6,
                    'place' => Array(
                        'name' => 3
                    ),
                    'date' => 2
                ),
            ),
            'fam' => Array(
                'spouseName' => 10,
                'childrenName' => 8,
                'note' => 6,
                'event' => Array(
                    'note' => 6,
                    'place' => Array(
                        'name' => 3
                    ),
                    'date' => 2
                ),
            ),
            'sour' => Array(
                'note' => 6,
            ),
            'media' => Array(
                'fileName' => 10,
                'note' => 8,
            ),
        );

        if(array_key_exists('match',$weighting)){
            $this->matchWeight = array_merge($this->matchWeight,$weighting['match']);
        }
        if(array_key_exists('type',$weighting)){
            $this->typeWeight= array_merge($this->typeWeight,$weighting['type']);
        }
        if(array_key_exists('object',$weighting)){
            $this->objectWeight= array_merge($this->objectWeight,$weighting['object']);
        }

        // Parse the given file
        $parser = new PhpGedcom\Parser();
        $this->gedcom = $parser->parse($gedcomFile);
    }

    // Run one search
    function search($searchString,$resultsLimit = 10){
        $results = Array();

        foreach($this->gedcom->getIndi() as $indi){

        }
        foreach($this->gedcom->getFam() as $fam){

        }
        foreach($this->gedcom->getSour() as $sour){

        }
        foreach($this->gedcom->getObje() as $obje){

        }

        return $results;
    }

    // Determine the match weight for the given needle and haystack
    function matchWeight($needle,$haystack){

        $needle = strtolower($needle);
        $haystack = strtolower($haystack);

        $highScore = 0;

        // exact match
        if($this->matchWeight['exact'] > $highScore){
            if(strpos($haystack,$needle) !== FALSE){
                $highScore = $this->matchWeight['exact'];
            }
        }

        // Exact Alpha -- strip all non-alpha characters and search
        if($this->matchWeight['exactAlpha'] > $highScore){
            if(strpos(
                preg_replace('/[^a-z]/','',$haystack),
                preg_replace('/[^a-z]/','',$needle) !== FALSE){
                    $highScore = $this->matchWeight['exactAlpha'];
                }
        }

        // exact soundex
        if($this->matchWeight['exactSoundex'] > $highScore){
            $needlePieces = array_map('soundex',preg_split('/\s+',$needle));
            $haystackPieces = array_map('soundex',preg_split('/\s+',$needle)); 
            $firstNeedle = array_shift($needlePieces);
            $soundexFoundex = array_search($firstNeedle,$haystackPieces);
            while($soundexFoundex !== FALSE){
                $foundMatch = TRUE;
                $haystackPieces = array_slice($soundexFoundex + 1,$haystackPieces);
                foreach($needlePieces as $i => $n){
                    if($haystackPieces[$i] != $n){
                        $foundMatch = FALSE;
                        break;
                    }
                }
                $soundexFoundex = array_search($firstNeedle,$haystackPieces);
            }
            if($foundMatch){
                $highScore = $this->matchWeight['exactSoundex'];
            }
        }

        // Out of order Exact -- all pieces must match
        // partialExact -- some pieces must match (score multipled by ratio of matches)
        if($this->matchWeight['outOfOrderExact'] > $highScore || $this->matchWeight['partialExact'] > $highScore){
            $needles = preg_split('/\s+/',$needle);
            if(count($needle) > 1){ // we did a 1 needle search first
                $matches = 0;
                foreach($needles as $needlePiece){
                    if(strpos($haystack,$needlePiece) !== FALSE){
                        $matches++;
                    }
                }
                $ratio = $matches/count($needles);
                if($ratio == 1 && $this->matchWeight['outOfOrderExact'] > $highScore){
                    $highScore = $this->matchWeight['outOfOrderExact'];
                }
                if($ratio > 0 && $this->matchWeight['partialExact'] > $highScore){
                    $highScore = $this->matchWeight['partialExact'];
                }
            }
        }
        return $highScore;
    }

    // Return a summary appropriate for search results
    function summary($obj,$callback){
    }
}
