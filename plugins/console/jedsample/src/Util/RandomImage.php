<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Util;

/**
 * @see https://github.com/theolepage/php-random-avatars
 */
class RandomImage
{
	private $arraypreset;

	private $color1;

	private $color2;

	private $image;

	private $image_location;

	private $preset;

	//List of colors (from http://flatuicolors.com/) and presets

	private $presets = [
		'------*-*---*---*-*--*-*-', '******---**-*-**---******', '*-----*----***----*-----*',
		'------***---*---***------', '--*--**-**--*--*****--*--', '-------*---***---*-------',
		'------*-*-*-*-*-*-*-*---*', '-----------*-*--*-*--***-', '-----*----*-*--*-----***-',
		'------*-*-*****----------',
	];

	private $primary_colors = ['#2ecc71', '#3498db', '#e74c3c', '#f39c12', '#1abc9c', '#9b59b6'];

	private $secondary_colors = ['#34495e', '#ecf0f1', '#95a5a6'];

	//Functions to convert # colors to RGB

	public function checkPresetIsUnique()
	{
		$max_try = 200;
		$try     = 0;
		while ($this->isPresetAlreadyUsed($this->preset) && $try <= $max_try)
		{
			$this->generate();
			$try++;
			if ($try == $max_try)
			{
				echo 'Error : All possible presets are already registered in the database !';
				exit;
			}
		}
	}

	public function draw()
	{
		//Create image (5px x 5px) and define RGB colors
		$image      = imagecreate(5, 5);
		$rgb_color1 = imagecolorallocate($image, $this->getRed($this->color1), $this->getGreen($this->color1), $this->getBlue($this->color1));
		$rgb_color2 = imagecolorallocate($image, $this->getRed($this->color2), $this->getGreen($this->color2), $this->getBlue($this->color2));

		//Parse $newpreset to write a pixel with the specified color
		$x = 0;
		$y = 0;
		$i = 0;
		while ($y != 5)
		{
			if ($this->arraypreset[$i] == $this->color1)
			{
				ImageSetPixel($image, $x, $y, $rgb_color1);
			}
			elseif ($this->arraypreset[$i] == $this->color2)
			{
				ImageSetPixel($image, $x, $y, $rgb_color2);
			}
			$x++;
			$i++;
			if ($x == 5)
			{
				$x = 0;
				$y++;
			}
		}

		//Resizing $image (5px x 5px) to $image_resized (320px x 320px)
		$image_resized = imagecreate(320, 320);
		imagecopyresampled($image_resized, $image, 0, 0, 0, 0, 320, 320, 5, 5);
		$this->image = $image_resized;
	}

	public function generate()
	{
		//Choose randomly color1, color2 and a preset
		$this->color1 = $this->pickrandom($this->primary_colors);
		$this->color2 = $this->pickrandom($this->secondary_colors);
		$preset       = $this->pickrandom($this->presets);

		//Parse $preset to replace with color1 or color2
		$newpreset[''] = null;
		$i             = 0;
		while ($i < 25)
		{
			if (substr($preset, $i, 1) == "*")
			{
				$newpreset[] = $this->color1;
			}
			elseif (substr($preset, $i, 1) == "-")
			{
				$newpreset[] = $this->color2;
			}
			$i++;
		}
		$this->arraypreset = $newpreset;
		$this->preset      = implode("", $newpreset);
	}

	//Function to randomly take an item in a list

	public function isPresetAlreadyUsed($preset)
	{
		return false;
	}

	public function saveImage($dirname, $filename)
	{
		if (!file_exists($dirname))
		{
			mkdir($dirname, 0777, true);
		}
		imagepng($this->image, $dirname . '/' . $filename);
		$this->image_location = $dirname . '/' . $filename;
	}

	public function show($tag)
	{
		$tag = str_replace('<img', '<img src="' . $this->image_location . '"', $tag);
		echo $tag;
	}

	private function getBlue($color)
	{
		$color = substr($color, 1);
		$blue  = substr($color, 4, 2);

		return hexdec($blue);
	}

	private function getGreen($color)
	{
		$color = substr($color, 1);
		$green = substr($color, 2, 2);

		return hexdec($green);
	}

	private function getRed($color)
	{
		$color = substr($color, 1);
		$red   = substr($color, 0, 2);

		return hexdec($red);
	}

	private function pickrandom($data)
	{
		$number = rand(0, count($data) - 1);

		return $data[$number];
	}
}
