<?php
/**
 * Global functions and constants for Semantic Drilldown.
 *
 * @author Yaron Koren
 */

if (!defined('MEDIAWIKI')) die();

define('SD_VERSION','0.5.1');

// constants for special properties
define('SD_SP_HAS_FILTER', 1);
define('SD_SP_COVERS_PROPERTY', 2);
define('SD_SP_HAS_VALUE', 3);
define('SD_SP_GETS_VALUES_FROM_CATEGORY', 4);
define('SD_SP_USES_TIME_PERIOD', 5);
define('SD_SP_REQUIRES_FILTER', 6);
define('SD_SP_HAS_LABEL', 7);
define('SD_SP_HAS_DRILLDOWN_TITLE', 8);
define('SD_SP_HAS_INPUT_TYPE', 9);

$wgExtensionCredits['specialpage'][]= array(
	'name'	=> 'Semantic Drilldown',
	'version'     => SD_VERSION,
	'author'      => 'Yaron Koren',
	'url'         => 'http://www.mediawiki.org/wiki/Extension:Semantic_Drilldown',
	'description' =>  'A drilldown interface for navigating through semantic data',
	'descriptionmsg'  => 'semanticdrilldown-desc',
);

require_once($sdgIP . '/languages/SD_Language.php');

$wgExtensionMessagesFiles['SemanticDrilldown'] = $sdgIP . '/languages/SD_Messages.php';
$wgExtensionAliasesFiles['SemanticDrilldown'] = $sdgIP . '/languages/SD_Aliases.php';

$wgHooks['smwInitProperties'][] = 'sdfInitProperties';

// register all special pages and other classes
$wgSpecialPages['Filters'] = 'SDFilters';
$wgSpecialPageGroups['Filters'] = 'users';
$wgAutoloadClasses['SDFilters'] = $sdgIP . '/specials/SD_Filters.php';
$wgSpecialPageGroups['Filters'] = 'sd_group';
$wgSpecialPages['CreateFilter'] = 'SDCreateFilter';
$wgSpecialPageGroups['CreateFilter'] = 'users';
$wgAutoloadClasses['SDCreateFilter'] = $sdgIP . '/specials/SD_CreateFilter.php';
$wgSpecialPageGroups['CreateFilter'] = 'sd_group';
$wgSpecialPages['BrowseData'] = 'SDBrowseData';
$wgSpecialPageGroups['BrowseData'] = 'users';
$wgAutoloadClasses['SDBrowseData'] = $sdgIP . '/specials/SD_BrowseData.php';
$wgSpecialPageGroups['BrowseData'] = 'sd_group';

$wgAutoloadClasses['SDFilter'] = $sdgIP . '/includes/SD_Filter.php';
$wgAutoloadClasses['SDFilterValue'] = $sdgIP . '/includes/SD_FilterValue.php';
$wgAutoloadClasses['SDAppliedFilter'] = $sdgIP . '/includes/SD_AppliedFilter.php';

/**********************************************/
/***** namespace settings                 *****/
/**********************************************/

/**
 * Init the additional namespaces used by Semantic Drilldown.
 */
function sdfInitNamespaces() {
	global $sdgNamespaceIndex, $wgExtraNamespaces, $wgNamespaceAliases, $wgNamespacesWithSubpages, $smwgNamespacesWithSemanticLinks;
	global $wgLanguageCode, $sdgContLang;

	if (!isset($sdgNamespaceIndex)) {
		$sdgNamespaceIndex = 170;
	}

	define('SD_NS_FILTER',       $sdgNamespaceIndex);
	define('SD_NS_FILTER_TALK',  $sdgNamespaceIndex+1);

	sdfInitContentLanguage($wgLanguageCode);

	// Register namespace identifiers
	if (!is_array($wgExtraNamespaces)) { $wgExtraNamespaces=array(); }
	$wgExtraNamespaces = $wgExtraNamespaces + $sdgContLang->getNamespaces();
	$wgNamespaceAliases = $wgNamespaceAliases + $sdgContLang->getNamespaceAliases();

	// Support subpages only for talk pages by default
	$wgNamespacesWithSubpages = $wgNamespacesWithSubpages + array(
		SD_NS_FILTER_TALK => true
	);

	// Enable semantic links on filter pages
	$smwgNamespacesWithSemanticLinks = $smwgNamespacesWithSemanticLinks + array(
		SD_NS_FILTER => true,
		SD_NS_FILTER_TALK => false
	);
}

