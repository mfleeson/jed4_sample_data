<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

function parseFile(string $jsonPath): array
{
	$ret = [];

	$json      = file_get_contents($jsonPath);
	$structure = json_decode($json);
	$photos    = $structure->photos;
	unset($structure);

	foreach ($photos as $photoObject)
	{
		$ret[] = $photoObject->src->large;
	}

	return $ret;
}

$photos = [];
$di     = new DirectoryIterator(__DIR__ . '/photos');

/** @var DirectoryIterator $item */
foreach ($di as $item)
{
	if (!$item->isFile())
	{
		continue;
	}

	if ($item->getExtension() != 'json')
	{
		continue;
	}

	echo $item->getPathname() . "\n";

	$newVideos = parseFile($item->getPathname());
	$photos    = array_merge($photos, $newVideos);
}

echo sprintf("%s photos found\n", count($photos));

file_put_contents('../plugins/console/jedsample/data/photos.json', json_encode($photos));
