<?php

namespace CKAN\Manager;

/**
 * Class Dataset
 * @package CKAN\Manager
 */
class Dataset
{
    /**
     * @var array
     */
    private $dataset = [];
    /**
     * @var array
     */
    private $extras = [];

    /**
     * @param $dataset
     */
    public function __construct($dataset){
        $this->dataset = $dataset;
        if (isset($dataset['extras'])) {
            foreach ($dataset['extras'] as $extra) {
                $this->extras[$extra['key']] = $extra['value'];
            }
        }
    }

    /**
     * @return array
     */
    public function get_groups_and_tags(){
        $groups = [];
        if (isset($this->dataset['groups'])) {
            foreach ($this->dataset['groups'] as $group) {
                if (strlen(trim($group['title']))) {
                    $tags = [];
                    if (isset($this->extras['__category_tag_'.$group['id']])) {
                        $tags = trim($this->extras['__category_tag_'.$group['id']],'[]');
                        $tags = explode('","', $tags);
                        foreach ($tags as &$tag) {
                            $tag = trim($tag, '" ');
                        }
                    }
                    $groups[trim($group['title'])] = $tags;
                }
            }
        }
        return $groups;
    }

    /**
     * Sometimes harvested ckan title does not exactly matches, but dataset is same, ex. double spaces
     * To avoid these cases, we remove all non-word chars, leaving only alphabetic and digit chars
     * Ex.
     * Input: Tree dog dataset    , agriculture, 1997 ?????!!!
     * Output: treedogdatasetagriculture1997
     *
     * @param $string
     *
     * @return mixed|string
     */
    public static function simplifyTitle(
        $string
    ) {
        $string = preg_replace('/[\W]+/', '', $string);
        $string = strtolower($string);

        return $string;
    }
}
