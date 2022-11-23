<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Util;

use DateInterval;
use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\User\User;

class RandomWords
{
	private array $adjectives;

	private array $nouns;

	private array $words;

	public function __construct()
	{
		$this->adjectives = json_decode(file_get_contents(__DIR__ . '/../../data/adjectives.json'));
		$this->nouns      = json_decode(file_get_contents(__DIR__ . '/../../data/nouns.json'));
		$this->words      = json_decode(file_get_contents(__DIR__ . '/../../data/words.json'));
	}

	public function date(int|string|DateTime $from = 0, int|string|DateTime $to = 'now'): Date
	{
		$dateFrom = $this->toDate($from);
		$dateTo   = $this->toDate($to);

		if ($dateTo < $dateFrom)
		{
			return $dateFrom;
		}

		$randomSeconds = random_int(0, $dateTo->getTimestamp() - $dateFrom->getTimestamp());

		return $dateFrom->add(new DateInterval('PT'  . $randomSeconds . 'S'));
	}

	private function toDate(int|float|string|DateTime $from)
	{
		if (is_integer($from) || is_float($from) || is_numeric($from))
		{
			return new Date('@' . $from);
		}

		if (is_string($from))
		{
			return new Date($from);
		}

		return new Date($from->format(\DateTimeInterface::RFC3339));
	}

	public function adjective(): string
	{
		static $count = null;

		$count = $count ?? count($this->adjectives);

		return $this->adjectives[random_int(0, $count - 1)];
	}

	public function bool(float $chancePercent): bool
	{
		$chancePercent = max(0, min($chancePercent, 100));

		if ($chancePercent < 0.001)
		{
			return false;
		}

		if ($chancePercent > 99.999)
		{
			return true;
		}

		return random_int(0, 10000) <= ($chancePercent * 100);
	}

	public function combo()
	{
		return ucfirst($this->adjective()) . ' ' . ucfirst($this->noun());
	}

	public function company(): string
	{
		$suffixes = [
			' Ltd',
			' SàRL',
			' SA',
			' LP',
			' GP',
			' Pty Ltd',
			' Pty',
			' LLC',
			' Corp.',
			' KGaA',
			' GmbH',
			' K/S',
			' e.V.',
			' A.G.',
			' Ky',
			' Oy',
			' Oyj',
			' OOD',
			' SD',
		];

		$suffix = $this->randomElement($suffixes);
		$word   = random_int(0, 1) ? $this->word() : $this->combo();

		return $word . $suffix;
	}

	public function component(): object
	{
		$title = $this->word();
		$slug  = 'com_' . strtolower($title);

		return (object) [
			'title' => $title,
			'slug'  => $slug,
		];
	}

	public function int(int $min, int $max, Bias $bias = Bias::UNBIASED, int $maxRepetitions = 100): int
	{
		$fn = $bias->bias();

		for ($i = 0; $i < $maxRepetitions; $i++)
		{
			$int         = random_int($min, $max);
			$probability = $fn($int / $max);

			if ($this->bool($probability))
			{
				return $int;
			}
		}

		return $int;
	}

	public function module(): object
	{
		$title = $this->word();
		$slug  = 'mod_' . strtolower($title);

		return (object) [
			'title' => $title,
			'slug'  => $slug,
		];
	}

	public function noun(): string
	{
		static $count = null;

		$count = $count ?? count($this->nouns);

		return $this->nouns[random_int(0, $count - 1)];
	}

	public function package(): object
	{
		$title = $this->word();
		$slug  = 'pkg_' . strtolower($title);

		return (object) [
			'title' => $title,
			'slug'  => $slug,
		];
	}

	public function plugin(): object
	{
		$title  = $this->word();
		$folder = $this->pluginFolder();
		$slug   = 'plg_' . str_replace('-', '_', $folder) . '_' . strtolower($title);

		return (object) [
			'title'  => $title,
			'folder' => $folder,
			'slug'   => $slug,
		];
	}

	public function randomElement(array $array): mixed
	{
		$arrayValues = array_values($array);

		return $arrayValues[random_int(0, count($array) - 1)];
	}

	public function user(): User
	{
		$user           = new User();
		$user->name     = $this->combo();
		$user->username = strtolower(str_replace(' ', '.', $user->name));
		$user->email    = $user->username . '@' . $this->emailDomain();

		return $user;
	}

	public function version($maxVersion = '10.9.23'): string
	{
		[$maxVersion, $maxPatch] = explode('-', $maxVersion);
		$versionParts = explode('.', $maxVersion);

		$version = random_int(1, (int) $versionParts[0] ?: 10) . '.' .
			random_int(0, (int) ($versionParts[1] ?? 9)) . '.' .
			random_int(0, (int) ($versionParts[2] ?? 23));

		if (!empty($maxPatch) && $this->bool(10))
		{
			$maxPatch = (int) substr($maxPatch, 1) ?: 5;

			$version .= '-p' . random_int(1, $maxPatch);
		}

		return $version;
	}

	public function word(): string
	{
		static $count = null;

		$count = $count ?? count($this->words);

		return $this->words[random_int(0, $count - 1)];
	}

	private function emailDomain(): string
	{
		static $domains = [
			'example.com',
			'example.net',
			'example.org',
		];

		return $this->randomElement($domains);
	}

	private function pluginFolder()
	{
		static $folders = [
			'actionlog',
			'api-authentication',
			'authentication',
			'behaviour',
			'captcha',
			'content',
			'editors',
			'editors-xtd',
			'extension',
			'fields',
			'filesystem',
			'finder',
			'installer',
			'media-action',
			'multifactorauth',
			'privacy',
			'quickicon',
			'sampledata',
			'system',
			'task',
			'user',
			'webservices',
			'workflow',
		];

		return $this->randomElement($folders);
	}
}