/**********************************************/
/***** language settings                  *****/
/**********************************************/

/**
 * Initialise a global language object for content language. This
 * must happen early on, even before user language is known, to
 * determine labels for additional namespaces. In contrast, messages
 * can be initialised much later when they are actually needed.
 */
function sdfInitContentLanguage($langcode) {
	global $sdgIP, $sdgContLang;

	if (!empty($sdgContLang)) { return; }

	$sdContLangClass = 'SD_Language' . str_replace( '-', '_', ucfirst( $langcode ) );

	if (file_exists($sdgIP . '/languages/'. $sdContLangClass . '.php')) {
		include_once( $sdgIP . '/languages/'. $sdContLangClass . '.php' );
	}

	// fallback if language not supported
	if ( !class_exists($sdContLangClass)) {
		include_once($sdgIP . '/languages/SD_LanguageEn.php');
		$sdContLangClass = 'SD_LanguageEn';
	}

	$sdgContLang = new $sdContLangClass();
}

/**
 * Initialise the global language object for user language. This
 * must happen after the content language was initialised, since
 * this language is used as a fallback.
 */
function sdfInitUserLanguage($langcode) {
	global $sdgIP, $sdgLang;

	if (!empty($sdgLang)) { return; }

	$sdLangClass = 'SD_Language' . str_replace( '-', '_', ucfirst( $langcode ) );
	if (file_exists($sdgIP . '/languages/'. $sdLangClass . '.php')) {
		include_once( $sdgIP . '/languages/'. $sdLangClass . '.php' );
	}

	// fallback if language not supported
	if ( !class_exists($sdLangClass)) {
		global $sdgContLang;
		$sdgLang = $sdgContLang;
	} else {
		$sdgLang = new $sdLangClass();
	}
}

/**
 * Setting of message cache for versions of MediaWiki that do not support
 * wgExtensionMessagesFiles - based on ceContributionScores() in
 * ContributionScores extension
 */
function sdfLoadMessagesManually() {
	global $sdgIP, $wgMessageCache;

	# add messages
	require($sdgIP . '/languages/SD_Messages.php');
	foreach($messages as $key => $value) {
		$wgMessageCache->addMessages($messages[$key], $key);
	}
}

/**********************************************/
/***** other global helpers               *****/
/**********************************************/

function sdfInitProperties() {
	global $sdgContLang;
	$sd_props = $sdgContLang->getSpecialPropertiesArray();
	SMWPropertyValue::registerProperty('_SD_F', '_wpg', $sd_props[SD_SP_HAS_FILTER], true);
	SMWPropertyValue::registerProperty('_SD_CP', '_wpp', $sd_props[SD_SP_COVERS_PROPERTY], true);
	SMWPropertyValue::registerProperty('_SD_V', '_str', $sd_props[SD_SP_HAS_VALUE], true);
	SMWPropertyValue::registerProperty('_SD_VC', '_wpc', $sd_props[SD_SP_GETS_VALUES_FROM_CATEGORY], true);
	SMWPropertyValue::registerProperty('_SD_TP', '_str', $sd_props[SD_SP_USES_TIME_PERIOD], true);
	SMWPropertyValue::registerProperty('_SD_IT', '_str', $sd_props[SD_SP_HAS_INPUT_TYPE], true);
	SMWPropertyValue::registerProperty('_SD_RF', '_wpg', $sd_props[SD_SP_REQUIRES_FILTER], true);
	SMWPropertyValue::registerProperty('_SD_L', '_str', $sd_props[SD_SP_HAS_LABEL], true);
	SMWPropertyValue::registerProperty('_SD_DT', '_str', $sd_props[SD_SP_HAS_DRILLDOWN_TITLE], true);

        return true;
}

