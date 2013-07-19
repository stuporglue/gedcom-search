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
    $pathToPhpGedcom = __DIR__ . '/lib/php-gedcom/library/'; 

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

            'exactWords' => 10,
            'outOfOrderWords' => 7,
            'partialWords' => 3, // score is multiplied by percent match. Eg. if two of three search terms match, then this is multiplied by 2/3

            'exactSoundex' => 6,
            'outOfOrderSoundex' => 5,
            'partialSoundex' => 3, // score is multiplied by percent match. Eg. if two of three search terms match, then this is multiplied by 2/3
        );

        $this->typeWeight = Array(
            'indi' => 10,
            'fam' => 8,
            'sour' => 6,
            'obje' => 6
        );

        $this->objectWeight= Array(
            'indi' => Array(
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
        $searchString = $this->simplifyText($searchString);

        $results = Array();

        foreach($this->gedcom->getIndi() as $indi){
            if($names = $indi->getName()){
                foreach($names as $name){
                    $nametxt = $name->getName();
                    $nameWeight = $this->calcMatchWeight($searchString,$nametxt);
                    print "$nametxt: $nameWeight\n";
                }
            }    
        }
        foreach($this->gedcom->getFam() as $fam){

        }
        foreach($this->gedcom->getSour() as $sour){

        }
        foreach($this->gedcom->getObje() as $obje){

        }

        return $results;
    }

    // Make text only have lower case alpha-numeric characters with single spaces between words
    function simplifyText($text){
        // probably need to add an iconv to get rid of accents and stuff
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/','',$text);
        $text = preg_replace('/\s+/',' ',$text);
        return $text;
    }

    // Determine the match weight for the given needle and haystack
    function calcMatchWeight($needle,$haystack){

        $haystack = $this->simplifyText($haystack);

        $highScore = 0;

        // exact match
        if($this->matchWeight['exactWords'] > $highScore){
            if(strpos($haystack,$needle) !== FALSE){
                $highScore = $this->matchWeight['exactWords'];
            }
        }


        // Out of order Exact -- all pieces must match
        // partialExact -- some pieces must match (score multipled by ratio of matches)
        if($this->matchWeight['outOfOrderWords'] > $highScore || $this->matchWeight['partialWords'] > $highScore){
            $needles = explode(' ',$needle);
            if(count($needle) > 1){ // we did a 1 needle search first
                $matches = 0;
                foreach($needles as $needlePiece){
                    if(strpos($haystack,$needlePiece) !== FALSE){
                        $matches++;
                    }
                }
                $ratio = $matches/count($needles);
                if($ratio == 1 && $this->matchWeight['outOfOrderWords'] > $highScore){
                    $highScore = $this->matchWeight['outOfOrderWords'];
                }
                if(($this->matchWeight['partialWords'] * $ratio) > $highScore){
                    $highScore = $this->matchWeight['partialWords'] * $ratio;
                }
            }
        }


        // soundex searches
        $needlePieces = array_map('metaphone',explode(' ',$needle));
        $haystackPieces = array_map('metaphone',explode(' ',$haystack)); 

        // find the index of the first search piece, then check if the subsequent haystake piece matches the 2nd, etc. 
        // repeat until the first search piece is not found
        if($this->matchWeight['exactSoundex'] > $highScore){
            $firstNeedle = $needlePieces[0];
            $partialHaystack = $haystackPieces;
            $soundexFoundex = array_search($firstNeedle,$partialHaystack);
            $foundMatch = FALSE;
            while($soundexFoundex !== FALSE && count($partialHaystack) > 0){
                $foundMatch = TRUE;
                $partialHaystack = array_slice($partialHaystack,$soundexFoundex);
                foreach($needlePieces as $i => $n){
                    if($partialHaystack[$i] != $n){
                        $foundMatch = FALSE;
                        break;
                    }
                }
                if($foundMatch){
                    break;
                }
                $soundexFoundex = array_search($firstNeedle,$partialHaystack);
            }
            if($foundMatch){
                $highScore = $this->matchWeight['exactSoundex'];
            }
        }

        // out of order soundex, partial soundex
        if($this->matchWeight['outOfOrderSoundex'] > $highScore || $this->matchWeight['partialSoundex'] > $highScore){
            $matches = 0;
            foreach($needlePieces as $needlePiece){
                if(array_search($needlePiece,$haystackPieces) !== FALSE){
                    $matches++;
                } 
            }
            $ratio = $matches/count($needlePieces);
            if($ratio == 1 && $this->matchWeight['outOfOrderSoundex'] > $highScore){
                $highScore = $this->matchWeight['outOfOrderSoundex'];
            }
            if(($this->matchWeight['partialSoundex'] * $ratio) > $highScore){
                $highScore = $this->matchWeight['partialSoundex'] * $ratio;
            }
        }

        return $highScore;
    }

    // Return a summary appropriate for search results
    function summary($obj,$callback){
    }
}
