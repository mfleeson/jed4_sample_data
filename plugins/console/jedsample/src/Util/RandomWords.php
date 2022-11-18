<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Util;

use Joomla\CMS\User\User;

class RandomWords
{
	private array $adjectives;

	private array $nouns;

	private array $words;

	public function __construct()
	{
		$this->init();
	}

	public function randomElement(array $array): mixed
	{
		$arrayValues = array_values($array);

		return $arrayValues[random_int(0, count($array) - 1)];
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

	public function adjective(): string
	{
		static $count = null;

		$count = $count ?? count($this->adjectives);

		return $this->adjectives[random_int(0, $count - 1)];
	}

	public function combo()
	{
		return ucfirst($this->adjective()) . ' ' . ucfirst($this->noun());
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

	public function user(): User
	{
		$user           = new User();
		$user->name     = $this->combo();
		$user->username = strtolower(str_replace(' ', '.', $user->name));
		$user->email    = $user->username . '@' . $this->emailDomain();

		return $user;
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

	private function init()
	{
		static $hasInitialised = false;

		if ($hasInitialised)
		{
			return;
		}

		$hasInitialised = true;

		$this->adjectives = json_decode(file_get_contents(__DIR__ . '/../../data/adjectives.json'));
		$this->nouns      = json_decode(file_get_contents(__DIR__ . '/../../data/nouns.json'));
		$this->words      = json_decode(file_get_contents(__DIR__ . '/../../data/words.json'));
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