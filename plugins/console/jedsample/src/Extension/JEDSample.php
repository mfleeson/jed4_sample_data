<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Extension;

use Joomla\Application\AbstractApplication;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\JEDSample\Command\CreateCategories;
use Joomla\Plugin\Console\JEDSample\Command\CreateUsers;
use Throwable;

class JEDSample extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	private static $commands = [
		CreateCategories::class,
		CreateUsers::class,
	];

	public static function getSubscribedEvents(): array
	{
		return [
			ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
		];
	}

	public function registerCLICommands(ApplicationEvent $event)
	{
		$this->loadLanguage();

		// Load the Composer autoloader
		$filePath = JPATH_PLUGINS . '/console/jedsample/vendor/autoload.php';

		if (!file_exists($filePath))
		{
			return;
		}

		require_once $filePath;

		// Register the commands
		foreach (self::$commands as $commandFQN)
		{
			try
			{
				if (!class_exists($commandFQN))
				{
					continue;
				}

				/** @var AbstractApplication|DatabaseAwareInterface $command */
				$command = new $commandFQN();
				$command->setDatabase($this->getDatabase());

				$this->getApplication()->addCommand($command);
			}
			catch (Throwable $e)
			{
				continue;
			}
		}
	}
}