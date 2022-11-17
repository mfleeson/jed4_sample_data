<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command\Traits;

defined('_JEXEC') || die;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Set up the Symfony I/O objects
 *
 * @since  1.0.0
 */
trait ConfigureIOTrait
{
	/**
	 * @var   SymfonyStyle
	 * @since 1.0.0
	 */
	private $ioStyle;

	/**
	 * @var   InputInterface
	 * @since 1.0.0
	 */
	private $cliInput;

	/**
	 * Configure the IO.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function configureSymfonyIO(InputInterface $input, OutputInterface $output)
	{
		$this->cliInput = $input;
		$this->ioStyle  = new SymfonyStyle($input, $output);
	}

}
