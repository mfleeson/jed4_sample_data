<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

/**
 * Make the word lists (adjectives.json and nouns.json) from the text files downloaded from
 * https://gist.github.com/hugsy/8910dc78d208e40de42deb29e62df913
 */

echo "Adjectives\n";
$adjectives = file('https://gist.github.com/hugsy/8910dc78d208e40de42deb29e62df913/raw/eec99c5597a73f6a9240cab26965a8609fa0f6ea/english-adjectives.txt');
$adjectives = array_map('trim', $adjectives);
asort($adjectives);
file_put_contents(
	__DIR__ . '/../plugins/console/jedsample/data/adjectives.json',
	json_encode(array_values(array_filter($adjectives)))
);

echo "Nouns\n";
$nouns = file('https://gist.github.com/hugsy/8910dc78d208e40de42deb29e62df913/raw/eec99c5597a73f6a9240cab26965a8609fa0f6ea/english-nouns.txt');
$nouns = array_map('trim', $nouns);
asort($nouns);
file_put_contents(
	__DIR__ . '/../plugins/console/jedsample/data/nouns.json',
	json_encode(array_values(array_filter($nouns)))
);

echo "Words\n";
/**
 * Post process the English words list downloaded from
 * https://raw.githubusercontent.com/dwyl/english-words/master/words_dictionary.json
 */
$words = json_decode(file_get_contents('https://raw.githubusercontent.com/dwyl/english-words/master/words_dictionary.json'), true);
$words = array_map('trim', array_keys($words));
asort($words);
file_put_contents(
	__DIR__ . '/../plugins/console/jedsample/data/words.json',
	json_encode(array_values(array_filter($words)))
);
