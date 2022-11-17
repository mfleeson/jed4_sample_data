<?php
/**
 * @package   JEDSample
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2, or later
 */

/**
 * Generate the categories.json file
 *
 * The categories.xml file was extracted from the JED4 demo installation which had imported the data from
 * JED3.
 */

$xml = new SimpleXMLElement(file_get_contents('categories.xml'));

$categories = [];
$category   = [
	'id'     => null,
	'title'  => null,
	'parent' => 0,
	'count'  => 0,
];

// Loop through all top level category containers
foreach ($xml->xpath('//div[@class=\'jed-home-item-view\']') as $container)
{
	// Parse the top-level category
	$allAnchors = $container->xpath('.//h4[@class=\'jed-home-category-title\']/a');
	$spans      = $container->xpath('.//span[contains(@class, \'jed-home-category-icon-numitems\')]');
	$href       = (string) $allAnchors[0]['href'];
	$query      = parse_url($href, PHP_URL_QUERY);
	parse_str($query, $params);

	$parentCatId        = $params['catid'];

	$categories[$parentCatId] = [
		'id'     => $parentCatId,
		'title'  => trim((string) $allAnchors[0]),
		'parent' => 0,
		'count'  => (int) $spans[0],
	];

	unset ($allAnchors, $spans, $href, $query, $params);

	// Loop through all subcategories
	foreach ($container->xpath('.//li[contains(@class, \'jed-home-subcategories-child\')]') as $subCategory)
	{
		$allAnchors = $subCategory->xpath('.//a');
		$spans      = $subCategory->xpath('.//span[contains(@class, \'badge-info\')]');
		$href       = (string) $allAnchors[0]['href'];
		$query      = parse_url($href, PHP_URL_QUERY);
		parse_str($query, $params);

		$thisCatId        = $params['catid'];

		if (isset($categories[$thisCatId]))
		{
			continue;
		}

		$categories[$thisCatId] = [
			'id'     => $thisCatId,
			'title'  => trim((string) $allAnchors[0]),
			'parent' => $parentCatId,
			'count'  => (int) $spans[0],
		];
	}
}

file_put_contents('../plugins/console/jedsample/data/categories.json', json_encode($categories));