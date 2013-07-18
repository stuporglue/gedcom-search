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
    var $typeWeight;
    var $matchWeight;

    function __construct($gedcom,){

        $this->matchWeight   = Array(
            'exact' => 10,
            'exactAlpha' => 9,
            'exactSoundex' => 8,
            'partialExact' => 6,
            'partialSoundex' => 5,
        );

        $this->typeWeight = Array(
            'indi' = Array('weight' => 10,
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
        'fam' => Array('wight' => 8,),
        'sour' => Array('weight' => 6),
        'media' => Array('weight' => 6),
    );

        $this->gedcom = // parse gedcom here

    }

    // Run one search
    function search($searchString){
    }

    // Return a summary appropriate for search results
    function summary($obj,$callback){
    }
}

// Search Match        Exact   exactAlpha  exactSoundex    partialExact    partialSoundex          
//         '== preg_match('/[^a-zA-Z]/','')    soundex()   array_intersect(explode(' ',$name),explode($search))    array_intersect(array_map('soundex',explode(' ',$name)),explode($search))           
//         10  9   8   6   5           
//                                     
//                                     
//                                     
//                                     
// Object Type     Indi (10)       Family  8   Sources 6   Media   6
//         10  Name    10  Spouse Names    10  Source note?    10  File name
//         8   Note    8   Children Names          8   Note
//         6   Event Note  6   Note                
//         6   Ordinance Note  5   Event Note              
//         3   Event Place 5   Ordinance Note              
//         3   Ordinance Place 3   Event Place             
//         2   Event Date  3   Ordinance Place             
//         2   Ordinance Date  2   Event Date              
//                 2   Ordinance Date              
// 
