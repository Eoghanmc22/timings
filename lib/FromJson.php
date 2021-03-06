<?php
/*
 * Aikar's Minecraft Timings Parser
 *
 * Written by Aikar <aikar@aikar.co>
 * http://aikar.co
 * http://starlis.com
 *
 * @license MIT
 */

namespace Starlis\Timings;

/**
 * Provides method to translate JSON Data into an object using PHP Doc comments.
 *
 * @see https://gist.github.com/aikar/52d2ef0870483696f059 for usage
 */

trait FromJson {
	static private $classMap = [
		Json\MinuteReport::class => 1,
		Json\Plugin::class => 2,
		Json\Region::class => 3,
		Json\TicksRecord::class => 4,
		Json\TimingData::class => 5,
		Json\TimingHandler::class => 6,
		Json\TimingHistory::class => 7,
		Json\TimingIdentity::class => 8,
		Json\TimingsMap::class => 9,
		Json\TimingsMaster::class => 10,
		Json\TimingsSystemData::class => 11,
		Json\World::class => 12,
	];

	/**
	 * @param                $rootData
	 * @param FromJsonParent $parentObj
	 *
	 * @return $this
	 */
	public static function createObject($rootData, $parentObj = null) {
		$class = __CLASS__;
		$ref = new \ReflectionClass($class);
		$props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);

		if (util::has_trait($class, 'Starlis\Timings\Singleton')) {
			/** @noinspection PhpUndefinedMethodInspection */
			$obj = self::getInstance();
		} else {
			$obj = new self();
		}
		$className = get_class($obj);
		if (!empty(self::$classMap[$className])) {
			$obj->{":cls"} = self::$classMap[$className];
		}

		$classDoc = $ref->getDocComment();
		if ($classDoc && preg_match('/@mapper\s+(.+?)\s/', $classDoc, $matches)) {
			$cb = $matches[1];
			if (false === strpos($cb, "::")) {
				$cb = __CLASS__ . "::$cb";
			}
			if (false === strpos($cb, "\\")) {
				$cb = util::getNamespace(__CLASS__) . "\\$cb";
			}
			$cb = explode("::", $cb, 2);
			$rootData = call_user_func($cb, $rootData, $parentObj);
		}


		foreach ($props as $prop) {
			$name = $prop->getName();
			$comment = $prop->getDocComment();
			$parent = new FromJsonParent($name, $comment, $obj, $parentObj);

			$isExpectingArray = false;
			if ($comment && preg_match('/@var .+?\[.*?\]/', $comment, $matches)) {
				$isExpectingArray = true;
			}

			$index = $name;
			if ($comment && preg_match('/@index\s+([:@\w\-]+)/', $comment, $matches)) {
				$index = $matches[1];

			}
			$vars = is_object($rootData) ? get_object_vars($rootData) : $rootData;

			$data = null;
			if ($index === '@key') {
				$data = $parentObj->name;
			} else if ($index === '@value') {
				$data = $rootData;
			} else if (array_key_exists($index, $vars)) {
				$data = $vars[$index];
			} else if (strpos($index, "::") !== false) {
				$data = self::executeCb($index, [$obj, $rootData, $parentObj]);
			}

			if (!is_null($data) || $isExpectingArray) {
				if ($isExpectingArray) {
					$result = [];
					if (($data) && !is_scalar($data)) {
						$data = is_object($data) ? get_object_vars($data) : $data;

						foreach ($data as $key => $entry) {
							$arrParent = new FromJsonParent($key, $comment, $result, $parent);
							$thisData = self::getData($entry, $arrParent);
							$result[$key] = $thisData;
						}
						$data = $result;
						$result = [];
						foreach ($data as $key => $value) {
							if ($parent->comment && preg_match('/@keymapper\s+(.+?)\s/', $parent->comment, $matches)) {
								$key = self::executeCb($matches[1], [$key, $value, $parent]);
							}
							$result[$key] = $value;
						}
					}
					$data = $result;
				} else {
					$data = self::getData($data, $parent);
				}
				$prop->setValue($obj, $data);
			}
		}
		$obj->init();

		return $obj;
	}

	public function init() {

	}

	/**
	 * @param                $data
	 * @param FromJsonParent $parent
	 *
	 * @return mixed
	 */
	private static function getData($data, FromJsonParent $parent) {
		$className = null;
		if ($parent->comment && preg_match('/@var\s+([\w_]+)(\[.*?\])?/', $parent->comment, $matches)) {
			$className = $matches[1];
			if (strpos($className, "\\") === false) {
				$className = util::getNamespace(__CLASS__) . "\\$className";
			}
		}

		if ($parent->comment && preg_match('/@mapper\s+(.+?)\s/', $parent->comment, $matches)) {
			$data = self::executeCb($matches[1], [$data, $parent]);
		} else if ($className && util::has_trait($className, __TRAIT__)) {
			$data = call_user_func("$className::createObject", $data, $parent);
		} else if (!is_scalar($data)) {
			$data = util::flattenObject($data);
		}

		if ($parent->comment && preg_match('/@filter\s+(.+?)\s/', $parent->comment, $matches)) {
			$data = self::executeCb($matches[1], [$data, $parent]);
		}

		return $data;
	}

	private static function executeCb($cb, $args) {
		if (strpos($cb, "::") === false) {
			$cb = __CLASS__ . "::$cb";;
		}
		if (strpos($cb, "\\") === false) {
			$cb = util::getNamespace(__CLASS__) . "\\$cb";
		}

		$cb = explode("::", $cb, 2);

		return is_callable($cb) ? call_user_func_array($cb, $args) : null;
	}
}

class FromJsonParent {
	/**
	 * @var FromJsonParent
	 */
	public $parent;
	public $comment, $obj, $name, $root;

	public function __construct($name, $comment, $obj, $parent) {
		$this->name = $name;
		$this->comment = $comment;
		$this->obj = $obj;
		$this->parent = $parent;
		$this->root = $parent == null || $parent->root == null ? $obj : $parent->root;
	}
}
