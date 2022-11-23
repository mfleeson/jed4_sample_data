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
	$videos    = $structure->videos;
	unset($structure);

	foreach ($videos as $videoObject)
	{
		foreach ($videoObject->video_files as $videoFile)
		{
			if ($videoFile->quality !== 'sd' && $videoFile->file_type != 'video/mp4')
			{
				continue;
			}

			$ret[] = $videoFile->link;

			break;
		}
	}

	return $ret;
}

$videos = [];
$di     = new DirectoryIterator(__DIR__ . '/videos');

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
	$videos = array_merge($videos, $newVideos);
}

echo sprintf("%s videos found\n", count($videos));

file_put_contents('../plugins/console/jedsample/data/videos.json', json_encode($videos));