/**
 * Based on Semantic Forms' sffCreateProperty()
 */
function sdfCreateProperty($property_name) {
        if (method_exists('SMWPropertyValue', 'makeProperty'))
                return SMWPropertyValue::makeProperty($property_name);
        else
                return Title::newFromText($property_name, SMW_NS_PROPERTY);
}

/**
 * Based on Semantic Forms' sffGetPropertyName()
 */
function sdfGetPropertyName($property) {
	if ($property instanceof Title)
		return $property->getText();
	else // $property instanceof SMWPropertyValue
		return $property->getWikiValue();
}

/**
 * Gets a list of the names of all categories in the wiki that aren't
 * children of some other category
 */
function sdfGetTopLevelCategories() {
	$categories = array();
	$dbr = wfGetDB( DB_SLAVE );
	extract($dbr->tableNames('page', 'categorylinks'));
	$cat_ns = NS_CATEGORY;
	$sql = "SELECT page_title FROM $page p LEFT OUTER JOIN $categorylinks cl ON p.page_id = cl.cl_from WHERE p.page_namespace = $cat_ns AND cl.cl_to IS NULL";
	$res = $dbr->query($sql);
	if ($dbr->numRows( $res ) > 0) {
		while ($row = $dbr->fetchRow($res)) {
			$categories[] = str_replace('_', ' ', $row[0]);
		}
	}
	$dbr->freeResult($res);
	return $categories;
}

/**
 * Gets a list of the names of all properties in the wiki
 */
function sdfGetSemanticProperties() {
	global $smwgContLang;
	$smw_namespace_labels = $smwgContLang->getNamespaces();
	$all_properties = array();

	// set limit on results - a temporary fix until SMW's getProperties()
	// functions stop requiring a limit
	global $smwgIP;
	include_once($smwgIP . '/includes/storage/SMW_Store.php');
	$options = new SMWRequestOptions();
	$options->limit = 10000;
	$used_properties = smwfGetStore()->getPropertiesSpecial($options);
	foreach ($used_properties as $property) {
		$property_name = sdfGetPropertyName($property[0]);
		$all_properties[$property_name] = $smw_namespace_labels[SMW_NS_PROPERTY];
	}
	$unused_properties = smwfGetStore()->getUnusedPropertiesSpecial($options);
	foreach ($unused_properties as $property) {
		$property_name = sdfGetPropertyName($property);
		$all_properties[$property_name] = $smw_namespace_labels[SMW_NS_PROPERTY];
	}
	// remove the special properties of Semantic Drilldown from this list...
	global $sdgContLang;
	$sd_props = $sdgContLang->getSpecialPropertiesArray();
	$sd_prop_aliases = $sdgContLang->getSpecialPropertyAliases();
	foreach (array_keys($all_properties) as $prop_name) {
		foreach ($sd_props as $prop => $label) {
			if ($prop_name == $label) {
				unset($all_properties[$prop_name]);
			}
		}
		foreach ($sd_prop_aliases as $alias => $cur_prop) {
			if ($prop_name == $alias) {
				unset($all_properties[$prop_name]);
			}
		}
	}

	// sort properties array by the key, which is the property name
	ksort($all_properties);
	return $all_properties;
}

/**
 *
 */
function sdfGetFilters() {
	$filters = array();
	$filter_ns = SD_NS_FILTER;
	$dbr = wfGetDB( DB_SLAVE );
	$page = $dbr->tableName( 'page' );
	$sql = "SELECT page_title FROM $page
		WHERE page_namespace = $filter_ns";
	$res = $dbr->query($sql);
	while ($row = $dbr->fetchRow($res)) {
		$filters[] = $row[0];
	}
	$dbr->freeResult($res);
	return $filters;
}

/**
 * Generic database-access function - gets all the values that a specific
 * page points to with a specific property, that also match some other
 * constraints
 */
