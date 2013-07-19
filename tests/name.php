<?php

require_once(__DIR__ . '/../gedcomsearch.php');

$search = new GedcomSearch(__DIR__ . '/family.ged');
$search->search('howard');
