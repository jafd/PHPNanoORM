<?php

namespace phpnanoorm;

class Util {

  /**
   * Util::decamelize('somethingCamelCased') == 'something_camel_cased';
   *
   * @param string $string
   * @return string
   */
  public static function decamelize($string) {
    preg_match_all("/(?:[A-Z]?[a-z0-9_]+|[A-Z]?[A-Z0-9_]+)/", $string, $matches);
    $result = implode('_', array_map('strtolower', $matches[0]));
    return $result;
  }

  /**
   * Util::camelize('an_underlined_thing') == 'anUnderlinedThing';
   *
   * @param string $string
   * @return string
   */
  public static function camelize($string) {
    if (strpos($string, '_'))
      return lcfirst(implode('', array_map('ucfirst', array_map('strtolower', explode('_', $string)))));
    return $string;
  }

  /**
   * Util::unprefix('someCamelCasedString') == 'CamelCasedString';
   *
   * @param string $string
   * @return string
   */
  public static function unprefix($string) {
    return preg_replace('/^.*?([A-Z])/', '\1', $string);
  }

  /**
   * Util::humanizeUnderlined('a_lump_sum_of_money') == 'A Lump Sum Of Money';
   *
   * @param string $string
   * @return string
   */
  public static function humanizeUnderlined($string) {
    if (strpos($string, '_'))
      return implode(' ', array_map('ucfirst', array_map('strtolower', explode('_', $string))));
    return $string;
  }

  /**
   * Util::extractKey(array(array('id' => 1, 'value' => 'foo'),
   *    array('id' => 2, 'value' => 'bar')), 'value') == array('foo', 'bar')
   *
   * @param array $arr
   * @param mixed $key
   * @return array
   */
  public static function extractKey(array $arr, $key) {
    $result = array();
    foreach ($arr as $value) {
      if (is_array($value) && isset($value[$key]))
        $result[] = $value[$key];
    }
    return $result;
  }
  
}
