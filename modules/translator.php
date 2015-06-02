<?php

/**
 * Module Name: Translator
 * Module Description: Allow translation of Jetpack from inside WordPress
 * First Introduced: x.x
 * Requires Connection: Yes
 * Auto Activate: No
 * Module Tags: Translation, i18n
 * Sort Order: 30
 */

function jetpack_load_translator() {
	include dirname( __FILE__ ) . "/translator/translator.php";
}

jetpack_load_translator();
