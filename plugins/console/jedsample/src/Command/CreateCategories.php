<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command;

use Joomla\CMS\Language\Text;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\CategoryTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ConfigureIOTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\ErrorReportingTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\MemoryInfoTrait;
use Joomla\Plugin\Console\JEDSample\Command\Traits\TimeInfoTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class CreateCategories extends AbstractCommand
{
	use ConfigureIOTrait;
	use MemoryInfoTrait;
	use TimeInfoTrait;
	use ErrorReportingTrait;
	use DatabaseAwareTrait;
	use CategoryTrait;

	/**
	 * The default command name
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected static $defaultName = 'jed4:create:categories';

	protected function configure(): void
	{
		$this->setDescription(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_CATEGORIES_DESC'));
		$this->setHelp(Text::_('PLG_CONSOLE_JEDSAMPLE_CREATE_CATEGORIES_HELP'));
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		// region Boilerplate
		$this->configureSymfonyIO($input, $output);
		$this->getDatabase()->setMonitor(null);
		$this->getApplication()->set('mailonline', 0);
		// endregion

		try
		{
			$this->categoriesComponent = $this->getApplication()
			                                  ->bootComponent('com_categories');

			$this->eraseCategories('com_jed');
			$this->makeCategories('com_jed');
		}
		catch (Throwable $e)
		{
			$this->reportError($e);

			return 255;
		}

		$this->ioStyle->success(Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_DONE'));

		return 0;
	}
}