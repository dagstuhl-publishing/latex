<?php

namespace Dagstuhl\Latex\Styles;

use Dagstuhl\Latex\Metadata\MetadataItem;

class LatexStyle
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var MetadataItem[] array
     */
    protected $metadataItems = [];

    /**
     * @var string|StyleDescription
     */
    protected $styleDescription;

    /**
     * LatexStyle constructor.
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;

        if (function_exists('config')) {
            $registry = config('latex.styles.registry');
        }

        if (empty($registry)) {
            $registry = StylesRegistry::class;
        }

        if (method_exists($registry, 'getDescriptionFor')) {
            $this->styleDescription = $registry::getDescriptionFor($name);
        }
        else {
            die($registry.' does not implement getDescriptionFor');
        }

        foreach($this->styleDescription::getMetadataItems() as $item) {
            $this->addMetadataItem(new MetadataItem($item));
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getStyleDescription()
    {
        return $this->styleDescription;
    }

    /**
     * @return string
     */
    public function getStyleDescriptionName()
    {
        return class_basename($this->styleDescription);
    }

    /**
     * @param MetadataItem $item
     *
     * IMPORTANT: An existing item is overwritten if the name is already in use!
     */
    private function addMetadataItem($item)
    {
        $name = $item->getName();

        foreach($this->metadataItems as $key=>$existingItem) {
            if ($existingItem->getName() === $name) {
                $this->metadataItems[$key] = $item;
            }
        }

        $this->metadataItems[] = $item;
    }

    /**
     * @param string[] $groups
     * @return MetadataItem[]
     *
     * returns MetadataItems belonging to all specified groups simultaneously
     * (returns all items if no group is specified)
     */
    public function getMetadataItems($groups = [])
    {
        if (count($groups) === 0) {
            return $this->metadataItems;
        }

        $items = [];
        foreach($this->metadataItems as $item) {
            if ($item->belongsTo($groups)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param string $name
     * @return MetadataItem|null
     */
    public function getMetadataItem($name)
    {
        foreach($this->metadataItems as $item) {
            if ($item->getName() === $name) {
                return $item;
            }
        }

        return NULL;
    }


    /**
     * @param $classification
     * @return array
     */
    public function getPackages($classification)
    {
        return $this->styleDescription::getPackages($classification, $this->getName());
    }

    /**
     * @return string[]
     */
    public function getFiles()
    {
        return $this->styleDescription::getFiles($this->name);
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->styleDescription::getPath($this->name);
    }
}