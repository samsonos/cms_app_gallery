<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 11.04.2015
 * Time: 9:58
 */
namespace samsoncms\app\gallery;

use samsoncms\app\gallery\field\Image;
use samsonframework\core\RenderInterface;
use samsonframework\orm\QueryInterface;
use samsonframework\pager\PagerInterface;
use samsoncms\field\Generic;
use samsoncms\field\Control;
use samson\activerecord\materialfield;

/**
 * Collection of SamsonCMS gallery images
 * @package samsoncms\app\gallery
 */
class Collection extends \samsoncms\Collection
{
    /** @var string Index collection view */
    protected $indexView = 'collection/index';

    /** @var string Entity fields row view */
    protected $rowView = 'collection/item';

    /** @var materialfield Gallery table materialfield entity */
    protected $entity;

    /**
     * Get only gallery records related to specific materialfield
     * @param array $ids Collection of gallery records
     */
    public function filterByMaterialField(&$ids)
    {
        $this->query->cond('materialFieldId', $this->entity->id)->fields($this->entityPrimaryField, $ids);
    }

    /**
     * Overload default constructor
     * @param RenderInterface $renderer View renderer
     * @param QueryInterface $query Database query
     * @param PagerInterface $pager Paging
     */
    public function __construct(RenderInterface $renderer, QueryInterface $query, materialfield $entity, PagerInterface $pager)
    {
        // Store parent entity
        $this->entity = $entity;
        
        // Fill collection fields
        $this->fields = array(
            new Image(),
            new Generic('id', '#', 0, 'id', false),
            new Generic('Name', '#', 0, 'id', false),
            new Generic('size', '#', 0, 'id', false),
            new Control(),
        );

        // Call parent
        parent::__construct($renderer, $query, $pager);

        // Add parent entity filter
        $this->handler(array($this, 'filterByMaterialField'));

        // Apply sorting by identifier
        $this->sorter('priority', 'DESC');

        // Fill collection on creation
        $this->fill();
    }
}
