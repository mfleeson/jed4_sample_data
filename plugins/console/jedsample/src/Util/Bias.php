<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Util;

enum Bias
{
	/** Unbiased distribution */
	case UNBIASED;

	/** Favour low numbers linearly */
	case LINEAR_LOW;

	/** Favour high numbers linearly */
	case LINEAR_HIGH;

	/** Favour low numbers exponentially */
	case EXP_LOW;

	/** Favour high numbers exponentially */
	case EXP_HIGH;

	public function bias(): callable
	{
		return match ($this)
		{
			self::UNBIASED => fn(float $x): float => 1,

			self::LINEAR_LOW => fn(float $x): float => 1 - $x,

			self::LINEAR_HIGH => fn(float $x): float => $x,

			self::EXP_HIGH => fn(float $x): float => sqrt($x),

			self::EXP_LOW => fn(float $x): float => 1 - sqrt($x),
		};
	}
}