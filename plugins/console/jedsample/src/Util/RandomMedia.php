<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Util;

class RandomMedia
{
	private array $videos;

	private array $photos;

	public function __construct(
		private RandomWords $random
	)
	{
		$this->videos = json_decode(file_get_contents(__DIR__ . '/../../data/videos.json'));
		$this->photos = json_decode(file_get_contents(__DIR__ . '/../../data/photos.json'));
	}

	public function video(): string
	{
		return $this->random->randomElement($this->videos);
	}

	public function videos(int $count): array
	{
		return $this->randomElements($this->videos, $count);
	}

	public function photo(): string
	{
		return $this->random->randomElement($this->photos);
	}

	public function photos(int $count): array
	{
		return $this->randomElements($this->photos, $count);
	}

	private function randomElements(array &$source, int $count = 1): array
	{
		if ($count <= 0)
		{
			return [];
		}

		if ($count >= count($source))
		{
			return $source;
		}

		if ($count === 1)
		{
			return [$this->random->randomElement($source)];
		}

		$ret   = [];

		while (count($ret) < $count)
		{
			$item = $this->random->randomElement($source);

			if (in_array($item, $ret, true))
			{
				continue;
			}

			$ret[] = $item;
		}

		return $ret;
	}
}