function sdfGetValuesForProperty($subject, $subject_namespace, $special_prop, $prop, $object_namespace) {
	$store = smwfGetStore();
	$subject_title = Title::newFromText($subject, $subject_namespace);

        // we can do this easily if we're using SMW 1.4 or higher
        if (class_exists('SMWPropertyValue')) {
                $property = SMWPropertyValue::makeProperty($special_prop);
                $res = $store->getPropertyValues($subject_title, $property);
                // there could be multiple alternate forms
                $values = array();
                foreach ($res as $prop_val) {
			$values[] = html_entity_decode(str_replace('_', ' ', $prop_val->getXSDValue()));
                }
                return $values;
        }

	// otherwise, it's a bit more complicated
	global $sdgContLang;

	$sd_props = $sdgContLang->getSpecialPropertiesArray();
	$values = array();
	if (array_key_exists($prop, $sd_props)) {
		$property = $sd_props[$prop];
	} else {
		$property = "";
	}
	if ($property != '') {
		$prop = sdfCreateProperty($property, SMW_NS_PROPERTY);
		$prop_vals = $store->getPropertyValues($subject_title, $prop);
		foreach ($prop_vals as $prop_val) {
			// html_entity_decode() is needed to get around temporary bug in SMWSQLStore2
			$values[] = html_entity_decode(str_replace('_', ' ', $prop_val->getXSDValue()));
		}
	}
	// try aliases as well
	foreach ($sdgContLang->getSpecialPropertyAliases() as $alias => $cur_prop) {
		// make sure alias doesn't match actual property name - this
		// is an issue for English, since the English-language values
		// are used for aliases
		if (($alias != $property) && (! $prop instanceof Title) && ($cur_prop == $prop)) {
			$prop = sdfCreateProperty($alias, SMW_NS_PROPERTY);
			$prop_vals = $store->getPropertyValues($subject_title, $prop);
			foreach ($prop_vals as $prop_val) {
				// make sure it's in the right namespace
				if ($prop_val->getNamespace() == $object_namespace) {
					$values[] = $prop_val->getTitle()->getText();
				}
			}
		}
	}
	return $values;
}

/**
 * Gets all the filters specified for a category.
 */
function sdfLoadFiltersForCategory($category) {
	global $sdgContLang;
	$sd_props = $sdgContLang->getSpecialPropertiesArray();

	$filters = array();
	$filter_names = sdfGetValuesForProperty(str_replace(' ', '_', $category), NS_CATEGORY, '_SD_F', SD_SP_HAS_FILTER, SD_NS_FILTER);
	foreach ($filter_names as $filter_name) {
		$filters[] = SDFilter::load($filter_name);
	}
	return $filters;
}

function sdfGetCategoryChildren($category_name, $get_categories, $levels) {
	if ($levels == 0) {
		return array();
	}
	$pages = array();
	$subcategories = array();
	$dbr = wfGetDB( DB_SLAVE );
	extract($dbr->tableNames('page', 'categorylinks'));
	$cat_ns = NS_CATEGORY;
	$query_category = str_replace(' ', '_', $category_name);
	$query_category = str_replace("'", "\'", $query_category);
	$sql = "SELECT p.page_title, p.page_namespace FROM $categorylinks cl
		JOIN $page p on cl.cl_from = p.page_id
		WHERE cl.cl_to = '$query_category'\n";
	if ($get_categories)
		$sql .= "AND p.page_namespace = $cat_ns\n";
	$sql .= "ORDER BY cl.cl_sortkey";
	$res = $dbr->query($sql);
	while ($row = $dbr->fetchRow($res)) {
		if ($get_categories) {
			$subcategories[] = $row[0];
			$pages[] = $row[0];
		} else {
			if ($row[1] == $cat_ns)
				$subcategories[] = $row[0];
			else
				$pages[] = $row[0];
		}
	}
	$dbr->freeResult($res);
	foreach ($subcategories as $subcategory) {
		$pages = array_merge($pages, sdfGetCategoryChildren($subcategory, $get_categories, $levels - 1));
	}
	return $pages;
}

