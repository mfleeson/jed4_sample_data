<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

namespace Joomla\Plugin\Console\JEDSample\Command\Traits;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Extension\Component;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use RuntimeException;

trait CategoryTrait
{
	/**
	 * The com_categories component object
	 *
	 * @since 1.0.0
	 * @var   Component|MVCFactoryInterface
	 */
	protected Component|MVCFactoryInterface $categoriesComponent;

	/**
	 * Erase existing categories for a component
	 *
	 * @param   string  $component  The component we're deleting categories for
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	protected function eraseCategories(string $component): void
	{
		$this->ioStyle->writeln('Deleting existing contact categories');
		$rootCategory = $this->getCategoriesRoot();

		if ($rootCategory === null)
		{
			return;
		}

		// This gets all categories, regardless of their depth
		$catIDs = $this->getAllChildrenCategoryIDs($rootCategory, $component);

		$this->ioStyle->progressStart(count($catIDs));

		foreach ($catIDs as $catId)
		{
			$this->deleteCategory($catId);
			$this->ioStyle->progressAdvance();
		}

		$this->ioStyle->progressFinish();
	}

	/**
	 * Create categories for the given component
	 *
	 * @param   string  $component The component's name
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	protected function makeCategories(string $component): void
	{
		$this->categoriesComponent ??= $this->getApplication()->bootComponent('com_categories');

		// Load the categories from the JSON file
		$categories = json_decode(
			file_get_contents(__DIR__ . '/../../../data/categories.json'),
			true
		);

		$progress = $this->ioStyle->createProgressBar(count($categories));

		foreach ($categories as $info)
		{
			$progress->setMessage(
				sprintf(
					'%u - %s',
					$info['id'],
					$info['title']
				)
			);
			$progress->display();

			/** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $model */
			$model = $this->categoriesComponent->getMVCFactory()->createModel('Category', 'Administrator');

			$parent = $model->getItem($info['parent']);

			$data = [
				'parent_id'       => $info['parent'],
				'level'           => $parent->level + 1,
				'extension'       => $component,
				'title'           => $info['title'],
				'alias'           => ApplicationHelper::stringURLSafe($info['title'], 'en-GB'),
				'description'     => '',
				'access'          => 1,
				'params'          => ['target' => '', 'image' => ''],
				'metadata'        => ['page_title' => '', 'author' => '', 'robots' => '', 'tags' => ''],
				'hits'            => 0,
				'language'        => '*',
				'associations'    => [],
				'published'       => 1,
				// TODO Set up a creator user ID?
				'created_user_id' => 42,
			];

			if (!$model->save($data))
			{
				throw new RuntimeException($model->getError());
			}

			$wrongId = $model->getState($model->getName() . '.id');

			if ($wrongId === null)
			{
				die('I should have never taken that left turn in Albuquerque.');
			}

			$model->setState($model->getName() . '.id', null);

			$db = $this->getDatabase();
			$query = $db->getQuery(true)
				->update($db->quoteName('#__categories'))
				->set($db->quoteName('id') . ' = :id')
				->where($db->quoteName('id') . ' = :wrongId')
				->bind(':id', $info['id'], ParameterType::INTEGER)
				->bind(':wrongId', $wrongId, ParameterType::INTEGER);
			$db->setQuery($query)->execute();

			$name = $component . '.category.' . $info['id'];
			$wrongName = $component . '.category.' . $wrongId;
			$query = $db->getQuery(true)
			            ->update($db->quoteName('#__assets'))
			            ->set($db->quoteName('name') . ' = :name')
			            ->where($db->quoteName('name') . ' = :wrongName')
			            ->bind(':name', $name, ParameterType::STRING)
			            ->bind(':wrongName', $wrongName, ParameterType::STRING);
			$db->setQuery($query)->execute();

			$progress->advance();
		}

		$progress->finish();
	}

	/**
	 * Deletes a category
	 *
	 * @param   int  $catID  THe category to delete
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function deleteCategory(int $catID): void
	{
		$this->categoriesComponent ??= $this->getApplication()->bootComponent('com_categories');

		$pks = [$catID];

		/** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $model */
		$model = $this->categoriesComponent->getMVCFactory()->createModel('Category', 'Administrator');

		$table = $model->getTable();

		if (!$table->load($catID))
		{
			throw new RuntimeException(
				sprintf('Cannot load category %u: %s', $catID, $table->getError())
			);
		}

		if (!$table->delete($catID))
		{
			throw new RuntimeException(
				sprintf('Cannot delete category %u: %s', $catID, $table->getError())
			);
		}
	}

	/**
	 * Get all the content categories that are children (infinite levels deep) of the root category.
	 *
	 * @param   int  $catID  Root category ID
	 *
	 * @return  array  Children category IDs, ordered from leaf nodes down (leaves first, immediate root children last)
	 *
	 * @since   1.0.0
	 */
	private function getAllChildrenCategoryIDs(int $catID, ?string $component = null): array
	{
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		// First, I need the lft and rgt of my root category
		$query = $db->getQuery(true)
		            ->select([
			            $db->qn('lft'),
			            $db->qn('rgt'),
		            ])
		            ->from($db->qn('#__categories'))
		            ->where($db->qn('id') . ' = ' . $db->q($catID));

		$rootInfo = $db->setQuery($query)->loadAssoc();

		if (empty($rootInfo))
		{
			throw new RuntimeException(sprintf("Could not retrieve information for category %d", $catID));
		}

		/**
		 * Now, I can find the IDs of the subtree nodes.
		 *
		 * Make sure to filter for extension because the root node contains everything, not just com_content.
		 *
		 * Categories are returned in descending level order. This way the category nuking will go from the leaf nodes
		 * towards the root nodes. It wouldn't work the other way around since we'd be trying to delete a non-empty
		 * category.
		 */
		$query = $db->getQuery(true)
		            ->select($db->qn('id'))
		            ->from($db->qn('#__categories'))
		            ->where($db->quoteName('lft') . ' > :lft')
		            ->where($db->quoteName('rgt') . ' < :rgt')
		            ->order($db->quoteName('level') . ' DESC')
		            ->bind(':lft', $rootInfo['lft'])
		            ->bind(':rgt', $rootInfo['rgt']);

		if ($component !== null)
		{
			$query->where($db->quoteName('extension') . ' = :component')
			      ->bind(':component', $component, ParameterType::STRING);
		}

		return $db->setQuery($query)->loadColumn() ?? [];
	}

	/**
	 * Get the ID of the root category.
	 *
	 * @return  int|null
	 *
	 * @since   1.0.0
	 */
	private function getCategoriesRoot(): ?int
	{
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select($db->quoteName('id'))
		            ->from($db->quoteName('#__categories'))
		            ->where($db->quoteName('parent_id') . ' = ' . $db->q(0))
		            ->where($db->quoteName('level') . ' = ' . $db->q(0));

		return $db->setQuery($query)->loadResult() ?? null;
	}

}