<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command\Traits;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Throwable;

/**
 * Detailed error reporting
 *
 * @since  1.0.0
 */
trait ErrorReportingTrait
{
	protected function reportError(Throwable $e, array $additional = [])
	{
		// Basic information on the exception
		$messages = [
			Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_ERROR'),
			str_repeat('=', 80),
			'',
			$e->getCode() . ' -- ' . $e->getMessage(),
			$e->getFile() . '(' . $e->getLine() . ')',
			'',
		];

		// Debug trace
		$messages = array_merge($messages, explode("\n", $e->getTraceAsString()));

		// Additional information
		if (!empty($additional))
		{
			$messages[] = '';
			$messages[] = Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_ERROR_ADDITIONAL');
			$messages[] = '';

			foreach ($additional as $k => $v)
			{
				$messages[] = '$' . $k;
				$messages[] = str_repeat('-', 80);
				$messages[] = print_r($v, true);
			}
		}

		// Include basic and debug trace information for all nested exceptions
		while ($e = $e->getPrevious())
		{
			if (empty($e)) {
				break;
			}

			$messages[] = '';
			$messages[] = Text::_('PLG_CONSOLE_JEDSAMPLE_COMMON_ERROR_PREVIOUS');
			$messages[] = str_repeat('=', 80);
			$messages[] = '';
			$messages[] = $e->getCode() . ' -- ' . $e->getMessage();
			$messages[] = $e->getFile() . '(' . $e->getLine() . ')';
			$messages = array_merge($messages, explode("\n", $e->getTraceAsString()));
		}

		// Display the error
		$this->ioStyle->error($messages);
	}
}