function sdfMonthToString($month) {
	if ($month == 1) {
		return wfMsg('january');
	} elseif ($month == 2) {
		return wfMsg('february');
	} elseif ($month == 3) {
		return wfMsg('march');
	} elseif ($month == 4) {
		return wfMsg('april');
	} elseif ($month == 5) {
		return wfMsg('may');
	} elseif ($month == 6) {
		return wfMsg('june');
	} elseif ($month == 7) {
		return wfMsg('july');
	} elseif ($month == 8) {
		return wfMsg('august');
	} elseif ($month == 9) {
		return wfMsg('september');
	} elseif ($month == 10) {
		return wfMsg('october');
	} elseif ($month == 11) {
		return wfMsg('november');
	} else { //if ($month == 12) {
		return wfMsg('december');
	}
}

function sdfStringToMonth($str) {
	if ($str == wfMsg('january')) {
		return 1;
	} elseif ($str == wfMsg('february')) {
		return 2;
	} elseif ($str == wfMsg('march')) {
		return 3;
	} elseif ($str == wfMsg('april')) {
		return 4;
	} elseif ($str == wfMsg('may')) {
		return 5;
	} elseif ($str == wfMsg('june')) {
		return 6;
	} elseif ($str == wfMsg('july')) {
		return 7;
	} elseif ($str == wfMsg('august')) {
		return 8;
	} elseif ($str == wfMsg('september')) {
		return 9;
	} elseif ($str == wfMsg('october')) {
		return 10;
	} elseif ($str == wfMsg('november')) {
		return 11;
	} else { //if ($strmonth == wfMsg('december')) {
		return 12;
	}
}

function sdfBooleanToString($bool_value) {
	$words_field_name = ($bool_value == true) ? 'smw_true_words' : 'smw_false_words';
	$words_array = explode(',', wfMsgForContent($words_field_name));
	// go with the value in the array that tends to be "yes" or "no" -
	// for SMW 0.7 it's the 2nd word, and for SMW 1.0 it's the 3rd
	$index_of_word = 2;
	// capitalize first letter of word
	if (count($words_array) > $index_of_word) {
		$string_value = ucwords($words_array[$index_of_word]);
	} elseif (count($words_array) == 0) {
		$string_value = $bool_value; // a safe value if no words are found
	} else {
		$string_value = ucwords($words_array[0]);
	}
	return $string_value;
}

/**
 * Prints the mini-form contained at the bottom of various pages, that
 * allows pages to spoof a normal edit page, that can preview, save,
 * etc.
 */
function sdfPrintRedirectForm($title, $page_contents, $edit_summary, $is_save, $is_preview, $is_diff, $is_minor_edit, $watch_this) {
	$article = new Article($title);
	$new_url = $title->getLocalURL('action=submit');
	$starttime = wfTimestampNow();
	$edittime = $article->getTimestamp();
	global $wgUser;
	if ( $wgUser->isLoggedIn() )
		$token = htmlspecialchars($wgUser->editToken());
	else
		$token = EDIT_TOKEN_SUFFIX;

	if ($is_save)
		$action = "wpSave";
	elseif ($is_preview)
		$action = "wpPreview";
	else // $is_diff
		$action = "wpDiff";

	$text =<<<END
	<form id="editform" name="editform" method="post" action="$new_url">
	<input type="hidden" name="wpTextbox1" id="wpTextbox1" value="$page_contents" />
	<input type="hidden" name="wpSummary" value="$edit_summary" />
	<input type="hidden" name="wpStarttime" value="$starttime" />
	<input type="hidden" name="wpEdittime" value="$edittime" />
	<input type="hidden" name="wpEditToken" value="$token" />
	<input type="hidden" name="$action" />

END;
	if ($is_minor_edit)
		$text .= '    <input type="hidden" name="wpMinoredit">' . "\n";
	if ($watch_this)
		$text .= '    <input type="hidden" name="wpWatchthis">' . "\n";
	$text .=<<<END
	</form>
	<script type="text/javascript">
	document.editform.submit();
	</script>

END;
	return $text;
}
